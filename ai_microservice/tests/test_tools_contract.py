"""Unit tests for deterministic tool contract helpers."""
from __future__ import annotations

from datetime import date

import pytest

from ai_microservice.schemas import AWARD_QUOTE_SCHEMA, INVOICE_DRAFT_SCHEMA
from ai_microservice.tools_contract import (
    build_award_quote,
    build_invoice_draft,
    forecast_inventory,
    forecast_spend,
    forecast_supplier_performance,
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


def test_forecast_spend_returns_confidence_interval_and_drivers() -> None:
    context = [
        {
            "metadata": {
                "values": [1000.0, 1200.0, 900.0, 1100.0],
                "notes": ["Expedite fees", "Volume discounts"],
            }
        }
    ]
    inputs = {
        "category": "MRO",
        "past_period_days": 60,
        "projected_period_days": 30,
        "drivers": [
            "Planned shutdown",
            "Supplier incentives",
            "Logistics surcharges",
            "Spot buys",
            "Rush orders",
            "Should be trimmed",
        ],
    }

    payload = forecast_spend(context, inputs)

    assert payload["category"] == "MRO"
    assert payload["projected_total"] > 0
    assert payload["confidence_interval"]["lower"] <= payload["confidence_interval"]["upper"]
    assert len(payload["drivers"]) == 5


def test_forecast_supplier_performance_clamps_projection() -> None:
    context = [
        {
            "metadata": {
                "series": [1.2, 0.95, 0.85, 0.8],
            }
        }
    ]
    inputs = {
        "supplier_id": "SUP-42",
        "metric": "on_time",
        "period_days": 45,
    }

    payload = forecast_supplier_performance(context, inputs)

    assert payload["supplier_id"] == "SUP-42"
    assert 0.0 <= payload["projection"] <= 1.0
    interval = payload["confidence_interval"]
    assert 0.0 <= interval["lower"] <= interval["upper"] <= 1.0


def test_forecast_inventory_returns_expected_usage_and_date() -> None:
    context = [
        {
            "metadata": {
                "values": [15.0, 18.0, 20.0, 22.0],
            }
        }
    ]
    inputs = {
        "item_id": "SKU-99",
        "period_days": 21,
        "lead_time_days": 14,
    }

    payload = forecast_inventory(context, inputs)

    assert payload["item_id"] == "SKU-99"
    assert payload["expected_usage"] > 0
    assert payload["safety_stock"] > 0
    assert payload["expected_reorder_date"]
    # ensure ISO-8601
    assert isinstance(date.fromisoformat(payload["expected_reorder_date"]), date)


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
