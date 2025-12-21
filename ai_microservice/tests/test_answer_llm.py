"""Schema and grounding tests for the /answer endpoint."""
from __future__ import annotations

from typing import Any


import pytest
from fastapi.testclient import TestClient

from ai_microservice import app as app_module
from ai_microservice.llm_provider import DummyLLMProvider
from ai_microservice.schemas import ANSWER_SCHEMA
from ai_microservice.vector_store import SearchHit


class _StubEmbeddingProvider:
    def embed_texts(self, texts):
        return [[1.0] for _ in texts]


class _StubVectorStore:
    def __init__(self) -> None:
        self.search_results: list[SearchHit] = []

    def upsert_chunks(self, *args: Any, **kwargs: Any) -> None:  # pragma: no cover - not needed
        return None

    def search(self, *args: Any, **kwargs: Any):
        return list(self.search_results)

    def delete_doc(self, *args: Any, **kwargs: Any) -> None:  # pragma: no cover - not needed
        return None


@pytest.fixture
def answer_env(monkeypatch: pytest.MonkeyPatch) -> _StubVectorStore:
    store = _StubVectorStore()
    monkeypatch.setattr(app_module, "embedding_provider", _StubEmbeddingProvider())
    monkeypatch.setattr(app_module, "vector_store", store)
    monkeypatch.setattr(app_module, "DEFAULT_LLM_PROVIDER_NAME", "dummy")
    return store


@pytest.fixture
def client(answer_env: _StubVectorStore) -> TestClient:
    del answer_env
    with TestClient(app_module.app) as test_client:
        yield test_client


def _set_hits(store: _StubVectorStore) -> None:
    store.search_results = [
        SearchHit(
            doc_id="doc-alpha",
            doc_version="v1",
            chunk_id=0,
            score=0.91,
            title="Alpha Manual",
            snippet="Alpha procedures",
            metadata={"tags": ["alpha"]},
        ),
        SearchHit(
            doc_id="doc-beta",
            doc_version="v2",
            chunk_id=1,
            score=0.77,
            title="Beta SOP",
            snippet="Beta workflow",
            metadata={"tags": ["beta"]},
        ),
    ]


def test_answer_matches_answer_schema(client: TestClient, answer_env: _StubVectorStore) -> None:
    _set_hits(answer_env)

    response = client.post("/answer", json={"company_id": 1, "query": "alpha process"})
    assert response.status_code == 200
    payload = response.json()
    assert payload["status"] == "ok"

    answer_body = {key: payload[key] for key in ANSWER_SCHEMA["required"]}
    _assert_matches_schema(answer_body)

def _assert_matches_schema(answer_body: dict[str, Any]) -> None:
    for key in ANSWER_SCHEMA["required"]:
        assert key in answer_body
    assert isinstance(answer_body["answer_markdown"], str) and answer_body["answer_markdown"].strip()
    assert isinstance(answer_body["citations"], list)
    for citation in answer_body["citations"]:
        assert isinstance(citation["doc_id"], str)
        assert isinstance(citation["doc_version"], str)
        assert isinstance(citation["chunk_id"], int)
        assert isinstance(citation["score"], (int, float))
        assert isinstance(citation["snippet"], str)
    assert 0.0 <= float(answer_body["confidence"]) <= 1.0
    assert isinstance(answer_body["needs_human_review"], bool)
    assert isinstance(answer_body["warnings"], list)
    for warning in answer_body["warnings"]:
        assert isinstance(warning, str)

def test_answer_citations_reference_hits(client: TestClient, answer_env: _StubVectorStore) -> None:
    _set_hits(answer_env)

    response = client.post("/answer", json={"company_id": 1, "query": "alpha process"})
    assert response.status_code == 200
    payload = response.json()
    hits_index = {
        (hit.doc_id, hit.chunk_id)
        for hit in answer_env.search_results
    }

    assert payload["citations"], "Expected at least one citation"
    for citation in payload["citations"]:
        key = (citation["doc_id"], citation["chunk_id"])
        assert key in hits_index


def test_answer_empty_hits_sets_review_flag(client: TestClient, answer_env: _StubVectorStore) -> None:
    answer_env.search_results = []

    response = client.post("/answer", json={"company_id": 1, "query": "unknown"})
    assert response.status_code == 200
    payload = response.json()

    assert payload["citations"] == []
    assert payload["needs_human_review"] is True
    assert "Not enough information" in payload["answer_markdown"]


def test_dummy_provider_is_deterministic(client: TestClient, answer_env: _StubVectorStore) -> None:
    _set_hits(answer_env)

    first = client.post("/answer", json={"company_id": 1, "query": "alpha"}).json()
    second = client.post("/answer", json={"company_id": 1, "query": "alpha"}).json()

    assert first["answer_markdown"] == second["answer_markdown"]
    assert first["citations"] == second["citations"]


def test_answer_respects_llm_provider_override(
    client: TestClient,
    answer_env: _StubVectorStore,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    _set_hits(answer_env)

    def fake_builder(name: str) -> DummyLLMProvider:
        if name == "openai":  # pragma: no cover - ensures override path
            raise AssertionError("OpenAI provider should not be instantiated")
        return DummyLLMProvider()

    monkeypatch.setattr(app_module, "build_llm_provider", fake_builder)
    monkeypatch.setattr(app_module, "DEFAULT_LLM_PROVIDER_NAME", "openai")

    response = client.post(
        "/answer",
        json={
            "company_id": 1,
            "query": "alpha",
            "llm_provider": "dummy",
        },
    )

    assert response.status_code == 200


def test_answer_allows_general_mode_when_enabled(
    client: TestClient,
    answer_env: _StubVectorStore,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    answer_env.search_results = []
    monkeypatch.setattr(app_module, "ALLOW_UNGROUNDED_ANSWERS", True)

    response = client.post(
        "/answer",
        json={
            "company_id": 1,
            "query": "Summarize the Elements Supply AI assistant mission.",
            "allow_general": True,
        },
    )

    assert response.status_code == 200
    payload = response.json()
    assert payload["status"] == "ok"
    assert payload["citations"] == []
    assert payload["needs_human_review"] is True
    warnings = " ".join(payload.get("warnings", [])).lower()
    assert "general" in warnings or "workspace" in warnings
