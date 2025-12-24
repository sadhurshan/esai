"""FastAPI microservice for forecasting and supplier risk workflows.

Run locally with ``uvicorn ai_microservice.app:app --reload``. The service exposes two
endpoints:

* ``POST /forecast`` — accepts a ``ForecastRequest`` payload containing ``part_id``,
    historical {date, quantity} records, and a forecast ``horizon``. Returns
    ``demand_qty``, ``avg_daily_demand``, ``reorder_point``, ``safety_stock``, and
    ``order_by_date`` fields from :class:`AISupplyService`.
* ``POST /supplier-risk`` — accepts a ``SupplierRiskRequest`` with a supplier feature
    dictionary and returns a risk category plus explanatory metadata.

Deployment requires the underlying :class:`AISupplyService` to resolve its database
connection and feature toggles via environment variables such as
``AI_SERVICE_DATABASE_URL`` (or ``DATABASE_URL``), ``AI_SERVICE_INVENTORY_TABLE``,
``AI_SERVICE_DEFAULT_LEAD_TIME_DAYS``, ``AI_SERVICE_RESPONSE_SLA_HOURS``, and
``AI_SERVICE_RISK_THRESHOLDS``.
"""
from __future__ import annotations

import contextvars
import copy
import hashlib
import json
import logging
import os
import re
import threading
import time
import uuid
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from statistics import StatisticsError, fmean
from typing import Any, Callable, Dict, List, Literal, Optional, Sequence

from fastapi import BackgroundTasks, FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import StreamingResponse
from jsonschema import Draft202012Validator, ValidationError
from pydantic import BaseModel, Field, field_validator

from ai_microservice import chat_router
from ai_microservice.chunking import chunk_text
from ai_microservice.context_packer import pack_context_hits
from ai_microservice.embedding_provider import EmbeddingProvider, get_embedding_provider
from ai_microservice.llm_provider import DummyLLMProvider, LLMProviderError, LLMProvider, build_llm_provider
from ai_microservice.schemas import (
    ANSWER_SCHEMA,
    AWARD_QUOTE_SCHEMA,
    CHAT_RESPONSE_SCHEMA,
    INVENTORY_WHATIF_SCHEMA,
    INVOICE_DRAFT_SCHEMA,
    MAINTENANCE_CHECKLIST_SCHEMA,
    PO_DRAFT_SCHEMA,
    REPORT_SUMMARY_SCHEMA,
    QUOTE_COMPARISON_SCHEMA,
    RFQ_DRAFT_SCHEMA,
    SUPPLIER_MESSAGE_SCHEMA,
)
from ai_microservice.supplier_scraper import SupplierScraper
from ai_microservice.tools_contract import (
    build_award_quote,
    build_invoice_draft,
    build_maintenance_checklist,
    build_rfq_draft,
    build_supplier_message,
    compare_quotes,
    draft_purchase_order,
    review_invoice,
    review_po,
    review_quote,
    review_rfq,
    run_inventory_whatif,
)
from ai_microservice.workflow_engine import WorkflowEngine, WorkflowEngineError, WorkflowNotFoundError
from ai_microservice.vector_store import InMemoryVectorStore, VectorStore
from ai_service import AISupplyService


LOGGER = logging.getLogger(__name__)
logging.basicConfig(level=logging.INFO)

