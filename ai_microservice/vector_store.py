"""Vector store abstractions for semantic search workflows."""
from __future__ import annotations

import math
from abc import ABC, abstractmethod
from dataclasses import dataclass
from typing import Any, Dict, Iterable, List, MutableMapping, Optional, Sequence

Vector = Sequence[float]


@dataclass(slots=True)
class SearchHit:
    """Represents a semantic search hit with metadata for the client UI."""

    doc_id: str
    doc_version: str
    chunk_id: int
    score: float
    title: Optional[str]
    snippet: str
    metadata: Dict[str, Any]


class VectorStore(ABC):
    """Defines the contract for storing and querying document embeddings."""

    @abstractmethod
    def upsert_chunks(
        self,
        company_id: int,
        doc_id: str,
        doc_version: str,
        chunks: Sequence[MutableMapping[str, Any]],
        embeddings: Sequence[Vector],
        metadata: MutableMapping[str, Any],
    ) -> None:
        """Persist embeddings for the provided document chunks."""
        raise NotImplementedError

    @abstractmethod
    def search(
        self,
        company_id: int,
        query_embedding: Vector,
        top_k: int,
        filters: Optional[MutableMapping[str, Any]] = None,
    ) -> List[SearchHit]:
        """Return the top matching chunks for the given query embedding."""
        raise NotImplementedError

    @abstractmethod
    def delete_doc(self, company_id: int, doc_id: str, doc_version: Optional[str] = None) -> None:
        """Remove embeddings for the specified document (optional version)."""
        raise NotImplementedError


@dataclass(slots=True)
class _StoredChunk:
    doc_id: str
    doc_version: str
    chunk_id: int
    title: Optional[str]
    snippet: str
    text: str
    vector: List[float]
    metadata: Dict[str, Any]


class InMemoryVectorStore(VectorStore):
    """Lightweight in-memory vector store for local development and tests."""

    def __init__(self) -> None:
        self._store: Dict[int, List[_StoredChunk]] = {}

    def upsert_chunks(
        self,
        company_id: int,
        doc_id: str,
        doc_version: str,
        chunks: Sequence[MutableMapping[str, Any]],
        embeddings: Sequence[Vector],
        metadata: MutableMapping[str, Any],
    ) -> None:
        if len(chunks) != len(embeddings):
            raise ValueError("chunks and embeddings must have the same length")

        company_chunks = self._store.setdefault(company_id, [])
        company_chunks[:] = [c for c in company_chunks if not (c.doc_id == doc_id and c.doc_version == doc_version)]

        for chunk, embedding in zip(chunks, embeddings):
            chunk_vector = list(map(float, embedding))
            combined_metadata = {
                **metadata,
                "chunk_index": chunk.get("chunk_index"),
                "char_start": chunk.get("char_start"),
                "char_end": chunk.get("char_end"),
            }
            text = str(chunk.get("text", ""))
            snippet = text[:250]
            stored_chunk = _StoredChunk(
                doc_id=doc_id,
                doc_version=doc_version,
                chunk_id=int(chunk.get("chunk_index", 0) or 0),
                title=metadata.get("title"),
                snippet=snippet,
                text=text,
                vector=chunk_vector,
                metadata=combined_metadata,
            )
            company_chunks.append(stored_chunk)

    def search(
        self,
        company_id: int,
        query_embedding: Vector,
        top_k: int,
        filters: Optional[MutableMapping[str, Any]] = None,
    ) -> List[SearchHit]:
        chunks = self._store.get(company_id, [])
        if not chunks:
            return []

        normalized_query = self._normalize_vector(query_embedding)
        if not normalized_query:
            return []

        filters = filters or {}
        hits: List[SearchHit] = []
        for chunk in chunks:
            if not self._matches_filters(chunk, filters):
                continue
            score = self._cosine_similarity(normalized_query, self._normalize_vector(chunk.vector))
            if math.isclose(score, 0.0, abs_tol=1e-12):
                continue
            hits.append(
                SearchHit(
                    doc_id=chunk.doc_id,
                    doc_version=chunk.doc_version,
                    chunk_id=chunk.chunk_id,
                    score=score,
                    title=chunk.title,
                    snippet=chunk.snippet,
                    metadata=chunk.metadata,
                )
            )

        hits.sort(key=lambda hit: hit.score, reverse=True)
        return hits[: max(1, top_k)]

    def delete_doc(self, company_id: int, doc_id: str, doc_version: Optional[str] = None) -> None:
        chunks = self._store.get(company_id)
        if not chunks:
            return
        if doc_version is None:
            self._store[company_id] = [c for c in chunks if c.doc_id != doc_id]
        else:
            self._store[company_id] = [c for c in chunks if not (c.doc_id == doc_id and c.doc_version == doc_version)]

    @staticmethod
    def _normalize_vector(vector: Vector) -> List[float]:
        values = list(map(float, vector))
        magnitude = math.sqrt(sum(value * value for value in values))
        if magnitude == 0:
            return []
        return [value / magnitude for value in values]

    @staticmethod
    def _cosine_similarity(vector_a: Sequence[float], vector_b: Sequence[float]) -> float:
        if not vector_a or not vector_b:
            return 0.0
        if len(vector_a) != len(vector_b):
            raise ValueError("Vectors must be the same length")
        return sum(a * b for a, b in zip(vector_a, vector_b))

    @staticmethod
    def _matches_filters(chunk: _StoredChunk, filters: MutableMapping[str, Any]) -> bool:
        for key, expected in filters.items():
            if expected is None:
                continue
            if key == "doc_id" and chunk.doc_id != expected:
                return False
            if key == "doc_version" and chunk.doc_version != expected:
                return False
            if key == "source_type" and chunk.metadata.get("source_type") != expected:
                return False
            if key == "tags":
                chunk_tags = chunk.metadata.get("tags") or []
                expected_set = set(expected if isinstance(expected, Iterable) and not isinstance(expected, (str, bytes)) else [expected])
                if not set(chunk_tags).intersection(expected_set):
                    return False
            elif key not in {"doc_id", "doc_version", "source_type", "tags"}:
                if chunk.metadata.get(key) != expected:
                    return False
        return True


__all__ = ["VectorStore", "InMemoryVectorStore", "SearchHit", "Vector"]
