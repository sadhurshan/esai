"""Tests for the FastAPI microservice endpoints."""
from __future__ import annotations

from typing import Any, Dict

import pytest
from fastapi.testclient import TestClient

from ai_microservice import app as app_module
from ai_microservice.vector_store import SearchHit


class _StubService:
    def __init__(self) -> None:
        self.last_forecast_args: Dict[str, Any] = {}
        self.supplier_payload: Dict[str, Any] = {}

    def predict_demand(self, part_id: int, history: Dict[str, float], horizon: int) -> Dict[str, Any]:
        self.last_forecast_args = {
            "part_id": part_id,
            "history": history,
            "horizon": horizon,
        }
        return {
            "model": "moving_average",
            "model_used": "moving_average",
            "demand_qty": 42.0,
            "avg_daily_demand": 4.2,
            "reorder_point": 16.8,
            "safety_stock": 3.5,
            "order_by_date": "2024-01-15",
        }

    def predict_supplier_risk(self, supplier: Dict[str, Any]) -> Dict[str, Any]:
        self.supplier_payload = supplier
        return {
            "risk_category": "low",
            "risk_score": 0.12,
            "explanation": "Stable delivery performance",
        }


class _StubEmbeddingProvider:
    def __init__(self) -> None:
        self.calls: list[list[str]] = []

    def embed_texts(self, texts):
        values = [str(text) for text in texts]
        self.calls.append(values)
        return [[float(len(text)) or 1.0] for text in values]


class _StubVectorStore:
    def __init__(self) -> None:
        self.upsert_calls: list[dict[str, Any]] = []
        self.search_calls: list[dict[str, Any]] = []
        self.search_results: list[SearchHit] = []

    def upsert_chunks(self, company_id, doc_id, doc_version, chunks, embeddings, metadata) -> None:
        self.upsert_calls.append(
            {
                "company_id": company_id,
                "doc_id": doc_id,
                "doc_version": doc_version,
                "chunks": chunks,
                "embeddings": embeddings,
                "metadata": metadata,
            }
        )

    def search(self, company_id, query_embedding, top_k, filters=None):
        self.search_calls.append(
            {
                "company_id": company_id,
                "query_embedding": query_embedding,
                "top_k": top_k,
                "filters": filters,
            }
        )
        return list(self.search_results)


class _StubLLMProvider:
    def __init__(self, response: Dict[str, Any]) -> None:
        self.response = response
        self.calls: list[dict[str, Any]] = []

    def generate_answer(self, query: str, context_blocks, response_schema, safety_identifier=None):
        self.calls.append(
            {
                "query": query,
                "context_blocks": context_blocks,
                "schema": response_schema,
                "safety_identifier": safety_identifier,
            }
        )
        return dict(self.response)


@pytest.fixture
def stub_service(monkeypatch: pytest.MonkeyPatch) -> _StubService:
    stub = _StubService()
    monkeypatch.setattr(app_module, "service", stub)
    return stub


@pytest.fixture
def client(stub_service: _StubService) -> TestClient:
    with TestClient(app_module.app) as test_client:
        yield test_client


@pytest.fixture
def stub_embedding_provider(monkeypatch: pytest.MonkeyPatch) -> _StubEmbeddingProvider:
    provider = _StubEmbeddingProvider()
    monkeypatch.setattr(app_module, "embedding_provider", provider)
    return provider


@pytest.fixture
def stub_vector_store(monkeypatch: pytest.MonkeyPatch) -> _StubVectorStore:
    store = _StubVectorStore()
    monkeypatch.setattr(app_module, "vector_store", store)
    return store


def test_forecast_endpoint_returns_expected_payload(client: TestClient, stub_service: _StubService) -> None:
    payload = {
        "part_id": 88,
        "horizon": 14,
        "history": [
            {"date": "2024-01-01", "quantity": 5.0},
            {"date": "2024-01-02", "quantity": 7.5},
        ],
    }

    response = client.post("/forecast", json=payload)

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    data = body["data"]
    assert {"model", "demand_qty", "avg_daily_demand", "reorder_point", "safety_stock", "order_by_date"}.issubset(data)
    assert stub_service.last_forecast_args == {
        "part_id": 88,
        "horizon": 14,
        "history": {
            "2024-01-01": 5.0,
            "2024-01-02": 7.5,
        },
    }


