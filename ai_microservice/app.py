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
import time
import uuid
from datetime import datetime, timezone
from typing import Any, Dict, List, Literal, Optional, Sequence

from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field, field_validator

from ai_microservice.chunking import chunk_text
from ai_microservice.context_packer import pack_context_hits
from ai_microservice.embedding_provider import EmbeddingProvider, get_embedding_provider
from ai_microservice.llm_provider import DummyLLMProvider, LLMProviderError, LLMProvider, build_llm_provider
from ai_microservice.schemas import (
    ANSWER_SCHEMA,
    INVENTORY_WHATIF_SCHEMA,
    MAINTENANCE_CHECKLIST_SCHEMA,
    PO_DRAFT_SCHEMA,
    QUOTE_COMPARISON_SCHEMA,
    RFQ_DRAFT_SCHEMA,
    SUPPLIER_MESSAGE_SCHEMA,
)
from ai_microservice.tools_contract import (
    build_maintenance_checklist,
    build_rfq_draft,
    build_supplier_message,
    compare_quotes,
    draft_purchase_order,
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

workflow_engine = WorkflowEngine()

ACTION_CONTEXT_MAX_CHARS = int(os.getenv("AI_ACTION_CONTEXT_MAX_CHARS", "9000"))
ACTION_CONTEXT_MAX_CHUNKS = int(os.getenv("AI_ACTION_CONTEXT_MAX_CHUNKS", "12"))
ACTION_CONTEXT_PER_DOC_LIMIT = int(os.getenv("AI_ACTION_CONTEXT_PER_DOC_LIMIT", "4"))

ACTION_TYPE_LABELS: Dict[str, str] = {
    "rfq_draft": "RFQ Draft",
    "supplier_message": "Supplier Message",
    "maintenance_checklist": "Maintenance Checklist",
    "inventory_whatif": "Inventory What-If",
    "compare_quotes": "Quote Comparison",
    "po_draft": "Purchase Order Draft",
}

ACTION_SCHEMA_BY_TYPE: Dict[str, Dict[str, Any]] = {
    "rfq_draft": RFQ_DRAFT_SCHEMA,
    "supplier_message": SUPPLIER_MESSAGE_SCHEMA,
    "maintenance_checklist": MAINTENANCE_CHECKLIST_SCHEMA,
    "inventory_whatif": INVENTORY_WHATIF_SCHEMA,
    "compare_quotes": QUOTE_COMPARISON_SCHEMA,
    "po_draft": PO_DRAFT_SCHEMA,
}

WORKFLOW_STEP_TEMPLATES: Dict[str, List[Dict[str, Any]]] = {
    "procurement": [
        {"action_type": "rfq_draft", "name": "RFQ Draft", "metadata": {"input_key": "rfq"}},
        {"action_type": "compare_quotes", "name": "Compare Quotes", "metadata": {"input_key": "quotes"}},
        {"action_type": "po_draft", "name": "Purchase Order Draft", "metadata": {"input_key": "po"}},
    ],
}


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

    @field_validator("inputs", "user_context", mode="before")
    @classmethod
    def ensure_object(cls, value: Any) -> Dict[str, Any]:  # noqa: D417
        if value is None:
            return {}
        if not isinstance(value, dict):
            raise ValueError("Value must be an object")
        return value

    @field_validator("llm_provider", mode="before")
    @classmethod
    def normalize_llm_provider(cls, value: Optional[str]) -> Optional[str]:  # noqa: D417
        if value is None:
            return None

        normalized = str(value).strip().lower()
        if normalized in SUPPORTED_LLM_PROVIDERS:
            return normalized
        return None


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
def resolve_llm_provider(override: Optional[str]) -> tuple[str, "LLMProvider"]:
    requested = (override or DEFAULT_LLM_PROVIDER_NAME or "dummy").strip().lower()
    if requested not in SUPPORTED_LLM_PROVIDERS:
        LOGGER.warning("llm_provider_override_invalid", extra=log_extra(provider=requested))
        requested = DEFAULT_LLM_PROVIDER_NAME

    provider = build_llm_provider(requested)
    return requested, provider



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
    }
    LOGGER.info("readyz_probe", extra=log_extra(**snapshot))
    return payload


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

    provider_used = "none"
    if not context_blocks:
        answer_payload = _build_no_hits_answer()
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

    duration_ms = (time.perf_counter() - started_at) * 1000
    LOGGER.info(
        "answer_success",
        extra=log_extra(
            company_id=payload.company_id,
            hit_count=len(context_blocks),
            provider=provider_used,
            duration_ms=round(duration_ms, 2),
        ),
    )
    return {"status": "ok", **answer_payload}


@app.post("/actions/plan")
async def plan_action(payload: ActionPlanRequest) -> Dict[str, Any]:
    return await _execute_action_plan(payload)


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


def _build_no_hits_answer() -> Dict[str, Any]:
    return {
        "answer_markdown": "Not enough information in indexed sources.",
        "citations": [],
        "confidence": 0.0,
        "needs_human_review": True,
        "warnings": ["No relevant sources found"],
    }


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
