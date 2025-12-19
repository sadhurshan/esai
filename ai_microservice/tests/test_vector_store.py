"""Tests for the in-memory vector store implementation."""
from __future__ import annotations

from ai_microservice.vector_store import InMemoryVectorStore


def _vec(values):
    return values


def test_upsert_and_search_returns_ranked_hits() -> None:
    store = InMemoryVectorStore()
    chunks = [
        {"chunk_index": 0, "text": "Alpha beta", "char_start": 0, "char_end": 10},
        {"chunk_index": 1, "text": "Gamma delta", "char_start": 10, "char_end": 20},
    ]
    embeddings = [_vec([1.0, 0.0]), _vec([0.0, 1.0])]
    metadata = {"title": "Doc A", "source_type": "manual", "tags": ["spec"]}

    store.upsert_chunks(company_id=1, doc_id="doc-a", doc_version="v1", chunks=chunks, embeddings=embeddings, metadata=metadata)

    hits = store.search(company_id=1, query_embedding=_vec([1.0, 0.0]), top_k=1)

    assert len(hits) == 1
    assert hits[0].doc_id == "doc-a"
    assert hits[0].chunk_id == 0
    assert hits[0].score > 0.9
    assert hits[0].snippet.startswith("Alpha")
    assert hits[0].metadata["chunk_index"] == 0


def test_filters_and_delete_remove_documents() -> None:
    store = InMemoryVectorStore()
    chunks = [{"chunk_index": 0, "text": "Alpha beta", "char_start": 0, "char_end": 10}]
    embeddings = [_vec([1.0, 0.0])]
    metadata = {"title": "Doc A", "source_type": "manual", "tags": ["spec"]}
    store.upsert_chunks(1, "doc-a", "v1", chunks, embeddings, metadata)

    hits = store.search(1, _vec([1.0, 0.0]), top_k=5, filters={"source_type": "manual", "tags": ["spec"]})
    assert hits

    hits = store.search(1, _vec([1.0, 0.0]), top_k=5, filters={"tags": ["other"]})
    assert hits == []

    store.delete_doc(1, "doc-a")
    hits = store.search(1, _vec([1.0, 0.0]), top_k=5)
    assert hits == []


def test_upsert_overwrites_existing_version() -> None:
    store = InMemoryVectorStore()
    chunks = [{"chunk_index": 0, "text": "Old", "char_start": 0, "char_end": 3}]
    embeddings = [_vec([1.0])]
    store.upsert_chunks(1, "doc-a", "v1", chunks, embeddings, {"title": "Doc"})

    new_chunks = [{"chunk_index": 0, "text": "New", "char_start": 0, "char_end": 3}]
    store.upsert_chunks(1, "doc-a", "v1", new_chunks, embeddings, {"title": "Doc"})

    hits = store.search(1, _vec([1.0]), top_k=5)
    assert hits[0].snippet.startswith("New")
