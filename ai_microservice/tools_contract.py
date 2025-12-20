"""Copilot tool contract functions for deterministic, side-effect-free actions."""
from __future__ import annotations

import importlib
import math
from contextlib import AbstractContextManager
from datetime import datetime, timezone
from typing import Any, Dict, Iterable, List, MutableMapping, Optional, Sequence, Tuple


class SideEffectBlockedError(RuntimeError):
    """Raised when a tool attempts a prohibited side-effect such as network or DB access."""


class _SideEffectGuard(AbstractContextManager[None]):
    """Context manager that temporarily blocks network, subprocess, and DB APIs."""

    _BLOCKLIST: Tuple[Tuple[str, str], ...] = (
        ("socket", "socket"),
        ("socket", "create_connection"),
        ("httpx", "Client"),
        ("httpx", "AsyncClient"),
        ("httpx", "post"),
        ("httpx", "get"),
        ("requests", "Session"),
        ("requests", "get"),
        ("requests", "post"),
        ("subprocess", "Popen"),
        ("subprocess", "run"),
        ("subprocess", "call"),
        ("subprocess", "check_output"),
        ("sqlite3", "connect"),
        ("psycopg2", "connect"),
        ("mysql.connector", "connect"),
    )

    def __init__(self, tool_name: str) -> None:
        self._tool_name = tool_name
        self._patched: List[Tuple[Any, str, Any]] = []

    def __enter__(self) -> None:
        for module_name, attr_name in self._BLOCKLIST:
            try:
                module = importlib.import_module(module_name)
            except Exception:
                continue
            if not hasattr(module, attr_name):
                continue
            original = getattr(module, attr_name)

            def _blocked_call(*args: Any, _mod: str = module_name, _attr: str = attr_name, **kwargs: Any) -> None:  # type: ignore[return-type]
                raise SideEffectBlockedError(
                    f"{self._tool_name} cannot invoke {_mod}.{_attr}; side-effects are not allowed"
                )

            setattr(module, attr_name, _blocked_call)
            self._patched.append((module, attr_name, original))

    def __exit__(self, *exc_info: Any) -> Optional[bool]:
        while self._patched:
            module, attr_name, original = self._patched.pop()
            try:
                setattr(module, attr_name, original)
            except Exception:
                pass
        return None


