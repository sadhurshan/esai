"""JSON schema definitions for AI microservice payloads."""
from __future__ import annotations

CITATION_ITEM_SCHEMA = {
    "type": "object",
    "additionalProperties": False,
    "required": ["doc_id", "doc_version", "chunk_id", "score", "snippet"],
    "properties": {
        "doc_id": {"type": "string", "minLength": 1},
        "doc_version": {"type": "string", "minLength": 1},
        "chunk_id": {"type": "integer"},
        "score": {"type": "number"},
        "snippet": {"type": "string"},
    },
}

CITATIONS_ARRAY_SCHEMA = {
    "type": "array",
    "items": CITATION_ITEM_SCHEMA,
}

WARNINGS_ARRAY_SCHEMA = {
    "type": "array",
    "items": {"type": "string"},
}

WRAPPER_REQUIRED_FIELDS = [
    "action_type",
    "summary",
    "payload",
    "citations",
    "confidence",
    "needs_human_review",
    "warnings",
]


def _make_action_wrapper_schema(title: str, payload_schema: dict[str, object]) -> dict[str, object]:
    """Return a strict schema for a Copilot Action response wrapper."""

    return {
        "$schema": "https://json-schema.org/draft/2020-12/schema",
        "title": title,
        "type": "object",
        "additionalProperties": False,
        "required": WRAPPER_REQUIRED_FIELDS,
        "properties": {
            "action_type": {"type": "string", "minLength": 1},
            "summary": {"type": "string", "minLength": 1},
            "payload": payload_schema,
            "citations": CITATIONS_ARRAY_SCHEMA,
            "confidence": {"type": "number", "minimum": 0, "maximum": 1},
            "needs_human_review": {"type": "boolean"},
            "warnings": WARNINGS_ARRAY_SCHEMA,
        },
    }


ANSWER_SCHEMA = {
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "title": "AnswerResponse",
    "type": "object",
    "additionalProperties": False,
    "required": [
        "answer_markdown",
        "citations",
        "confidence",
        "needs_human_review",
        "warnings",
    ],
    "properties": {
        "answer_markdown": {
            "type": "string",
            "minLength": 1,
            "description": "LLM answer formatted in Markdown.",
        },
        "citations": CITATIONS_ARRAY_SCHEMA,
        "confidence": {"type": "number", "minimum": 0, "maximum": 1},
        "needs_human_review": {"type": "boolean"},
        "warnings": WARNINGS_ARRAY_SCHEMA,
    },
}

RFQ_LINE_ITEM_SCHEMA = {
    "type": "object",
    "additionalProperties": False,
    "required": ["part_id", "description", "quantity", "target_date"],
    "properties": {
        "part_id": {"type": "string", "minLength": 1},
        "description": {"type": "string", "minLength": 1},
        "quantity": {"type": "number", "exclusiveMinimum": 0},
        "target_date": {"type": "string", "format": "date"},
        "spec_notes": {"type": "string"},
        "source_citation_ids": {
            "type": "array",
            "items": {"type": "string", "minLength": 1},
        },
    },
}

RFQ_RUBRIC_ITEM_SCHEMA = {
    "type": "object",
    "additionalProperties": False,
    "required": ["criterion", "weight", "guidance"],
    "properties": {
        "criterion": {"type": "string", "minLength": 1},
        "weight": {"type": "number", "minimum": 0, "maximum": 1},
        "guidance": {"type": "string", "minLength": 1},
    },
}

RFQ_DRAFT_PAYLOAD_SCHEMA = {
    "type": "object",
    "additionalProperties": False,
    "required": [
        "rfq_title",
        "scope_summary",
        "line_items",
        "terms_and_conditions",
        "questions_for_suppliers",
        "evaluation_rubric",
    ],
    "properties": {
        "rfq_title": {"type": "string", "minLength": 1},
        "scope_summary": {"type": "string", "minLength": 1},
        "line_items": {
            "type": "array",
            "minItems": 1,
            "items": RFQ_LINE_ITEM_SCHEMA,
        },
        "terms_and_conditions": {
            "type": "array",
            "items": {"type": "string", "minLength": 1},
        },
        "questions_for_suppliers": {
            "type": "array",
            "items": {"type": "string", "minLength": 1},
        },
        "evaluation_rubric": {
            "type": "array",
            "minItems": 1,
            "items": RFQ_RUBRIC_ITEM_SCHEMA,
        },
    },
}

SUPPLIER_MESSAGE_PAYLOAD_SCHEMA = {
    "type": "object",
    "additionalProperties": False,
    "required": [
        "subject",
        "message_body",
        "negotiation_points",
        "fallback_options",
    ],
    "properties": {
        "subject": {"type": "string", "minLength": 1},
        "message_body": {"type": "string", "minLength": 1},
        "negotiation_points": {
            "type": "array",
            "items": {"type": "string", "minLength": 1},
        },
        "fallback_options": {
            "type": "array",
            "items": {"type": "string", "minLength": 1},
        },
    },
}

MAINTENANCE_CHECKLIST_PAYLOAD_SCHEMA = {
    "type": "object",
    "additionalProperties": False,
    "required": [
        "safety_notes",
        "diagnostic_steps",
        "likely_causes",
        "recommended_actions",
        "when_to_escalate",
    ],
    "properties": {
        "safety_notes": {
            "type": "array",
            "items": {"type": "string", "minLength": 1},
        },
        "diagnostic_steps": {
            "type": "array",
            "items": {"type": "string", "minLength": 1},
        },
        "likely_causes": {
            "type": "array",
            "items": {"type": "string", "minLength": 1},
        },
        "recommended_actions": {
            "type": "array",
            "items": {"type": "string", "minLength": 1},
        },
        "when_to_escalate": {
            "type": "array",
            "items": {"type": "string", "minLength": 1},
        },
    },
}

INVENTORY_WHATIF_PAYLOAD_SCHEMA = {
    "type": "object",
    "additionalProperties": False,
    "required": [
        "projected_stockout_risk",
        "expected_stockout_days",
        "expected_holding_cost_change",
        "recommendation",
        "assumptions",
    ],
    "properties": {
        "projected_stockout_risk": {"type": "number", "minimum": 0, "maximum": 1},
        "expected_stockout_days": {"type": "number", "minimum": 0},
        "expected_holding_cost_change": {"type": "number"},
        "recommendation": {"type": "string", "minLength": 1},
        "assumptions": {
            "type": "array",
            "items": {"type": "string", "minLength": 1},
        },
    },
}

RFQ_DRAFT_SCHEMA = _make_action_wrapper_schema(
    "CopilotActionRfqDraft", RFQ_DRAFT_PAYLOAD_SCHEMA
)

SUPPLIER_MESSAGE_SCHEMA = _make_action_wrapper_schema(
    "CopilotActionSupplierMessage", SUPPLIER_MESSAGE_PAYLOAD_SCHEMA
)

MAINTENANCE_CHECKLIST_SCHEMA = _make_action_wrapper_schema(
    "CopilotActionMaintenanceChecklist", MAINTENANCE_CHECKLIST_PAYLOAD_SCHEMA
)

INVENTORY_WHATIF_SCHEMA = _make_action_wrapper_schema(
    "CopilotActionInventoryWhatIf", INVENTORY_WHATIF_PAYLOAD_SCHEMA
)

__all__ = [
    "ANSWER_SCHEMA",
    "RFQ_DRAFT_SCHEMA",
    "SUPPLIER_MESSAGE_SCHEMA",
    "MAINTENANCE_CHECKLIST_SCHEMA",
    "INVENTORY_WHATIF_SCHEMA",
]
