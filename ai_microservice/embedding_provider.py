"""Embedding provider abstractions for the AI microservice."""
from __future__ import annotations

import hashlib
import os
from abc import ABC, abstractmethod
from dataclasses import dataclass
from typing import Callable, Iterable, List, Sequence

import httpx

Vector = List[float]


class EmbeddingProvider(ABC):
    """Abstract base class for embedding providers."""

    @abstractmethod
    def embed_texts(self, texts: Sequence[str]) -> List[Vector]:
        """Generate vector embeddings for the provided texts."""
        raise NotImplementedError


@dataclass(slots=True)
class DummyEmbeddingProvider(EmbeddingProvider):
    """Deterministic hash-based provider suitable for local dev and tests."""

    dimensions: int = 64

    def embed_texts(self, texts: Sequence[str]) -> List[Vector]:
        return [self._embed_text(text) for text in texts]

    def _embed_text(self, text: str) -> Vector:
        seed = text or ""
        digest = hashlib.sha256(seed.encode("utf-8")).digest()
        vector: Vector = []
        while len(vector) < self.dimensions:
            for i in range(0, len(digest), 4):
                chunk = digest[i : i + 4]
                if len(chunk) < 4:
                    chunk = chunk.ljust(4, b"\0")
                value = int.from_bytes(chunk, "big", signed=False)
                normalized = (value % 10_000) / 10_000.0
                vector.append(normalized)
                if len(vector) >= self.dimensions:
                    break
            digest = hashlib.sha256(digest).digest()
        return vector


class OpenAIEmbeddingProvider(EmbeddingProvider):
    """Placeholder OpenAI provider."""

    def __init__(self) -> None:
        self.api_key = os.getenv("AI_OPENAI_API_KEY") or os.getenv("OPENAI_API_KEY")
        self.base_url = os.getenv("AI_OPENAI_BASE_URL", "https://api.openai.com/v1")
        self.model = os.getenv("AI_EMBEDDING_MODEL", "text-embedding-3-small")

    def embed_texts(self, texts: Sequence[str]) -> List[Vector]:
        if not self.api_key:
            raise ValueError("OpenAI API key is not configured for embeddings")

        payload = {
            "model": self.model,
            "input": list(texts),
        }
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
        }

        with httpx.Client(timeout=30.0) as client:
            response = client.post(
                f"{self.base_url.rstrip('/')}/embeddings",
                headers=headers,
                json=payload,
            )
            response.raise_for_status()
            data = response.json()

        embeddings = [item.get("embedding") for item in data.get("data", [])]
        if len(embeddings) != len(texts):
            raise ValueError("Embedding response size mismatch")

        return embeddings  # type: ignore[return-value]


class LocalEmbeddingProvider(EmbeddingProvider):
    """Placeholder provider for on-prem/local models."""

    def embed_texts(self, texts: Sequence[str]) -> List[Vector]:
        raise NotImplementedError("Local embedding provider not implemented yet")


def _build_provider(name: str) -> EmbeddingProvider:
    registry: dict[str, Callable[[], EmbeddingProvider]] = {
        "dummy": DummyEmbeddingProvider,
        "openai": OpenAIEmbeddingProvider,
        "local": LocalEmbeddingProvider,
    }
    try:
        return registry[name]()
    except KeyError as exc:  # pragma: no cover - defensive guard
        raise ValueError(f"Unsupported embedding provider '{name}'") from exc


def get_embedding_provider() -> EmbeddingProvider:
    """Return the configured embedding provider based on environment variables."""

    provider_name = os.getenv("AI_EMBEDDING_PROVIDER", "dummy").strip().lower()
    return _build_provider(provider_name)


__all__ = [
    "EmbeddingProvider",
    "DummyEmbeddingProvider",
    "OpenAIEmbeddingProvider",
    "LocalEmbeddingProvider",
    "get_embedding_provider",
]