def build_rfq_draft(context: Sequence[MutableMapping[str, Any]], inputs: MutableMapping[str, Any]) -> Dict[str, Any]:
    """Return a structured RFQ draft payload using grounded context."""

    with _SideEffectGuard("build_rfq_draft"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        today = datetime.now(timezone.utc).date().isoformat()

        rfq_title = _text(normalized_inputs.get("rfq_title")) or _text(normalized_inputs.get("category"))
        rfq_title = rfq_title or f"RFQ Draft - {today}"
        scope_summary = _text(normalized_inputs.get("scope_summary")) or "Scope derived from retrieved documents."

        line_items = _build_line_items(normalized_inputs.get("items"), normalized_context, today)
        terms = _collect_terms(normalized_inputs, normalized_context)
        questions = _collect_questions(normalized_inputs)
        evaluation_rubric = _collect_rubric(normalized_inputs)

        return {
            "rfq_title": rfq_title,
            "scope_summary": scope_summary,
            "line_items": line_items,
            "terms_and_conditions": terms,
            "questions_for_suppliers": questions,
            "evaluation_rubric": evaluation_rubric,
        }


def build_supplier_message(context: Sequence[MutableMapping[str, Any]], inputs: MutableMapping[str, Any]) -> Dict[str, Any]:
    """Return a professional supplier message draft."""

    with _SideEffectGuard("build_supplier_message"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        supplier_name = _text(normalized_inputs.get("supplier_name")) or "Supplier Partner"
        goal = _text(normalized_inputs.get("goal")) or "optimize pricing"
        tone = (_text(normalized_inputs.get("tone")) or "professional").capitalize()
        subject = _text(normalized_inputs.get("subject")) or f"Follow-up on sourcing opportunity ({supplier_name})"
        constraints = _text(normalized_inputs.get("constraints"))
        context_note = _text(normalized_inputs.get("context"))

        citations = _format_source_labels(normalized_context, limit=3)
        citation_suffix = f" {' '.join(citations)}" if citations else ""
        paragraphs: List[str] = [
            f"Hi {supplier_name},",
            f"We are reviewing options to {goal}." + (f" {context_note}" if context_note else ""),
            "Please share any flexibility around schedule, pricing, or quality assurances so we can keep the build plan on track.",
        ]
        if constraints:
            paragraphs.append(f"Constraints to keep in mind: {constraints}.")
        paragraphs.append("Thank you for your partnership.\n\nBest regards,\nProcurement Team" + citation_suffix)
        message_body = "\n\n".join(paragraphs)

        negotiation_points = _string_list(normalized_inputs.get("negotiation_points"))
        if not negotiation_points:
            negotiation_points = [
                "Updated pricing tiers for the next build",
                "Lead-time improvements or pull-ins",
                "Quality or warranty assurances",
            ]

        fallback_options = _string_list(normalized_inputs.get("fallback_options"))
        if not fallback_options:
            fallback_options = [
                "Escalate to alternate qualified supplier",
                "Adjust production schedule to align with confirmed capacity",
            ]

        return {
            "subject": subject,
            "message_body": message_body,
            "negotiation_points": negotiation_points,
            "fallback_options": fallback_options,
        }


def build_maintenance_checklist(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a grounded maintenance checklist."""

    with _SideEffectGuard("build_maintenance_checklist"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        asset_id = _text(normalized_inputs.get("asset_id")) or "Asset"
        symptom = _text(normalized_inputs.get("symptom")) or "Unspecified symptom"
        environment = _text(normalized_inputs.get("environment")) or "standard operating conditions"
        urgency = _text(normalized_inputs.get("urgency")) or "medium"

        source_insights = _contextual_insights(normalized_context, limit=4)
        if not source_insights:
            source_insights = ["Document observations carefully; insufficient references retrieved."]

        safety_notes = _string_list(normalized_inputs.get("safety_notes"))
        if not safety_notes:
            safety_notes = [
                "Apply lockout-tagout before servicing equipment.",
                "Use PPE suitable for the described environment.",
            ]

        diagnostic_steps = _string_list(normalized_inputs.get("diagnostic_steps"))
        if not diagnostic_steps:
            diagnostic_steps = [
                f"Record the reported symptom: {symptom} on {asset_id}.",
                f"Inspect surrounding environment for {environment} stressors.",
                "Capture vibration, thermal, and electrical baselines where applicable.",
            ]
            diagnostic_steps.extend(source_insights[:2])

        likely_causes = _string_list(normalized_inputs.get("likely_causes"))
        if not likely_causes:
            likely_causes = [
                "Wear on moving assemblies",
                "Contamination or debris",
                "Control calibration drift",
            ]

        recommended_actions = _string_list(normalized_inputs.get("recommended_actions"))
        if not recommended_actions:
            recommended_actions = [
                "Clean and lubricate critical points",
                "Verify firmware/calibration baselines",
                "Schedule follow-up inspection after adjustments",
            ]

        escalation_rules = _string_list(normalized_inputs.get("when_to_escalate"))
        if not escalation_rules:
            escalation_rules = [
                f"Escalate if downtime exceeds SLA for {urgency} priority assets.",
                "Escalate if critical parameters exceed safe thresholds after remediation.",
            ]

        return {
            "safety_notes": safety_notes,
            "diagnostic_steps": diagnostic_steps,
            "likely_causes": likely_causes,
            "recommended_actions": recommended_actions,
            "when_to_escalate": escalation_rules,
        }


def run_inventory_whatif(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a deterministic inventory what-if simulation payload."""

    with _SideEffectGuard("run_inventory_whatif"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        current_policy = _mapping(normalized_inputs.get("current_policy"))
        proposed_policy = _mapping(normalized_inputs.get("proposed_policy"))
        forecast_snapshot = _resolve_forecast_snapshot(normalized_context, normalized_inputs.get("forecast_snapshot"))
        service_level_target = _clamp(float(normalized_inputs.get("service_level_target", 0.95)), 0.5, 0.999)

        demand_mean = float(forecast_snapshot.get("avg_daily_demand") or forecast_snapshot.get("mean") or 1.0)
        demand_std = float(forecast_snapshot.get("std_dev") or forecast_snapshot.get("std") or max(demand_mean * 0.15, 0.1))
        lead_time_days = float(proposed_policy.get("lead_time") or current_policy.get("lead_time") or forecast_snapshot.get("lead_time_days") or 1.0)
        holding_cost_unit = float(forecast_snapshot.get("holding_cost_per_unit") or forecast_snapshot.get("holding_cost") or 1.0)

        current_safety = float(current_policy.get("safety_stock") or 0.0)
        proposed_safety = float(proposed_policy.get("safety_stock") or current_safety)
        current_reorder_point = float(current_policy.get("reorder_point") or demand_mean * lead_time_days)
        proposed_reorder_point = float(proposed_policy.get("reorder_point") or current_reorder_point)

        lead_time_demand = demand_mean * lead_time_days
        z_value = _service_level_to_z(service_level_target)
        required_inventory = lead_time_demand + z_value * demand_std * math.sqrt(max(lead_time_days, 1.0))
        available_inventory = proposed_reorder_point + proposed_safety
        coverage_ratio = available_inventory / max(required_inventory, 0.01)
        projected_stockout_risk = _clamp(1.0 - min(coverage_ratio, 1.0), 0.0, 1.0)

        expected_stockout_days = max(0.0, (required_inventory - available_inventory) / max(demand_mean, 0.01))
        expected_stockout_days = round(min(expected_stockout_days, lead_time_days), 2)

        expected_holding_cost_change = round((proposed_safety - current_safety) * holding_cost_unit, 2)
        recommendation = _recommendation_from_risk(projected_stockout_risk)

        assumptions = [
            f"Demand mean derived from forecast snapshot ({demand_mean:.2f} units/day)",
            f"Lead time assumed at {lead_time_days:.2f} days",
            f"Service level target set to {service_level_target:.3f}",
            f"Safety stock delta of {proposed_safety - current_safety:.2f} units",
        ]

        return {
            "projected_stockout_risk": round(projected_stockout_risk, 4),
            "expected_stockout_days": expected_stockout_days,
            "expected_holding_cost_change": expected_holding_cost_change,
            "recommendation": recommendation,
            "assumptions": assumptions,
        }


def compare_quotes(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Rank supplier quotes deterministically using price, lead time, quality, and risk."""

    with _SideEffectGuard("compare_quotes"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        quotes = _normalize_quotes(normalized_inputs.get("quotes") or normalized_inputs.get("quote_options"))
        if not quotes:
            return {
                "rankings": [],
                "summary": ["No quotes provided for comparison."],
                "recommendation": "",
            }

        risk_scores = _normalize_risk_scores(normalized_inputs.get("supplier_risk_scores"))
        price_values = [quote["price"] for quote in quotes]
        lead_values = [quote["lead_time_days"] for quote in quotes]
        quality_values = [quote["quality_rating"] for quote in quotes]
        risk_values = [risk_scores.get(quote["supplier_id"], quote["risk_score"]) for quote in quotes]
        citations = _format_source_labels(normalized_context, limit=2)

        rankings: List[Dict[str, Any]] = []
        for quote in quotes:
            supplier_id = quote["supplier_id"]
            supplier_name = quote["supplier_name"]
            price = quote["price"]
            lead_time = quote["lead_time_days"]
            quality = quote["quality_rating"]
            risk = risk_scores.get(supplier_id, quote["risk_score"])

            price_component = _score_lower_better(price, price_values)
            lead_component = _score_lower_better(lead_time, lead_values)
            quality_component = _score_higher_better(quality, quality_values)
            risk_component = _score_lower_better(risk, risk_values)

            normalized_score = round(
                0.45 * price_component
                + 0.20 * lead_component
                + 0.25 * quality_component
                + 0.10 * risk_component,
                4,
            )
            total_score = round(normalized_score * 100, 2)
            notes = (
                f"{supplier_name}: ${price:,.2f}/unit, {lead_time:.1f}d lead, "
                f"quality {(quality * 100):.0f}%, risk {(risk * 100):.0f}%"
            )
            if citations:
                notes = f"{notes} ({' '.join(citations)})"
            rankings.append(
                {
                    "supplier_id": supplier_id,
                    "supplier_name": supplier_name,
                    "score": total_score,
                    "normalized_score": normalized_score,
                    "notes": notes,
                    "price": round(price, 2),
                    "lead_time_days": round(lead_time, 2),
                    "quality_rating": round(quality, 4),
                    "risk_score": round(risk, 4),
                }
            )

        rankings.sort(key=lambda item: item["normalized_score"], reverse=True)
        recommendation = rankings[0]["supplier_id"]
        summary = _build_quote_summary(rankings)
        return {
            "rankings": rankings,
            "summary": summary,
            "recommendation": recommendation,
        }


def draft_purchase_order(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a structured PO draft payload aligned to procurement workflows."""

    with _SideEffectGuard("draft_purchase_order"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        supplier_block = _mapping(
            normalized_inputs.get("selected_supplier")
            or normalized_inputs.get("supplier")
            or normalized_inputs.get("awardee")
        )
        rfq_details = _mapping(normalized_inputs.get("rfq") or normalized_inputs.get("rfq_details"))
        rfq_id = _text(normalized_inputs.get("rfq_id")) or _text(rfq_details.get("rfq_id")) or _text(rfq_details.get("id"))
        currency = _text(normalized_inputs.get("currency") or rfq_details.get("currency")) or "USD"

        supplier_id = _text(supplier_block.get("supplier_id") or supplier_block.get("id")) or "supplier"
        supplier_name = _text(supplier_block.get("name") or supplier_block.get("legal_name")) or supplier_id
        supplier_contact = _text(supplier_block.get("contact")) or _text(supplier_block.get("contact_name"))

        line_items = _build_po_line_items(
            normalized_inputs.get("line_items") or rfq_details.get("line_items"),
            currency,
            normalized_context,
        )
        delivery_schedule = _build_delivery_schedule(
            normalized_inputs.get("delivery_schedule"),
            line_items,
        )
        terms = _build_po_terms(normalized_inputs, normalized_context)
        total_value = round(sum(item["subtotal"] for item in line_items), 2)
        po_number = _text(normalized_inputs.get("po_number")) or _generate_po_number(rfq_id or supplier_id)

        return {
            "po_number": po_number,
            "rfq_id": rfq_id,
            "supplier": {
                "supplier_id": supplier_id,
                "name": supplier_name,
                "contact": supplier_contact,
            },
            "currency": currency,
            "line_items": line_items,
            "terms_and_conditions": terms,
            "delivery_schedule": delivery_schedule,
            "total_value": total_value,
            "approver_notes": normalized_inputs.get("approver_notes") or "Human approval required before release.",
        }


def _normalize_context(context: Sequence[MutableMapping[str, Any]] | None) -> List[MutableMapping[str, Any]]:
    if not context:
        return []
    normalized: List[MutableMapping[str, Any]] = []
    for block in context:
        if isinstance(block, MutableMapping):
            normalized.append(block)
    return normalized


def _text(value: Any) -> str:
    if isinstance(value, str) and value.strip():
        return value.strip()
    return ""


def _mapping(value: Any) -> Dict[str, Any]:
    if isinstance(value, MutableMapping):
        return dict(value)
    return {}


def _string_list(value: Any) -> List[str]:
    if isinstance(value, (list, tuple)):
        result: List[str] = []
        for entry in value:
            text = _text(entry)
            if text:
                result.append(text)
        return result
    text = _text(value)
    return [text] if text else []


def _collect_terms(inputs: MutableMapping[str, Any], context: List[MutableMapping[str, Any]]) -> List[str]:
    terms = _string_list(inputs.get("commercial_terms"))
    if not terms:
        terms = ["Manual review required before publishing RFQ."]
    for block in context:
        metadata = block.get("metadata") or {}
        doc_terms = metadata.get("terms") or metadata.get("commercial_terms")
        for entry in _string_list(doc_terms):
            if entry not in terms:
                terms.append(entry)
        if len(terms) >= 6:
            break
    return terms[:6]


def _collect_questions(inputs: MutableMapping[str, Any]) -> List[str]:
    questions = _string_list(inputs.get("questions_for_suppliers"))
    if not questions:
        questions = [
            "Confirm achievable lead time and MOQ",
            "Provide warranty or quality assurances",
        ]
    return questions


def _collect_rubric(inputs: MutableMapping[str, Any]) -> List[Dict[str, Any]]:
    rubric_entries: List[Dict[str, Any]] = []
    raw = inputs.get("evaluation_criteria")
    if isinstance(raw, Iterable) and not isinstance(raw, (str, bytes, dict)):
        for entry in raw:
            if not isinstance(entry, MutableMapping):
                continue
            rubric_entries.append(
                {
                    "criterion": _text(entry.get("name")) or _text(entry.get("criterion")) or "Total cost of ownership",
                    "weight": _clamp(float(entry.get("weight", 0.25)), 0.0, 1.0),
                    "guidance": _text(entry.get("guidance")) or "Compare supplier proposals against baseline contract.",
                }
            )
    if not rubric_entries:
        rubric_entries = [
            {
                "criterion": "Commercial terms",
                "weight": 0.5,
                "guidance": "Evaluate unit pricing, rebates, and payment terms.",
            },
            {
                "criterion": "Schedule reliability",
                "weight": 0.3,
                "guidance": "Review supplier ability to meet target lead times.",
            },
            {
                "criterion": "Quality & risk",
                "weight": 0.2,
                "guidance": "Assess certifications and field performance.",
            },
        ]
    return rubric_entries[:6]


def _build_line_items(
    items: Any,
    context: List[MutableMapping[str, Any]],
    today: str,
) -> List[Dict[str, Any]]:
    normalized_items: List[Dict[str, Any]] = []
    if isinstance(items, Iterable) and not isinstance(items, (str, bytes, dict)):
        for idx, item in enumerate(items):
            if not isinstance(item, MutableMapping):
                continue
            part_id = _text(item.get("part_id")) or f"TBD-{idx + 1}"
            description = _text(item.get("description")) or "Pending description"
            quantity = _clamp(float(item.get("qty") or item.get("quantity") or 1.0), 0.01, 9_999_999)
            target_date = _text(item.get("target_date")) or today
            citations = _nearest_citation_ids(context, limit=2, offset=idx)
            normalized_items.append(
                {
                    "part_id": part_id,
                    "description": description,
                    "quantity": quantity,
                    "target_date": target_date,
                    "source_citation_ids": citations,
                }
            )
    if not normalized_items:
        normalized_items.append(
            {
                "part_id": "TBD-1",
                "description": "Pending sourcing inputs",
                "quantity": 1.0,
                "target_date": today,
                "source_citation_ids": _nearest_citation_ids(context, limit=2, offset=0),
            }
        )
    return normalized_items[:20]


def _contextual_insights(context: List[MutableMapping[str, Any]], limit: int) -> List[str]:
    insights: List[str] = []
    for block in context:
        snippet = _text(block.get("snippet"))
        if snippet:
            label = _format_source_label(block)
            insights.append(f"{snippet} ({label})")
        if len(insights) >= limit:
            break
    return insights


def _format_source_labels(context: List[MutableMapping[str, Any]], limit: int) -> List[str]:
    labels: List[str] = []
    for block in context[:limit]:
        label = _format_source_label(block)
        if label:
            labels.append(f"[{label}]")
    return labels


def _nearest_citation_ids(
    context: List[MutableMapping[str, Any]],
    *,
    limit: int,
    offset: int,
) -> List[str]:
    ids: List[str] = []
    for block in context[offset: offset + limit]:
        label = _format_source_label(block)
        if label:
            ids.append(label)
    return ids


def _format_source_label(block: MutableMapping[str, Any]) -> str:
    doc_id = _text(block.get("doc_id"))
    chunk_id = block.get("chunk_id")
    doc_version = _text(block.get("doc_version"))
    if not doc_id:
        return ""
    chunk_suffix = f"#c{chunk_id}" if isinstance(chunk_id, (int, float)) else ""
    version_suffix = f"@{doc_version}" if doc_version else ""
    return f"doc:{doc_id}{version_suffix}{chunk_suffix}"


def _resolve_forecast_snapshot(
    context: List[MutableMapping[str, Any]],
    snapshot: Any,
) -> Dict[str, Any]:
    if isinstance(snapshot, MutableMapping):
        return dict(snapshot)
    for block in context:
        metadata = block.get("metadata") or {}
        forecast = metadata.get("forecast_snapshot") or metadata.get("inventory_forecast")
        if isinstance(forecast, MutableMapping):
            return dict(forecast)
    return {
        "avg_daily_demand": 1.0,
        "std_dev": 0.2,
        "lead_time_days": 1.0,
        "holding_cost_per_unit": 1.0,
    }


def _service_level_to_z(service_level: float) -> float:
    # Approximate z-score mapping for common service levels without scipy dependency
    table = {
        0.50: 0.0,
        0.68: 1.0,
        0.80: 1.28,
        0.90: 1.64,
        0.95: 1.96,
        0.975: 2.24,
        0.99: 2.58,
        0.995: 2.81,
        0.999: 3.29,
    }
    keys = sorted(table.keys())
    for idx, key in enumerate(keys):
        if service_level <= key:
            return table[key]
        if idx < len(keys) - 1 and service_level < keys[idx + 1]:
            low = keys[idx]
            high = keys[idx + 1]
            fraction = (service_level - low) / (high - low)
            return table[low] + fraction * (table[high] - table[low])
    return table[keys[-1]]


def _recommendation_from_risk(risk: float) -> str:
    if risk <= 0.15:
        return "Proposed policy meets the target service level."
    if risk <= 0.4:
        return "Increase safety stock or expedite replenishment to reduce stockout exposure."
    return "Stockout risk high; revisit reorder point, expedite supply, or adjust demand plan."


def _clamp(value: float, minimum: float, maximum: float) -> float:
    if math.isnan(value):
        return minimum
    if value < minimum:
        return minimum
    if value > maximum:
        return maximum
    return value


def _normalize_quotes(raw_quotes: Any) -> List[Dict[str, Any]]:
    normalized: List[Dict[str, Any]] = []
    if isinstance(raw_quotes, Iterable) and not isinstance(raw_quotes, (str, bytes, dict)):
        for idx, entry in enumerate(raw_quotes):
            if not isinstance(entry, MutableMapping):
                continue
            supplier_id = _text(entry.get("supplier_id") or entry.get("supplier") or entry.get("id")) or f"supplier-{idx + 1}"
            supplier_name = _text(entry.get("supplier_name") or entry.get("name")) or supplier_id
            price = _safe_float(entry.get("price") or entry.get("unit_price"), default=0.0)
            lead_time = _safe_float(entry.get("lead_time_days") or entry.get("lead_time") or entry.get("lead_time_weeks"), default=0.0)
            quality_rating = _safe_float(entry.get("quality_rating") or entry.get("quality_score"), default=0.7)
            quality_rating = quality_rating / 5.0 if quality_rating > 1 else quality_rating
            quality_rating = _clamp(quality_rating, 0.0, 1.0)
            risk_score = _safe_float(entry.get("risk_score") or entry.get("supplier_risk"), default=0.3)
            risk_score = risk_score / 100.0 if risk_score > 1 else risk_score
            risk_score = _clamp(risk_score, 0.0, 1.0)

            normalized.append(
                {
                    "supplier_id": supplier_id,
                    "supplier_name": supplier_name,
                    "price": max(price, 0.0),
                    "lead_time_days": max(lead_time, 0.0),
                    "quality_rating": quality_rating,
                    "risk_score": risk_score,
                }
            )
    return normalized


def _normalize_risk_scores(raw_scores: Any) -> Dict[str, float]:
    result: Dict[str, float] = {}
    if isinstance(raw_scores, MutableMapping):
        for supplier_id, score in raw_scores.items():
            value = _safe_float(score, default=0.3)
            value = value / 100.0 if value > 1 else value
            result[_text(supplier_id) or str(supplier_id)] = _clamp(value, 0.0, 1.0)
    return result


def _score_lower_better(value: float, values: List[float]) -> float:
    finite_values = [val for val in values if not math.isnan(val)]
    if not finite_values:
        return 1.0
    min_value = min(finite_values)
    max_value = max(finite_values)
    if max_value == min_value:
        return 1.0
    return 1.0 - (value - min_value) / (max_value - min_value)


def _score_higher_better(value: float, values: List[float]) -> float:
    finite_values = [val for val in values if not math.isnan(val)]
    if not finite_values:
        return 1.0
    min_value = min(finite_values)
    max_value = max(finite_values)
    if max_value == min_value:
        return 1.0
    return (value - min_value) / (max_value - min_value)


def _build_quote_summary(rankings: List[Dict[str, Any]]) -> List[str]:
    summary: List[str] = []
    if not rankings:
        return ["No quote rankings computed."]

    top = rankings[0]
    summary.append(
        f"Recommend {top['supplier_name']} (score {top['score']:.1f}) based on cost, lead time, and quality mix."
    )
    if len(rankings) > 1:
        runner = rankings[1]
        delta = round(top["score"] - runner["score"], 2)
        summary.append(
            f"{runner['supplier_name']} trails by {delta:.2f} points; consider if alternate schedule flexibility is needed."
        )
    lowest_price = min(rankings, key=lambda item: item.get("price", float("inf")))
    summary.append(
        f"Lowest unit cost from {lowest_price.get('supplier_name')} at ${lowest_price.get('price', 0):,.2f}/unit; confirm risk and quality trade-offs."
    )
    return summary


def _build_po_line_items(
    raw_items: Any,
    currency: str,
    context: List[MutableMapping[str, Any]],
) -> List[Dict[str, Any]]:
    line_items: List[Dict[str, Any]] = []
    if isinstance(raw_items, MutableMapping):
        iterable: Iterable[Any] = raw_items.values()
    elif isinstance(raw_items, Iterable) and not isinstance(raw_items, (str, bytes)):
        iterable = raw_items
    else:
        iterable = []

    for idx, entry in enumerate(iterable):
            if not isinstance(entry, MutableMapping):
                continue
            quantity = _clamp(_safe_float(entry.get("quantity") or entry.get("qty"), default=1.0), 0.01, 9_999_999)
            unit_price = _safe_float(entry.get("unit_price") or entry.get("price"), default=1.0)
            delivery_date = _text(entry.get("delivery_date") or entry.get("need_by") or entry.get("target_date"))
            line_items.append(
                {
                    "line_number": idx + 1,
                    "item_code": _text(entry.get("item_code") or entry.get("part_id")) or f"ITEM-{idx + 1}",
                    "description": _text(entry.get("description")) or "Pending description",
                    "quantity": quantity,
                    "unit_price": round(max(unit_price, 0.0), 2),
                    "currency": currency,
                    "subtotal": round(quantity * max(unit_price, 0.0), 2),
                    "delivery_date": delivery_date or datetime.now(timezone.utc).date().isoformat(),
                    "notes": _text(entry.get("notes")) or "Based on awarded RFQ line.",
                }
            )

    if not line_items:
        citation = _format_source_labels(context, limit=1)
        line_items.append(
            {
                "line_number": 1,
                "item_code": "TBD-1",
                "description": "Awaiting finalized award details",
                "quantity": 1.0,
                "unit_price": 1.0,
                "currency": currency,
                "subtotal": 1.0,
                "delivery_date": datetime.now(timezone.utc).date().isoformat(),
                "notes": "Placeholder generated from workflow context" + (f" ({citation[0]})" if citation else ""),
            }
        )
    return line_items[:25]


def _build_delivery_schedule(
    raw_schedule: Any,
    line_items: List[Dict[str, Any]],
) -> List[Dict[str, Any]]:
    schedule: List[Dict[str, Any]] = []
    if isinstance(raw_schedule, Iterable) and not isinstance(raw_schedule, (str, bytes, dict)):
        iterable = raw_schedule
    elif isinstance(raw_schedule, MutableMapping):
        iterable = raw_schedule.values()
    else:
        iterable = []

    for entry in iterable:
        if not isinstance(entry, MutableMapping):
            continue
        schedule.append(
            {
                "milestone": _text(entry.get("milestone")) or _text(entry.get("name")) or "Delivery",
                "date": _text(entry.get("date")) or _text(entry.get("delivery_date")) or datetime.now(timezone.utc).date().isoformat(),
                "quantity": _clamp(_safe_float(entry.get("quantity") or entry.get("qty"), default=0.0), 0.0, 9_999_999),
                "notes": _text(entry.get("notes")) or "Align with logistics plan.",
            }
        )

    if not schedule:
        for item in line_items:
            schedule.append(
                {
                    "milestone": f"Deliver line {item['line_number']}",
                    "date": item["delivery_date"],
                    "quantity": item["quantity"],
                    "notes": f"Matches item_code {item['item_code']}",
                }
            )
    return schedule[:25]


def _build_po_terms(
    inputs: MutableMapping[str, Any],
    context: List[MutableMapping[str, Any]],
) -> List[str]:
    explicit_terms = _string_list(inputs.get("terms_and_conditions"))
    negotiated_terms = _string_list(inputs.get("negotiated_terms"))
    terms = explicit_terms or negotiated_terms
    if not terms:
        terms = [
            "PO remains draft until digitally approved.",
            "Payment terms Net 30 unless superseded by master agreement.",
        ]
    citations = _format_source_labels(context, limit=2)
    if citations:
        terms.append(f"Context references: {' '.join(citations)}")
    return terms[:15]


def _generate_po_number(seed: str) -> str:
    timestamp = datetime.now(timezone.utc).strftime("%Y%m%d")
    normalized_seed = (seed or "AI").strip().upper().replace(" ", "-")
    return f"PO-DRAFT-{normalized_seed}-{timestamp}"


def _safe_float(value: Any, *, default: float = 0.0) -> float:
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


__all__ = [
    "SideEffectBlockedError",
    "build_rfq_draft",
    "build_supplier_message",
    "build_maintenance_checklist",
    "run_inventory_whatif",
    "compare_quotes",
    "draft_purchase_order",
]
