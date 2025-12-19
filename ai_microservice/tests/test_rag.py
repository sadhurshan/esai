"""Integration tests for the semantic search + answer flow."""
from __future__ import annotations

from typing import Any, Tuple

import pytest
from fastapi.testclient import TestClient

from ai_microservice import app as app_module
from ai_microservice.vector_store import InMemoryVectorStore


class KeywordEmbeddingProvider:
    """Deterministic embedding provider keyed on simple keyword presence."""

    def __init__(self) -> None:
        self._vocabulary = ("alpha", "gamma", "rfq")

    def embed_texts(self, texts):
        vectors = []
        for text in texts:
            lower = (text or "").lower()
            vector = [1.0 if token in lower else 0.0 for token in self._vocabulary]
            if not any(vector):
                # small epsilon to avoid zero magnitude vectors so cosine similarity works
                vector = [0.1] * len(self._vocabulary)
            vectors.append(vector)
        return vectors


@pytest.fixture
def rag_environment(monkeypatch: pytest.MonkeyPatch) -> Tuple[KeywordEmbeddingProvider, InMemoryVectorStore]:
    provider = KeywordEmbeddingProvider()
    store = InMemoryVectorStore()
    monkeypatch.setattr(app_module, "embedding_provider", provider)
    monkeypatch.setattr(app_module, "vector_store", store)
    return provider, store


@pytest.fixture
def client(rag_environment: Tuple[KeywordEmbeddingProvider, InMemoryVectorStore]) -> TestClient:
    del rag_environment  # enforced to ensure fixture executes
    with TestClient(app_module.app) as test_client:
        yield test_client


def _index_document(client: TestClient, payload_overrides: dict[str, Any]) -> None:
    base_payload = {
        "company_id": 1,
        "doc_id": "doc-alpha",
        "doc_version": "v1",
        "title": "Doc Alpha",
        "source_type": "manual",
        "mime_type": "text/plain",
        "text": "Alpha procedures and maintenance steps.",
        "metadata": {"tags": ["alpha"]},
        "acl": ["role:admin"],
    }
    base_payload.update(payload_overrides)
    response = client.post("/index/document", json=base_payload)
    assert response.status_code == 200, response.text


def test_search_prefers_matching_document(client: TestClient) -> None:
    _index_document(client, {})
    _index_document(
        client,
        {
            "doc_id": "doc-gamma",
            "title": "Doc Gamma",
            "text": "Gamma handbook for suppliers.",
            "metadata": {"tags": ["gamma"]},
        },
    )

    search_payload = {
        "company_id": 1,
        "query": "alpha process steps",
        "top_k": 5,
    }
    response = client.post("/search", json=search_payload)

    assert response.status_code == 200
    body = response.json()
    assert body["hits"], "Expected at least one search hit"
    assert body["hits"][0]["doc_id"] == "doc-alpha"


def test_answer_returns_citations(client: TestClient) -> None:
    _index_document(client, {})

    response = client.post(
        "/answer",
        json={"company_id": 1, "query": "alpha procedures"},
    )

    assert response.status_code == 200
    body = response.json()
    assert body["citations"], "Citations should not be empty"
    first_citation = body["citations"][0]
    assert first_citation["doc_id"] == "doc-alpha"
    assert body["answer_markdown"].startswith("- ")