app = FastAPI(title="Elements Supply AI Microservice", version="0.1.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
REQUEST_ID_HEADER = "X-Request-Id"
REQUEST_ID_CTX: contextvars.ContextVar[str | None] = contextvars.ContextVar("request_id", default=None)


def current_request_id() -> str | None:
    return REQUEST_ID_CTX.get()


def log_extra(**kwargs: Any) -> Dict[str, Any]:
    payload: Dict[str, Any] = {"request_id": current_request_id()}
    payload.update(kwargs)
    return payload


def _env_flag(name: str, default: bool = False) -> bool:
    value = os.getenv(name)
    if value is None:
        return default
    normalized = value.strip().lower()
    return normalized in {"1", "true", "yes", "on"}


@app.middleware("http")
async def correlation_id_middleware(request: Request, call_next):
    incoming_id = request.headers.get(REQUEST_ID_HEADER)
    request_id = incoming_id or str(uuid.uuid4())
    token = REQUEST_ID_CTX.set(request_id)
    started_at = time.perf_counter()
    try:
        response = await call_next(request)
    except Exception:
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.exception(
            "request_exception",
            extra={
                "request_id": request_id,
                "method": request.method,
                "path": request.url.path,
                "duration_ms": round(duration_ms, 2),
            },
        )
        REQUEST_ID_CTX.reset(token)
        raise

    duration_ms = (time.perf_counter() - started_at) * 1000
    response.headers[REQUEST_ID_HEADER] = request_id
    LOGGER.info(
        "request_complete",
        extra={
            "request_id": request_id,
            "method": request.method,
            "path": request.url.path,
            "status_code": response.status_code,
            "duration_ms": round(duration_ms, 2),
        },
    )
    REQUEST_ID_CTX.reset(token)
    return response


class TTLCache:
    def __init__(self, default_ttl: int = 300) -> None:
        self._default_ttl = max(1, int(default_ttl))
        self._store: Dict[str, tuple[float, Any]] = {}

    def get(self, key: str) -> Optional[Any]:
        record = self._store.get(key)
        if record is None:
            return None
        expires_at, value = record
        if expires_at <= time.time():
            self._store.pop(key, None)
            return None
        return value

    def set(self, key: str, value: Any, ttl: Optional[int] = None) -> None:
        lifetime = ttl if ttl is not None else self._default_ttl
        expires_at = time.time() + max(1, int(lifetime))
        self._store[key] = (expires_at, value)

    def purge(self) -> None:
        now = time.time()
        expired_keys = [key for key, (expires_at, _) in self._store.items() if expires_at <= now]
        for key in expired_keys:
            self._store.pop(key, None)


CACHE_TTL_SECONDS = int(os.getenv("AI_CACHE_TTL_SECONDS", "300"))
response_cache = TTLCache(default_ttl=CACHE_TTL_SECONDS)
service = AISupplyService()
embedding_provider: EmbeddingProvider = get_embedding_provider()
vector_store: VectorStore = InMemoryVectorStore()
DEFAULT_LLM_PROVIDER_NAME = os.getenv("AI_LLM_PROVIDER", "dummy").strip().lower()
SUPPORTED_LLM_PROVIDERS = {"dummy", "openai"}
ALLOW_UNGROUNDED_ANSWERS = _env_flag("AI_ALLOW_UNGROUNDED_ANSWERS", False)
GENERAL_ANSWER_WARNING = "Response used general model knowledge; no workspace sources were available."

TRAINING_JOBS_STATE_PATH = os.getenv(
    "AI_TRAINING_STATE_PATH",
    str(Path(os.getcwd()) / "storage" / "ai_training_jobs.json"),
)
DEFAULT_FORECAST_TRAINING_HORIZON = int(os.getenv("AI_TRAINING_FORECAST_HORIZON_DAYS", "30"))


class TrainingJobStore:
    """Persists lightweight job metadata for async training requests."""

    def __init__(self, path: str) -> None:
        self._path = Path(path)
        self._lock = threading.Lock()
        self._jobs: Dict[str, Dict[str, Any]] = {}
        self._load()

    def create_job(self, feature: str, parameters: Dict[str, Any]) -> Dict[str, Any]:
        now = datetime.now(timezone.utc).isoformat()
        job_id = str(uuid.uuid4())
        job = {
            "id": job_id,
            "feature": feature,
            "status": "pending",
            "parameters": copy.deepcopy(parameters),
            "result": None,
            "error_message": None,
            "created_at": now,
            "updated_at": now,
            "started_at": None,
            "finished_at": None,
        }
        with self._lock:
            self._jobs[job_id] = job
            self._persist()
            return copy.deepcopy(job)

    def mark_running(self, job_id: str) -> Optional[Dict[str, Any]]:
        return self._update_job(job_id, status="running", started_at=datetime.now(timezone.utc).isoformat())

    def mark_completed(self, job_id: str, result: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        timestamp = datetime.now(timezone.utc).isoformat()
        return self._update_job(
            job_id,
            status="completed",
            result=result,
            error_message=None,
            finished_at=timestamp,
        )

    def mark_failed(self, job_id: str, message: str) -> Optional[Dict[str, Any]]:
        timestamp = datetime.now(timezone.utc).isoformat()
        return self._update_job(
            job_id,
            status="failed",
            error_message=message,
            finished_at=timestamp,
        )

    def get_job(self, job_id: str) -> Optional[Dict[str, Any]]:
        with self._lock:
            job = self._jobs.get(job_id)
            return copy.deepcopy(job) if job else None

    def summary(self, recent_limit: int = 5) -> Dict[str, Any]:
        with self._lock:
            jobs = list(self._jobs.values())
            status_counts = Counter(job["status"] for job in jobs)
            recent_jobs = sorted(
                jobs,
                key=lambda item: item.get("updated_at") or item.get("created_at") or "",
                reverse=True,
            )[:recent_limit]

        return {
            "total": len(jobs),
            "by_status": dict(status_counts),
            "recent": [copy.deepcopy(job) for job in recent_jobs],
        }

    def _update_job(self, job_id: str, **fields: Any) -> Optional[Dict[str, Any]]:
        with self._lock:
            job = self._jobs.get(job_id)
            if job is None:
                return None
            job.update(fields)
            job["updated_at"] = datetime.now(timezone.utc).isoformat()
            self._persist()
            return copy.deepcopy(job)

    def _load(self) -> None:
        if not self._path.exists():
            return
        try:
            raw = self._path.read_text(encoding="utf-8")
            data = json.loads(raw)
            if isinstance(data, dict):
                self._jobs = data
        except Exception:  # pragma: no cover - defensive load
            LOGGER.warning("training_job_store_load_failed", exc_info=True)
            self._jobs = {}

    def _persist(self) -> None:
        try:
            if self._path.parent:
                self._path.parent.mkdir(parents=True, exist_ok=True)
            tmp_path = self._path.with_suffix(".tmp")
            tmp_path.write_text(json.dumps(self._jobs, indent=2), encoding="utf-8")
            tmp_path.replace(self._path)
        except Exception:  # pragma: no cover - persistence errors logged upstream
            LOGGER.warning("training_job_store_persist_failed", exc_info=True)


training_job_store = TrainingJobStore(TRAINING_JOBS_STATE_PATH)

workflow_engine = WorkflowEngine()


class ScrapeJobStore:
    """Lightweight in-memory store for supplier scrape jobs/results."""

    def __init__(self) -> None:
        self._lock = threading.Lock()
        self._jobs: Dict[str, Dict[str, Any]] = {}
        self._results: Dict[str, List[Dict[str, Any]]] = {}

    def create_job(self, parameters: Dict[str, Any]) -> Dict[str, Any]:
        job_id = str(uuid.uuid4())
        timestamp = datetime.now(timezone.utc).isoformat()
        job = {
            "id": job_id,
            "status": "pending",
            "parameters": copy.deepcopy(parameters),
            "result_count": 0,
            "error_message": None,
            "created_at": timestamp,
            "updated_at": timestamp,
            "started_at": None,
            "finished_at": None,
        }
        with self._lock:
            self._jobs[job_id] = job
            self._results[job_id] = []
        return copy.deepcopy(job)

    def mark_running(self, job_id: str) -> Optional[Dict[str, Any]]:
        return self._update_job(job_id, status="running", started_at=datetime.now(timezone.utc).isoformat())

    def mark_completed(self, job_id: str, results: List[Dict[str, Any]]) -> Optional[Dict[str, Any]]:
        timestamp = datetime.now(timezone.utc).isoformat()
        with self._lock:
            job = self._jobs.get(job_id)
            if job is None:
                return None
            job.update(
                {
                    "status": "completed",
                    "result_count": len(results),
                    "error_message": None,
                    "finished_at": timestamp,
                    "updated_at": timestamp,
                }
            )
            self._results[job_id] = copy.deepcopy(results)
            return copy.deepcopy(job)

    def mark_failed(self, job_id: str, message: str) -> Optional[Dict[str, Any]]:
        timestamp = datetime.now(timezone.utc).isoformat()
        return self._update_job(
            job_id,
            status="failed",
            error_message=message,
            finished_at=timestamp,
        )

    def get_job(self, job_id: str) -> Optional[Dict[str, Any]]:
        with self._lock:
            job = self._jobs.get(job_id)
            return copy.deepcopy(job) if job else None

    def get_results(self, job_id: str, offset: int, limit: int) -> Optional[Dict[str, Any]]:
        with self._lock:
            if job_id not in self._jobs:
                return None
            results = self._results.get(job_id, [])
            bounded_limit = max(1, limit)
            start = max(0, offset)
            end = min(len(results), start + bounded_limit)
            items = copy.deepcopy(results[start:end])
            next_offset = end if end < len(results) else None
            prev_offset = start - bounded_limit
            if prev_offset < 0:
                prev_offset = None
        return {
            "items": items,
            "meta": {
                "total": len(results),
                "offset": start,
                "limit": bounded_limit,
                "next_offset": next_offset,
                "prev_offset": prev_offset,
            },
        }

    def _update_job(self, job_id: str, **fields: Any) -> Optional[Dict[str, Any]]:
        with self._lock:
            job = self._jobs.get(job_id)
            if job is None:
                return None
            job.update(fields)
            job["updated_at"] = datetime.now(timezone.utc).isoformat()
            return copy.deepcopy(job)


scrape_job_store = ScrapeJobStore()
supplier_scraper = SupplierScraper(llm_provider=build_llm_provider(DEFAULT_LLM_PROVIDER_NAME))

ACTION_CONTEXT_MAX_CHARS = int(os.getenv("AI_ACTION_CONTEXT_MAX_CHARS", "9000"))
ACTION_CONTEXT_MAX_CHUNKS = int(os.getenv("AI_ACTION_CONTEXT_MAX_CHUNKS", "12"))
ACTION_CONTEXT_PER_DOC_LIMIT = int(os.getenv("AI_ACTION_CONTEXT_PER_DOC_LIMIT", "4"))
CHAT_HISTORY_LIMIT = int(os.getenv("AI_CHAT_HISTORY_LIMIT", "40"))
CHAT_TOOL_RESULTS_LIMIT = int(os.getenv("AI_CHAT_TOOL_RESULTS_LIMIT", "5"))
CHAT_SUMMARY_TRIGGER_TOKENS = int(os.getenv("AI_CHAT_SUMMARY_TRIGGER_TOKENS", "2800"))
CHAT_SUMMARY_TARGET_CHARS = int(os.getenv("AI_CHAT_SUMMARY_TARGET_CHARS", "1800"))
CHAT_TOOL_ROUND_LIMIT = int(os.getenv("AI_CHAT_TOOL_ROUND_LIMIT", "3"))

REVIEW_TOOL_TYPE_MAP = {
    "workspace.review_rfq": "review_rfq",
    "workspace.review_quote": "review_quote",
    "workspace.review_po": "review_po",
    "workspace.review_invoice": "review_invoice",
}

REVIEW_TOOL_QUICK_REPLIES = {
    "review_rfq": ["Plan award", "Ask for quote status"],
    "review_quote": ["Share with buyer", "Request best and final"],
    "review_po": ["Check receipts", "Notify logistics"],
    "review_invoice": ["Mark as approved", "Flag discrepancy"],
}

ACTION_TYPE_LABELS: Dict[str, str] = {
    "rfq_draft": "RFQ Draft",
    "supplier_message": "Supplier Message",
    "maintenance_checklist": "Maintenance Checklist",
    "inventory_whatif": "Inventory What-If",
    "compare_quotes": "Quote Comparison",
    "po_draft": "Purchase Order Draft",
    "award_quote": "Award Quote",
    "invoice_draft": "Invoice Draft",
}

ACTION_SCHEMA_BY_TYPE: Dict[str, Dict[str, Any]] = {
    "rfq_draft": RFQ_DRAFT_SCHEMA,
    "supplier_message": SUPPLIER_MESSAGE_SCHEMA,
    "maintenance_checklist": MAINTENANCE_CHECKLIST_SCHEMA,
    "inventory_whatif": INVENTORY_WHATIF_SCHEMA,
    "compare_quotes": QUOTE_COMPARISON_SCHEMA,
    "po_draft": PO_DRAFT_SCHEMA,
    "award_quote": AWARD_QUOTE_SCHEMA,
    "invoice_draft": INVOICE_DRAFT_SCHEMA,
}

WORKFLOW_STEP_TEMPLATES: Dict[str, List[Dict[str, Any]]] = {
    "procurement": [
        {"action_type": "rfq_draft", "name": "RFQ Draft", "metadata": {"input_key": "rfq"}},
        {"action_type": "compare_quotes", "name": "Compare Quotes", "metadata": {"input_key": "quotes"}},
        {"action_type": "po_draft", "name": "Purchase Order Draft", "metadata": {"input_key": "po"}},
    ],
}

INTENT_CLASSIFICATION_SCHEMA: Dict[str, Any] = {
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "title": "ChatIntentClassification",
    "type": "object",
    "additionalProperties": False,
    "required": ["intent", "reason"],
    "properties": {
        "intent": {
            "type": "string",
            "enum": list(chat_router.ACTION_INTENTS.keys())
            + list(chat_router.WORKFLOW_INTENTS)
            + ["workspace_qna", "general_qna"],
        },
        "reason": {"type": "string"},
    },
}

CHAT_RESPONSE_VALIDATOR = Draft202012Validator(CHAT_RESPONSE_SCHEMA)


def should_bypass_cache(request: Request) -> bool:
    header_value = request.headers.get("X-AI-Cache")
    return isinstance(header_value, str) and header_value.strip().lower() == "bypass"


def hash_history_descriptor(length: int, last_date: Optional[str]) -> str:
    descriptor = f"{length}:{last_date or 'none'}"
    return hashlib.sha256(descriptor.encode("utf-8")).hexdigest()


def extract_last_history_date(history: list[Dict[str, Any]], keys: Optional[list[str]] = None) -> Optional[str]:
    date_keys = keys or ["date", "timestamp", "recorded_at", "measured_at", "occurred_at"]
    candidates: list[str] = []
    for entry in history:
        for key in date_keys:
            value = entry.get(key)
            if value:
                candidates.append(str(value))
                break
    if not candidates:
        return None
    return max(candidates)


def build_forecast_cache_key(payload: "ForecastRequest") -> str:
    last_date = extract_last_history_date(payload.history)
    history_hash = hash_history_descriptor(len(payload.history), last_date)
    company_token = str(payload.company_id) if payload.company_id is not None else "none"
    return f"forecast:{company_token}:{payload.part_id}:{payload.horizon}:{history_hash}"


def build_supplier_risk_cache_key(payload: "SupplierRiskRequest") -> str:
    supplier = payload.supplier
    company_token_value = (
        payload.company_id
        or supplier.get("company_id")
        or supplier.get("tenant_id")
        or "none"
    )
    supplier_identifier_value = (
        supplier.get("supplier_id")
        or supplier.get("id")
        or supplier.get("code")
        or supplier.get("name")
        or "anonymous"
    )
    company_token = str(company_token_value)
    supplier_identifier = str(supplier_identifier_value)

    history_source = supplier.get("history") or supplier.get("performance_history") or supplier.get("delivery_history")
    if isinstance(history_source, list):
        last_date = extract_last_history_date(history_source)
        history_length = len(history_source)
    else:
        fallback_date = supplier.get("last_delivery_date") or supplier.get("last_order_date") or supplier.get("updated_at")
        last_date = str(fallback_date) if fallback_date else None
        history_length = len(supplier)

    history_hash = hash_history_descriptor(history_length, last_date)
    horizon_token = supplier.get("horizon") or supplier.get("window_days") or 0
    return f"supplier-risk:{company_token}:{supplier_identifier}:{horizon_token}:{history_hash}"


class ForecastRequest(BaseModel):
    part_id: int = Field(..., ge=1)
    history: list[Dict[str, Any]] = Field(..., description="List of {date, quantity} records")
    horizon: int = Field(..., gt=0, le=90)
    company_id: Optional[int] = Field(default=None, ge=1)

    @field_validator("history")
    @classmethod
    def validate_history(cls, value: list[Dict[str, Any]]) -> list[Dict[str, Any]]:  # noqa: D417
        if not value:
            raise ValueError("history must contain at least one record")
        for entry in value:
            if "date" not in entry or "quantity" not in entry:
                raise ValueError("Each history entry must include date and quantity")
        return value


class SupplierRiskRequest(BaseModel):
    company_id: Optional[int] = Field(default=None, ge=1)
    supplier: Dict[str, Any]

    @field_validator("supplier")
    @classmethod
    def validate_supplier(cls, value: Dict[str, Any]) -> Dict[str, Any]:  # noqa: D417
        if not value:
            raise ValueError("supplier object cannot be empty")
        return value


class ScrapeSuppliersRequest(BaseModel):
    query: str = Field(..., min_length=3, max_length=240)
    region: Optional[str] = Field(default=None, max_length=160)
    max_results: int = Field(default=10, ge=1, le=25)

    @field_validator("query", mode="before")
    @classmethod
    def normalize_query(cls, value: Any) -> str:  # noqa: D417
        if value is None:
            raise ValueError("query is required")
        normalized = str(value).strip()
        if not normalized:
            raise ValueError("query cannot be empty")
        return normalized

    @field_validator("region", mode="before")
    @classmethod
    def normalize_region(cls, value: Any) -> Optional[str]:  # noqa: D417
        if value is None:
            return None
        normalized = str(value).strip()
        return normalized or None


class IndexDocumentRequest(BaseModel):
    company_id: int = Field(..., ge=1)
    doc_id: str = Field(..., min_length=1)
    doc_version: str = Field(..., min_length=1)
    title: str = Field(..., min_length=1)
    source_type: str = Field(..., min_length=1)
    mime_type: str = Field(..., min_length=1)
    text: str = Field(..., min_length=1)
    metadata: Dict[str, Any] = Field(default_factory=dict)
    acl: list[str] = Field(default_factory=list, description="Role/user scopes allowed to read the document")

    @field_validator("metadata", mode="before")
    @classmethod
    def validate_metadata(cls, value: Any) -> Dict[str, Any]:  # noqa: D417
        if value is None:
            return {}
        if not isinstance(value, dict):
            raise ValueError("metadata must be an object")
        return value


class SearchFilters(BaseModel):
    source_type: Optional[str] = Field(default=None, min_length=1)
    doc_id: Optional[str] = Field(default=None, min_length=1)
    tags: Optional[list[str]] = Field(default=None)


class SearchRequest(BaseModel):
    company_id: int = Field(..., ge=1)
    query: str = Field(..., min_length=1)
    top_k: int = Field(default=8, ge=1, le=25)
    filters: Optional[SearchFilters] = Field(default=None)


class AnswerRequest(SearchRequest):
    llm_provider: Optional[str] = Field(default=None)
    safety_identifier: Optional[str] = Field(default=None, max_length=256)
    allow_general: bool = Field(default=False, description="Allow general (ungrounded) answers when enabled")

    @field_validator("llm_provider", mode="before")
    @classmethod
    def normalize_llm_provider(cls, value: Optional[str]) -> Optional[str]:  # noqa: D417
        if value is None:
            return None

        normalized = str(value).strip().lower()
        if normalized in SUPPORTED_LLM_PROVIDERS:
            return normalized

        return None


class ReportSummaryRequest(BaseModel):
    company_id: int = Field(..., ge=1)
    report_type: Literal["forecast", "supplier_performance"]
    report_data: Dict[str, Any] = Field(default_factory=dict)
    filters_used: Dict[str, Any] = Field(default_factory=dict)
    user_id_hash: Optional[str] = Field(default=None, max_length=256)
    llm_provider: Optional[str] = Field(default=None)

    @field_validator("report_data", "filters_used", mode="before")
    @classmethod
    def ensure_mapping(cls, value: Any) -> Dict[str, Any]:  # noqa: D417
        if value is None:
            return {}
        if isinstance(value, dict):
            return value
        raise ValueError("Value must be an object")

    @field_validator("llm_provider", mode="before")
    @classmethod
    def normalize_llm_provider(cls, value: Optional[str]) -> Optional[str]:  # noqa: D417
        if value is None:
            return None

        normalized = str(value).strip().lower()
        if normalized in SUPPORTED_LLM_PROVIDERS:
            return normalized

        return None


class ActionPlanRequest(BaseModel):
    company_id: int = Field(..., ge=1)
    action_type: Literal[
        "rfq_draft",
        "supplier_message",
        "maintenance_checklist",
        "inventory_whatif",
        "compare_quotes",
        "po_draft",
    ]
    query: str = Field(..., min_length=1)
    inputs: Dict[str, Any] = Field(default_factory=dict)
    user_context: Dict[str, Any] = Field(default_factory=dict)
    top_k: int = Field(default=8, ge=1, le=25)
    filters: Optional[SearchFilters] = Field(default=None)
    llm_provider: Optional[str] = Field(default=None)
    safety_identifier: Optional[str] = Field(default=None, max_length=256)

    @field_validator("action_type", mode="before")
    @classmethod
    def normalize_action_type(cls, value: Any) -> str:  # noqa: D417
        if value is None:
            raise ValueError("action_type is required")
        normalized = str(value).strip().lower()
        if normalized not in ACTION_SCHEMA_BY_TYPE:
            raise ValueError("Unsupported action_type")
        return normalized


class ForecastTrainingRequest(BaseModel):
    company_id: Optional[int] = Field(default=None, ge=1)
    start_date: Optional[str] = Field(default=None)
    end_date: Optional[str] = Field(default=None)
    horizon: Optional[int] = Field(default=None, ge=1, le=365)


class RiskTrainingRequest(BaseModel):
    company_id: Optional[int] = Field(default=None, ge=1)
    start_date: Optional[str] = Field(default=None)
    end_date: Optional[str] = Field(default=None)


class RagTrainingRequest(BaseModel):
    company_id: Optional[int] = Field(default=None, ge=1)
    reindex_all: bool = Field(default=False)


class DeterministicTrainingRequest(BaseModel):
    company_id: Optional[int] = Field(default=None, ge=1)
    parameters: Dict[str, Any] = Field(default_factory=dict)

    @field_validator("parameters", mode="before")
    @classmethod
    def ensure_parameters(cls, value: Any) -> Dict[str, Any]:  # noqa: D417
        if value is None:
            return {}
        if not isinstance(value, dict):
            raise ValueError("parameters must be an object")
        return value


class WorkflowPlanRequest(BaseModel):
    company_id: int = Field(..., ge=1)
    workflow_type: str = Field(..., min_length=1)
    inputs: Dict[str, Any] = Field(default_factory=dict)
    user_context: Dict[str, Any] = Field(default_factory=dict)
    rfq_id: Optional[str] = Field(default=None)
    goal: Optional[str] = Field(default=None)

    @field_validator("workflow_type", mode="before")
    @classmethod
    def normalize_workflow_type(cls, value: Any) -> str:
        if value is None:
            raise ValueError("workflow_type is required")
        normalized = str(value).strip().lower()
        if not normalized:
            raise ValueError("workflow_type cannot be empty")
        return normalized

    @field_validator("inputs", "user_context", mode="before")
    @classmethod
    def ensure_mapping(cls, value: Any) -> Dict[str, Any]:
        if value is None:
            return {}
        if not isinstance(value, dict):
            raise ValueError("Value must be an object")
        return value

    @field_validator("goal", mode="before")
    @classmethod
    def normalize_goal(cls, value: Any) -> Optional[str]:
        if value is None:
            return None
        stripped = str(value).strip()
        return stripped or None


class WorkflowCompleteRequest(BaseModel):
    output: Dict[str, Any] = Field(default_factory=dict)
    approval: bool
    approved_by: Optional[str] = Field(default=None)

    @field_validator("output", mode="before")
    @classmethod
    def ensure_output(cls, value: Any) -> Dict[str, Any]:
        if value is None:
            return {}
        if not isinstance(value, dict):
            raise ValueError("output must be an object")
        return value

    @field_validator("approved_by", mode="before")
    @classmethod
    def normalize_approver(cls, value: Any) -> Optional[str]:
        if value is None:
            return None
        stripped = str(value).strip()
        return stripped or None


class ChatMessagePayload(BaseModel):
    role: Literal["user", "assistant", "system", "tool"]
    content: str = Field(..., min_length=1)
    content_json: Optional[Dict[str, Any]] = Field(default=None)
    created_at: Optional[str] = Field(default=None)

    @field_validator("content", mode="before")
    @classmethod
    def normalize_content(cls, value: Any) -> str:
        if not isinstance(value, str):
            raise ValueError("content must be a string")
        normalized = value.strip()
        if not normalized:
            raise ValueError("content cannot be empty")
        return normalized


class ChatRespondRequest(BaseModel):
    company_id: int = Field(..., ge=1)
    thread_id: str = Field(..., min_length=1)
    user_id_hash: str = Field(..., min_length=8, max_length=128)
    messages: List[ChatMessagePayload] = Field(..., min_length=1, max_length=100)
    context: Dict[str, Any] = Field(default_factory=dict)
    thread_summary: Optional[str] = Field(default=None, max_length=5000)
    allow_general: bool = Field(default=False)

    @field_validator("context", mode="before")
    @classmethod
    def ensure_context(cls, value: Any) -> Dict[str, Any]:
        if value is None:
            return {}
        if not isinstance(value, dict):
            raise ValueError("context must be an object")
        return value

    @field_validator("thread_summary", mode="before")
    @classmethod
    def normalize_thread_summary(cls, value: Any) -> Optional[str]:
        if value is None:
            return None
        if not isinstance(value, str):
            raise ValueError("thread_summary must be a string")
        sanitized = value.strip()
        return sanitized or None


class ToolResultPayload(BaseModel):
    tool_name: str = Field(..., min_length=1)
    call_id: str = Field(..., min_length=1)
    result: Dict[str, Any] = Field(default_factory=dict)

    @field_validator("result", mode="before")
    @classmethod
    def ensure_result(cls, value: Any) -> Dict[str, Any]:
        if value is None:
            return {}
        if not isinstance(value, dict):
            raise ValueError("result must be an object")
        return value


class WorkspaceToolRequest(BaseModel):
    company_id: Optional[int] = Field(default=None, ge=1)
    thread_id: Optional[int] = Field(default=None, ge=1)
    user_id: Optional[int] = Field(default=None, ge=1)
    context: List[Dict[str, Any]] = Field(default_factory=list, max_length=CHAT_TOOL_RESULTS_LIMIT)
    inputs: Dict[str, Any] = Field(default_factory=dict)

    @field_validator("context", mode="before")
    @classmethod
    def normalize_context_blocks(cls, value: Any) -> List[Dict[str, Any]]:
        if value is None:
            return []
        if not isinstance(value, list):
            raise ValueError("context must be an array")
        normalized: List[Dict[str, Any]] = []
        for block in value:
            if isinstance(block, dict):
                normalized.append(block)
        return normalized[:CHAT_TOOL_RESULTS_LIMIT]

    @field_validator("inputs", mode="before")
    @classmethod
    def ensure_inputs(cls, value: Any) -> Dict[str, Any]:
        if value is None:
            return {}
        if not isinstance(value, dict):
            raise ValueError("inputs must be an object")
        return value


class AwardQuoteToolRequest(WorkspaceToolRequest):
    pass


class InvoiceDraftToolRequest(WorkspaceToolRequest):
    pass


class ChatContinueRequest(BaseModel):
    company_id: int = Field(..., ge=1)
    thread_id: str = Field(..., min_length=1)
    user_id_hash: str = Field(..., min_length=8, max_length=128)
    messages: List[ChatMessagePayload] = Field(..., min_length=1, max_length=100)
    tool_results: List[ToolResultPayload] = Field(..., min_length=1, max_length=CHAT_TOOL_RESULTS_LIMIT)
    context: Dict[str, Any] = Field(default_factory=dict)
    thread_summary: Optional[str] = Field(default=None, max_length=5000)
    allow_general: bool = Field(default=False)

    @field_validator("context", mode="before")
    @classmethod
    def ensure_context(cls, value: Any) -> Dict[str, Any]:
        if value is None:
            return {}
        if not isinstance(value, dict):
            raise ValueError("context must be an object")
        return value

    @field_validator("thread_summary", mode="before")
    @classmethod
    def normalize_thread_summary(cls, value: Any) -> Optional[str]:
        if value is None:
            return None
        if not isinstance(value, str):
            raise ValueError("thread_summary must be a string")
        sanitized = value.strip()
        return sanitized or None


def resolve_llm_provider(override: Optional[str]) -> tuple[str, "LLMProvider"]:
    requested = (override or DEFAULT_LLM_PROVIDER_NAME or "dummy").strip().lower()
    if requested not in SUPPORTED_LLM_PROVIDERS:
        LOGGER.warning("llm_provider_override_invalid", extra=log_extra(provider=requested))
        requested = DEFAULT_LLM_PROVIDER_NAME

    provider = build_llm_provider(requested)
    return requested, provider


TrainingJobRunner = Callable[[str, Dict[str, Any]], None]


def _start_training_job(
    feature: str,
    parameters: Dict[str, Any],
    background_tasks: BackgroundTasks,
    runner: TrainingJobRunner,
) -> Dict[str, Any]:
    job = training_job_store.create_job(feature, {k: v for k, v in parameters.items() if v is not None})
    background_tasks.add_task(runner, job["id"], copy.deepcopy(job["parameters"]))
    LOGGER.info("training_job_enqueued", extra=log_extra(feature=feature, job_id=job["id"]))
    return job


def _coerce_forecast_horizon(parameters: Dict[str, Any]) -> int:
    horizon = parameters.get("horizon") or DEFAULT_FORECAST_TRAINING_HORIZON
    try:
        return max(1, int(horizon))
    except (TypeError, ValueError):  # pragma: no cover - sanitized upstream
        return DEFAULT_FORECAST_TRAINING_HORIZON


def _summarize_forecast_training(results: Dict[int, Dict[str, Any]], horizon: int) -> Dict[str, Any]:
    best_counts: Dict[str, int] = {}
    mape_values: List[float] = []
    mae_values: List[float] = []
    for part_data in results.values():
        best_model = part_data.get("best_model")
        if best_model:
            best_counts[best_model] = best_counts.get(best_model, 0) + 1
        metrics = (part_data.get("metrics") or {}).get(best_model or "", {})
        mape_value = metrics.get("mape")
        mae_value = metrics.get("mae")
        try:
            if mape_value is not None:
                mape_values.append(float(mape_value))
        except (TypeError, ValueError):  # pragma: no cover - defensive cast
            pass
        try:
            if mae_value is not None:
                mae_values.append(float(mae_value))
        except (TypeError, ValueError):  # pragma: no cover
            pass

    avg_mape: Optional[float] = None
    avg_mae: Optional[float] = None
    if mape_values:
        try:
            avg_mape = round(fmean(mape_values), 6)
        except StatisticsError:  # pragma: no cover
            avg_mape = None
    if mae_values:
        try:
            avg_mae = round(fmean(mae_values), 6)
        except StatisticsError:  # pragma: no cover
            avg_mae = None

    return {
        "feature": "forecast",
        "parts_trained": len(results),
        "horizon": horizon,
        "avg_mape": avg_mape,
        "avg_mae": avg_mae,
        "best_model_distribution": best_counts,
    }


def _simulate_rag_reindex(parameters: Dict[str, Any]) -> Dict[str, Any]:
    company_id = parameters.get("company_id")
    reindex_all = bool(parameters.get("reindex_all", False))
    stats = {
        "feature": "rag",
        "company_id": company_id,
        "reindex_all": reindex_all,
        "chunks_scanned": 0,
        "documents_scanned": 0,
        "tenants_touched": [],
        # TODO: clarify re-index orchestration strategy once document pipeline spec lands.
    }

    store = getattr(vector_store, "_store", None)
    if isinstance(store, dict):
        target_company_ids: List[int]
        if company_id is not None and not reindex_all:
            target_company_ids = [company_id]
        else:
            target_company_ids = list(store.keys())

        seen_tenants: set[int] = set()
        for tenant_id in target_company_ids:
            chunks = store.get(tenant_id, [])
            if not chunks:
                continue
            docs = {(chunk.doc_id, chunk.doc_version) for chunk in chunks}
            stats["chunks_scanned"] += len(chunks)
            stats["documents_scanned"] += len(docs)
            seen_tenants.add(int(tenant_id))

        stats["tenants_touched"] = sorted(seen_tenants)

    return stats


def _run_forecast_training(job_id: str, parameters: Dict[str, Any]) -> None:
    training_job_store.mark_running(job_id)
    started_at = time.perf_counter()
    try:
        horizon = _coerce_forecast_horizon(parameters)
        dataset = service.load_inventory_data(
            start_date=parameters.get("start_date"),
            end_date=parameters.get("end_date"),
            company_id=parameters.get("company_id"),
        )
        results = service.train_forecasting_models(dataset, horizon)
        summary = _summarize_forecast_training(results, horizon)
        summary["rows_processed"] = int(len(dataset)) if hasattr(dataset, "__len__") else 0
        summary["duration_ms"] = round((time.perf_counter() - started_at) * 1000, 2)
        training_job_store.mark_completed(job_id, summary)
        LOGGER.info(
            "training_forecast_complete",
            extra=log_extra(job_id=job_id, parts_trained=summary["parts_trained"], horizon=horizon),
        )
    except Exception as exc:  # pragma: no cover - surfaced to Laravel orchestrator
        training_job_store.mark_failed(job_id, str(exc))
        LOGGER.exception("training_forecast_failed", extra=log_extra(job_id=job_id))


def _run_risk_training(job_id: str, parameters: Dict[str, Any]) -> None:
    training_job_store.mark_running(job_id)
    started_at = time.perf_counter()
    try:
        supplier_df = service.load_supplier_data(
            start_date=parameters.get("start_date"),
            end_date=parameters.get("end_date"),
            company_id=parameters.get("company_id"),
        )
        sample_count = int(len(supplier_df)) if hasattr(supplier_df, "__len__") else 0
        if sample_count == 0:
            raise ValueError("No supplier data available for risk training")

        _, metrics = service.train_risk_model(supplier_df)
        summary = {
            "feature": "risk",
            "samples": sample_count,
            "metrics": metrics,
            "duration_ms": round((time.perf_counter() - started_at) * 1000, 2),
        }
        training_job_store.mark_completed(job_id, summary)
        LOGGER.info("training_risk_complete", extra=log_extra(job_id=job_id, samples=sample_count))
    except Exception as exc:  # pragma: no cover
        training_job_store.mark_failed(job_id, str(exc))
        LOGGER.exception("training_risk_failed", extra=log_extra(job_id=job_id))


def _run_rag_training(job_id: str, parameters: Dict[str, Any]) -> None:
    training_job_store.mark_running(job_id)
    started_at = time.perf_counter()
    try:
        summary = _simulate_rag_reindex(parameters)
        summary["duration_ms"] = round((time.perf_counter() - started_at) * 1000, 2)
        training_job_store.mark_completed(job_id, summary)
        LOGGER.info(
            "training_rag_complete",
            extra=log_extra(job_id=job_id, chunks=summary.get("chunks_scanned", 0)),
        )
    except Exception as exc:  # pragma: no cover
        training_job_store.mark_failed(job_id, str(exc))
        LOGGER.exception("training_rag_failed", extra=log_extra(job_id=job_id))


def _run_future_training(job_id: str, feature: str, parameters: Dict[str, Any]) -> None:
    training_job_store.mark_running(job_id)
    started_at = time.perf_counter()
    try:
        summary = {
            "feature": feature,
            "message": "Training hook placeholder executed.",
            "parameters": parameters,
            "duration_ms": round((time.perf_counter() - started_at) * 1000, 2),
        }
        # TODO: replace placeholder logic with deterministic heuristics/workflow training once spec is finalized.
        training_job_store.mark_completed(job_id, summary)
        LOGGER.info("training_%s_complete", feature, extra=log_extra(job_id=job_id))
    except Exception as exc:  # pragma: no cover
        training_job_store.mark_failed(job_id, str(exc))
        LOGGER.exception("training_%s_failed", feature, extra=log_extra(job_id=job_id))


def _run_actions_training(job_id: str, parameters: Dict[str, Any]) -> None:
    _run_future_training(job_id, "actions", parameters)


def _run_workflows_training(job_id: str, parameters: Dict[str, Any]) -> None:
    _run_future_training(job_id, "workflows", parameters)



def _execute_supplier_scrape_job(job_id: str, parameters: Dict[str, Any]) -> None:
    scrape_job_store.mark_running(job_id)
    try:
        results = supplier_scraper.scrape_suppliers(
            parameters.get("query", ""),
            parameters.get("region"),
            parameters.get("max_results", 10),
        )
        scrape_job_store.mark_completed(job_id, results or [])
        LOGGER.info(
            "supplier_scrape_completed",
            extra=log_extra(job_id=job_id, result_count=len(results or [])),
        )
    except Exception as exc:  # pragma: no cover - unexpected failures logged for operators
        scrape_job_store.mark_failed(job_id, str(exc))
        LOGGER.exception("supplier_scrape_failed", extra=log_extra(job_id=job_id))


@app.get("/healthz")
async def healthz() -> Dict[str, Any]:
    current_time = datetime.now(timezone.utc).isoformat()
    payload = {"status": "ok", "service": "ai_microservice", "time": current_time}
    LOGGER.info("healthz_probe", extra=log_extra(time=current_time))
    return payload


@app.get("/readyz")
async def readyz() -> Dict[str, Any]:
    snapshot = service.readiness_snapshot()
    payload = {
        "status": "ok",
        "models_loaded": bool(snapshot.get("models_loaded")),
        "last_trained_at": snapshot.get("last_trained_at"),
        "training_jobs": training_job_store.summary(),
    }
    LOGGER.info("readyz_probe", extra=log_extra(**snapshot))
    return payload


@app.post("/train/forecast", status_code=202)
async def train_forecast_job(payload: ForecastTrainingRequest, background_tasks: BackgroundTasks) -> Dict[str, Any]:
    parameters = payload.model_dump(exclude_none=True)
    job = _start_training_job("forecast", parameters, background_tasks, _run_forecast_training)
    return {"status": "accepted", "job_id": job["id"], "job": job}


@app.post("/train/risk", status_code=202)
async def train_risk_job(payload: RiskTrainingRequest, background_tasks: BackgroundTasks) -> Dict[str, Any]:
    parameters = payload.model_dump(exclude_none=True)
    job = _start_training_job("risk", parameters, background_tasks, _run_risk_training)
    return {"status": "accepted", "job_id": job["id"], "job": job}


@app.post("/train/rag", status_code=202)
async def train_rag_job(payload: RagTrainingRequest, background_tasks: BackgroundTasks) -> Dict[str, Any]:
    parameters = payload.model_dump(exclude_none=True)
    job = _start_training_job("rag", parameters, background_tasks, _run_rag_training)
    return {"status": "accepted", "job_id": job["id"], "job": job}


@app.post("/train/actions", status_code=202)
async def train_actions_job(payload: DeterministicTrainingRequest, background_tasks: BackgroundTasks) -> Dict[str, Any]:
    parameters = payload.model_dump(exclude_none=True)
    job = _start_training_job("actions", parameters, background_tasks, _run_actions_training)
    return {"status": "accepted", "job_id": job["id"], "job": job}


@app.post("/train/workflows", status_code=202)
async def train_workflows_job(payload: DeterministicTrainingRequest, background_tasks: BackgroundTasks) -> Dict[str, Any]:
    parameters = payload.model_dump(exclude_none=True)
    job = _start_training_job("workflows", parameters, background_tasks, _run_workflows_training)
    return {"status": "accepted", "job_id": job["id"], "job": job}


@app.get("/train/{job_id}/status")
async def training_job_status(job_id: str) -> Dict[str, Any]:
    job = training_job_store.get_job(job_id)
    if job is None:
        raise HTTPException(status_code=404, detail="Job not found")
    return {"status": "ok", "job": job}


@app.post("/scrape/suppliers", status_code=202)
async def start_supplier_scrape_job(payload: ScrapeSuppliersRequest, background_tasks: BackgroundTasks) -> Dict[str, Any]:
    parameters = payload.model_dump(exclude_none=True)
    job = scrape_job_store.create_job(parameters)
    background_tasks.add_task(_execute_supplier_scrape_job, job["id"], copy.deepcopy(parameters))
    LOGGER.info(
        "supplier_scrape_enqueued",
        extra=log_extra(job_id=job["id"], query=parameters.get("query")),
    )
    return {
        "status": "success",
        "message": "Supplier scrape job enqueued.",
        "data": {
            "job_id": job["id"],
            "job": job,
        },
        "errors": [],
    }


@app.get("/scrape/jobs/{job_id}")
async def fetch_supplier_scrape_job(job_id: str) -> Dict[str, Any]:
    job = scrape_job_store.get_job(job_id)
    if job is None:
        raise HTTPException(status_code=404, detail="Job not found")
    return {
        "status": "success",
        "message": "Supplier scrape job retrieved.",
        "data": {"job": job},
        "errors": [],
    }


@app.get("/scrape/jobs/{job_id}/results")
async def fetch_supplier_scrape_results(job_id: str, offset: int = 0, limit: int = 20) -> Dict[str, Any]:
    page = scrape_job_store.get_results(job_id, offset, limit)
    if page is None:
        raise HTTPException(status_code=404, detail="Job not found")
    return {
        "status": "success",
        "message": "Supplier scrape results retrieved.",
        "data": {
            "items": page["items"],
            "meta": page["meta"],
        },
        "errors": [],
    }


@app.post("/forecast")
async def forecast(payload: ForecastRequest, request: Request) -> Dict[str, Any]:
    cache_key = build_forecast_cache_key(payload)
    bypass_cache = should_bypass_cache(request)
    if not bypass_cache:
        cached_response = response_cache.get(cache_key)
        if cached_response is not None:
            LOGGER.info("forecast_cache_hit", extra=log_extra(cache_key=cache_key))
            return cached_response

    started_at = time.perf_counter()
    history_series = {entry["date"]: entry["quantity"] for entry in payload.history}
    try:
        response = service.predict_demand(payload.part_id, history_series, payload.horizon)
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.info(
            "forecast_success",
            extra=log_extra(
                part_id=payload.part_id,
                horizon=payload.horizon,
                history_points=len(payload.history),
                duration_ms=round(duration_ms, 2),
                demand_qty=float(response.get("demand_qty", 0.0)),
            ),
        )
        payload_response = {"status": "ok", "data": response}
        if not bypass_cache:
            response_cache.set(cache_key, payload_response)
            LOGGER.info("forecast_cache_store", extra=log_extra(cache_key=cache_key))
        return payload_response
    except Exception as exc:  # pragma: no cover - logged for operators
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.exception(
            "forecast_failure",
            extra=log_extra(
                part_id=payload.part_id,
                horizon=payload.horizon,
                history_points=len(payload.history),
                duration_ms=round(duration_ms, 2),
            ),
        )
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.post("/supplier-risk")
async def supplier_risk(payload: SupplierRiskRequest, request: Request) -> Dict[str, Any]:
    cache_key = build_supplier_risk_cache_key(payload)
    bypass_cache = should_bypass_cache(request)
    if not bypass_cache:
        cached_response = response_cache.get(cache_key)
        if cached_response is not None:
            LOGGER.info("supplier_risk_cache_hit", extra=log_extra(cache_key=cache_key))
            return cached_response

    started_at = time.perf_counter()
    try:
        response = service.predict_supplier_risk(payload.supplier)
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.info(
            "supplier_risk_success",
            extra=log_extra(
                supplier_id=payload.supplier.get("supplier_id") or payload.supplier.get("id"),
                company_id=payload.company_id,
                duration_ms=round(duration_ms, 2),
                risk_category=response.get("risk_category"),
            ),
        )
        payload_response = {"status": "ok", "data": response}
        if not bypass_cache:
            response_cache.set(cache_key, payload_response)
            LOGGER.info("supplier_risk_cache_store", extra=log_extra(cache_key=cache_key))
        return payload_response
    except Exception as exc:  # pragma: no cover
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.exception(
            "supplier_risk_failure",
            extra=log_extra(
                supplier_id=payload.supplier.get("supplier_id") or payload.supplier.get("id"),
                company_id=payload.company_id,
                duration_ms=round(duration_ms, 2),
            ),
        )
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.post("/index/document")
async def index_document(payload: IndexDocumentRequest) -> Dict[str, Any]:
    started_at = time.perf_counter()
    chunks = chunk_text(payload.text)
    if not chunks:
        raise HTTPException(status_code=400, detail="Document text must contain content to index")

    chunk_texts = [str(chunk.get("text", "")) for chunk in chunks]
    try:
        embeddings = embedding_provider.embed_texts(chunk_texts)
        metadata_payload = {
            "title": payload.title,
            "source_type": payload.source_type,
            "mime_type": payload.mime_type,
            "doc_version": payload.doc_version,
            **payload.metadata,
            "acl": payload.acl,
        }
        vector_store.upsert_chunks(
            company_id=payload.company_id,
            doc_id=payload.doc_id,
            doc_version=payload.doc_version,
            chunks=chunks,
            embeddings=embeddings,
            metadata=metadata_payload,
        )
    except HTTPException:
        raise
    except Exception as exc:  # pragma: no cover - logged for operators
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.exception(
            "index_document_failure",
            extra=log_extra(
                company_id=payload.company_id,
                doc_id=payload.doc_id,
                doc_version=payload.doc_version,
                chunk_count=len(chunks),
                duration_ms=round(duration_ms, 2),
            ),
        )
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    duration_ms = (time.perf_counter() - started_at) * 1000
    LOGGER.info(
        "index_document_success",
        extra=log_extra(
            company_id=payload.company_id,
            doc_id=payload.doc_id,
            doc_version=payload.doc_version,
            chunk_count=len(chunks),
            duration_ms=round(duration_ms, 2),
        ),
    )
    return {"status": "ok", "indexed_chunks": len(chunks)}


@app.post("/search")
async def semantic_search(payload: SearchRequest) -> Dict[str, Any]:
    started_at = time.perf_counter()
    filter_payload = payload.filters.model_dump(exclude_none=True) if payload.filters else {}
    try:
        query_embedding = embedding_provider.embed_texts([payload.query])[0]
        hits = vector_store.search(
            company_id=payload.company_id,
            query_embedding=query_embedding,
            top_k=payload.top_k,
            filters=filter_payload or None,
        )
    except HTTPException:
        raise
    except Exception as exc:  # pragma: no cover - logged for operators
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.exception(
            "semantic_search_failure",
            extra=log_extra(
                company_id=payload.company_id,
                top_k=payload.top_k,
                duration_ms=round(duration_ms, 2),
            ),
        )
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    duration_ms = (time.perf_counter() - started_at) * 1000
    response_hits: list[dict[str, Any]] = []
    for hit in hits:
        snippet = (hit.snippet or "")[:250]
        response_hits.append(
            {
                "doc_id": hit.doc_id,
                "doc_version": hit.doc_version,
                "chunk_id": hit.chunk_id,
                "score": hit.score,
                "title": hit.title,
                "snippet": snippet,
                "metadata": hit.metadata,
            }
        )

    response_hits = pack_context_hits(response_hits)

    LOGGER.info(
        "semantic_search_success",
        extra=log_extra(
            company_id=payload.company_id,
            hit_count=len(response_hits),
            top_k=payload.top_k,
            duration_ms=round(duration_ms, 2),
        ),
    )
    return {"status": "ok", "hits": response_hits}


@app.post("/answer")
async def answer(payload: AnswerRequest) -> Dict[str, Any]:
    started_at = time.perf_counter()
    search_response = await semantic_search(payload)
    hits = pack_context_hits(search_response.get("hits", []))
    context_blocks = _build_context_blocks(hits)
    general_allowed = ALLOW_UNGROUNDED_ANSWERS and bool(payload.allow_general)
    used_general_mode = False

    if not context_blocks and general_allowed:
        context_blocks = _build_general_context_blocks(payload.query)
        used_general_mode = True

    provider_used = "none"
    if not context_blocks:
        answer_payload = _build_no_hits_answer(payload.query)
    else:
        provider_name, provider_instance = resolve_llm_provider(payload.llm_provider)
        provider_used = provider_name
        try:
            answer_payload = provider_instance.generate_answer(
                payload.query,
                context_blocks,
                ANSWER_SCHEMA,
                payload.safety_identifier,
            )
        except LLMProviderError as exc:
            LOGGER.warning(
                "llm_provider_failure",
                extra=log_extra(
                    company_id=payload.company_id,
                    provider=provider_name,
                    error=str(exc),
                ),
            )
            answer_payload = _fallback_deterministic_answer(
                payload.query,
                context_blocks,
                warning="LLM provider unavailable; deterministic summary used",
            )
            provider_used = f"{provider_name}_fallback"

    answer_payload = _enforce_citation_integrity(answer_payload, context_blocks)
    if used_general_mode:
        answer_payload = _apply_general_answer_metadata(answer_payload)

    duration_ms = (time.perf_counter() - started_at) * 1000
    LOGGER.info(
        "answer_success",
        extra=log_extra(
            company_id=payload.company_id,
            hit_count=len(context_blocks),
            provider=provider_used,
            ungrounded=used_general_mode,
            duration_ms=round(duration_ms, 2),
        ),
    )
    return {"status": "ok", **answer_payload}


@app.post("/reports/summarize")
async def summarize_report(payload: ReportSummaryRequest) -> Dict[str, Any]:
    started_at = time.perf_counter()
    context_blocks = _build_report_summary_context_blocks(payload)
    provider_used = "deterministic"
    fallback_used = False
    summary_payload: Dict[str, Any]

    if not context_blocks:
        summary_payload = _fallback_report_summary(payload.report_type, payload.report_data, payload.filters_used)
        fallback_used = True
    else:
        query = _build_report_summary_query(payload.report_type, payload.filters_used)
        try:
            provider_name, provider = resolve_llm_provider(payload.llm_provider)
            provider_used = provider_name
            summary_payload = provider.generate_answer(
                query,
                context_blocks,
                REPORT_SUMMARY_SCHEMA,
                payload.user_id_hash,
            )
            summary_payload = _sanitize_report_summary(summary_payload)
        except LLMProviderError as exc:
            LOGGER.warning(
                "report_summary_llm_failed",
                extra=log_extra(
                    company_id=payload.company_id,
                    report_type=payload.report_type,
                    provider=provider_used,
                    error=str(exc),
                ),
            )
            summary_payload = _fallback_report_summary(payload.report_type, payload.report_data, payload.filters_used)
            provider_used = f"{provider_used}_fallback"
            fallback_used = True
        except Exception as exc:  # pragma: no cover - defensive guard
            LOGGER.exception(
                "report_summary_unexpected_failure",
                extra=log_extra(
                    company_id=payload.company_id,
                    report_type=payload.report_type,
                    error=str(exc),
                ),
            )
            raise HTTPException(status_code=500, detail="Failed to summarize report") from exc

    duration_ms = (time.perf_counter() - started_at) * 1000
    LOGGER.info(
        "report_summary_generated",
        extra=log_extra(
            company_id=payload.company_id,
            report_type=payload.report_type,
            provider=provider_used,
            fallback=fallback_used,
            duration_ms=round(duration_ms, 2),
        ),
    )

    return {
        "status": "ok",
        "data": summary_payload,
    }


@app.post("/actions/plan")
async def plan_action(payload: ActionPlanRequest) -> Dict[str, Any]:
    return await _execute_action_plan(payload)


@app.post("/chat/respond")
async def chat_respond(payload: ChatRespondRequest) -> Dict[str, Any]:
    started_at = time.perf_counter()
    router_response, memory_payload = await _chat_generate_router_response(payload)
    duration_ms = (time.perf_counter() - started_at) * 1000
    LOGGER.info(
        "chat_respond_success",
        extra=log_extra(
            thread_id=payload.thread_id,
            company_id=payload.company_id,
            intent=router_response.get("type"),
            duration_ms=round(duration_ms, 2),
        ),
    )
    return {
        "status": "ok",
        "data": {
            "response": router_response,
            "memory": memory_payload,
        },
    }


@app.post("/chat/respond_stream")
async def chat_respond_stream(payload: ChatRespondRequest) -> StreamingResponse:
    started_at = time.perf_counter()
    router_response, memory_payload = await _chat_generate_router_response(payload)
    duration_ms = (time.perf_counter() - started_at) * 1000
    chunk_size = int(os.getenv("AI_CHAT_STREAM_CHUNK_SIZE", "280"))
    chunks = _chat_chunk_markdown(router_response.get("assistant_message_markdown") or "", chunk_size)
    tool_calls = router_response.get("tool_calls") or []

    async def event_stream():
        yield _sse_encode(
            "start",
            {
                "thread_id": payload.thread_id,
                "company_id": payload.company_id,
                "response_type": router_response.get("type"),
            },
        )
        if tool_calls:
            yield _sse_encode("tool", {"tool_calls": tool_calls})
        for chunk in chunks:
            yield _sse_encode("delta", {"text": chunk})
        yield _sse_encode(
            "complete",
            {
                "response": router_response,
                "memory": memory_payload,
                "duration_ms": round(duration_ms, 2),
            },
        )

    LOGGER.info(
        "chat_respond_stream_ready",
        extra=log_extra(
            thread_id=payload.thread_id,
            company_id=payload.company_id,
            intent=router_response.get("type"),
            chunk_count=len(chunks),
        ),
    )

    headers = {
        "Cache-Control": "no-cache",
        "X-Accel-Buffering": "no",
    }
    return StreamingResponse(event_stream(), media_type="text/event-stream", headers=headers)


@app.post("/chat/continue")
async def chat_continue(payload: ChatContinueRequest) -> Dict[str, Any]:
    started_at = time.perf_counter()
    raw_messages = [message.model_dump() for message in payload.messages]
    trimmed_messages, overflow_messages = _chat_trim_messages(raw_messages)
    latest_user_text = _chat_latest_user_text(trimmed_messages)

    if not latest_user_text:
        raise HTTPException(status_code=400, detail="At least one user message is required")

    _, memory_payload = await _chat_prepare_conversation(
        trimmed_messages,
        overflow_messages,
        payload.thread_summary,
    )

    tool_results = [tool.model_dump() for tool in payload.tool_results]
    if not tool_results:
        raise HTTPException(status_code=400, detail="tool_results are required")

    tool_context_blocks = _chat_tool_results_to_context_blocks(tool_results)
    if not tool_context_blocks:
        raise HTTPException(status_code=400, detail="tool_results must include result data")

    review_response = _chat_try_build_review_response(tool_results)

    try:
        if review_response is not None:
            router_response = review_response
        else:
            router_response = await _chat_build_answer_response(
                company_id=payload.company_id,
                query=latest_user_text,
                safety_identifier=payload.user_id_hash,
                intent="workspace_qna",
                extra_context_blocks=tool_context_blocks,
                allow_general_override=bool(payload.allow_general),
            )
    except HTTPException:
        raise
    except Exception as exc:  # pragma: no cover - defensive guard
        LOGGER.exception(
            "chat_continue_failure",
            extra=log_extra(thread_id=payload.thread_id, error=str(exc)),
        )
        raise HTTPException(status_code=500, detail="Failed to continue chat") from exc

    try:
        _chat_validate_response(router_response)
    except ValidationError as exc:  # pragma: no cover
        LOGGER.error(
            "chat_continue_validation_failed",
            extra=log_extra(error=str(exc), thread_id=payload.thread_id),
        )
        raise HTTPException(status_code=500, detail="Chat response failed validation") from exc

    duration_ms = (time.perf_counter() - started_at) * 1000
    LOGGER.info(
        "chat_continue_success",
        extra=log_extra(
            thread_id=payload.thread_id,
            company_id=payload.company_id,
            tool_count=len(tool_results),
            duration_ms=round(duration_ms, 2),
        ),
    )

    return {
        "status": "ok",
        "data": {
            "response": router_response,
            "memory": memory_payload,
        },
    }


@app.post("/v1/ai/tools/build_award_quote")
async def build_award_quote_tool(payload: AwardQuoteToolRequest) -> Dict[str, Any]:
    context_blocks = payload.context[:CHAT_TOOL_RESULTS_LIMIT]
    tool_payload = build_award_quote(context_blocks, payload.inputs)
    citations = _build_tool_citations(context_blocks)
    summary = "Award quote draft generated."
    LOGGER.info(
        "tool_award_quote_built",
        extra=log_extra(
            company_id=payload.company_id,
            thread_id=payload.thread_id,
            user_id=payload.user_id,
        ),
    )
    return {
        "status": "ok",
        "message": summary,
        "data": {
            "summary": summary,
            "payload": tool_payload,
            "citations": citations,
        },
    }


@app.post("/v1/ai/tools/build_invoice_draft")
async def build_invoice_draft_tool(payload: InvoiceDraftToolRequest) -> Dict[str, Any]:
    context_blocks = payload.context[:CHAT_TOOL_RESULTS_LIMIT]
    tool_payload = build_invoice_draft(context_blocks, payload.inputs)
    citations = _build_tool_citations(context_blocks)
    summary = "Invoice draft generated."
    LOGGER.info(
        "tool_invoice_draft_built",
        extra=log_extra(
            company_id=payload.company_id,
            thread_id=payload.thread_id,
            user_id=payload.user_id,
        ),
    )
    return {
        "status": "ok",
        "message": summary,
        "data": {
            "summary": summary,
            "payload": tool_payload,
            "citations": citations,
        },
    }


@app.post("/v1/ai/tools/review_rfq")
async def review_rfq_tool(payload: WorkspaceToolRequest) -> Dict[str, Any]:
    context_blocks = payload.context[:CHAT_TOOL_RESULTS_LIMIT]
    tool_payload = review_rfq(context_blocks, payload.inputs)
    citations = _build_tool_citations(context_blocks)
    summary = "RFQ review checklist generated."
    LOGGER.info(
        "tool_review_rfq_built",
        extra=log_extra(company_id=payload.company_id, thread_id=payload.thread_id, user_id=payload.user_id),
    )
    return {
        "status": "ok",
        "message": summary,
        "data": {
            "summary": summary,
            "payload": tool_payload,
            "citations": citations,
        },
    }


@app.post("/v1/ai/tools/review_quote")
async def review_quote_tool(payload: WorkspaceToolRequest) -> Dict[str, Any]:
    context_blocks = payload.context[:CHAT_TOOL_RESULTS_LIMIT]
    tool_payload = review_quote(context_blocks, payload.inputs)
    citations = _build_tool_citations(context_blocks)
    summary = "Quote review checklist generated."
    LOGGER.info(
        "tool_review_quote_built",
        extra=log_extra(company_id=payload.company_id, thread_id=payload.thread_id, user_id=payload.user_id),
    )
    return {
        "status": "ok",
        "message": summary,
        "data": {
            "summary": summary,
            "payload": tool_payload,
            "citations": citations,
        },
    }


@app.post("/v1/ai/tools/review_po")
async def review_po_tool(payload: WorkspaceToolRequest) -> Dict[str, Any]:
    context_blocks = payload.context[:CHAT_TOOL_RESULTS_LIMIT]
    tool_payload = review_po(context_blocks, payload.inputs)
    citations = _build_tool_citations(context_blocks)
    summary = "PO review checklist generated."
    LOGGER.info(
        "tool_review_po_built",
        extra=log_extra(company_id=payload.company_id, thread_id=payload.thread_id, user_id=payload.user_id),
    )
    return {
        "status": "ok",
        "message": summary,
        "data": {
            "summary": summary,
            "payload": tool_payload,
            "citations": citations,
        },
    }


@app.post("/v1/ai/tools/review_invoice")
async def review_invoice_tool(payload: WorkspaceToolRequest) -> Dict[str, Any]:
    context_blocks = payload.context[:CHAT_TOOL_RESULTS_LIMIT]
    tool_payload = review_invoice(context_blocks, payload.inputs)
    citations = _build_tool_citations(context_blocks)
    summary = "Invoice review checklist generated."
    LOGGER.info(
        "tool_review_invoice_built",
        extra=log_extra(company_id=payload.company_id, thread_id=payload.thread_id, user_id=payload.user_id),
    )
    return {
        "status": "ok",
        "message": summary,
        "data": {
            "summary": summary,
            "payload": tool_payload,
            "citations": citations,
        },
    }


async def _chat_generate_router_response(payload: ChatRespondRequest) -> tuple[Dict[str, Any], Dict[str, Any]]:
    raw_messages = [message.model_dump() for message in payload.messages]
    trimmed_messages, overflow_messages = _chat_trim_messages(raw_messages)
    latest_user_text = _chat_latest_user_text(trimmed_messages)

    if not latest_user_text:
        raise HTTPException(status_code=400, detail="At least one user message is required")

    context_payload = payload.context or {}
    safety_identifier = payload.user_id_hash
    conversation_messages, memory_payload = await _chat_prepare_conversation(
        trimmed_messages,
        overflow_messages,
        payload.thread_summary,
    )
    tool_rounds = _chat_tool_round_count(trimmed_messages)
    allow_general_override = bool(payload.allow_general)

    async def answer_builder(intent: chat_router.ChatIntent) -> Dict[str, Any]:
        return await _chat_build_answer_response(
            company_id=payload.company_id,
            query=latest_user_text,
            safety_identifier=safety_identifier,
            intent=intent,
            allow_general_override=allow_general_override,
        )

    async def action_builder(intent: chat_router.ChatIntent) -> Dict[str, Any]:
        action_type = chat_router.ACTION_INTENTS.get(intent)
        if action_type is None:
            raise HTTPException(status_code=400, detail=f"Unsupported action intent '{intent}'")
        return await _chat_build_action_response(
            company_id=payload.company_id,
            query=latest_user_text,
            action_type=action_type,
            intent=intent,
            context_payload=context_payload,
            safety_identifier=safety_identifier,
        )

    async def workflow_builder(intent: chat_router.ChatIntent) -> Dict[str, Any]:
        return await _chat_build_workflow_response(
            company_id=payload.company_id,
            query=latest_user_text,
            context_payload=context_payload,
            intent=intent,
        )

    def tool_request_builder(intent: chat_router.ChatIntent) -> Dict[str, Any]:
        if CHAT_TOOL_ROUND_LIMIT > 0 and tool_rounds >= CHAT_TOOL_ROUND_LIMIT:
            return _chat_build_tool_limit_response(intent=intent, query=latest_user_text)

        return _chat_build_tool_request_response(intent=intent, query=latest_user_text)

    async def llm_classifier(messages: Sequence[Dict[str, Any]], query: str) -> Optional[chat_router.ChatIntent]:
        return await _chat_llm_intent_classifier(messages, query, safety_identifier)

    dependencies: chat_router.ChatRouterDependencies = {
        "answer_builder": answer_builder,
        "action_builder": action_builder,
        "workflow_builder": workflow_builder,
        "tool_request_builder": tool_request_builder,
        "llm_intent_classifier": llm_classifier,
    }

    try:
        router_response = await chat_router.handle_chat_request(
            messages=conversation_messages,
            latest_user_text=latest_user_text,
            context=context_payload,
            dependencies=dependencies,
        )
    except RuntimeError as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc

    try:
        _chat_validate_response(router_response)
    except ValidationError as exc:  # pragma: no cover - defensive validation
        LOGGER.error(
            "chat_response_validation_failed",
            extra=log_extra(error=str(exc), thread_id=payload.thread_id),
        )
        raise HTTPException(status_code=500, detail="Chat response failed validation") from exc

    return router_response, memory_payload


async def _execute_action_plan(payload: ActionPlanRequest) -> Dict[str, Any]:
    started_at = time.perf_counter()
    action_schema = ACTION_SCHEMA_BY_TYPE[payload.action_type]
    filter_payload = payload.filters.model_dump(exclude_none=True) if payload.filters else {}
    warnings: List[str] = []

    try:
        hits = _run_action_semantic_search(
            payload.company_id,
            payload.query,
            payload.top_k,
            filter_payload or None,
        )
        context_blocks = _build_context_blocks(hits)
        context_insufficient = not context_blocks

        tool_payload = _invoke_tool_contract(payload.action_type, context_blocks, payload.inputs)
        tool_response = _build_tool_response(payload, tool_payload, context_blocks)

        action_prompt = _build_action_prompt(payload)
        provider_name, provider_instance = resolve_llm_provider(payload.llm_provider)
        provider_used = provider_name
        try:
            ai_response = provider_instance.generate_answer(
                action_prompt,
                context_blocks,
                action_schema,
                payload.safety_identifier,
            )
        except LLMProviderError as exc:
            warnings.append("LLM provider unavailable; deterministic tool result only")
            LOGGER.warning(
                "action_plan_llm_failure",
                extra=log_extra(
                    company_id=payload.company_id,
                    action_type=payload.action_type,
                    provider=provider_name,
                    error=str(exc),
                ),
            )
            ai_response = None
            provider_used = f"{provider_name}_fallback"

        sanitized_response = _enforce_citation_integrity(ai_response or {}, context_blocks)
        action_response = _merge_tool_and_llm(payload, tool_response, sanitized_response)
        if context_insufficient:
            action_response.setdefault("warnings", []).append("Insufficient grounded context for this action")
            action_response["needs_human_review"] = True
        if warnings:
            action_response.setdefault("warnings", []).extend(warnings)
        if action_response.get("warnings"):
            deduped = list(dict.fromkeys(action_response["warnings"]))
            action_response["warnings"] = deduped

        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.info(
            "action_plan_success",
            extra=log_extra(
                company_id=payload.company_id,
                action_type=payload.action_type,
                provider=provider_used,
                hit_count=len(context_blocks),
                duration_ms=round(duration_ms, 2),
            ),
        )
        return action_response
    except HTTPException:
        raise
    except Exception as exc:  # pragma: no cover - defensive guard
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.exception(
            "action_plan_failure",
            extra=log_extra(
                company_id=payload.company_id,
                action_type=payload.action_type,
                duration_ms=round(duration_ms, 2),
                error=str(exc),
            ),
        )
        raise HTTPException(status_code=500, detail="Failed to generate AI action plan") from exc


@app.post("/workflows/plan")
async def plan_workflow(payload: WorkflowPlanRequest) -> Dict[str, Any]:
    try:
        action_sequence = _build_workflow_plan(payload.workflow_type, payload.rfq_id, payload.inputs)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    workflow_goal = payload.goal or payload.inputs.get("goal")
    query = workflow_goal or f"{payload.workflow_type} workflow"

    try:
        workflow = workflow_engine.plan_workflow(
            query=query,
            action_sequence=action_sequence,
            company_id=payload.company_id,
            user_context=payload.user_context,
            workflow_type=payload.workflow_type,
            metadata={
                "rfq_id": payload.rfq_id,
                "inputs": payload.inputs,
                "goal": workflow_goal,
            },
        )
    except WorkflowEngineError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    next_step = workflow["steps"][0] if workflow.get("steps") else None
    LOGGER.info(
        "workflow_plan_created",
        extra=log_extra(
            workflow_id=workflow["workflow_id"],
            workflow_type=workflow["workflow_type"],
            company_id=payload.company_id,
        ),
    )
    return {
        "status": "ok",
        "data": {
            "workflow_id": workflow["workflow_id"],
            "workflow_type": workflow["workflow_type"],
            "status": workflow["status"],
            "next_step": _serialize_step(next_step),
        },
    }


@app.get("/workflows/{workflow_id}/next")
async def get_next_workflow_step(workflow_id: str) -> Dict[str, Any]:
    try:
        step = workflow_engine.get_next_step(workflow_id)
    except WorkflowNotFoundError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc

    workflow_snapshot = workflow_engine.get_workflow_snapshot(workflow_id)
    if not step:
        return {
            "status": "ok",
            "data": {
                "workflow_id": workflow_id,
                "workflow_status": workflow_snapshot["status"],
                "step": None,
            },
        }

    action_response = await _execute_workflow_step(workflow_snapshot, step)
    updated_step = workflow_engine.update_step_draft(workflow_id, step.get("step_index", 0), action_response)
    refreshed_workflow = workflow_engine.get_workflow_snapshot(workflow_id)
    LOGGER.info(
        "workflow_step_drafted",
        extra=log_extra(
            workflow_id=workflow_id,
            step_index=step.get("step_index"),
            action_type=step.get("action_type"),
        ),
    )
    return {
        "status": "ok",
        "data": {
            "workflow_id": workflow_id,
            "workflow_status": refreshed_workflow["status"],
            "step": _serialize_step(updated_step),
        },
    }


@app.post("/workflows/{workflow_id}/complete")
async def complete_workflow_step(workflow_id: str, payload: WorkflowCompleteRequest) -> Dict[str, Any]:
    try:
        workflow = workflow_engine.complete_step(
            workflow_id,
            payload.output,
            payload.approval,
            approved_by=payload.approved_by,
        )
    except WorkflowNotFoundError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc
    except WorkflowEngineError as exc:  # pragma: no cover - validation guard
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    next_step = None
    current_index = workflow.get("current_step_index")
    if current_index is not None:
        steps = workflow.get("steps", [])
        if 0 <= current_index < len(steps):
            next_step = steps[current_index]

    LOGGER.info(
        "workflow_step_completed",
        extra=log_extra(
            workflow_id=workflow_id,
            approval=payload.approval,
            next_step=current_index,
        ),
    )
    return {
        "status": "ok",
        "data": {
            "workflow_id": workflow_id,
            "workflow_status": workflow.get("status"),
            "next_step": _serialize_step(next_step),
        },
    }


def _chat_chunk_markdown(markdown: str, chunk_size: int) -> List[str]:
    safe_chunk_size = max(1, int(chunk_size))
    sanitized = markdown or ""
    if not sanitized:
        return [""]
    return [sanitized[index : index + safe_chunk_size] for index in range(0, len(sanitized), safe_chunk_size)]


def _sse_encode(event: str, payload: Dict[str, Any]) -> bytes:
    try:
        serialized = json.dumps(payload, ensure_ascii=True, separators=(",", ":"))
    except TypeError:
        serialized = json.dumps(str(payload), ensure_ascii=True)
    return f"event: {event}\ndata: {serialized}\n\n".encode("utf-8")


def _chat_trim_messages(messages: Sequence[Dict[str, Any]]) -> tuple[List[Dict[str, Any]], List[Dict[str, Any]]]:
    sanitized: List[Dict[str, Any]] = []
    for entry in messages:
        role = str(entry.get("role") or "").lower()
        content = entry.get("content")
        if role not in {"user", "assistant", "system", "tool"}:
            continue
        if not isinstance(content, str):
            continue
        normalized = content.strip()
        if not normalized:
            continue
        content_json = entry.get("content_json") if isinstance(entry.get("content_json"), dict) else None
        sanitized.append({"role": role, "content": normalized, "content_json": content_json})

    overflow: List[Dict[str, Any]] = []
    if len(sanitized) > CHAT_HISTORY_LIMIT:
        overflow = sanitized[:-CHAT_HISTORY_LIMIT]
        sanitized = sanitized[-CHAT_HISTORY_LIMIT:]

    return sanitized, overflow


async def _chat_prepare_conversation(
    trimmed_messages: List[Dict[str, Any]],
    overflow_messages: List[Dict[str, Any]],
    thread_summary: Optional[str],
) -> tuple[List[Dict[str, Any]], Dict[str, Any]]:
    summary_text = (thread_summary or "").strip() or None
    summary_updated = False

    if overflow_messages:
        updated_summary = await _chat_generate_summary_text(overflow_messages, summary_text)
        if updated_summary:
            summary_updated = updated_summary != summary_text
            summary_text = updated_summary

    conversation = list(trimmed_messages)
    memory_payload: Dict[str, Any] = {}

    if summary_text:
        truncated = _truncate_summary(summary_text)
        conversation = [
            {
                "role": "system",
                "content": f"Conversation summary (earlier turns):\n{truncated}",
                "content_json": None,
            },
            *conversation,
        ]

        if summary_updated and truncated:
            memory_payload["thread_summary"] = truncated

    return conversation, memory_payload


async def _chat_generate_summary_text(
    overflow_messages: Sequence[Dict[str, Any]],
    existing_summary: Optional[str],
) -> Optional[str]:
    transcript = _chat_messages_to_text(overflow_messages)
    if not transcript:
        return existing_summary

    snippet = transcript[-CHAT_SUMMARY_TRIGGER_TOKENS:]
    context_blocks = [
        {
            "doc_id": "conversation_history",
            "doc_version": "overflow",
            "chunk_id": 0,
            "score": 1.0,
            "snippet": snippet,
        }
    ]

    if existing_summary:
        context_blocks.append(
            {
                "doc_id": "existing_summary",
                "doc_version": "1",
                "chunk_id": 1,
                "score": 0.9,
                "snippet": existing_summary[-CHAT_SUMMARY_TARGET_CHARS:],
            }
        )

    summary_prompt = (
        "Summarize the earlier conversation so future AI responses stay grounded. "
        "Provide up to four concise bullets covering goals, blockers, and decisions."
    )
    provider_name, provider = resolve_llm_provider(None)

    try:
        summary_payload = provider.generate_answer(
            summary_prompt,
            context_blocks,
            ANSWER_SCHEMA,
            None,
        )
        summary_text = summary_payload.get("answer_markdown", "").strip()
    except LLMProviderError as exc:
        LOGGER.warning("chat_summary_llm_failure", extra=log_extra(error=str(exc), provider=provider_name))
        summary_text = _chat_fallback_summary(snippet)

    if not summary_text:
        return existing_summary

    merged = summary_text if not existing_summary else f"{existing_summary}\n\nEarlier turns:\n{summary_text}"
    return _truncate_summary(merged)


def _chat_messages_to_text(messages: Sequence[Dict[str, Any]]) -> str:
    lines: List[str] = []
    for entry in messages:
        role = entry.get("role", "assistant")
        content = entry.get("content")
        if isinstance(content, str) and content.strip():
            lines.append(f"{role}: {content.strip()}")
    return "\n".join(lines)


def _chat_fallback_summary(transcript: str) -> str:
    sentences = [segment.strip() for segment in transcript.split("\n") if segment.strip()]
    if not sentences:
        return ""

    bullets = []
    for sentence in sentences[:4]:
        bullets.append(f"- {sentence[:200]}")
    return "\n".join(bullets)


def _truncate_summary(summary: str) -> str:
    if len(summary) <= CHAT_SUMMARY_TARGET_CHARS:
        return summary
    return summary[-CHAT_SUMMARY_TARGET_CHARS:]


def _chat_tool_round_count(messages: Sequence[Dict[str, Any]]) -> int:
    rounds = 0
    for entry in reversed(messages):
        role = entry.get("role")
        if role == "assistant":
            content_json = entry.get("content_json")
            if isinstance(content_json, dict) and content_json.get("type") == "tool_request":
                rounds += 1
            continue
        if role == "tool":
            continue
        if role == "user":
            break
    return rounds


def _chat_latest_user_text(messages: Sequence[Dict[str, Any]]) -> Optional[str]:
    for entry in reversed(messages):
        if entry.get("role") != "user":
            continue
        content = entry.get("content")
        if isinstance(content, str) and content.strip():
            return content.strip()
    return None


async def _chat_build_answer_response(
    *,
    company_id: int,
    query: str,
    safety_identifier: Optional[str],
    intent: chat_router.ChatIntent,
    extra_context_blocks: Optional[List[Dict[str, Any]]] = None,
    allow_general_override: bool = False,
) -> Dict[str, Any]:
    allow_general = ALLOW_UNGROUNDED_ANSWERS and (intent == "general_qna" or allow_general_override)
    answer_payload, provider_used, used_general_mode = _chat_generate_answer(
        company_id,
        query,
        safety_identifier,
        extra_context_blocks=extra_context_blocks,
        allow_general=allow_general,
    )
    response = {
        "type": "answer",
        "assistant_message_markdown": answer_payload.get("answer_markdown", ""),
        "citations": answer_payload.get("citations", []),
        "suggested_quick_replies": _chat_suggest_quick_replies(intent, "answer"),
        "draft": None,
        "workflow": None,
        "tool_calls": None,
        "needs_human_review": bool(answer_payload.get("needs_human_review", True)),
        "confidence": _clamp_float(answer_payload.get("confidence", 0.0)),
        "warnings": answer_payload.get("warnings", []),
    }
    LOGGER.info(
        "chat_answer_response",
        extra=log_extra(company_id=company_id, provider=provider_used, intent=intent, ungrounded=used_general_mode),
    )
    return response


def _chat_generate_answer(
    company_id: int,
    query: str,
    safety_identifier: Optional[str],
    *,
    extra_context_blocks: Optional[List[Dict[str, Any]]] = None,
    allow_general: bool = False,
) -> tuple[Dict[str, Any], str, bool]:
    hits = _run_action_semantic_search(company_id, query, top_k=8)
    context_blocks = _build_context_blocks(hits)
    if extra_context_blocks:
        context_blocks.extend(extra_context_blocks)
    used_general_mode = False
    if not context_blocks and allow_general:
        context_blocks = _build_general_context_blocks(query)
        used_general_mode = True
    provider_name, provider_instance = resolve_llm_provider(None)
    provider_used = provider_name
    if not context_blocks:
        answer_payload = _build_no_hits_answer(query)
    else:
        try:
            answer_payload = provider_instance.generate_answer(
                query,
                context_blocks,
                ANSWER_SCHEMA,
                safety_identifier,
            )
        except LLMProviderError as exc:
            LOGGER.warning(
                "chat_answer_llm_failure",
                extra=log_extra(company_id=company_id, provider=provider_name, error=str(exc)),
            )
            answer_payload = _fallback_deterministic_answer(
                query,
                context_blocks,
                warning="LLM provider unavailable; deterministic output used",
            )
            provider_used = f"{provider_name}_fallback"

    sanitized = _enforce_citation_integrity(answer_payload, context_blocks)
    if used_general_mode:
        sanitized = _apply_general_answer_metadata(sanitized)
    return sanitized, provider_used, used_general_mode


async def _chat_build_action_response(
    *,
    company_id: int,
    query: str,
    action_type: str,
    intent: chat_router.ChatIntent,
    context_payload: Dict[str, Any],
    safety_identifier: Optional[str],
) -> Dict[str, Any]:
    inputs = _chat_coerce_action_inputs(context_payload)
    user_context = _chat_get_user_context(context_payload)
    action_request = ActionPlanRequest(
        company_id=company_id,
        action_type=action_type,
        query=query,
        inputs=inputs,
        user_context=user_context,
    )
    action_response = await _execute_action_plan(action_request)
    assistant_markdown = action_response.get("summary") or f"Draft ready for {action_type}"
    return {
        "type": "draft_action",
        "assistant_message_markdown": assistant_markdown,
        "citations": action_response.get("citations", []),
        "suggested_quick_replies": _chat_suggest_quick_replies(intent, "draft_action"),
        "draft": action_response,
        "workflow": None,
        "tool_calls": None,
        "needs_human_review": bool(action_response.get("needs_human_review", True)),
        "confidence": _clamp_float(action_response.get("confidence", 0.0)),
        "warnings": action_response.get("warnings", []),
    }


def _chat_coerce_action_inputs(context_payload: Dict[str, Any]) -> Dict[str, Any]:
    candidate = context_payload.get("context") or context_payload.get("inputs") or {}
    return candidate if isinstance(candidate, dict) else {}


def _chat_get_user_context(context_payload: Dict[str, Any]) -> Dict[str, Any]:
    candidate = context_payload.get("user_context")
    return candidate if isinstance(candidate, dict) else {}


async def _chat_build_workflow_response(
    *,
    company_id: int,
    query: str,
    context_payload: Dict[str, Any],
    intent: chat_router.ChatIntent,
) -> Dict[str, Any]:
    workflow_type = str(context_payload.get("workflow_type") or "procurement").lower()
    template = WORKFLOW_STEP_TEMPLATES.get(workflow_type) or WORKFLOW_STEP_TEMPLATES.get("procurement", [])
    steps: List[Dict[str, Any]] = []
    for index, step in enumerate(template[:5]):
        steps.append(
            {
                "title": step.get("name") or ACTION_TYPE_LABELS.get(step.get("action_type"), "Workflow Step"),
                "summary": f"Draft {step.get('action_type')} task #{index + 1} based on Copilot plan.",
                "payload": {"action_type": step.get("action_type"), "metadata": step.get("metadata", {})},
            }
        )

    workflow_payload = {
        "workflow_type": workflow_type,
        "steps": steps,
        "payload": {
            "goal": query,
            "company_id": company_id,
        },
    }
    return {
        "type": "workflow_suggestion",
        "assistant_message_markdown": f"Suggested {workflow_type} workflow ready to review.",
        "citations": [],
        "suggested_quick_replies": _chat_suggest_quick_replies(intent, "workflow"),
        "draft": None,
        "workflow": workflow_payload,
        "tool_calls": None,
        "needs_human_review": True,
        "confidence": 0.45,
        "warnings": ["Workflow suggestions require approval before execution."],
    }


def _chat_build_tool_request_response(*, intent: chat_router.ChatIntent, query: str) -> Dict[str, Any]:
    tool_name, arguments = _chat_detect_tool_call(query)
    tool_call = {
        "tool_name": tool_name,
        "call_id": str(uuid.uuid4()),
        "arguments": arguments,
    }
    return {
        "type": "tool_request",
        "assistant_message_markdown": "Fetching workspace information before responding...",
        "citations": [],
        "suggested_quick_replies": _chat_suggest_quick_replies(intent, "tool"),
        "draft": None,
        "workflow": None,
        "tool_calls": [tool_call],
        "needs_human_review": False,
        "confidence": 0.35,
        "warnings": [],
    }


def _chat_try_build_review_response(tool_results: Sequence[Dict[str, Any]]) -> Optional[Dict[str, Any]]:
    for tool_result in tool_results:
        tool_name = tool_result.get("tool_name")
        if not isinstance(tool_name, str):
            continue
        response_type = REVIEW_TOOL_TYPE_MAP.get(tool_name)
        if response_type is None:
            continue
        result_payload = tool_result.get("result") or {}
        if not isinstance(result_payload, dict):
            continue
        review_payload = result_payload.get("payload")
        if not isinstance(review_payload, dict):
            continue
        summary = result_payload.get("summary") or "Review checklist generated."
        citations = result_payload.get("citations")
        return _chat_build_review_response(
            response_type=response_type,
            summary=summary,
            payload=review_payload,
            citations=citations if isinstance(citations, list) else [],
        )
    return None


def _chat_build_review_response(
    *,
    response_type: str,
    summary: str,
    payload: Dict[str, Any],
    citations: Sequence[Dict[str, Any]] | Sequence[str],
) -> Dict[str, Any]:
    markdown = _chat_render_review_markdown(summary, payload)
    quick_replies = REVIEW_TOOL_QUICK_REPLIES.get(response_type, [
        "Share summary",
        "Ask for follow-up",
    ])
    highlights = payload.get("highlights") if isinstance(payload.get("highlights"), list) else []
    return {
        "type": response_type,
        "assistant_message_markdown": markdown,
        "citations": citations,
        "suggested_quick_replies": quick_replies,
        "draft": None,
        "workflow": None,
        "tool_calls": None,
        "needs_human_review": True,
        "confidence": 0.6,
        "warnings": highlights or [],
        "review": payload,
    }


def _chat_render_review_markdown(summary: str, payload: Dict[str, Any]) -> str:
    lines = [summary]
    checklist = payload.get("checklist")
    if isinstance(checklist, list):
        for item in checklist[:4]:
            if not isinstance(item, dict):
                continue
            label = item.get("label") or "Metric"
            value = item.get("value")
            detail = item.get("detail") or ""
            status = item.get("status") or "ok"
            value_text = f" — {value}" if value not in (None, "") else ""
            lines.append(f"- **{label}** ({status}){value_text}. {detail}".strip())
    return "\n".join(lines)


def _chat_build_tool_limit_response(*, intent: chat_router.ChatIntent, query: str) -> Dict[str, Any]:  # noqa: ARG001
    warning = "Reached the configured workspace lookup limit. Please refine the question or request help."
    return {
        "type": "answer",
        "assistant_message_markdown": (
            "I already reviewed multiple workspace sources for this question. "
            "Let's adjust the request or involve a buyer to continue."
        ),
        "citations": [],
        "suggested_quick_replies": _chat_suggest_quick_replies(intent, "answer"),
        "draft": None,
        "workflow": None,
        "tool_calls": None,
        "needs_human_review": True,
        "confidence": 0.2,
        "warnings": [warning],
    }


AGGREGATE_WORKSPACE_QUERY_PATTERNS: List[re.Pattern[str]] = [
    re.compile(r"\bhow many\b", re.IGNORECASE),
    re.compile(r"\bcount\b", re.IGNORECASE),
    re.compile(r"\bnumber of\b", re.IGNORECASE),
    re.compile(r"\btotal\b", re.IGNORECASE),
]

WORKSPACE_STATUS_KEYWORDS: Dict[str, List[re.Pattern[str]]] = {
    "draft": [re.compile(r"\bdraft(s)?\b", re.IGNORECASE)],
    "open": [re.compile(r"\bopen\b", re.IGNORECASE), re.compile(r"\bpublished\b", re.IGNORECASE)],
    "closed": [re.compile(r"\bclosed\b", re.IGNORECASE), re.compile(r"\bcomplete(d)?\b", re.IGNORECASE)],
    "awarded": [re.compile(r"\baward(ed)?\b", re.IGNORECASE)],
    "cancelled": [re.compile(r"\bcancel(l)?ed\b", re.IGNORECASE), re.compile(r"\bvoid(ed)?\b", re.IGNORECASE)],
}

QUOTE_STATUS_KEYWORDS: Dict[str, List[re.Pattern[str]]] = {
    "draft": [re.compile(r"\bdraft(s)?\b", re.IGNORECASE)],
    "submitted": [re.compile(r"\bsubmitted\b", re.IGNORECASE), re.compile(r"\bnew quote(s)?\b", re.IGNORECASE)],
    "withdrawn": [re.compile(r"\bwithdrawn\b", re.IGNORECASE)],
    "rejected": [re.compile(r"\breject(ed)?\b", re.IGNORECASE)],
    "awarded": [re.compile(r"\baward(ed)?\b", re.IGNORECASE), re.compile(r"\bwon\b", re.IGNORECASE)],
}


def _chat_detect_tool_call(query: str) -> tuple[str, Dict[str, Any]]:
    patterns = [
        (re.compile(r"rfq\s*(?:#|no\.?|id)?\s*(\d+)", re.IGNORECASE), "workspace.get_rfq", "rfq_id"),
        (re.compile(r"quote\s*(?:#|no\.?|id)?\s*(\d+)", re.IGNORECASE), "workspace.get_quotes_for_rfq", "rfq_id"),
        (re.compile(r"inventory\s+(?:item|sku)\s*([\w-]+)", re.IGNORECASE), "workspace.get_inventory_item", "sku"),
    ]
    for pattern, tool_name, argument_key in patterns:
        match = pattern.search(query)
        if match:
            return tool_name, {argument_key: match.group(1), "limit": 1}
    lowered = query.lower()
    if "quote" in lowered:
        arguments = {"limit": 5}
        quote_statuses = _extract_quote_status_filters(query)
        if quote_statuses:
            arguments["statuses"] = quote_statuses
        return "workspace.stats_quotes", arguments
    if "supplier" in lowered:
        return "workspace.list_suppliers", {"limit": 5}
    if "inventory" in lowered or "stock" in lowered:
        return "workspace.low_stock", {"limit": 5}

    arguments: Dict[str, Any] = {"query": query.strip(), "limit": 5}
    status_filters = _extract_workspace_status_filters(query)
    if status_filters:
        arguments["statuses"] = status_filters
    if _is_aggregate_workspace_query(query):
        arguments["query"] = ""
    return "workspace.search_rfqs", arguments


def _is_aggregate_workspace_query(query: str) -> bool:
    normalized = query.strip().lower()
    if not normalized:
        return False
    return any(pattern.search(normalized) for pattern in AGGREGATE_WORKSPACE_QUERY_PATTERNS)


def _extract_workspace_status_filters(query: str) -> List[str]:
    normalized = query.strip().lower()
    if not normalized:
        return []
    statuses: List[str] = []
    for status, patterns in WORKSPACE_STATUS_KEYWORDS.items():
        if any(pattern.search(normalized) for pattern in patterns):
            statuses.append(status)
    # Preserve order but drop duplicates
    seen: set[str] = set()
    deduped: List[str] = []
    for status in statuses:
        if status in seen:
            continue
        seen.add(status)
        deduped.append(status)
    return deduped


def _extract_quote_status_filters(query: str) -> List[str]:
    normalized = query.strip().lower()
    if not normalized:
        return []
    statuses: List[str] = []
    for status, patterns in QUOTE_STATUS_KEYWORDS.items():
        if any(pattern.search(normalized) for pattern in patterns):
            statuses.append(status)
    seen: set[str] = set()
    deduped: List[str] = []
    for status in statuses:
        if status in seen:
            continue
        seen.add(status)
        deduped.append(status)
    return deduped


def _chat_tool_results_to_context_blocks(tool_results: Sequence[Dict[str, Any]]) -> List[Dict[str, Any]]:
    blocks: List[Dict[str, Any]] = []
    for index, tool_result in enumerate(tool_results[:CHAT_TOOL_RESULTS_LIMIT]):
        tool_name = str(tool_result.get("tool_name") or "workspace.tool")
        call_id = str(tool_result.get("call_id") or index)
        result_payload = tool_result.get("result")
        snippet = _chat_render_tool_result_snippet(tool_name, result_payload)
        blocks.append(
            {
                "doc_id": f"workspace_tool::{tool_name}",
                "doc_version": call_id,
                "chunk_id": index,
                "score": 0.98,
                "snippet": snippet,
            }
        )
    return blocks


def _chat_render_tool_result_snippet(tool_name: str, result_payload: Any) -> str:
    if not result_payload:
        return f"Tool {tool_name} returned no data."

    if isinstance(result_payload, dict):
        formatted = _format_tool_result_payload(tool_name, result_payload)
        if formatted:
            return formatted

    try:
        serialized = json.dumps(result_payload, ensure_ascii=True, separators=(",", ":"))
    except TypeError:
        serialized = str(result_payload)
    snippet = f"{tool_name}: {serialized}"
    return snippet[:2000]


def _format_tool_result_payload(tool_name: str, payload: Dict[str, Any]) -> Optional[str]:
    formatter_registry: Dict[str, Callable[[Dict[str, Any]], str]] = {
        "workspace.search_rfqs": _format_workspace_search_rfqs_result,
        "workspace.stats_quotes": _format_workspace_stats_quotes_result,
    }
    formatter = formatter_registry.get(tool_name)
    if not formatter:
        return None
    try:
        return formatter(payload)
    except Exception as exc:  # pragma: no cover - defensive formatting guard
        LOGGER.warning(
            "tool_result_format_failed",
            extra=log_extra(tool_name=tool_name, error=str(exc)),
        )
        return None


def _format_workspace_search_rfqs_result(payload: Dict[str, Any]) -> str:
    items = payload.get("items") if isinstance(payload.get("items"), list) else []
    meta = payload.get("meta") if isinstance(payload.get("meta"), dict) else {}
    query = str(meta.get("query") or "").strip()
    total_count = _coerce_int(meta.get("total_count"), default=len(items))
    status_filters = _normalize_status_filter_meta(meta.get("statuses"))
    subject = _format_status_filter_subject(status_filters, "RFQs") or "RFQs"

    qualifier_parts: List[str] = []
    if status_filters:
        qualifier_parts.append(subject)
    else:
        qualifier_parts.append("RFQs")
    if query:
        qualifier_parts.append(f"matching \"{query}\"")
    qualifier_text = " ".join(part for part in qualifier_parts if part)

    subject_text = qualifier_text or subject
    if total_count <= 0:
        headline = f"No {subject_text}."
    elif total_count == 1:
        headline = f"Found 1 {subject_text}."
    else:
        headline = f"Found {total_count} {subject_text}."

    status_summary = _build_status_summary(meta.get("status_counts"))
    details: List[str] = []
    for entry in items[:3]:
        if not isinstance(entry, dict):
            continue
        label = entry.get("number") or f"RFQ #{entry.get('rfq_id') or '?'}"
        status_text = _format_status_label(entry.get("status"))
        due_text = _format_iso_date(entry.get("due_at") or entry.get("close_at"))
        snippet = f"{label} · {status_text}"
        if due_text:
            snippet = f"{snippet} · due {due_text}"
        details.append(snippet)

    parts = [headline]
    if status_summary:
        parts.append(f"Status mix: {status_summary}.")
    if details:
        parts.append(f"Top results: {'; '.join(details)}.")
    return " ".join(parts)


def _format_workspace_stats_quotes_result(payload: Dict[str, Any]) -> str:
    items = payload.get("items") if isinstance(payload.get("items"), list) else []
    meta = payload.get("meta") if isinstance(payload.get("meta"), dict) else {}
    total_count = _coerce_int(meta.get("total_count"), default=len(items))
    status_filters = _normalize_status_filter_meta(meta.get("statuses"))
    subject = _format_status_filter_subject(status_filters, "Quotes") or "Quotes"
    descriptor = subject.lower() if status_filters else "quotes"

    descriptor_singular = descriptor[:-1] if descriptor.endswith("s") else descriptor
    if total_count <= 0:
        headline = f"No {descriptor} on file."
    elif total_count == 1:
        headline = f"1 {descriptor_singular} on file."
    else:
        headline = f"{total_count} {descriptor} on file."

    status_summary = _build_status_summary(meta.get("status_counts"))
    details: List[str] = []
    for entry in items[:3]:
        if not isinstance(entry, dict):
            continue
        label = entry.get("quote_id")
        quote_label = f"Quote #{label}" if label is not None else "Quote"
        status_text = _format_status_label(entry.get("status"))
        total_text = _format_currency_value(entry.get("total_price"), entry.get("currency"))
        submitted_text = _format_iso_date(entry.get("submitted_at"))
        rfq_ref = entry.get("rfq_id")

        snippet_parts = [quote_label, status_text]
        if total_text:
            snippet_parts.append(total_text)
        if submitted_text:
            snippet_parts.append(f"submitted {submitted_text}")
        if rfq_ref:
            snippet_parts.append(f"RFQ {rfq_ref}")
        details.append(" · ".join(snippet_parts))

    parts = [headline]
    if status_summary:
        parts.append(f"Status mix: {status_summary}.")
    if details:
        parts.append(f"Recent submissions: {'; '.join(details)}.")
    return " ".join(parts)


def _normalize_status_filter_meta(value: Any) -> List[str]:
    if isinstance(value, list):
        candidates = value
    elif isinstance(value, str):
        candidates = [segment.strip() for segment in value.split(",")]
    else:
        return []

    normalized: List[str] = []
    seen: set[str] = set()
    for candidate in candidates:
        if not isinstance(candidate, str):
            continue
        clean = candidate.strip().lower()
        if not clean or clean in seen:
            continue
        seen.add(clean)
        normalized.append(clean)
    return normalized


def _format_status_filter_subject(status_filters: Sequence[str], noun: str) -> str:
    labels = [_format_status_label(status) for status in status_filters if status]
    labels = [label for label in labels if label]
    subject = noun.strip() or "Records"
    if not labels:
        return ""
    if len(labels) == 1:
        return f"{labels[0]} {subject}"
    if len(labels) == 2:
        return f"{labels[0]} and {labels[1]} {subject}"
    return f"{', '.join(labels[:-1])}, and {labels[-1]} {subject}"


def _build_status_summary(status_counts: Any) -> str:
    if isinstance(status_counts, dict):
        items = status_counts.items()
    elif isinstance(status_counts, list):
        items = []
        for entry in status_counts:
            if not isinstance(entry, dict):
                continue
            status = entry.get("status")
            count = entry.get("count") or entry.get("aggregate")
            items.append((status, count))
    else:
        return ""

    summary_parts: List[str] = []
    for status, count in items:
        status_label = _format_status_label(status)
        count_value = _coerce_int(count)
        summary_parts.append(f"{status_label} {count_value}")
    return ", ".join(summary_parts)


def _format_status_label(value: Any) -> str:
    if not isinstance(value, str) or not value.strip():
        return "Unspecified"
    normalized = value.strip().replace("_", " ")
    return normalized.title() or "Unspecified"


def _format_iso_date(value: Any) -> Optional[str]:
    if not isinstance(value, str) or not value.strip():
        return None
    normalized = value.strip().replace("Z", "+00:00")
    try:
        parsed = datetime.fromisoformat(normalized)
    except ValueError:
        return normalized[:10]
    return parsed.strftime("%b %d")


def _coerce_int(value: Any, default: int = 0) -> int:
    try:
        return int(value)
    except (TypeError, ValueError):
        return default


def _format_currency_value(amount: Any, currency: Any) -> Optional[str]:
    try:
        value = float(amount)
    except (TypeError, ValueError):
        return None
    code = str(currency).upper().strip() if isinstance(currency, str) else ""
    formatted_value = f"{value:,.2f}" if abs(value) < 1000 else f"{value:,.0f}"
    if not code:
        return formatted_value
    return f"{code} {formatted_value}".strip()


def _chat_suggest_quick_replies(intent: chat_router.ChatIntent, mode: str) -> List[str]:
    mapping = {
        "answer": ["Show citations", "Summarize again", "Draft an RFQ"],
        "draft_action": ["Approve draft", "Revise inputs", "Share with team"],
        "workflow": ["Start workflow", "Show next step"],
        "tool": ["Use workspace data", "Never mind"],
    }
    return mapping.get(mode, ["Thanks", "Follow up later"])


def _chat_validate_response(response: Dict[str, Any]) -> None:
    CHAT_RESPONSE_VALIDATOR.validate(response)


async def _chat_llm_intent_classifier(
    messages: Sequence[Dict[str, Any]],
    query: str,
    safety_identifier: Optional[str],
) -> Optional[chat_router.ChatIntent]:
    provider_name, provider = resolve_llm_provider(None)
    if isinstance(provider, DummyLLMProvider):
        return None

    conversation_text = _chat_conversation_text(messages)
    classification_prompt = (
        "Classify the user's latest request into one of these intents: workspace_qna, rfq_draft, "
        "supplier_message, maintenance_checklist, inventory_whatif, quote_compare, start_workflow, general_qna. "
        "Return a JSON object with keys intent and reason."
    )
    context_blocks = [
        {
            "doc_id": "conversation",
            "doc_version": "1",
            "chunk_id": 0,
            "score": 1.0,
            "snippet": conversation_text[-4000:],
        }
    ]
    try:
        result = provider.generate_answer(
            f"{classification_prompt}\nLatest user request: {query}",
            context_blocks,
            INTENT_CLASSIFICATION_SCHEMA,
            safety_identifier,
        )
    except LLMProviderError as exc:  # pragma: no cover - network guard
        LOGGER.warning("chat_intent_llm_failure", extra=log_extra(error=str(exc), provider=provider_name))
        return None

    intent_value = result.get("intent")
    if isinstance(intent_value, str):
        normalized = intent_value.strip().lower()
        allowed = set(chat_router.ACTION_INTENTS.keys()) | chat_router.WORKFLOW_INTENTS | {"workspace_qna", "general_qna"}
        if normalized in allowed:
            return normalized  # type: ignore[return-value]
    return None


def _chat_conversation_text(messages: Sequence[Dict[str, Any]]) -> str:
    lines: List[str] = []
    for entry in messages[-10:]:
        role = entry.get("role", "assistant")
        content = entry.get("content", "")
        if isinstance(content, str):
            lines.append(f"{role}: {content.strip()}")
    return "\n".join(lines)


def _build_context_blocks(hits: Sequence[Dict[str, Any]]) -> List[Dict[str, Any]]:
    blocks: List[Dict[str, Any]] = []
    for hit in hits:
        blocks.append(
            {
                "doc_id": hit.get("doc_id"),
                "doc_version": hit.get("doc_version"),
                "chunk_id": hit.get("chunk_id"),
                "score": hit.get("score", 0.0),
                "title": hit.get("title"),
                "snippet": (hit.get("snippet") or "")[:250],
                "metadata": hit.get("metadata") or {},
            }
        )
    return blocks


def _build_general_context_blocks(query: Optional[str]) -> List[Dict[str, Any]]:
    prompt = (
        "No workspace documents matched this request. Provide general procurement guidance and be explicit "
        "that the answer is not grounded in tenant data."
    )
    if query:
        prompt = f"{prompt} User question: {str(query)[:500]}"
    return [
        {
            "doc_id": "general_knowledge",
            "doc_version": "v1",
            "chunk_id": 0,
            "score": 0.0,
            "title": "General Knowledge",
            "snippet": prompt[:600],
            "metadata": {"source_type": "general"},
        }
    ]


def _build_no_hits_answer(query: Optional[str] = None) -> Dict[str, Any]:
    return {
        "answer_markdown": _friendly_default_message(query),
        "citations": [],
        "confidence": 0.0,
        "needs_human_review": True,
        "warnings": ["No relevant sources found"],
    }


def _friendly_default_message(query: Optional[str]) -> str:
    normalized = (query or "").strip().lower()
    greeting_terms = [
        "hi",
        "hello",
        "hey",
        "good morning",
        "good afternoon",
        "good evening",
        "greetings",
    ]
    if normalized:
        for term in greeting_terms:
            if normalized == term or normalized.startswith(f"{term} "):
                return "Hello! How can I help you today?"

    if not normalized:
        return "Hello! How can I help you today?"

    return (
        "Not enough information in workspace data for that yet, but I am ready to chat and help with RFQs, "
        "quotes, suppliers, or general questions. What should we tackle?"
    )


def _fallback_deterministic_answer(
    query: str,
    context_blocks: Sequence[Dict[str, Any]],
    warning: str,
) -> Dict[str, Any]:
    fallback = DummyLLMProvider()
    answer_payload = fallback.generate_answer(query, context_blocks, ANSWER_SCHEMA)
    answer_payload.setdefault("warnings", []).append(warning)
    answer_payload["needs_human_review"] = True
    return answer_payload


def _build_report_summary_query(report_type: str, filters: Dict[str, Any]) -> str:
    range_label = _report_filters_label(filters)
    if report_type == "forecast":
        focus = (
            "Summarize forecast accuracy, demand deltas, and reorder recommendations. "
            "Highlight parts driving variance and actionable adjustments. "
        )
    else:
        focus = (
            "Summarize supplier service levels covering on-time delivery, defects, lead-time variance, "
            "price volatility, responsiveness, and risk posture. "
        )

    return (
        f"{focus}Data covers {range_label}. "
        "Return JSON with summary_markdown (2 focused sentences) and bullets (3-6 concise highlights). "
        "Call out anomalies, improvements, or risks."
    )


def _build_report_summary_context_blocks(payload: ReportSummaryRequest) -> List[Dict[str, Any]]:
    lines: List[str] = []
    range_label = _report_filters_label(payload.filters_used)
    if range_label:
        lines.append(f"Date range: {range_label}")

    filter_line = _report_filter_details(payload.filters_used)
    if filter_line:
        lines.append(filter_line)

    if payload.report_type == "forecast":
        lines.extend(_forecast_context_lines(payload.report_data))
    else:
        lines.extend(_supplier_context_lines(payload.report_data))

    snippet = "\n".join(line for line in lines if line).strip()
    if not snippet:
        snippet = "No analytics data supplied."

    snippet = snippet[:1600]
    return [
        {
            "doc_id": f"{payload.report_type}_report",
            "doc_version": "summary_v1",
            "chunk_id": 0,
            "score": 1.0,
            "title": f"{payload.report_type.replace('_', ' ').title()} analytics",
            "snippet": snippet,
            "metadata": {
                "report_type": payload.report_type,
                "company_id": payload.company_id,
            },
        }
    ]


def _forecast_context_lines(report_data: Dict[str, Any]) -> List[str]:
    lines: List[str] = []
    aggregates = report_data.get("aggregates") if isinstance(report_data, dict) else {}
    if isinstance(aggregates, dict) and aggregates:
        total_actual = _format_number(aggregates.get("total_actual"), 1)
        total_forecast = _format_number(aggregates.get("total_forecast"), 1)
        variance = _format_number(_safe_number(aggregates.get("total_actual")) - _safe_number(aggregates.get("total_forecast")), 1)
        lines.append(
            "Aggregates: "
            f"actual={total_actual}, forecast={total_forecast}, variance={variance}, "
            f"MAPE={_format_percent(aggregates.get('mape'))}, MAE={_format_number(aggregates.get('mae'), 2)}, "
            f"avg_daily_demand={_format_number(aggregates.get('avg_daily_demand'), 2)}, "
            f"recommended_reorder={_format_number(aggregates.get('recommended_reorder_point'), 2)}, "
            f"recommended_safety_stock={_format_number(aggregates.get('recommended_safety_stock'), 2)}"
        )

    table = report_data.get("table") if isinstance(report_data, dict) else None
    if isinstance(table, list) and table:
        ranked_rows = sorted(
            table,
            key=lambda row: _safe_number((row or {}).get("total_actual")),
            reverse=True,
        )[:3]
        for row in ranked_rows:
            if not isinstance(row, dict):
                continue
            part_name = _normalize_string(row.get("part_name"), f"Part {row.get('part_id') or '?'}") or "Part"
            actual = _format_number(row.get("total_actual"), 1)
            forecast = _format_number(row.get("total_forecast"), 1)
            delta = _format_number(_safe_number(row.get("total_actual")) - _safe_number(row.get("total_forecast")), 1)
            lines.append(
                f"{part_name}: actual={actual}, forecast={forecast}, variance={delta}, "
                f"reorder_point={_format_number(row.get('reorder_point'), 2)}, safety_stock={_format_number(row.get('safety_stock'), 2)}"
            )

    return lines


def _supplier_context_lines(report_data: Dict[str, Any]) -> List[str]:
    lines: List[str] = []
    aggregates = report_data.get("aggregates") if isinstance(report_data, dict) else {}
    if isinstance(aggregates, dict) and aggregates:
        lines.append(
            "Aggregates: "
            f"on_time={_format_percent(aggregates.get('on_time_delivery_rate'))}, "
            f"defect_rate={_format_percent(aggregates.get('defect_rate'))}, "
            f"lead_time_std={_format_number(aggregates.get('lead_time_variance'), 2)} days, "
            f"price_volatility={_format_number(aggregates.get('price_volatility'), 3)}, "
            f"responsiveness={_format_number(aggregates.get('service_responsiveness'), 2)} hours"
        )

    table = report_data.get("table") if isinstance(report_data, dict) else None
    if isinstance(table, list) and table:
        row = table[0] if isinstance(table[0], dict) else {}
        if isinstance(row, dict):
            supplier_name = _normalize_string(row.get("supplier_name"), "Supplier") or "Supplier"
            risk_category = _normalize_string(row.get("risk_category"), "unknown") or "unknown"
            risk_score = _format_number(row.get("risk_score"), 2)
            lines.append(
                f"Supplier {supplier_name}: risk_category={risk_category}, risk_score={risk_score}, "
                f"on_time={_format_percent(row.get('on_time_delivery_rate'))}, defect_rate={_format_percent(row.get('defect_rate'))}"
            )

    series = report_data.get("series") if isinstance(report_data, dict) else None
    if isinstance(series, list):
        for entry in series[:3]:
            trend = _supplier_metric_trend(entry)
            if trend:
                lines.append(trend)

    return lines


def _supplier_metric_trend(entry: Any) -> Optional[str]:
    if not isinstance(entry, dict):
        return None
    data = entry.get("data")
    if not isinstance(data, list) or len(data) < 2:
        return None
    first = data[0]
    last = data[-1]
    if not isinstance(first, dict) or not isinstance(last, dict):
        return None
    start_value = _safe_number(first.get("value"))
    end_value = _safe_number(last.get("value"))
    if start_value == end_value == 0:
        return None
    metric_label = _normalize_string(entry.get("label") or entry.get("metric_name"), "Metric") or "Metric"
    delta = end_value - start_value
    return f"{metric_label}: {start_value:.3f} -> {end_value:.3f} (Δ {delta:.3f})"


def _sanitize_report_summary(payload: Dict[str, Any]) -> Dict[str, Any]:
    summary_markdown = _normalize_string(payload.get("summary_markdown"), "")
    if not summary_markdown:
        summary_markdown = "Summary unavailable."

    bullets: List[str] = []
    raw_bullets = payload.get("bullets")
    if isinstance(raw_bullets, list):
        for entry in raw_bullets:
            text = _normalize_string(entry, "")
            if text:
                bullets.append(text)
    if not bullets:
        bullets = ["No additional highlights were provided."]

    source = _normalize_string(payload.get("source"), "ai") or "ai"
    provider = _normalize_string(payload.get("provider"), "llm") or "llm"

    return {
        "summary_markdown": summary_markdown,
        "bullets": bullets,
        "source": source,
        "provider": provider,
    }


def _fallback_report_summary(report_type: str, report_data: Dict[str, Any], filters: Dict[str, Any]) -> Dict[str, Any]:
    if report_type == "forecast":
        return _fallback_forecast_summary(report_data, filters)
    return _fallback_supplier_summary(report_data, filters)


def _fallback_forecast_summary(report_data: Dict[str, Any], filters: Dict[str, Any]) -> Dict[str, Any]:
    aggregates = report_data.get("aggregates") if isinstance(report_data, dict) else {}
    total_forecast = _safe_number((aggregates or {}).get("total_forecast"))
    total_actual = _safe_number((aggregates or {}).get("total_actual"))
    variance = total_actual - total_forecast
    range_label = _report_filters_label(filters)

    summary_markdown = (
        f"Between {range_label} we recorded **{_format_number(total_actual, 1)}** units of actual consumption "
        f"versus **{_format_number(total_forecast, 1)}** forecasted units (Δ {_format_number(variance, 1)})."
    )

    bullets = [
        f"MAPE {_format_percent((aggregates or {}).get('mape'))} and MAE {_format_number((aggregates or {}).get('mae'), 2)} units.",
        f"Average daily demand is ~{_format_number((aggregates or {}).get('avg_daily_demand'), 2)} units.",
        (
            "Recommended reorder point "
            f"{_format_number((aggregates or {}).get('recommended_reorder_point'), 2)} with safety stock "
            f"{_format_number((aggregates or {}).get('recommended_safety_stock'), 2)}."
        ),
    ]

    table = report_data.get("table") if isinstance(report_data, dict) else None
    if isinstance(table, list) and table:
        top_row = max(table, key=lambda row: _safe_number((row or {}).get("total_actual")))
        if isinstance(top_row, dict):
            part_name = _normalize_string(top_row.get("part_name"), f"Part {top_row.get('part_id') or '?'}") or "Top part"
            part_delta = _safe_number(top_row.get("total_actual")) - _safe_number(top_row.get("total_forecast"))
            bullets.append(
                f"{part_name}: actual {_format_number(top_row.get('total_actual'), 1)} vs forecast "
                f"{_format_number(top_row.get('total_forecast'), 1)} (Δ {_format_number(part_delta, 1)})."
            )

    return {
        "summary_markdown": summary_markdown,
        "bullets": bullets,
        "source": "fallback",
        "provider": "deterministic",
    }


def _fallback_supplier_summary(report_data: Dict[str, Any], filters: Dict[str, Any]) -> Dict[str, Any]:
    aggregates = report_data.get("aggregates") if isinstance(report_data, dict) else {}
    table = report_data.get("table") if isinstance(report_data, dict) else None
    table_row = (table[0] if isinstance(table, list) and table else {}) if table else {}
    on_time = _format_percent((aggregates or {}).get("on_time_delivery_rate") or table_row.get("on_time_delivery_rate"))
    defect = _format_percent((aggregates or {}).get("defect_rate") or table_row.get("defect_rate"))
    range_label = _report_filters_label(filters)

    summary_markdown = (
        f"Performance from {range_label} shows an on-time delivery rate of **{on_time}** with **{defect}** defects."
    )

    lead_variance = _format_number((aggregates or {}).get("lead_time_variance") or table_row.get("lead_time_variance"), 2)
    responsiveness = _format_number((aggregates or {}).get("service_responsiveness") or table_row.get("service_responsiveness"), 2)
    price_volatility = _format_number((aggregates or {}).get("price_volatility") or table_row.get("price_volatility"), 3)
    risk_category = _normalize_string(table_row.get("risk_category"), "unknown") or "unknown"
    risk_score = _format_number(table_row.get("risk_score"), 2)

    bullets = [
        f"Lead-time volatility ~{lead_variance} days; responsiveness around {responsiveness} hours.",
        f"Price volatility index {price_volatility}; current risk category {risk_category} (score {risk_score}).",
        "Monitor defect spikes alongside lead-time swings to protect service levels.",
    ]

    return {
        "summary_markdown": summary_markdown,
        "bullets": bullets,
        "source": "fallback",
        "provider": "deterministic",
    }


def _report_filters_label(filters: Dict[str, Any]) -> str:
    start = _normalize_string(filters.get("start_date"), "") if isinstance(filters, dict) else ""
    end = _normalize_string(filters.get("end_date"), "") if isinstance(filters, dict) else ""
    if start and end:
        return f"{start} to {end}"
    if start:
        return f"{start} onward"
    if end:
        return f"up to {end}"
    return "the selected period"


def _report_filter_details(filters: Dict[str, Any]) -> str:
    if not isinstance(filters, dict) or not filters:
        return ""
    bits: List[str] = []
    for key in ("part_ids", "category_ids", "location_ids"):
        value = filters.get(key)
        if isinstance(value, list) and value:
            bits.append(f"{key}={len(value)} selected")
    bucket = filters.get("bucket")
    if bucket:
        bits.append(f"bucket={bucket}")
    supplier_id = filters.get("supplier_id")
    if supplier_id:
        bits.append(f"supplier_id={supplier_id}")
    return f"Filters: {', '.join(bits)}" if bits else ""


def _apply_general_answer_metadata(answer_payload: Dict[str, Any]) -> Dict[str, Any]:
    payload = dict(answer_payload)
    payload["citations"] = []
    warnings = list(payload.get("warnings") or [])
    warnings.append(GENERAL_ANSWER_WARNING)
    payload["warnings"] = list(dict.fromkeys(warnings))
    payload["needs_human_review"] = True
    payload["confidence"] = _clamp_float(payload.get("confidence", 0.35), maximum=0.55)
    return payload


def _enforce_citation_integrity(
    answer_payload: Dict[str, Any],
    context_blocks: Sequence[Dict[str, Any]],
) -> Dict[str, Any]:
    try:
        sanitized_payload = dict(answer_payload)
        citations = list(sanitized_payload.get("citations") or [])
        warnings = list(sanitized_payload.get("warnings") or [])
        context_index = {
            (
                str(block.get("doc_id")),
                str(block.get("doc_version")),
                str(block.get("chunk_id")),
            ): block
            for block in context_blocks
            if block.get("doc_id") is not None and block.get("doc_version") is not None
        }

        sanitized_citations: List[Dict[str, Any]] = []
        invalid_count = 0
        for citation in citations:
            doc_id = citation.get("doc_id")
            doc_version = citation.get("doc_version")
            chunk_id = citation.get("chunk_id")
            key = (str(doc_id), str(doc_version), str(chunk_id))
            block = context_index.get(key)
            if not block:
                invalid_count += 1
                continue

            sanitized_citations.append(
                {
                    "doc_id": str(block.get("doc_id")),
                    "doc_version": str(block.get("doc_version")),
                    "chunk_id": int(block.get("chunk_id") or 0),
                    "score": float(citation.get("score", block.get("score", 0.0)) or 0.0),
                    "snippet": (citation.get("snippet") or block.get("snippet") or "")[:250],
                }
            )

        if invalid_count:
            warnings.append("One or more citations could not be verified against retrieved sources")
        if not sanitized_citations and context_blocks:
            warnings.append("Response did not include verifiable citations")

        sanitized_payload["citations"] = sanitized_citations
        if warnings:
            deduped_warnings = list(dict.fromkeys(warnings))
            sanitized_payload["warnings"] = deduped_warnings
            sanitized_payload["needs_human_review"] = True
        else:
            sanitized_payload["warnings"] = []
        return sanitized_payload
    except Exception as exc:  # pragma: no cover - defensive guard
        LOGGER.warning("citation_validation_error", extra=log_extra(error=str(exc)))
        return answer_payload


def _run_action_semantic_search(
    company_id: int,
    query: str,
    top_k: int,
    filters: Optional[Dict[str, Any]] = None,
) -> List[Dict[str, Any]]:
    started_at = time.perf_counter()
    filter_payload = dict(filters or {})
    try:
        query_embedding = embedding_provider.embed_texts([query])[0]
        hits = vector_store.search(
            company_id=company_id,
            query_embedding=query_embedding,
            top_k=top_k,
            filters=filter_payload or None,
        )
    except HTTPException:
        raise
    except Exception as exc:  # pragma: no cover - logged for operators
        duration_ms = (time.perf_counter() - started_at) * 1000
        LOGGER.exception(
            "action_semantic_search_failure",
            extra=log_extra(
                company_id=company_id,
                top_k=top_k,
                duration_ms=round(duration_ms, 2),
                error=str(exc),
            ),
        )
        raise HTTPException(status_code=400, detail="Semantic search failed") from exc

    return hits


def _build_workflow_plan(workflow_type: str, rfq_id: Optional[str], inputs: Dict[str, Any]) -> List[Dict[str, Any]]:
    template = WORKFLOW_STEP_TEMPLATES.get(workflow_type)
    if not template:
        raise ValueError(f"Unsupported workflow_type '{workflow_type}'")

    steps: List[Dict[str, Any]] = []
    for template_step in template:
        action_type = template_step["action_type"]
        snapshot = _extract_step_snapshot(action_type, inputs, rfq_id)
        steps.append(
            {
                "action_type": action_type,
                "name": template_step.get("name") or ACTION_TYPE_LABELS.get(action_type, action_type.title()),
                "required_inputs": snapshot,
                "metadata": template_step.get("metadata") or {},
            }
        )
    return steps


def _extract_step_snapshot(action_type: str, inputs: Dict[str, Any], rfq_id: Optional[str]) -> Dict[str, Any]:
    snapshot: Dict[str, Any]
    if action_type == "rfq_draft":
        source = inputs.get("rfq") or inputs.get("rfq_inputs") or {}
        snapshot = copy.deepcopy(source if isinstance(source, dict) else {})
    elif action_type == "compare_quotes":
        quotes_source = inputs.get("quotes") or []
        risk_source = inputs.get("supplier_risk_scores") or inputs.get("risk_scores") or {}
        snapshot = {
            "rfq_id": rfq_id,
            "quotes": copy.deepcopy(quotes_source if isinstance(quotes_source, list) else []),
            "supplier_risk_scores": copy.deepcopy(risk_source if isinstance(risk_source, dict) else {}),
        }
    elif action_type == "po_draft":
        source = inputs.get("po") or inputs.get("po_draft") or {}
        snapshot = copy.deepcopy(source if isinstance(source, dict) else {})
    else:
        snapshot = copy.deepcopy(inputs)

    if rfq_id and (not isinstance(snapshot.get("rfq_id"), str) or not snapshot.get("rfq_id")):
        snapshot["rfq_id"] = rfq_id
    return snapshot


def _serialize_step(step: Optional[Dict[str, Any]]) -> Optional[Dict[str, Any]]:
    if not step:
        return None
    return {
        "step_index": step.get("step_index"),
        "name": step.get("name"),
        "action_type": step.get("action_type"),
        "approval_state": step.get("approval_state"),
        "required_inputs": step.get("required_inputs"),
        "draft_output": step.get("draft_output"),
        "output": step.get("output"),
        "approved_by": step.get("approved_by"),
        "approved_at": step.get("approved_at"),
        "metadata": step.get("metadata"),
    }


async def _execute_workflow_step(workflow: Dict[str, Any], step: Dict[str, Any]) -> Dict[str, Any]:
    step_inputs = _prepare_step_inputs(workflow, step)
    action_request = ActionPlanRequest(
        company_id=int(workflow.get("company_id") or 0),
        action_type=step.get("action_type"),
        query=_workflow_step_query(workflow, step),
        inputs=step_inputs,
        user_context=workflow.get("user_context") or {},
    )
    return await _execute_action_plan(action_request)


def _prepare_step_inputs(workflow: Dict[str, Any], step: Dict[str, Any]) -> Dict[str, Any]:
    base_inputs = step.get("required_inputs")
    if isinstance(base_inputs, dict):
        prepared: Dict[str, Any] = copy.deepcopy(base_inputs)
    elif isinstance(base_inputs, list):
        prepared = {"items": copy.deepcopy(base_inputs)}
    else:
        prepared = {}

    metadata = workflow.get("metadata") or {}
    rfq_id = metadata.get("rfq_id")
    if rfq_id and not prepared.get("rfq_id"):
        prepared["rfq_id"] = rfq_id
    prepared.setdefault("workflow_id", workflow.get("workflow_id"))
    prepared["workflow_context"] = {
        "workflow_id": workflow.get("workflow_id"),
        "workflow_type": workflow.get("workflow_type"),
        "company_id": workflow.get("company_id"),
        "rfq_id": rfq_id,
        "goal": metadata.get("goal"),
    }

    previous_outputs = _collect_previous_outputs(workflow, step.get("step_index", 0))
    if previous_outputs:
        prepared["previous_steps"] = previous_outputs

    if step.get("action_type") == "po_draft":
        recommendation = _extract_recommendation_from_steps(workflow)
        if recommendation and "selected_supplier" not in prepared:
            prepared["selected_supplier"] = recommendation
    return prepared


def _collect_previous_outputs(workflow: Dict[str, Any], current_index: int) -> List[Dict[str, Any]]:
    outputs: List[Dict[str, Any]] = []
    for step in workflow.get("steps", []):
        step_idx = step.get("step_index")
        if step_idx is None or step_idx >= current_index:
            continue
        outputs.append(
            {
                "step_index": step_idx,
                "action_type": step.get("action_type"),
                "approval_state": step.get("approval_state"),
                "draft_output": step.get("draft_output"),
                "output": step.get("output"),
            }
        )
    return outputs


def _workflow_step_query(workflow: Dict[str, Any], step: Dict[str, Any]) -> str:
    action_label = ACTION_TYPE_LABELS.get(step.get("action_type"), step.get("action_type", "Workflow Step").title())
    workflow_goal = (workflow.get("metadata") or {}).get("goal") or workflow.get("query") or action_label
    return f"{action_label} for workflow {workflow.get('workflow_id')}: {workflow_goal}"


def _extract_recommendation_from_steps(workflow: Dict[str, Any]) -> Optional[Dict[str, Any]]:
    for step in workflow.get("steps", []):
        if step.get("action_type") != "compare_quotes":
            continue
        payload_source: Optional[Dict[str, Any]] = None
        for candidate in (step.get("output"), step.get("draft_output")):
            if isinstance(candidate, dict):
                payload = candidate.get("payload") if isinstance(candidate.get("payload"), dict) else candidate
                if isinstance(payload, dict):
                    payload_source = payload
                    break
        if payload_source:
            recommendation = payload_source.get("recommendation")
            rankings = payload_source.get("rankings")
            if isinstance(recommendation, str) and isinstance(rankings, list):
                supplier_details = next(
                    (
                        ranking
                        for ranking in rankings
                        if isinstance(ranking, dict) and ranking.get("supplier_id") == recommendation
                    ),
                    None,
                )
                if isinstance(supplier_details, dict):
                    return {
                        "supplier_id": supplier_details.get("supplier_id"),
                        "supplier_name": supplier_details.get("supplier_name"),
                        "score": supplier_details.get("score"),
                    }
            if isinstance(payload_source.get("selected_supplier"), dict):
                return payload_source.get("selected_supplier")
    return None

    response_hits: List[Dict[str, Any]] = []
    for hit in hits:
        snippet = (hit.snippet or "")[:250]
        response_hits.append(
            {
                "doc_id": hit.doc_id,
                "doc_version": hit.doc_version,
                "chunk_id": hit.chunk_id,
                "score": hit.score,
                "title": hit.title,
                "snippet": snippet,
                "metadata": hit.metadata,
            }
        )

    packed_hits = pack_context_hits(
        response_hits,
        max_chars=ACTION_CONTEXT_MAX_CHARS,
        max_chunks=ACTION_CONTEXT_MAX_CHUNKS,
        per_doc_limit=ACTION_CONTEXT_PER_DOC_LIMIT,
    )
    duration_ms = (time.perf_counter() - started_at) * 1000
    LOGGER.info(
        "action_semantic_search_success",
        extra=log_extra(
            company_id=company_id,
            hit_count=len(packed_hits),
            top_k=top_k,
            duration_ms=round(duration_ms, 2),
        ),
    )
    return packed_hits


def _build_action_prompt(payload: ActionPlanRequest) -> str:
    action_label = ACTION_TYPE_LABELS.get(payload.action_type, payload.action_type.title())
    user_context_json = _safe_json_dumps(payload.user_context)
    inputs_json = _safe_json_dumps(payload.inputs)
    return (
        f"Action Type: {action_label} ({payload.action_type})\n"
        f"User Goal: {payload.query}\n\n"
        "User Context (JSON):\n"
        f"{user_context_json}\n\n"
        "Action Inputs (JSON):\n"
        f"{inputs_json}\n\n"
        "Use only the retrieved sources to populate the schema-compliant payload."
    )


def _safe_json_dumps(value: Any) -> str:
    def _fallback_serializer(obj: Any) -> str:
        return str(obj)

    try:
        return json.dumps(value, default=_fallback_serializer, indent=2, ensure_ascii=True)
    except TypeError:
        return json.dumps(str(value), indent=2, ensure_ascii=True)


def _invoke_tool_contract(
    action_type: str,
    context_blocks: Sequence[Dict[str, Any]],
    inputs: Dict[str, Any],
) -> Dict[str, Any]:
    tool_mapping = {
        "rfq_draft": build_rfq_draft,
        "supplier_message": build_supplier_message,
        "maintenance_checklist": build_maintenance_checklist,
        "inventory_whatif": run_inventory_whatif,
        "compare_quotes": compare_quotes,
        "po_draft": draft_purchase_order,
        "award_quote": build_award_quote,
        "invoice_draft": build_invoice_draft,
    }
    tool = tool_mapping.get(action_type)
    if not tool:
        return {}

    try:
        return tool(context_blocks, inputs)
    except Exception as exc:
        LOGGER.warning(
            "tool_contract_failure",
            extra=log_extra(action_type=action_type, error=str(exc)),
        )
        return {}


def _build_tool_response(
    payload: ActionPlanRequest,
    tool_payload: Dict[str, Any],
    context_blocks: Sequence[Dict[str, Any]],
) -> Dict[str, Any]:
    citations = _build_tool_citations(context_blocks)
    payload_value = tool_payload if isinstance(tool_payload, dict) else {}
    warnings: List[str] = []
    if not payload_value:
        warnings.append("Tool payload missing; verify inputs and retrieved context")
    summary = f"{ACTION_TYPE_LABELS.get(payload.action_type, payload.action_type.title())} draft generated from retrieved sources"
    return {
        "action_type": payload.action_type,
        "summary": summary,
        "payload": payload_value,
        "citations": citations,
        "confidence": 0.55 if citations else 0.25,
        "needs_human_review": True,
        "warnings": warnings or ([] if citations else ["No citations captured for tool output"]),
    }


def _build_tool_citations(context_blocks: Sequence[Dict[str, Any]]) -> List[Dict[str, Any]]:
    citations: List[Dict[str, Any]] = []
    for block in context_blocks[:5]:
        citations.append(
            {
                "doc_id": block.get("doc_id"),
                "doc_version": block.get("doc_version"),
                "chunk_id": block.get("chunk_id"),
                "score": float(block.get("score") or 0.0),
                "snippet": (block.get("snippet") or "")[:250],
            }
        )
    return citations


def _merge_tool_and_llm(
    payload: ActionPlanRequest,
    tool_response: Dict[str, Any],
    llm_response: Dict[str, Any],
) -> Dict[str, Any]:
    merged = dict(tool_response)

    if llm_response:
        merged_payload = merged.get("payload")
        llm_payload = llm_response.get("payload")
        if isinstance(merged_payload, dict) and isinstance(llm_payload, dict):
            merged_payload = {**merged_payload, **llm_payload}
            merged["payload"] = merged_payload

        merged["summary"] = llm_response.get("summary") or merged.get("summary")
        merged["citations"] = llm_response.get("citations") or merged.get("citations")
        merged["confidence"] = llm_response.get("confidence", merged.get("confidence"))
        merged["warnings"] = llm_response.get("warnings") or merged.get("warnings")
        merged["needs_human_review"] = llm_response.get("needs_human_review", merged.get("needs_human_review"))

    return _coerce_action_response(payload, merged)


def _coerce_action_response(
    payload: ActionPlanRequest,
    response: Dict[str, Any],
) -> Dict[str, Any]:
    sanitized = dict(response or {})
    sanitized["action_type"] = payload.action_type
    default_summary = f"{ACTION_TYPE_LABELS.get(payload.action_type, payload.action_type.title())} generated for '{payload.query}'"
    summary_value = sanitized.get("summary")
    if not isinstance(summary_value, str) or not summary_value.strip():
        sanitized["summary"] = default_summary
    payload_value = sanitized.get("payload")
    if not isinstance(payload_value, dict):
        sanitized["payload"] = _placeholder_payload_for_action(payload.action_type, payload.inputs)
    citations_value = sanitized.get("citations")
    if not isinstance(citations_value, list):
        sanitized["citations"] = []
    warnings_value = sanitized.get("warnings")
    if not isinstance(warnings_value, list):
        sanitized["warnings"] = []
    sanitized["confidence"] = _clamp_float(sanitized.get("confidence", 0.0))
    sanitized["needs_human_review"] = bool(sanitized.get("needs_human_review", True))
    return sanitized


def _placeholder_payload_for_action(action_type: str, inputs: Dict[str, Any]) -> Dict[str, Any]:
    builders = {
        "rfq_draft": _placeholder_rfq_draft_payload,
        "supplier_message": _placeholder_supplier_message_payload,
        "maintenance_checklist": _placeholder_maintenance_checklist_payload,
        "inventory_whatif": _placeholder_inventory_whatif_payload,
    }
    builder = builders.get(action_type)
    if not builder:
        return {}
    try:
        return builder(inputs)
    except Exception:  # pragma: no cover - defensive guard
        return builder({})


def _placeholder_rfq_draft_payload(inputs: Dict[str, Any]) -> Dict[str, Any]:
    today = datetime.now(timezone.utc).date().isoformat()
    items = inputs.get("items") if isinstance(inputs.get("items"), list) else []
    line_items: List[Dict[str, Any]] = []
    for index, item in enumerate(items[:5]):
        if not isinstance(item, dict):
            continue
        part_id = str(item.get("part_id") or f"TBD-{index + 1}")
        description = _normalize_string(item.get("description"), "Pending description")
        quantity = max(_safe_positive_number(item.get("qty") or item.get("quantity") or 1.0), 0.01)
        target_date = str(item.get("target_date") or today)
        line_items.append(
            {
                "part_id": part_id,
                "description": description,
                "quantity": quantity,
                "target_date": target_date,
            }
        )
    if not line_items:
        line_items.append(
            {
                "part_id": "TBD-1",
                "description": "Pending sourcing inputs",
                "quantity": 1.0,
                "target_date": today,
            }
        )

    commercial_terms = inputs.get("commercial_terms")
    terms = _ensure_string_list(commercial_terms)
    if not terms:
        terms = ["Manual review required before releasing RFQ to suppliers."]

    questions = _ensure_string_list(inputs.get("questions_for_suppliers"))
    if not questions:
        questions = ["Confirm achievable lead time and MOQ."]

    evaluation_raw = inputs.get("evaluation_criteria")
    rubric: List[Dict[str, Any]] = []
    if isinstance(evaluation_raw, list):
        for criterion in evaluation_raw[:5]:
            if not isinstance(criterion, dict):
                continue
            rubric.append(
                {
                    "criterion": _normalize_string(criterion.get("name"), "Total cost"),
                    "weight": _clamp_float(criterion.get("weight"), minimum=0.0, maximum=1.0) or 0.25,
                    "guidance": _normalize_string(
                        criterion.get("guidance"),
                        "Compare proposed terms against baseline contract.",
                    ),
                }
            )
    if not rubric:
        rubric = [
            {
                "criterion": "Commercial terms",
                "weight": 0.5,
                "guidance": "Assess discounts, payment terms, and any rebates.",
            },
            {
                "criterion": "Schedule reliability",
                "weight": 0.5,
                "guidance": "Favor suppliers that can certify lead time commitments.",
            },
        ]

    scope_summary = _normalize_string(
        inputs.get("scope_summary") or inputs.get("category"),
        "Draft scope generated from Copilot.",
    )

    return {
        "rfq_title": _normalize_string(inputs.get("rfq_title"), f"RFQ Draft - {today}"),
        "scope_summary": scope_summary,
        "line_items": line_items,
        "terms_and_conditions": terms,
        "questions_for_suppliers": questions,
        "evaluation_rubric": rubric,
    }


def _placeholder_supplier_message_payload(inputs: Dict[str, Any]) -> Dict[str, Any]:
    supplier_name = _normalize_string(inputs.get("supplier_name"), "Supplier Partner")
    goal = _normalize_string(inputs.get("goal"), "optimize pricing and lead time")
    tone = _normalize_string(inputs.get("tone"), "professional")
    subject = _normalize_string(
        inputs.get("subject"),
        f"Follow-up on collaboration opportunity ({supplier_name})",
    )
    context_note = _normalize_string(inputs.get("context"), "Active sourcing event in review.")
    message_body = (
        f"Hi {supplier_name},\n\n"
        f"Thank you for the continued partnership. We are reviewing {goal} needs for the next build. "
        f"{context_note} Please share any flexibility you have around schedule or pricing adjustments.\n\n"
        "Best regards,\nProcurement Team"
    )
    negotiation_points = _ensure_string_list(inputs.get("negotiation_points"))
    if not negotiation_points:
        negotiation_points = [
            "Align on updated unit pricing tiers",
            "Confirm expedited lead time options",
            "Clarify warranty or quality commitments",
        ]
    fallback_options = _ensure_string_list(inputs.get("fallback_options"))
    if not fallback_options:
        fallback_options = [
            "Escalate to alternate qualified supplier",
            "Adjust build plan to align with confirmed schedule",
        ]
    return {
        "subject": subject,
        "message_body": message_body,
        "negotiation_points": negotiation_points,
        "fallback_options": fallback_options,
    }


def _placeholder_maintenance_checklist_payload(inputs: Dict[str, Any]) -> Dict[str, Any]:
    asset_id = _normalize_string(inputs.get("asset_id"), "Asset")
    symptom = _normalize_string(inputs.get("symptom"), "Unspecified symptom")
    environment = _normalize_string(inputs.get("environment"), "standard operating conditions")
    urgency = _normalize_string(inputs.get("urgency"), "medium")

    safety_notes = _ensure_string_list(inputs.get("safety_notes"))
    if not safety_notes:
        safety_notes = [
            "Lockout-tagout the equipment before inspection.",
            "Use PPE suitable for the environment described.",
        ]

    diagnostic_steps = _ensure_string_list(inputs.get("diagnostic_steps"))
    if not diagnostic_steps:
        diagnostic_steps = [
            f"Document the reported symptom: {symptom} on {asset_id}.",
            "Capture baseline readings (temperature, vibration, power draw).",
            f"Inspect operating environment for {environment} stressors.",
        ]

    likely_causes = _ensure_string_list(inputs.get("likely_causes"))
    if not likely_causes:
        likely_causes = [
            "Wear on moving components",
            "Calibration drift",
            "Environmental contamination",
        ]

    recommended_actions = _ensure_string_list(inputs.get("recommended_actions"))
    if not recommended_actions:
        recommended_actions = [
            "Clean and lubricate moving assemblies",
            "Verify firmware and control parameters",
            "Schedule calibration after root cause confirmation",
        ]

    escalation_rules = _ensure_string_list(inputs.get("when_to_escalate"))
    if not escalation_rules:
        escalation_rules = [
            "Escalate if vibration or temperature exceeds safe thresholds",
            f"Escalate if downtime exceeds agreed SLA ({urgency} priority)",
        ]

    return {
        "safety_notes": safety_notes,
        "diagnostic_steps": diagnostic_steps,
        "likely_causes": likely_causes,
        "recommended_actions": recommended_actions,
        "when_to_escalate": escalation_rules,
    }


def _placeholder_inventory_whatif_payload(inputs: Dict[str, Any]) -> Dict[str, Any]:
    current_policy = inputs.get("current_policy") if isinstance(inputs.get("current_policy"), dict) else {}
    proposed_policy = inputs.get("proposed_policy") if isinstance(inputs.get("proposed_policy"), dict) else {}
    forecast = inputs.get("forecast_snapshot") if isinstance(inputs.get("forecast_snapshot"), dict) else {}
    service_level_target = _clamp_float(inputs.get("service_level_target", 0.95))

    current_reorder_point = _safe_positive_number(current_policy.get("reorder_point") or 0.0)
    proposed_reorder_point = _safe_positive_number(proposed_policy.get("reorder_point") or current_reorder_point)
    current_safety = _safe_positive_number(current_policy.get("safety_stock") or 0.0)
    proposed_safety = _safe_positive_number(proposed_policy.get("safety_stock") or current_safety)
    lead_time_days = _safe_positive_number(proposed_policy.get("lead_time") or forecast.get("lead_time_days") or 1.0)
    demand_mean = _safe_positive_number(forecast.get("avg_daily_demand") or forecast.get("mean") or 1.0)

    coverage_days = (proposed_reorder_point + proposed_safety) / max(demand_mean, 0.01)
    target_coverage = service_level_target * lead_time_days
    projected_stockout_risk = max(0.0, min(1.0, 1 - min(coverage_days / max(target_coverage, 0.01), 1.0)))
    expected_stockout_days = max(0.0, round((1 - coverage_days / max(target_coverage, 0.01)) * lead_time_days, 2))
    holding_cost_unit = _safe_positive_number(forecast.get("holding_cost_per_unit") or 1.0)
    expected_holding_cost_change = round((proposed_safety - current_safety) * holding_cost_unit, 2)

    if projected_stockout_risk <= 0.15:
        recommendation = "Proposed policy meets the target service level."
    elif projected_stockout_risk <= 0.4:
        recommendation = "Increase safety stock or pull-in supply to reduce risk."
    else:
        recommendation = "Stockout risk high; revisit reorder point and expedite supply."

    assumptions = [
        f"Demand mean derived from forecast snapshot ({demand_mean} units/day)",
        f"Lead time assumed at {lead_time_days} days",
        f"Service level target set to {service_level_target:.2f}",
    ]

    return {
        "projected_stockout_risk": round(projected_stockout_risk, 4),
        "expected_stockout_days": expected_stockout_days,
        "expected_holding_cost_change": expected_holding_cost_change,
        "recommendation": recommendation,
        "assumptions": assumptions,
    }


def _ensure_string_list(value: Any) -> List[str]:
    if isinstance(value, list):
        result: List[str] = []
        for entry in value:
            text = _normalize_string(entry, "")
            if text:
                result.append(text)
        return result
    if isinstance(value, str) and value.strip():
        return [value.strip()]
    return []


def _normalize_string(value: Any, fallback: str) -> str:
    if isinstance(value, str) and value.strip():
        return value.strip()
    return fallback


def _safe_number(value: Any) -> float:
    try:
        return float(value)
    except (TypeError, ValueError):
        return 0.0


def _format_number(value: Any, decimals: int = 2) -> str:
    return f"{_safe_number(value):.{decimals}f}"


def _format_percent(value: Any, decimals: int = 1) -> str:
    return f"{_safe_number(value) * 100:.{decimals}f}%"


def _safe_positive_number(value: Any) -> float:
    try:
        number = float(value)
    except (TypeError, ValueError):
        return 0.0
    return number if number > 0 else 0.0


def _clamp_float(value: Any, *, minimum: float = 0.0, maximum: float = 1.0) -> float:
    try:
        number = float(value)
    except (TypeError, ValueError):
        return minimum
    if number < minimum:
        return minimum
    if number > maximum:
        return maximum
    return number