def test_supplier_risk_endpoint_returns_expected_fields(client: TestClient, stub_service: _StubService) -> None:
    payload = {
        "supplier": {
            "supplier_id": 501,
            "company_id": 10,
            "on_time_rate": 0.96,
        }
    }

    response = client.post("/supplier-risk", json=payload)

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    data = body["data"]
    assert data["risk_category"] == "low"
    assert "risk_score" in data
    assert "explanation" in data
    assert stub_service.supplier_payload == payload["supplier"]


def test_forecast_endpoint_returns_400_when_service_errors(
    client: TestClient,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    class _ErrorService:
        def predict_demand(self, *args: Any, **kwargs: Any) -> Dict[str, Any]:  # pragma: no cover - intentionally failing
            raise ValueError("Invalid history payload")

        def predict_supplier_risk(self, supplier: Dict[str, Any]) -> Dict[str, Any]:
            return {"risk_category": "low", "explanation": "n/a"}

    monkeypatch.setattr(app_module, "service", _ErrorService())

    payload = {
        "part_id": 1,
        "horizon": 7,
        "history": [
            {"date": "2024-01-01", "quantity": 5.0},
        ],
    }

    response = client.post("/forecast", json=payload)

    assert response.status_code == 400
    body = response.json()
    assert body["detail"] == "Invalid history payload"


def test_forecast_validation_errors_return_422(client: TestClient) -> None:
    payload = {
        "part_id": 88,
        "horizon": 14,
        "history": [],
    }

    response = client.post("/forecast", json=payload)

    assert response.status_code == 422
    detail = response.json()["detail"]
    assert any("history must contain at least one record" in error["msg"] for error in detail)


def test_supplier_risk_validation_errors_return_422(client: TestClient) -> None:
    payload = {
        "supplier": {},
    }

    response = client.post("/supplier-risk", json=payload)

    assert response.status_code == 422
    detail = response.json()["detail"]
    assert any("supplier object cannot be empty" in error["msg"] for error in detail)


def test_index_document_endpoint_persists_chunks(
    client: TestClient,
    stub_embedding_provider: _StubEmbeddingProvider,
    stub_vector_store: _StubVectorStore,
) -> None:
    payload = {
        "company_id": 44,
        "doc_id": "doc-123",
        "doc_version": "v2",
        "title": "Maintenance Manual",
        "source_type": "manual",
        "mime_type": "text/plain",
        "text": "Paragraph one.\n\nParagraph two.",
        "metadata": {"tags": ["maintenance"]},
        "acl": ["role:admin"],
    }

    response = client.post("/index/document", json=payload)

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert body["indexed_chunks"] >= 1
    assert stub_embedding_provider.calls, "embeddings should be generated"
    assert stub_vector_store.upsert_calls, "vector store upsert should be invoked"
    stored_metadata = stub_vector_store.upsert_calls[0]["metadata"]
    assert stored_metadata["title"] == "Maintenance Manual"
    assert stored_metadata["acl"] == ["role:admin"]


def test_index_document_endpoint_returns_400_on_vector_store_error(
    client: TestClient,
    stub_embedding_provider: _StubEmbeddingProvider,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    class _FailingVectorStore(_StubVectorStore):
        def upsert_chunks(self, *args: Any, **kwargs: Any) -> None:  # pragma: no cover - intentionally failing
            raise ValueError("failed to persist")

    monkeypatch.setattr(app_module, "vector_store", _FailingVectorStore())

    payload = {
        "company_id": 44,
        "doc_id": "doc-123",
        "doc_version": "v2",
        "title": "Maintenance Manual",
        "source_type": "manual",
        "mime_type": "text/plain",
        "text": "Paragraph one.\n\nParagraph two.",
    }

    response = client.post("/index/document", json=payload)

    assert response.status_code == 400
    assert response.json()["detail"] == "failed to persist"


def test_search_endpoint_returns_hits(
    client: TestClient,
    stub_embedding_provider: _StubEmbeddingProvider,
    stub_vector_store: _StubVectorStore,
) -> None:
    stub_vector_store.search_results = [
        SearchHit(
            doc_id="doc-1",
            doc_version="v1",
            chunk_id=0,
            score=0.87,
            title="Manual",
            snippet="Important instructions",
            metadata={"tags": ["safety"]},
        )
    ]
    payload = {
        "company_id": 44,
        "query": "safety instructions",
        "top_k": 3,
        "filters": {
            "source_type": "manual",
            "tags": ["safety"],
        },
    }

    response = client.post("/search", json=payload)

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert len(body["hits"]) == 1
    hit = body["hits"][0]
    assert hit["doc_id"] == "doc-1"
    assert hit["metadata"]["tags"] == ["safety"]
    assert stub_embedding_provider.calls[-1] == ["safety instructions"]
    assert stub_vector_store.search_calls[-1]["filters"] == {"source_type": "manual", "tags": ["safety"]}


def test_search_endpoint_returns_400_on_error(
    client: TestClient,
    stub_embedding_provider: _StubEmbeddingProvider,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    class _FailingVectorStore(_StubVectorStore):
        def search(self, *args: Any, **kwargs: Any):  # pragma: no cover - intentionally failing
            raise ValueError("search failure")

    monkeypatch.setattr(app_module, "vector_store", _FailingVectorStore())

    payload = {
        "company_id": 44,
        "query": "safety",
    }

    response = client.post("/search", json=payload)

    assert response.status_code == 400
    assert response.json()["detail"] == "search failure"


def test_answer_endpoint_returns_bulleted_summary(
    client: TestClient,
    stub_embedding_provider: _StubEmbeddingProvider,
    stub_vector_store: _StubVectorStore,
) -> None:
    stub_vector_store.search_results = [
        SearchHit(
            doc_id="doc-1",
            doc_version="v1",
            chunk_id=0,
            score=0.92,
            title="Manual",
            snippet="First snippet",
            metadata={},
        ),
        SearchHit(
            doc_id="doc-2",
            doc_version="v1",
            chunk_id=1,
            score=0.77,
            title="Guide",
            snippet="Second snippet with more context",
            metadata={},
        ),
    ]

    payload = {
        "company_id": 44,
        "query": "instructions",
    }

    response = client.post("/answer", json=payload)

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert body["citations"] and len(body["citations"]) == 2
    assert body["answer_markdown"].startswith("- First snippet")


def test_answer_endpoint_enforces_citation_integrity(
    client: TestClient,
    stub_embedding_provider: _StubEmbeddingProvider,
    stub_vector_store: _StubVectorStore,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    stub_vector_store.search_results = [
        SearchHit(
            doc_id="doc-1",
            doc_version="v1",
            chunk_id=0,
            score=0.92,
            title="Manual",
            snippet="Original snippet",
            metadata={},
        )
    ]
    stub_response = {
        "answer_markdown": "Example",
        "citations": [
            {
                "doc_id": "doc-1",
                "doc_version": "v1",
                "chunk_id": 0,
                "score": 0.7,
                "snippet": "x" * 400,
            },
            {
                "doc_id": "doc-missing",
                "doc_version": "v99",
                "chunk_id": 999,
                "score": 0.1,
                "snippet": "bad",
            },
        ],
        "confidence": 0.8,
        "needs_human_review": False,
        "warnings": [],
    }
    stub_provider = _StubLLMProvider(stub_response)

    def fake_resolver(override: str | None) -> tuple[str, app_module.LLMProvider]:  # type: ignore[attr-defined]
        return "openai", stub_provider

    monkeypatch.setattr(app_module, "resolve_llm_provider", fake_resolver)

    response = client.post("/answer", json={"company_id": 44, "query": "instructions"})

    assert response.status_code == 200
    body = response.json()
    assert body["citations"] and len(body["citations"]) == 1
    cleaned_snippet = body["citations"][0]["snippet"]
    assert len(cleaned_snippet) == 250
    assert body["citations"][0]["doc_id"] == "doc-1"
    assert body["citations"][0]["chunk_id"] == 0
    assert body["needs_human_review"] is True
    assert any("citation" in warning.lower() for warning in body["warnings"])


def test_report_summary_endpoint_returns_ai_summary(client: TestClient, monkeypatch: pytest.MonkeyPatch) -> None:
    stub_response = {
        "summary_markdown": "AI summary of forecast trends.",
        "bullets": ["Demand up 12 %", "Safety stock raised"],
        "source": "ai",
        "provider": "mock-llm",
    }
    stub_provider = _StubLLMProvider(stub_response)

    def fake_resolver(override: str | None):  # type: ignore[override]
        return "mock-llm", stub_provider

    monkeypatch.setattr(app_module, "resolve_llm_provider", fake_resolver)

    payload = {
        "company_id": 99,
        "report_type": "forecast",
        "report_data": {
            "aggregates": {
                "total_actual": 150.0,
                "total_forecast": 120.0,
                "mape": 0.08,
                "mae": 5.0,
                "avg_daily_demand": 4.5,
                "recommended_reorder_point": 52.0,
                "recommended_safety_stock": 18.0,
            },
            "table": [
                {
                    "part_id": 101,
                    "part_name": "HX-101",
                    "total_actual": 80.0,
                    "total_forecast": 60.0,
                }
            ],
        },
        "filters_used": {
            "start_date": "2025-01-01",
            "end_date": "2025-01-31",
            "part_ids": [101],
        },
        "user_id_hash": "hash-123",
    }

    response = client.post("/reports/summarize", json=payload)

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    data = body["data"]
    assert data["summary_markdown"] == stub_response["summary_markdown"]
    assert data["bullets"] == stub_response["bullets"]
    assert stub_provider.calls and stub_provider.calls[0]["schema"] == app_module.REPORT_SUMMARY_SCHEMA
    assert "Summarize forecast" in stub_provider.calls[0]["query"]


def test_report_summary_endpoint_falls_back_when_llm_fails(client: TestClient, monkeypatch: pytest.MonkeyPatch) -> None:
    class _ErrorProvider:
        def __init__(self) -> None:
            self.calls: list[dict[str, Any]] = []

        def generate_answer(self, query: str, context_blocks, response_schema, safety_identifier=None):  # noqa: D401
            self.calls.append({"query": query})
            raise app_module.LLMProviderError("LLM disabled")

    error_provider = _ErrorProvider()

    def failing_resolver(override: str | None):  # type: ignore[override]
        return "mock-llm", error_provider

    monkeypatch.setattr(app_module, "resolve_llm_provider", failing_resolver)

    payload = {
        "company_id": 77,
        "report_type": "supplier_performance",
        "report_data": {
            "aggregates": {
                "on_time_delivery_rate": 0.93,
                "defect_rate": 0.02,
                "lead_time_variance": 1.4,
                "price_volatility": 0.05,
                "service_responsiveness": 6.5,
            },
            "table": [
                {
                    "supplier_name": "Acme Co",
                    "supplier_id": 301,
                    "risk_category": "medium",
                    "risk_score": 0.42,
                    "on_time_delivery_rate": 0.93,
                    "defect_rate": 0.02,
                    "lead_time_variance": 1.4,
                    "price_volatility": 0.05,
                    "service_responsiveness": 6.5,
                }
            ],
        },
        "filters_used": {
            "start_date": "2025-05-01",
            "end_date": "2025-05-31",
            "supplier_id": 301,
        },
    }

    response = client.post("/reports/summarize", json=payload)

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    data = body["data"]
    assert data["provider"] == "deterministic"
    assert data["source"] == "fallback"
    assert "2025-05-01" in data["summary_markdown"] and "2025-05-31" in data["summary_markdown"]
    assert any("risk" in bullet.lower() for bullet in data["bullets"])
