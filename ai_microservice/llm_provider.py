"""LLM provider abstractions for the AI microservice."""
from __future__ import annotations

import json
import logging
import os
from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from typing import Any, Callable, Dict, List, Sequence

import httpx
from ai_microservice.prompts import build_answer_messages

ContextBlock = Dict[str, Any]
AnswerPayload = Dict[str, Any]
LOGGER = logging.getLogger(__name__)


class LLMProviderError(RuntimeError):
    """Base exception for LLM provider failures."""


class ProviderConfigError(LLMProviderError):
    """Raised when a provider is misconfigured (e.g., missing API key)."""


class ProviderResponseError(LLMProviderError):
    """Raised when a provider returns an invalid or failed response."""


class LLMProvider(ABC):
    """Abstract base class for large-language-model providers."""

    @abstractmethod
    def generate_answer(
        self,
        query: str,
        context_blocks: Sequence[ContextBlock],
        response_schema: Dict[str, Any],
        safety_identifier: str | None = None,
    ) -> AnswerPayload:
        """Return a structured answer for the provided query and context."""
        raise NotImplementedError


@dataclass(slots=True)
class DummyLLMProvider(LLMProvider):
    """Deterministic fallback provider without external dependencies."""

    max_blocks: int = 8

    def generate_answer(
        self,
        query: str,
        context_blocks: Sequence[ContextBlock],
        response_schema: Dict[str, Any],
        safety_identifier: str | None = None,
    ) -> AnswerPayload:
        summary_lines: List[str] = []
        for block in list(context_blocks)[: self.max_blocks]:
            snippet = str(block.get("snippet") or block.get("text") or "").strip()
            if not snippet:
                continue
            normalized = " ".join(snippet.split())
            summary_lines.append(f"- {normalized[:240]}")
        summary = "\n".join(summary_lines) if summary_lines else "Not enough information in indexed sources."

        citations: List[Dict[str, Any]] = []
        for block in context_blocks:
            citations.append(
                {
                    "doc_id": block.get("doc_id"),
                    "doc_version": block.get("doc_version"),
                    "chunk_id": block.get("chunk_id"),
                    "score": block.get("score", 0.0),
                    "snippet": (block.get("snippet") or "")[:250],
                }
            )

        return {
            "answer_markdown": summary,
            "citations": citations,
            "confidence": 0.35 if summary_lines else 0.0,
            "needs_human_review": not bool(summary_lines),
            "warnings": [] if summary_lines else ["No supporting sources"],
        }


