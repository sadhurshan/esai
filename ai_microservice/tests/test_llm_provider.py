"""Tests for LLM provider implementations."""
from __future__ import annotations

import json
from typing import Any, Dict

import pytest

from ai_microservice.llm_provider import OpenAILLMProvider
from ai_microservice.schemas import ANSWER_SCHEMA


class _StubResponse:
    def __init__(self, payload: Dict[str, Any], status_code: int = 200) -> None:
        self._payload = payload
        self.status_code = status_code
        self.text = json.dumps(payload)

    def json(self) -> Dict[str, Any]:
        return self._payload


def test_openai_provider_returns_refusal_payload(monkeypatch: pytest.MonkeyPatch) -> None:
    provider = OpenAILLMProvider(api_key="test-key", model="gpt-test")
    context_blocks = [
        {
            "doc_id": "doc-1",
            "doc_version": "v1",
            "chunk_id": 0,
            "snippet": "alpha",
            "score": 0.9,
        }
    ]

    payload = {
        "choices": [
            {
                "finish_reason": "content_filter",
                "message": {
                    "content": None,
                    "refusal": "policy_violation",
                },
            }
        ]
    }

    def _fake_post(*_: Any, **__: Any) -> _StubResponse:
        return _StubResponse(payload)

    monkeypatch.setattr("ai_microservice.llm_provider.httpx.post", _fake_post)

    result = provider.generate_answer("question", context_blocks, ANSWER_SCHEMA)

    assert result["citations"] == []
    assert result["needs_human_review"] is True
    assert result["confidence"] == 0.0
    assert any("refused" in warning for warning in result["warnings"])
