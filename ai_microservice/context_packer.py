"""Utilities for truncating and deduplicating semantic-search context."""
from __future__ import annotations

from collections import defaultdict
from typing import Any, Dict, Iterable, List, Sequence

DEFAULT_MAX_CHARS = 12_000
DEFAULT_MAX_CHUNKS = 12
DEFAULT_PER_DOC_LIMIT = 4

Hit = Dict[str, Any]


def pack_context_hits(
    hits: Sequence[Hit],
    *,
    max_chars: int = DEFAULT_MAX_CHARS,
    max_chunks: int = DEFAULT_MAX_CHUNKS,
    per_doc_limit: int = DEFAULT_PER_DOC_LIMIT,
) -> List[Hit]:
    """Deduplicate and trim semantic search hits to honor token budgets."""

    if max_chars <= 0 or max_chunks <= 0 or per_doc_limit <= 0:
        return []

    ordered_hits = list(hits)
    selected: List[Hit] = []
    doc_counts: Dict[str, int] = defaultdict(int)
    seen_snippets: set[str] = set()
    seen_chunks: set[tuple[str, str, str]] = set()
    total_chars = 0

    def _try_select(hit: Hit) -> bool:
        nonlocal total_chars
        if len(selected) >= max_chunks:
            return False

        doc_id = str(hit.get("doc_id")) if hit.get("doc_id") is not None else None
        doc_version = str(hit.get("doc_version")) if hit.get("doc_version") is not None else None
        chunk_id = hit.get("chunk_id")
        if doc_id is None or doc_version is None or chunk_id is None:
            return False

        key = (doc_id, doc_version, str(chunk_id))
        if key in seen_chunks:
            return False
        if doc_counts[doc_id] >= per_doc_limit:
            return False

        snippet = str(hit.get("snippet") or hit.get("text") or "").strip()
        normalized = _normalize_snippet(snippet)
        if normalized and normalized in seen_snippets:
            return False

        trimmed_snippet = snippet[:250]
        snippet_len = len(trimmed_snippet)
        if selected and snippet_len and total_chars + snippet_len > max_chars:
            return False

        selected_hit = {
            **hit,
            "doc_id": doc_id,
            "doc_version": doc_version,
            "chunk_id": int(chunk_id),
            "snippet": trimmed_snippet,
        }
        selected.append(selected_hit)
        seen_chunks.add(key)
        if normalized:
            seen_snippets.add(normalized)
        doc_counts[doc_id] += 1
        total_chars += snippet_len
        return True

    # Pass 1: ensure at least one chunk per document when possible
    for hit in ordered_hits:
        doc_id = hit.get("doc_id")
        if doc_id is None:
            continue
        if doc_counts[str(doc_id)]:
            continue
        _try_select(hit)

    # Pass 2: fill remaining budget while respecting per-doc limits
    for hit in ordered_hits:
        if len(selected) >= max_chunks:
            break
        _try_select(hit)

    return selected


def _normalize_snippet(snippet: str) -> str:
    lowered = snippet.lower().strip()
    if not lowered:
        return ""
    return " ".join(lowered.split())


__all__ = [
    "pack_context_hits",
    "DEFAULT_MAX_CHARS",
    "DEFAULT_MAX_CHUNKS",
    "DEFAULT_PER_DOC_LIMIT",
]