@dataclass(slots=True)
class OpenAILLMProvider(LLMProvider):
    """LLM provider that calls OpenAI's Chat Completions API."""

    model: str = field(default_factory=lambda: os.getenv("AI_LLM_MODEL", "gpt-4.1-mini"))
    api_key: str | None = field(default=None)
    base_url: str = field(default_factory=lambda: os.getenv("OPENAI_BASE_URL", "https://api.openai.com/v1"))
    timeout_seconds: float = field(default_factory=lambda: float(os.getenv("AI_LLM_TIMEOUT_SECONDS", "45")))
    temperature: float = 0.1
    max_output_tokens: int = 800

    def __post_init__(self) -> None:
        self.api_key = (self.api_key or os.getenv("OPENAI_API_KEY") or os.getenv("AI_OPENAI_API_KEY") or "").strip() or None

    def generate_answer(
        self,
        query: str,
        context_blocks: Sequence[ContextBlock],
        response_schema: Dict[str, Any],
        safety_identifier: str | None = None,
    ) -> AnswerPayload:
        if not self.api_key:
            raise ProviderConfigError("OPENAI_API_KEY is not configured")

        messages = build_answer_messages(query, context_blocks)
        payload: Dict[str, Any] = {
            "model": self.model,
            "messages": messages,
            "temperature": self.temperature,
            "max_output_tokens": self.max_output_tokens,
            "response_format": {
                "type": "json_schema",
                "json_schema": {
                    "strict": True,
                    "schema": response_schema,
                },
            },
        }
        if safety_identifier:
            payload["user"] = safety_identifier
            payload.setdefault("metadata", {})["safety_identifier"] = safety_identifier

        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
        }
        url = f"{self.base_url.rstrip('/')}/chat/completions"

        try:
            response = httpx.post(url, json=payload, headers=headers, timeout=self.timeout_seconds)
        except httpx.TimeoutException as exc:
            raise ProviderResponseError("OpenAI request timed out") from exc
        except httpx.RequestError as exc:  # pragma: no cover - network failure
            raise ProviderResponseError(f"OpenAI request failed: {exc}") from exc

        if response.status_code >= 400:
            raise ProviderResponseError(f"OpenAI error {response.status_code}: {response.text[:256]}")

        try:
            body = response.json()
        except json.JSONDecodeError as exc:  # pragma: no cover - defensive guard
            raise ProviderResponseError("OpenAI response was not valid JSON") from exc

        return self._extract_answer(body)

    def _extract_answer(self, body: Dict[str, Any]) -> AnswerPayload:
        choices = body.get("choices") or []
        if not choices:
            raise ProviderResponseError("OpenAI response missing choices")

        choice = choices[0]
        message = choice.get("message") or {}
        finish_reason = str(choice.get("finish_reason") or "").lower()
        refusal_reason = self._detect_refusal_reason(finish_reason, message)
        if refusal_reason:
            return self._build_refusal_payload(refusal_reason)

        content = message.get("content")
        if isinstance(content, list):
            content = "".join(part.get("text", "") for part in content if isinstance(part, dict))
        if not content or not isinstance(content, str):
            raise ProviderResponseError("OpenAI response missing content")
        try:
            return json.loads(content)
        except json.JSONDecodeError as exc:
            raise ProviderResponseError("OpenAI response content was not valid JSON") from exc

    def _detect_refusal_reason(self, finish_reason: str, message: Dict[str, Any]) -> str | None:
        refusal_tokens: List[str] = []
        normalized_finish = finish_reason.strip().lower()
        if normalized_finish in {"content_filter", "safety", "refusal", "length"}:
            refusal_tokens.append(normalized_finish or "refused")

        refusal_hint = self._extract_refusal_text(message.get("refusal"))
        if refusal_hint:
            refusal_tokens.append(refusal_hint)

        content = message.get("content")
        if isinstance(content, list):
            for part in content:
                if isinstance(part, dict) and part.get("type") == "refusal":
                    embedded_reason = self._extract_refusal_text(part.get("refusal"))
                    if embedded_reason:
                        refusal_tokens.append(embedded_reason)
        if refusal_tokens:
            return refusal_tokens[0]
        return None

    @staticmethod
    def _extract_refusal_text(value: Any) -> str | None:
        if isinstance(value, str) and value.strip():
            return value.strip()
        if isinstance(value, dict):
            for key in ("reason", "message", "detail", "text"):
                inner = value.get(key)
                if isinstance(inner, str) and inner.strip():
                    return inner.strip()
        return None

    @staticmethod
    def _build_refusal_payload(reason: str | None = None) -> AnswerPayload:
        warnings = ["refused"]
        clean_reason = (reason or "").strip()
        if clean_reason and clean_reason not in {"refused"}:
            warnings.append(f"refused:{clean_reason}")
        return {
            "answer_markdown": "I'm sorry, I can't answer that request with the available sources.",
            "citations": [],
            "confidence": 0.0,
            "needs_human_review": True,
            "warnings": warnings,
        }


def _build_provider(name: str) -> LLMProvider:
    registry: Dict[str, Callable[[], LLMProvider]] = {
        "dummy": DummyLLMProvider,
        "openai": OpenAILLMProvider,
    }
    try:
        return registry[name]()
    except KeyError as exc:  # pragma: no cover - defensive guard
        raise ValueError(f"Unsupported LLM provider '{name}'") from exc

def build_llm_provider(name: str) -> LLMProvider:
    normalized = name.strip().lower() if isinstance(name, str) else "dummy"
    return _build_provider(normalized)


def get_llm_provider() -> LLMProvider:
    """Return the configured LLM provider."""

    provider_name = os.getenv("AI_LLM_PROVIDER", "dummy").strip().lower()
    return build_llm_provider(provider_name)


__all__ = [
    "LLMProviderError",
    "ProviderConfigError",
    "ProviderResponseError",
    "LLMProvider",
    "DummyLLMProvider",
    "OpenAILLMProvider",
    "build_llm_provider",
    "get_llm_provider",
]
