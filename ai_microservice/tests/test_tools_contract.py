"""Unit tests for deterministic tool contract helpers."""
from __future__ import annotations

from datetime import date

import pytest

from ai_microservice.schemas import (
    AWARD_QUOTE_SCHEMA,
    INVOICE_DRAFT_SCHEMA,
    INVOICE_MATCH_SCHEMA,
    INVOICE_MISMATCH_RESOLUTION_SCHEMA,
    PAYMENT_DRAFT_SCHEMA,
    RECEIPT_DRAFT_SCHEMA,
)
from ai_microservice.tools_contract import (
    build_award_quote,
    build_invoice_draft,
    build_payment_draft,
    build_receipt_draft,
    build_rfq_draft,
    match_invoice_to_po_and_receipt,
    forecast_inventory,
    forecast_spend,
    forecast_supplier_performance,
    review_invoice,
    review_po,
    review_quote,
    review_rfq,
    resolve_invoice_mismatch,
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


def test_receipt_draft_schema_declares_expected_payload_shape() -> None:
    payload_schema = RECEIPT_DRAFT_SCHEMA["properties"]["payload"]

    assert payload_schema["type"] == "object"
    assert set(payload_schema["required"]) == {"po_id", "received_date", "line_items"}

    line_schema = payload_schema["properties"]["line_items"]
    assert line_schema["type"] == "array"
    required_fields = set(line_schema["items"]["required"])
    assert {"po_line_id", "description", "received_qty", "accepted_qty", "rejected_qty"}.issubset(required_fields)


def test_build_receipt_draft_uses_context_po_and_totals() -> None:
    context = [
        {
            "doc_id": "po-ctx",
            "doc_version": "v1",
            "chunk_id": 1,
            "metadata": {"po_id": "PO-77"},
            "snippet": "Line ready for inspection",
        }
    ]
    inputs = {
        "line_items": [
            {
                "po_line_id": "LINE-1",
                "description": "Widget",
                "expected_qty": 6,
                "received_qty": 5,
                "accepted_qty": 4,
                "rejected_qty": 1,
            }
        ]
    }

    payload = build_receipt_draft(context, inputs)

    assert payload["po_id"] == "PO-77"
    assert payload["status"] == "draft"
    assert payload["total_received_qty"] == pytest.approx(5.0)
    assert payload["line_items"][0]["po_line_id"] == "LINE-1"


def test_payment_draft_schema_declares_expected_payload_shape() -> None:
    payload_schema = PAYMENT_DRAFT_SCHEMA["properties"]["payload"]

    assert payload_schema["type"] == "object"
    assert set(payload_schema["required"]) == {"invoice_id", "amount", "payment_method", "notes"}


def test_build_payment_draft_respects_defaults() -> None:
    context = [
        {
            "metadata": {"invoice_id": "INV-10"},
            "snippet": "Invoice ready for payment",
            "doc_id": "invoice",
            "doc_version": "1",
            "chunk_id": 0,
        }
    ]
    inputs = {
        "amount": 123.45,
        "payment_method": "wire",
        "notes": "Schedule wire transfer",
    }

    payload = build_payment_draft(context, inputs)

    assert payload["invoice_id"] == "INV-10"
    assert payload["amount"] == pytest.approx(123.45)
    assert payload["currency"] == "USD"
    assert isinstance(date.fromisoformat(payload["scheduled_date"]), date)
    assert payload["payment_method"] == "wire"
    assert payload["notes"] == "Schedule wire transfer"


def test_invoice_match_schema_requires_core_fields() -> None:
    payload_schema = INVOICE_MATCH_SCHEMA["properties"]["payload"]

    assert set(payload_schema["required"]) == {
        "invoice_id",
        "matched_po_id",
        "matched_receipt_ids",
        "mismatches",
        "recommendation",
    }


def test_invoice_mismatch_resolution_schema_requires_core_fields() -> None:
    payload_schema = INVOICE_MISMATCH_RESOLUTION_SCHEMA["properties"]["payload"]

    assert set(payload_schema["required"]) == {
        "invoice_id",
        "resolution",
        "actions",
        "impacted_lines",
        "next_steps",
    }


def test_match_invoice_tool_detects_qty_and_price_mismatches() -> None:
    context = [
        {
            "doc_id": "invoice-ctx",
            "doc_version": "1",
            "chunk_id": 0,
            "metadata": {"po_id": "PO-77", "receipt_ids": ["RCPT-8"]},
            "snippet": "Invoice INV-9 references PO-77",
        }
    ]
    inputs = {
        "invoice_id": "INV-9",
        "po_id": "PO-77",
        "invoice_lines": [
            {"line_number": 1, "item_code": "A", "qty": 12, "unit_price": 11.5, "tax_rate": 0.08},
        ],
        "po_lines": [
            {"line_number": 1, "item_code": "A", "qty": 10, "unit_price": 10.0, "tax_rate": 0.07},
        ],
        "receipt_lines": [
            {"line_number": 1, "item_code": "A", "accepted_qty": 9.5},
        ],
    }

    payload = match_invoice_to_po_and_receipt(context, inputs)

    assert payload["matched_po_id"] == "PO-77"
    assert payload["matched_receipt_ids"] == ["RCPT-8"]
    mismatch_types = {entry["type"] for entry in payload["mismatches"]}
    assert {"qty", "price"}.issubset(mismatch_types)
    assert payload["recommendation"]["status"] == "hold"
    assert payload["match_score"] < 1.0


def test_match_invoice_tool_approves_when_lines_align() -> None:
    inputs = {
        "invoice_id": "INV-10",
        "po_id": "PO-88",
        "invoice_lines": [
            {"line_number": 1, "item_code": "BK", "qty": 5, "unit_price": 20.0, "tax_rate": 0.05},
        ],
        "po_lines": [
            {"line_number": 1, "item_code": "BK", "qty": 5, "unit_price": 20.0, "tax_rate": 0.05},
        ],
        "receipt_lines": [
            {"line_number": 1, "item_code": "BK", "accepted_qty": 5},
        ],
    }

    payload = match_invoice_to_po_and_receipt([], inputs)

    assert payload["matched_po_id"] == "PO-88"
    assert payload["mismatches"] == []
    assert payload["recommendation"]["status"] == "approve"
    assert payload["match_score"] == pytest.approx(1.0)


def test_resolve_invoice_mismatch_returns_actions() -> None:
    context = [
        {
            "doc_id": "invoice-match",
            "doc_version": "1",
            "chunk_id": 0,
            "snippet": "Qty variance identified on INV-55",
        }
    ]
    inputs = {
        "invoice_id": "INV-55",
        "mismatches": [
            {
                "type": "qty",
                "line_reference": "Line 1",
                "severity": "warning",
                "detail": "Received quantity is 5 units short",
                "variance": 5,
            }
        ],
        "preferred_resolution": "hold",
        "reason_codes": ["qty_warning"],
    }

    payload = resolve_invoice_mismatch(context, inputs)

    assert payload["invoice_id"] == "INV-55"
    assert payload["resolution"]["type"] in {"hold", "partial_approve", "request_credit_note", "adjust_po"}
    assert payload["actions"], "Expected at least one follow-up action"
    assert payload["impacted_lines"], "Expected impacted line entries"
    assert payload["next_steps"], "Expected next steps guidance"
    assert payload["notes"], "Expected supplemental notes"


def test_build_rfq_draft_uses_name_alias_for_title() -> None:
    payload = build_rfq_draft([], {"name": "Rotar Blades"})

    assert payload["rfq_title"] == "Rotar Blades"


def test_build_rfq_draft_extracts_structured_fields_from_query_prompt() -> None:
    prompt = (
        "Draft an RFQ for custom rotor blades, qty 500, material aluminum 7075, "
        "lead time 21 days, delivery to Dubai, include QA cert requirements."
    )

    payload = build_rfq_draft([], {"query": prompt})

    assert payload["rfq_title"] == "custom rotor blades"
    assert payload["line_items"][0]["quantity"] == 500.0
    assert "Material: aluminum 7075" in payload["line_items"][0]["description"]
    assert any("Requested lead time: 21 days" == term for term in payload["terms_and_conditions"])
    assert any("Delivery location: Dubai" == term for term in payload["terms_and_conditions"])
    assert any("QA certification" in term for term in payload["terms_and_conditions"])
    assert any("QA certification" in question for question in payload["questions_for_suppliers"])


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
