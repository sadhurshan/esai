"""Lightweight workflow orchestration engine for AI multi-step flows."""
from __future__ import annotations

import copy
import json
import logging
import threading
import uuid
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List, MutableMapping, Optional, Sequence

LOGGER = logging.getLogger(__name__)

DEFAULT_STORAGE_DIR = Path(__file__).resolve().parent.parent / "tmp" / "workflows"

TERMINAL_STATUSES = {"completed", "failed", "rejected", "aborted"}


class WorkflowEngineError(Exception):
    """Base exception for workflow engine failures."""


class WorkflowNotFoundError(WorkflowEngineError):
    """Raised when a workflow cannot be located."""


class WorkflowEngine:
    """In-memory workflow registry with JSON persistence."""

    def __init__(self, storage_dir: Optional[str | Path] = None) -> None:
        self._storage_dir = Path(storage_dir) if storage_dir else DEFAULT_STORAGE_DIR
        self._storage_dir.mkdir(parents=True, exist_ok=True)
        self._workflows: Dict[str, Dict[str, Any]] = {}
        self._lock = threading.RLock()
        self._load_existing_workflows()

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------
    def plan_workflow(
        self,
        query: str,
        action_sequence: Sequence[MutableMapping[str, Any] | str],
        *,
        company_id: int,
        user_context: Optional[MutableMapping[str, Any]] = None,
        workflow_type: str = "custom",
        metadata: Optional[MutableMapping[str, Any]] = None,
    ) -> Dict[str, Any]:
        """Create a workflow plan and persist it to disk."""

        if not action_sequence:
            raise WorkflowEngineError("action_sequence must include at least one step")

        with self._lock:
            workflow_id = str(uuid.uuid4())
            now = self._utc_now()
            steps = self._build_steps(action_sequence)
            workflow_record: Dict[str, Any] = {
                "workflow_id": workflow_id,
                "workflow_type": workflow_type.lower(),
                "company_id": company_id,
                "query": query,
                "user_context": dict(user_context or {}),
                "metadata": dict(metadata or {}),
                "current_step_index": 0,
                "status": "pending",
                "steps": steps,
                "created_at": now,
                "updated_at": now,
            }

            self._workflows[workflow_id] = workflow_record
            self._persist_workflow(workflow_record)
            LOGGER.info(
                "workflow_planned",
                extra={
                    "workflow_id": workflow_id,
                    "company_id": company_id,
                    "workflow_type": workflow_type,
                    "step_count": len(steps),
                },
            )
            return workflow_record

    def get_next_step(self, workflow_id: str) -> Optional[Dict[str, Any]]:
        """Return the next actionable step and mark workflow as in-progress."""

        with self._lock:
            workflow = self._get_workflow(workflow_id)
            if workflow["status"] in TERMINAL_STATUSES:
                return None

            idx = workflow.get("current_step_index", 0)
            steps = workflow.get("steps", [])
            if idx is None or idx >= len(steps):
                workflow["status"] = "completed"
                workflow["current_step_index"] = None
                workflow["updated_at"] = self._utc_now()
                self._persist_workflow(workflow)
                return None

            step = steps[idx]
            if step.get("approval_state") in {"approved", "rejected"}:
                return None

            step["approval_state"] = "in_progress"
            step["updated_at"] = self._utc_now()
            workflow["status"] = "in_progress"
            workflow["updated_at"] = step["updated_at"]
            self._persist_workflow(workflow)
            return step

    def get_workflow_snapshot(self, workflow_id: str) -> Dict[str, Any]:
        """Return a deep copy of the workflow for external consumers."""

        with self._lock:
            workflow = self._get_workflow(workflow_id)
            return copy.deepcopy(workflow)

    def update_step_draft(self, workflow_id: str, step_index: int, draft_output: Any) -> Dict[str, Any]:
        """Persist a draft output for the given step."""

        with self._lock:
            workflow = self._get_workflow(workflow_id)
            steps = workflow.get("steps", [])
            if step_index < 0 or step_index >= len(steps):
                raise WorkflowEngineError("Invalid step_index for draft update")
            step = steps[step_index]
            step["draft_output"] = draft_output
            step["updated_at"] = self._utc_now()
            workflow["updated_at"] = step["updated_at"]
            self._persist_workflow(workflow)
            return copy.deepcopy(step)

    def complete_step(
        self,
        workflow_id: str,
        output: Optional[MutableMapping[str, Any]],
        approval: bool,
        *,
        approved_by: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Record step output and advance workflow pointer."""

        with self._lock:
            workflow = self._get_workflow(workflow_id)
            steps = workflow.get("steps", [])
            idx = workflow.get("current_step_index", 0)
            if idx is None or idx >= len(steps):
                raise WorkflowEngineError("No remaining steps to complete")

            step = steps[idx]
            if step.get("approval_state") in {"approved", "rejected"}:
                raise WorkflowEngineError("Current step already finalized")

            step["output"] = dict(output or {})
            step["approval_state"] = "approved" if approval else "rejected"
            step["approved_by"] = approved_by
            step["approved_at"] = self._utc_now()
            step["updated_at"] = step["approved_at"]

            if approval:
                workflow["current_step_index"] = idx + 1
                if workflow["current_step_index"] is not None and workflow["current_step_index"] >= len(steps):
                    workflow["status"] = "completed"
                    workflow["current_step_index"] = None
                else:
                    workflow["status"] = "in_progress"
            else:
                workflow["status"] = "rejected"
                workflow["current_step_index"] = None

            workflow["updated_at"] = step["approved_at"]
            self._persist_workflow(workflow)
            LOGGER.info(
                "workflow_step_completed",
                extra={
                    "workflow_id": workflow_id,
                    "step_index": idx,
                    "approval": approval,
                    "workflow_status": workflow["status"],
                },
            )
            return workflow

    def abort_workflow(self, workflow_id: str, reason: Optional[str] = None) -> Dict[str, Any]:
        """Abort workflow and set terminal status."""

        with self._lock:
            workflow = self._get_workflow(workflow_id)
            workflow["status"] = "aborted"
            workflow["current_step_index"] = None
            workflow["abort_reason"] = reason or "unspecified"
            workflow["aborted_at"] = self._utc_now()
            workflow["updated_at"] = workflow["aborted_at"]
            self._persist_workflow(workflow)
            LOGGER.warning(
                "workflow_aborted",
                extra={"workflow_id": workflow_id, "reason": reason},
            )
            return workflow

    # ------------------------------------------------------------------
    # Internal helpers
    # ------------------------------------------------------------------
    def _get_workflow(self, workflow_id: str) -> Dict[str, Any]:
        workflow = self._workflows.get(workflow_id)
        if workflow is not None:
            return workflow
        workflow = self._load_workflow_from_disk(workflow_id)
        if workflow is None:
            raise WorkflowNotFoundError(f"Workflow {workflow_id} not found")
        self._workflows[workflow_id] = workflow
        return workflow

    def _persist_workflow(self, workflow: Dict[str, Any]) -> None:
        workflow_id = workflow["workflow_id"]
        path = self._storage_dir / f"{workflow_id}.json"
        temp_path = path.with_suffix(".json.tmp")
        with temp_path.open("w", encoding="utf-8") as handle:
            json.dump(workflow, handle, ensure_ascii=True, indent=2)
        temp_path.replace(path)

    def _load_existing_workflows(self) -> None:
        for file_path in self._storage_dir.glob("*.json"):
            workflow = self._load_workflow_from_path(file_path)
            if workflow:
                self._workflows[workflow["workflow_id"]] = workflow

    def _load_workflow_from_disk(self, workflow_id: str) -> Optional[Dict[str, Any]]:
        path = self._storage_dir / f"{workflow_id}.json"
        if not path.exists():
            return None
        return self._load_workflow_from_path(path)

    def _load_workflow_from_path(self, path: Path) -> Optional[Dict[str, Any]]:
        try:
            with path.open("r", encoding="utf-8") as handle:
                return json.load(handle)
        except json.JSONDecodeError as exc:
            LOGGER.error("workflow_deserialize_error", extra={"path": str(path), "error": str(exc)})
            return None

    def _build_steps(self, action_sequence: Sequence[MutableMapping[str, Any] | str]) -> List[Dict[str, Any]]:
        steps: List[Dict[str, Any]] = []
        for idx, raw_step in enumerate(action_sequence):
            if isinstance(raw_step, str):
                step_map: Dict[str, Any] = {"action_type": raw_step}
            elif isinstance(raw_step, MutableMapping):
                step_map = dict(raw_step)
            else:
                raise WorkflowEngineError("Invalid step definition")

            action_type = str(step_map.get("action_type") or "").strip()
            if not action_type:
                raise WorkflowEngineError("Each step must define an action_type")

            steps.append(
                {
                    "step_index": idx,
                    "name": step_map.get("name") or action_type,
                    "action_type": action_type,
                    "description": step_map.get("description") or "",
                    "required_inputs": step_map.get("required_inputs") or {},
                    "draft_output": step_map.get("draft_output"),
                    "output": None,
                    "approval_state": "pending",
                    "approved_by": None,
                    "approved_at": None,
                    "metadata": step_map.get("metadata") or {},
                    "created_at": self._utc_now(),
                    "updated_at": self._utc_now(),
                }
            )
        return steps

    @staticmethod
    def _utc_now() -> str:
        return datetime.now(timezone.utc).isoformat()


__all__ = ["WorkflowEngine", "WorkflowEngineError", "WorkflowNotFoundError"]
