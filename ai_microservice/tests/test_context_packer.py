"""Tests for the context packer helper."""
from __future__ import annotations

from ai_microservice.context_packer import pack_context_hits


def _make_hit(doc: str, chunk: int, snippet: str, score: float = 0.5) -> dict:
    return {
        "doc_id": doc,
        "doc_version": "v1",
        "chunk_id": chunk,
        "snippet": snippet,
        "score": score,
    }


def test_pack_context_hits_limits_per_doc_and_deduplicates() -> None:
    hits = [
        _make_hit("doc-1", 0, "Repeated snippet"),
        _make_hit("doc-1", 1, "Repeated snippet"),
        _make_hit("doc-1", 2, "Unique A"),
        _make_hit("doc-2", 0, "Doc2 snippet"),
        _make_hit("doc-2", 1, "Doc2 extra"),
        _make_hit("doc-3", 0, "Doc3 snippet"),
    ]

    packed = pack_context_hits(hits, max_chars=1_000, max_chunks=5, per_doc_limit=2)

    assert len(packed) <= 5
    assert sum(1 for hit in packed if hit["doc_id"] == "doc-1") == 2
    assert sum(1 for hit in packed if hit["doc_id"] == "doc-2") == 2
    assert any(hit["doc_id"] == "doc-3" for hit in packed)
    snippets = [hit["snippet"] for hit in packed]
    assert snippets.count("Repeated snippet") == 1


def test_pack_context_hits_respects_character_budget() -> None:
    long_snippet = "x" * 249
    hits = [
        _make_hit("doc-1", 0, long_snippet),
        _make_hit("doc-2", 0, long_snippet),
        _make_hit("doc-3", 0, long_snippet),
    ]

    packed = pack_context_hits(hits, max_chars=260, max_chunks=10, per_doc_limit=5)

    assert len(packed) == 1  # only first fits before exceeding char budget
    assert packed[0]["doc_id"] == "doc-1"


def test_pack_context_hits_requires_document_metadata() -> None:
    hits = [
        {
            "doc_id": None,
            "doc_version": "v1",
            "chunk_id": 0,
            "snippet": "Missing doc",
            "score": 0.1,
        },
        _make_hit("doc-1", 0, "Valid"),
    ]

    packed = pack_context_hits(hits)

    assert len(packed) == 1
    assert packed[0]["doc_id"] == "doc-1"
