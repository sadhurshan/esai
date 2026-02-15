"""Intent routing utilities for Copilot chat requests."""
from __future__ import annotations

import re
from typing import Any, Awaitable, Callable, Dict, List, Literal, Optional, Sequence, TypedDict

ChatIntent = Literal[
    "workspace_qna",
    "rfq_draft",
    "supplier_message",
    "maintenance_checklist",
    "inventory_whatif",
    "invoice_draft",
    "quote_compare",
    "start_workflow",
    "general_qna",
]

ACTION_INTENTS: Dict[str, str] = {
    "rfq_draft": "rfq_draft",
    "supplier_message": "supplier_message",
    "maintenance_checklist": "maintenance_checklist",
    "inventory_whatif": "inventory_whatif",
    "invoice_draft": "invoice_draft",
    "quote_compare": "compare_quotes",
}

WORKFLOW_INTENTS = {"start_workflow"}

KEYWORD_RULES: Dict[ChatIntent, List[str]] = {
    "rfq_draft": [
        "draft rfq",
        "draft a rfq",
        "draft an rfq",
        "draft the rfq",
        "draft request for quote",
        "draft a request for quote",
        "create rfq",
        "create a rfq",
        "create an rfq",
        "new rfq",
        "prepare rfq",
        "prepare a rfq",
        "prepare an rfq",
        "start rfq",
        "request for quote draft",
    ],
    "supplier_message": ["supplier message", "write supplier", "email supplier", "respond to supplier"],
    "maintenance_checklist": ["maintenance", "checklist", "diagnostic", "troubleshoot"],
    "inventory_whatif": [
        "inventory what-if",
        "inventory scenario",
        "simulate inventory",
        "what if",
        "stockout scenario",
        "safety stock analysis",
    ],
    "quote_compare": ["compare quote", "quote comparison", "rank suppliers", "score suppliers"],
    "invoice_draft": [
        "invoice draft",
        "create invoice",
        "generate invoice",
        "prepare invoice",
        "supplier invoice",
    ],
    "start_workflow": ["start workflow", "kick off workflow", "workflow suggestion"],
}

WORKSPACE_KEYWORDS = [
    "rfq",
    "quote",
    "po",
    "purchase order",
    "inventory",
    "supplier",
    "award",
    "invoice",
    "lead time",
    "pricing",
]

FOLLOWUP_KEYWORDS = [
    "update",
    "revise",
    "adjust",
    "change",
    "continue",
    "add",
    "remove",
    "tweak",
    "edit",
    "modify",
    "approve",
    "reject",
    "share",
    "send",
    "resend",
    "rescope",
    "next",
]

DATA_LOOKUP_PHRASES = [
    "how many",
    "count",
    "list",
    "show me",
    "do we have",
    "what is the status",
    "what are the",
]

TOOL_LOOKUP_PATTERNS = [
    re.compile(r"rfq\s*(?:#|no\.?|id)?\s*(\d+)", re.IGNORECASE),
    re.compile(r"quote\s*(?:#|no\.?|id)?\s*(\d+)", re.IGNORECASE),
    re.compile(r"inventory\s+(?:item|sku)\s*([\w-]+)", re.IGNORECASE),
]

TOOL_LOOKUP_KEYWORDS = [
    "rfq",
    "request for quote",
    "supplier",
    "inventory",
    "stock",
    "quote",
    "pricing",
    "invoice",
]


class ChatRouterDependencies(TypedDict, total=False):
    """Async builders supplied by the FastAPI layer."""

    answer_builder: Callable[[ChatIntent], Awaitable[Dict[str, Any]]]
    action_builder: Callable[[ChatIntent], Awaitable[Dict[str, Any]]]
    workflow_builder: Callable[[ChatIntent], Awaitable[Dict[str, Any]]]
    tool_request_builder: Callable[[ChatIntent], Dict[str, Any]]
    llm_intent_classifier: Callable[[Sequence[Dict[str, Any]], str], Awaitable[Optional[ChatIntent]]]


def _normalize_text(value: str | None) -> str:
    return value.lower().strip() if isinstance(value, str) else ""


