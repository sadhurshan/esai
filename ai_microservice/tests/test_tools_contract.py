"""Unit tests for deterministic tool contract helpers."""
from __future__ import annotations

from datetime import date

import pytest

from ai_microservice.schemas import AWARD_QUOTE_SCHEMA, INVOICE_DRAFT_SCHEMA
from ai_microservice.tools_contract import (
    build_award_quote,
    build_invoice_draft,
    review_invoice,
    review_po,
    review_quote,
    review_rfq,
)


def test_award_quote_schema_declares_expected_payload_shape() -> None:
    payload_schema = AWARD_QUOTE_SCHEMA["properties"]["payload"]

    assert payload_schema["type"] == "object"
    assert set(payload_schema["required"]) == {
        "rfq_id",
        "supplier_id",
        "selected_quote_id",
        "justification",
        "delivery_date",
        "terms",
    }

    properties = payload_schema["properties"]
    assert properties["rfq_id"]["type"] == "string"
    assert properties["supplier_id"]["type"] == "string"
    assert properties["selected_quote_id"]["type"] == "string"
    assert properties["justification"]["type"] == "string"
    assert properties["delivery_date"]["format"] == "date"
    assert properties["terms"]["type"] == "array"
    assert properties["terms"]["items"]["type"] == "string"


def test_build_award_quote_returns_typed_payload() -> None:
    context = [
        {
            "snippet": "Supplier A can meet the 21 day lead time",
            "doc_id": "rfq-brief",
            "doc_version": "v1",
            "chunk_id": 7,
        }
    ]
    inputs = {
        "rfq_id": "123",
        "supplier_id": "sup-9",
        "selected_quote_id": "quote-42",
        "justification": "Supplier A offered the best total value.",
        "delivery_date": "2035-01-15",
        "terms": ["Net 30", "Expedite fees require approval"],
    }

    payload = build_award_quote(context, inputs)

    assert payload["rfq_id"] == "123"
    assert payload["supplier_id"] == "sup-9"
    assert payload["selected_quote_id"] == "quote-42"
    assert payload["justification"]
    assert payload["terms"] == ["Net 30", "Expedite fees require approval"]
    # ensure the delivery date is a valid ISO date string
    assert isinstance(date.fromisoformat(payload["delivery_date"]), date)
    assert all(isinstance(term, str) and term for term in payload["terms"])


def test_invoice_draft_schema_declares_expected_payload_shape() -> None:
    payload_schema = INVOICE_DRAFT_SCHEMA["properties"]["payload"]

    assert payload_schema["type"] == "object"
    assert set(payload_schema["required"]) == {"po_id", "invoice_date", "due_date", "line_items", "notes"}

    line_schema = payload_schema["properties"]["line_items"]
    assert line_schema["type"] == "array"
    assert line_schema["items"]["required"] == ["description", "qty", "unit_price", "tax_rate"]


def test_build_invoice_draft_returns_expected_fields() -> None:
    context = [
        {
            "snippet": "PO-99 covers two machining operations",
            "doc_id": "po-summary",
            "doc_version": "1",
            "chunk_id": 3,
            "metadata": {"po_id": "PO-99"},
        }
    ]
    inputs = {
        "po_id": "PO-123",
        "invoice_date": "2034-05-01",
        "due_in_days": 45,
        "line_items": [
            {"description": "Machining lot", "qty": 10, "unit_price": 50, "tax_rate": 0.07},
            {"description": "Finishing", "qty": 5, "unit_price": 25, "tax_rate": 0.05},
        ],
        "notes": "Match against PO-123 prior to approval.",
    }

    payload = build_invoice_draft(context, inputs)

    assert payload["po_id"] == "PO-123"
    assert payload["invoice_date"] == "2034-05-01"
    # due_date should respect provided due_in_days offset
    assert payload["due_date"] > payload["invoice_date"]
    assert len(payload["line_items"]) == 2
    assert payload["line_items"][0]["description"] == "Machining lot"
    assert payload["line_items"][0]["qty"] == 10
    assert payload["notes"] == "Match against PO-123 prior to approval."


def test_review_rfq_requires_identifier() -> None:
    with pytest.raises(ValueError):
        review_rfq([], {})


def test_review_rfq_returns_checklist() -> None:
    payload = review_rfq([], {"rfq_id": "RFQ-9"})

    assert payload["entity_type"] == "rfq"
    assert payload["entity_id"] == "RFQ-9"
    assert payload["checklist"]
    for entry in payload["checklist"]:
        assert entry["status"] in {"ok", "warning", "risk"}
        assert isinstance(entry["detail"], str)


def test_review_quote_returns_pricing_summary() -> None:
    payload = review_quote([], {"quote_id": "Q-77", "unit_price": 150, "target_price": 140})

    assert payload["entity_type"] == "quote"
    assert payload["entity_id"] == "Q-77"
    statuses = {item["status"] for item in payload["checklist"]}
    assert statuses <= {"ok", "warning", "risk"}


def test_review_po_returns_delivery_metrics() -> None:
    payload = review_po([], {"po_id": "PO-5001", "total_value": 60000, "line_items": [1, 2]})

    assert payload["entity_type"] == "po"
    assert payload["entity_id"] == "PO-5001"
    assert any("delivery" in item["label"].lower() for item in payload["checklist"])


def test_review_invoice_flags_due_dates() -> None:
    payload = review_invoice([], {"invoice_id": "INV-77", "due_date": date.today().isoformat()})

    assert payload["entity_type"] == "invoice"
    assert payload["entity_id"] == "INV-77"
    due_item = next(entry for entry in payload["checklist"] if entry["label"] == "Due date")
    assert due_item["status"] in {"warning", "risk"}
