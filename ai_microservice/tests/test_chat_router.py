from __future__ import annotations

import asyncio
from typing import Any, Dict, List

from ai_microservice import chat_router


def test_classify_intent_draft_rfq_with_article_routes_to_rfq_draft() -> None:
    intent = chat_router.classify_intent([], "Can you draft a RFQ?")

    assert intent == "rfq_draft"


def test_handle_chat_request_draft_rfq_uses_action_builder() -> None:
    action_calls: List[str] = []
    tool_calls: List[str] = []

    async def action_builder(intent: chat_router.ChatIntent) -> Dict[str, Any]:
        action_calls.append(intent)
        return {"type": "action_suggestion", "intent": intent}

    def tool_request_builder(intent: chat_router.ChatIntent) -> Dict[str, Any]:
        tool_calls.append(intent)
        return {"type": "tool_request", "intent": intent}

    async def _execute() -> Dict[str, Any]:
        return await chat_router.handle_chat_request(
            messages=[{"role": "user", "content": "Can you draft a RFQ?"}],
            latest_user_text="Can you draft a RFQ?",
            context={},
            dependencies={
                "action_builder": action_builder,
                "tool_request_builder": tool_request_builder,
                "answer_builder": lambda intent: None,  # not used in this route
            },
        )

    response = asyncio.run(_execute())

    assert response["type"] == "action_suggestion"
    assert action_calls == ["rfq_draft"]
    assert tool_calls == []