def classify_intent(messages: Sequence[Dict[str, Any]], latest_user_text: str) -> ChatIntent:
    """Return the most likely chat intent using keyword heuristics."""

    latest_text = _normalize_text(latest_user_text)
    if not latest_text:
        return "general_qna"

    if _is_greeting_text(latest_text):
        return "general_qna"

    if _is_workspace_data_question(latest_text):
        return "workspace_qna"

    direct_match = _match_intent_keywords(latest_text)
    if direct_match:
        return direct_match

    if _contains_keywords(latest_text, WORKSPACE_KEYWORDS):
        return "workspace_qna"

    followup_requested = _contains_keywords(latest_text, FOLLOWUP_KEYWORDS)
    if followup_requested:
        historical_text = _recent_user_text(messages[:-1], limit=5)
        historical_match = _match_intent_keywords(historical_text)
        if historical_match:
            return historical_match
        if _contains_keywords(historical_text, WORKSPACE_KEYWORDS):
            return "workspace_qna"

    return "general_qna"


async def handle_chat_request(
    *,
    messages: Sequence[Dict[str, Any]],
    latest_user_text: str,
    context: Dict[str, Any],
    dependencies: ChatRouterDependencies,
) -> Dict[str, Any]:
    """Route the chat request to the appropriate builder."""

    intent = classify_intent(messages, latest_user_text)

    classifier = dependencies.get("llm_intent_classifier")
    if intent == "general_qna" and classifier is not None:
        refined = await classifier(messages, latest_user_text)
        if refined:
            intent = refined

    if intent in ACTION_INTENTS:
        builder = dependencies.get("action_builder")
        if builder is None:
            raise RuntimeError("action_builder dependency missing for chat router")
        return await builder(intent)

    if intent in WORKFLOW_INTENTS:
        builder = dependencies.get("workflow_builder")
        if builder is None:
            raise RuntimeError("workflow_builder dependency missing for chat router")
        return await builder(intent)

    tool_builder = dependencies.get("tool_request_builder")
    if intent == "workspace_qna" and tool_builder is not None and _requires_tool_lookup(latest_user_text):
        return tool_builder(intent)

    answer_builder = dependencies.get("answer_builder")
    if answer_builder is None:
        raise RuntimeError("answer_builder dependency missing for chat router")
    return await answer_builder(intent)


def _requires_tool_lookup(query: str) -> bool:
    normalized = _normalize_text(query)
    if not normalized:
        return False
    if any(pattern.search(query) for pattern in TOOL_LOOKUP_PATTERNS):
        return True
    return _contains_keywords(normalized, TOOL_LOOKUP_KEYWORDS)


def _recent_user_text(messages: Sequence[Dict[str, Any]], limit: int = 5) -> str:
    if limit <= 0:
        return ""

    collected: List[str] = []
    for message in reversed(messages):
        if message.get("role") != "user":
            continue
        normalized = _normalize_text(message.get("content"))
        if not normalized:
            continue
        collected.append(normalized)
        if len(collected) >= limit:
            break

    return " ".join(reversed(collected))


def _match_intent_keywords(text: str) -> Optional[ChatIntent]:
    if not text:
        return None
    for intent, keywords in KEYWORD_RULES.items():
        if _contains_keywords(text, keywords):
            return intent
    return None


def _contains_keywords(text: str, keywords: Sequence[str]) -> bool:
    if not text:
        return False
    return any(keyword in text for keyword in keywords)


def _is_greeting_text(text: str) -> bool:
    if not text:
        return False
    greetings = [
        "hi",
        "hello",
        "hey",
        "howdy",
        "good morning",
        "good afternoon",
        "good evening",
        "greetings",
        "yo",
    ]
    return any(text == term or text.startswith(f"{term} ") for term in greetings)


def _is_workspace_data_question(text: str) -> bool:
    if not text:
        return False
    if not _contains_keywords(text, WORKSPACE_KEYWORDS):
        return False
    return _contains_keywords(text, DATA_LOOKUP_PHRASES)


__all__ = [
    "ACTION_INTENTS",
    "ChatIntent",
    "ChatRouterDependencies",
    "WORKFLOW_INTENTS",
    "classify_intent",
    "handle_chat_request",
]
