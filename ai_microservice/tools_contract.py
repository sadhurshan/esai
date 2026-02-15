"""Copilot tool contract functions for deterministic, side-effect-free actions."""
from __future__ import annotations

import importlib
import math
import os
import re
from contextlib import AbstractContextManager
from datetime import datetime, timedelta, timezone, date
from functools import lru_cache
from pathlib import Path
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


REPO_ROOT = Path(__file__).resolve().parents[1]
HELP_DOC_BASE_URL = os.getenv("AI_HELP_BASE_URL", "https://docs.elements-supply.ai")
HELP_DEFAULT_LOCALE = "en"
HELP_DOC_SOURCES: Tuple[tuple[str, Path], ...] = (
    ("user_guide", REPO_ROOT / "docs" / "USER_GUIDE.md"),
)

HELP_TRANSLATIONS: Dict[str, Dict[str, Dict[str, Any]]] = {
    "es": {
        "user_guide::draft-an-rfq-with-copilot": {
            "title": "Redactar una RFQ con Copilot",
            "summary": "Crea la solicitud manualmente cuando el asistente no este disponible.",
            "steps": [
                "Abre Compras -> RFQs y selecciona Nueva RFQ.",
                "Adjunta planos, listas de materiales y notas que Copilot usó en el chat.",
                "Completa alcance, cronograma y rubrica para mantener la trazabilidad.",
                "Agrega proveedores y fechas límite antes de publicar la solicitud.",
            ],
            "cta_label": "Abrir guía de RFQ",
        },
        "user_guide::compare-supplier-quotes": {
            "title": "Comparar cotizaciones de proveedores",
            "summary": "Evalua precio, tiempo de entrega y calidad cuando el comparador no responda.",
            "steps": [
                "Abre Abastecimiento → Cotizaciones y filtra el RFQ correspondiente.",
                "Ordena la vista por precio, plazo y calificaciones de calidad.",
                "Aplica los pesos de la rúbrica y documenta la justificación en el registro de actividad.",
            ],
            "cta_label": "Revisar cotizaciones",
        },
        "user_guide::issue-a-purchase-order": {
            "title": "Emitir una orden de compra",
            "summary": "Convierte la recomendacion de Copilot en una orden aprobada.",
            "steps": [
                "Desde la adjudicacion del RFQ selecciona Crear PO o ve a Ordenes -> Purchase Orders -> New PO.",
                "Elige el proveedor adjudicado e importa las líneas del RFQ o de la cotización.",
                "Revisa moneda, terminos y calendario de entregas antes de enviar a aprobacion.",
            ],
            "cta_label": "Abrir guía de PO",
        },
    }
}


def build_rfq_draft(context: Sequence[MutableMapping[str, Any]], inputs: MutableMapping[str, Any]) -> Dict[str, Any]:
    """Return a structured RFQ draft payload using grounded context."""

    with _SideEffectGuard("build_rfq_draft"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        today = datetime.now(timezone.utc).date().isoformat()
        inferred = _extract_rfq_prompt_fields(normalized_inputs)

        rfq_title = None
        for key in ("rfq_title", "rfq_name", "name", "title", "category"):
            candidate = _text(normalized_inputs.get(key))
            if candidate:
                rfq_title = candidate
                break
        if not rfq_title:
            rfq_title = inferred.get("title")
        if not rfq_title:
            rfq_title = f"RFQ Draft - {today}"
        scope_summary = _text(normalized_inputs.get("scope_summary"))
        if not scope_summary:
            scope_summary = inferred.get("scope_summary") or "Scope derived from retrieved documents."

        line_items = _build_line_items(normalized_inputs.get("items"), normalized_context, today)
        if not normalized_inputs.get("items"):
            line_items = _build_inferred_line_items(inferred, today, line_items)

        terms = _collect_terms(normalized_inputs, normalized_context)
        terms = _merge_unique(
            terms,
            _build_inferred_terms(inferred),
            limit=6,
        )

        questions = _collect_questions(normalized_inputs)
        questions = _merge_unique(
            questions,
            _build_inferred_questions(inferred),
            limit=6,
        )

        evaluation_rubric = _collect_rubric(normalized_inputs)

        return {
            "rfq_title": rfq_title,
            "scope_summary": scope_summary,
            "line_items": line_items,
            "terms_and_conditions": terms,
            "questions_for_suppliers": questions,
            "evaluation_rubric": evaluation_rubric,
        }


def _extract_rfq_prompt_fields(inputs: MutableMapping[str, Any]) -> Dict[str, Any]:
    raw_prompt = ""
    for key in ("query", "prompt", "user_query", "request"):
        candidate = _text(inputs.get(key))
        if candidate:
            raw_prompt = candidate
            break

    if not raw_prompt:
        return {}

    normalized = raw_prompt.strip()
    lowered = normalized.lower()
    fields: Dict[str, Any] = {}

    title_match = re.search(
        r"\b(?:draft|create|prepare|start)\s+(?:an?\s+)?rfq\s+for\s+(.+?)(?:,|;|$)",
        normalized,
        re.IGNORECASE,
    )
    if title_match:
        title_value = _clean_phrase(title_match.group(1))
        if title_value:
            fields["title"] = title_value[:140]

    quantity_match = re.search(r"\b(?:qty|quantity)\s*[:=]?\s*(\d{1,9})\b", lowered)
    if not quantity_match:
        quantity_match = re.search(r"\b(\d{1,9})\s*(?:pcs|pieces|units|ea)\b", lowered)
    if quantity_match:
        try:
            qty = int(quantity_match.group(1))
            if qty > 0:
                fields["quantity"] = qty
        except (TypeError, ValueError):
            pass

    material_match = re.search(r"\bmaterial\s+(.+?)(?:,|;|$)", normalized, re.IGNORECASE)
    if material_match:
        material_value = _clean_phrase(material_match.group(1))
        if material_value:
            fields["material"] = material_value[:80]

    lead_match = re.search(r"\blead\s*time\s*(\d{1,3})\s*(day|days|week|weeks)\b", lowered)
    if lead_match:
        try:
            amount = int(lead_match.group(1))
            unit = lead_match.group(2)
            fields["lead_time_days"] = amount * 7 if unit.startswith("week") else amount
        except (TypeError, ValueError):
            pass

    delivery_match = re.search(r"\bdelivery\s+(?:to\s+)?(.+?)(?:,|;|$)", normalized, re.IGNORECASE)
    if delivery_match:
        delivery_value = _clean_phrase(delivery_match.group(1))
        if delivery_value:
            fields["delivery_location"] = delivery_value[:120]

    qa_required = bool(
        re.search(r"\bqa\s*cert(?:ificate)?\b", lowered)
        or re.search(r"\bquality\s*cert(?:ificate)?\b", lowered)
        or re.search(r"\bcert(?:ification|ifications|ificates?)\b", lowered)
    )
    if qa_required:
        fields["qa_cert_required"] = True

    if fields:
        title = fields.get("title")
        material = fields.get("material")
        delivery = fields.get("delivery_location")
        fragments = [
            f"RFQ drafted from request: {title}." if isinstance(title, str) and title else "RFQ drafted from user request.",
            f"Material target: {material}." if isinstance(material, str) and material else "",
            f"Delivery location: {delivery}." if isinstance(delivery, str) and delivery else "",
        ]
        fields["scope_summary"] = " ".join(fragment for fragment in fragments if fragment).strip()

    return fields


def _build_inferred_line_items(inferred: Dict[str, Any], today: str, fallback: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    title = _text(inferred.get("title")) or "Pending sourcing inputs"
    material = _text(inferred.get("material"))
    quantity = inferred.get("quantity")
    lead_time_days = inferred.get("lead_time_days")

    target_date = today
    try:
        if isinstance(lead_time_days, int) and lead_time_days > 0:
            target_date = (datetime.now(timezone.utc).date() + timedelta(days=lead_time_days)).isoformat()
    except Exception:
        target_date = today

    description = title
    if material:
        description = f"{description} | Material: {material}"

    qty_value = float(quantity) if isinstance(quantity, int) and quantity > 0 else 1.0

    inferred_item = {
        "part_id": "TBD-1",
        "description": description,
        "quantity": qty_value,
        "target_date": target_date,
        "source_citation_ids": [],
    }

    if fallback and fallback[0].get("description") != "Pending sourcing inputs":
        return fallback[:20]

    return [inferred_item]


def _build_inferred_terms(inferred: Dict[str, Any]) -> List[str]:
    terms: List[str] = []
    lead_time_days = inferred.get("lead_time_days")
    delivery_location = _text(inferred.get("delivery_location"))

    if isinstance(lead_time_days, int) and lead_time_days > 0:
        terms.append(f"Requested lead time: {lead_time_days} days")
    if delivery_location:
        terms.append(f"Delivery location: {delivery_location}")
    if bool(inferred.get("qa_cert_required")):
        terms.append("Supplier must provide QA certification documents with quote")

    return terms


def _build_inferred_questions(inferred: Dict[str, Any]) -> List[str]:
    if not bool(inferred.get("qa_cert_required")):
        return []
    return [
        "Please attach current QA certification(s) and expiry dates.",
    ]


def _merge_unique(existing: List[str], additions: List[str], *, limit: int) -> List[str]:
    merged: List[str] = []
    for entry in [*existing, *additions]:
        value = _text(entry)
        if not value:
            continue
        if value in merged:
            continue
        merged.append(value)
        if len(merged) >= limit:
            break
    return merged


def _clean_phrase(value: str) -> str:
    cleaned = re.sub(r"\s+", " ", value).strip(" .,:;\t\n\r")
    return cleaned


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


def forecast_spend(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a projected spend forecast using historical context data."""

    with _SideEffectGuard("forecast_spend"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        category = _text(normalized_inputs.get("category")) or "General"
        past_period_days = int(max(1, _safe_float(normalized_inputs.get("past_period_days"), default=90.0)))
        projected_period_days = int(max(1, _safe_float(normalized_inputs.get("projected_period_days"), default=30.0)))

        history = _extract_numeric_series(normalized_context)
        if not history:
            fallback_total = _safe_float(normalized_inputs.get("historical_total"), default=25000.0)
            history = [fallback_total / max(float(past_period_days), 1.0)]

        avg_daily_spend = max(_series_mean(history), 0.0)
        projected_total = round(avg_daily_spend * projected_period_days, 2)
        volatility = _series_stddev(history)
        if volatility <= 0.0:
            volatility = max(avg_daily_spend * 0.1, 1.0)
        margin = volatility * math.sqrt(projected_period_days / max(past_period_days, 1))
        confidence_interval = _build_confidence_interval(projected_total, margin, minimum=0.0)

        drivers = _string_list(normalized_inputs.get("drivers"))
        if not drivers:
            drivers = _contextual_insights(normalized_context, limit=3)
        if not drivers:
            drivers = [
                f"Average daily spend estimated at ${avg_daily_spend:,.2f}.",
                f"Projection window: {projected_period_days} day(s).",
            ]

        return {
            "category": category,
            "past_period_days": past_period_days,
            "projected_period_days": projected_period_days,
            "projected_total": projected_total,
            "confidence_interval": confidence_interval,
            "drivers": drivers[:5],
        }


def forecast_supplier_performance(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a deterministic supplier performance projection."""

    with _SideEffectGuard("forecast_supplier_performance"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        supplier_id = _text(normalized_inputs.get("supplier_id")) or "supplier"
        metric = _text(normalized_inputs.get("metric")) or "on-time delivery"
        period_days = int(max(1, _safe_float(normalized_inputs.get("period_days"), default=30.0)))

        history = _extract_numeric_series(normalized_context)
        if not history:
            history = [_safe_float(normalized_inputs.get("historical_metric"), default=0.9)]

        projection = _clamp(_series_mean(history), 0.0, 1.0)
        variability = _series_stddev(history)
        if variability <= 0.0:
            variability = 0.05
        confidence_interval = _build_confidence_interval(
            projection,
            variability,
            minimum=0.0,
            maximum=1.0,
        )

        return {
            "supplier_id": supplier_id,
            "metric": metric,
            "period_days": period_days,
            "projection": round(projection, 4),
            "confidence_interval": confidence_interval,
        }


def forecast_inventory(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return an inventory usage forecast with reorder guidance."""

    with _SideEffectGuard("forecast_inventory"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        item_id = _text(normalized_inputs.get("item_id")) or "item"
        period_days = int(max(1, _safe_float(normalized_inputs.get("period_days"), default=30.0)))
        lead_time_days = int(max(1, _safe_float(normalized_inputs.get("lead_time_days"), default=14.0)))

        history = _extract_numeric_series(normalized_context)
        if not history:
            history = [_safe_float(normalized_inputs.get("avg_daily_usage"), default=25.0)]

        avg_daily_usage = max(_series_mean(history), 0.0)
        expected_usage = round(avg_daily_usage * period_days, 2)
        safety_stock = round(max(avg_daily_usage * math.sqrt(float(lead_time_days)), 0.0), 2)

        reorder_date_input = _text(normalized_inputs.get("expected_reorder_date"))
        reorder_date = _parse_iso_date(reorder_date_input)
        if reorder_date is None:
            reorder_date = datetime.now(timezone.utc).date() + timedelta(days=max(lead_time_days // 2, 1))

        return {
            "item_id": item_id,
            "period_days": period_days,
            "expected_usage": expected_usage,
            "expected_reorder_date": reorder_date.isoformat(),
            "safety_stock": safety_stock,
        }


def get_help(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a deterministic help guide sourced from local documentation."""

    with _SideEffectGuard("get_help"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        topic = (
            _text(normalized_inputs.get("topic"))
            or _text(normalized_inputs.get("query"))
            or _text(normalized_inputs.get("action"))
        )
        topic = topic or "copilot workspace basics"

        locale = _normalize_locale(_text(normalized_inputs.get("locale")))
        section = _select_help_section(topic)
        localized_section = _localize_help_section(section, locale)
        guidance_steps = localized_section.get("steps") or []
        if not guidance_steps:
            guidance_steps = _fallback_help_steps(topic)

        description = localized_section.get("summary") or f"Follow these steps to {topic}."
        references = _format_source_labels(normalized_context, limit=2)
        if localized_section.get("reference"):
            references.append(localized_section["reference"])
        cta_label = localized_section.get("cta_label") or "Open help center"
        cta_url = localized_section.get("url")
        available_locales = localized_section.get("available_locales") or _available_help_locales(
            localized_section.get("slug")
        )

        return {
            "topic": topic,
            "title": localized_section.get("title") or "Copilot help",
            "description": description,
            "steps": guidance_steps[:8],
            "cta_label": cta_label,
            "cta_url": cta_url,
            "source": localized_section.get("source"),
            "references": references[:5],
            "locale": localized_section.get("locale", HELP_DEFAULT_LOCALE),
            "available_locales": available_locales,
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


def build_invoice_draft(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a deterministic invoice draft derived from a purchase order."""

    with _SideEffectGuard("build_invoice_draft"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        po_id = _text(normalized_inputs.get("po_id"))
        if not po_id:
            for block in normalized_context:
                metadata = block.get("metadata") or {}
                candidate = metadata.get("po_id") or metadata.get("purchase_order_id") or metadata.get("po_number")
                po_id = _text(candidate)
                if po_id:
                    break
        if not po_id:
            po_id = "PO-TBD"

        invoice_date_obj = _parse_iso_date(_text(normalized_inputs.get("invoice_date")))
        if invoice_date_obj is None:
            invoice_date_obj = datetime.now(timezone.utc).date()
        invoice_date = invoice_date_obj.isoformat()

        due_date_obj = _parse_iso_date(_text(normalized_inputs.get("due_date")))
        if due_date_obj is None:
            payment_term_days = int(max(_safe_float(normalized_inputs.get("payment_terms_days") or normalized_inputs.get("due_in_days"), default=30.0), 1.0))
            due_date_obj = invoice_date_obj + timedelta(days=payment_term_days)
        due_date = due_date_obj.isoformat()

        line_items = _build_invoice_line_items(normalized_inputs.get("line_items"), normalized_context)

        notes = _text(normalized_inputs.get("notes"))
        if not notes:
            citations = _format_source_labels(normalized_context, limit=2)
            notes = "Draft invoice generated from workflow context."
            if citations:
                notes = f"{notes} Sources: {' '.join(citations)}"

        return {
            "po_id": po_id,
            "invoice_date": invoice_date,
            "due_date": due_date,
            "line_items": line_items,
            "notes": notes,
        }


def build_item_draft(context: Sequence[MutableMapping[str, Any]], inputs: MutableMapping[str, Any]) -> Dict[str, Any]:
    """Draft an inventory item definition with specs and preferred suppliers."""

    with _SideEffectGuard("build_item_draft"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        today = datetime.now(timezone.utc).strftime("%Y%m%d")

        item_code = (
            _text(normalized_inputs.get("item_code"))
            or _text(normalized_inputs.get("sku"))
            or _infer_from_context(normalized_context, ("item_code", "part_number", "sku"))
            or f"ITEM-{today}"
        )

        name = (
            _text(normalized_inputs.get("name"))
            or _infer_from_context(normalized_context, ("name", "item_name", "title", "part_name"))
            or item_code
        )

        uom = (
            _text(normalized_inputs.get("uom"))
            or _infer_from_context(normalized_context, ("uom", "unit", "unit_of_measure"))
            or "ea"
        )

        category = _text(normalized_inputs.get("category")) or _infer_from_context(
            normalized_context,
            ("category", "family", "commodity"),
        )

        description = _text(normalized_inputs.get("description")) or _item_description_from_context(
            normalized_context,
            fallback=name,
        )

        spec = _text(normalized_inputs.get("spec")) or _infer_from_context(
            normalized_context,
            ("spec", "standard", "drawing", "revision"),
        )

        status = _normalize_item_status(_text(normalized_inputs.get("status")))
        attributes = _build_item_attributes(normalized_inputs.get("attributes"), normalized_context)
        preferred_suppliers = _build_preferred_suppliers(normalized_inputs, normalized_context)

        return {
            "item_code": item_code,
            "name": name,
            "uom": uom,
            "status": status,
            "category": category or None,
            "description": description or None,
            "spec": spec or None,
            "attributes": attributes,
            "preferred_suppliers": preferred_suppliers,
        }


def build_supplier_onboard_draft(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Draft a supplier onboarding payload with document requirements."""

    with _SideEffectGuard("build_supplier_onboard_draft"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        today = datetime.now(timezone.utc).strftime("%Y%m%d")

        legal_name = (
            _text(normalized_inputs.get("legal_name"))
            or _infer_from_context(normalized_context, ("legal_name", "supplier_name", "company"))
            or f"Supplier Prospect {today}"
        )

        email = (
            _text(normalized_inputs.get("email"))
            or _infer_from_context(normalized_context, ("email", "contact_email"))
        )
        if not email:
            email = f"{_slugify_identifier(legal_name)}@prospect.suppliers.local"

        phone = (
            _text(normalized_inputs.get("phone"))
            or _infer_from_context(normalized_context, ("phone", "contact_phone"))
            or "+1-555-0100"
        )

        country = (
            _text(normalized_inputs.get("country"))
            or _infer_from_context(normalized_context, ("country", "country_code"))
            or "US"
        ).upper()[:64]

        payment_terms = _text(normalized_inputs.get("payment_terms")) or "Net 30"
        tax_id = _text(normalized_inputs.get("tax_id")) or f"TAX-{today}"

        website = _text(normalized_inputs.get("website")) or _infer_from_context(
            normalized_context,
            ("website", "url", "site"),
        )
        address = _text(normalized_inputs.get("address")) or _infer_from_context(
            normalized_context,
            ("address", "location", "hq"),
        )

        documents_needed = _build_document_requirements(
            normalized_inputs.get("documents_needed"),
            normalized_context,
        )
        notes = _text(normalized_inputs.get("notes"))

        return {
            "legal_name": legal_name,
            "country": country,
            "email": email,
            "phone": phone,
            "payment_terms": payment_terms,
            "tax_id": tax_id,
            "website": website or None,
            "address": address or None,
            "documents_needed": documents_needed,
            "notes": notes or None,
        }


def build_receipt_draft(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a deterministic goods receipt draft tied to a purchase order."""

    with _SideEffectGuard("build_receipt_draft"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}

        po_id = _text(normalized_inputs.get("po_id"))
        if not po_id:
            for block in normalized_context:
                metadata = block.get("metadata") or {}
                candidate = metadata.get("po_id") or metadata.get("purchase_order_id") or metadata.get("po_number")
                po_id = _text(candidate)
                if po_id:
                    break
        if not po_id:
            po_id = "PO-TBD"

        received_date_obj = _parse_iso_date(_text(normalized_inputs.get("received_date")))
        if received_date_obj is None:
            received_date_obj = datetime.now(timezone.utc).date()
        received_date = received_date_obj.isoformat()

        line_items = _build_receipt_line_items(normalized_inputs.get("line_items"), normalized_context)
        total_received_qty = round(sum(float(item.get("received_qty", 0.0)) for item in line_items), 4)

        inspector = _text(
            normalized_inputs.get("inspected_by")
            or normalized_inputs.get("receiver_name")
            or normalized_inputs.get("inspector")
        ) or "Receiving Team"

        reference = _text(
            normalized_inputs.get("reference")
            or normalized_inputs.get("asn")
            or normalized_inputs.get("shipment_reference")
        )
        if not reference:
            citations = _format_source_labels(normalized_context, limit=1)
            reference = citations[0] if citations else f"{po_id}-RECEIPT"

        status = _text(normalized_inputs.get("status")) or "draft"

        notes = _text(normalized_inputs.get("notes"))
        if not notes:
            insights = _contextual_insights(normalized_context, limit=1)
            notes = insights[0] if insights else "Draft goods receipt generated from workflow context."

        return {
            "po_id": po_id,
            "received_date": received_date,
            "reference": reference,
            "inspected_by": inspector,
            "status": status,
            "total_received_qty": total_received_qty,
            "line_items": line_items,
            "notes": notes,
        }


def build_payment_draft(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a deterministic payment draft aligned to AP workflows."""

    with _SideEffectGuard("build_payment_draft"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}

        invoice_id = _text(normalized_inputs.get("invoice_id") or normalized_inputs.get("id"))
        if not invoice_id:
            for block in normalized_context:
                metadata = block.get("metadata") or {}
                candidate = metadata.get("invoice_id") or metadata.get("invoice_number")
                invoice_id = _text(candidate)
                if invoice_id:
                    break
        if not invoice_id:
            invoice_id = "INV-TBD"

        amount = _safe_float(
            normalized_inputs.get("amount")
            or normalized_inputs.get("open_balance")
            or normalized_inputs.get("total")
            or normalized_inputs.get("grand_total"),
            default=0.0,
        )
        amount = round(max(amount, 0.01), 2)

        currency = (_text(normalized_inputs.get("currency")) or "USD").upper()
        payment_method = _text(normalized_inputs.get("payment_method") or normalized_inputs.get("method")) or "ach"

        scheduled_date_obj = _parse_iso_date(
            _text(normalized_inputs.get("scheduled_date") or normalized_inputs.get("payment_date"))
        )
        if scheduled_date_obj is None:
            scheduled_date_obj = datetime.now(timezone.utc).date()
        scheduled_date = scheduled_date_obj.isoformat()

        reference = _text(normalized_inputs.get("reference") or normalized_inputs.get("payment_reference"))
        if not reference:
            reference = f"{invoice_id}-{scheduled_date}"

        notes = _text(normalized_inputs.get("notes"))
        if not notes:
            insights = _contextual_insights(normalized_context, limit=1)
            notes = insights[0] if insights else "Draft payment generated from workflow context."

        return {
            "invoice_id": invoice_id,
            "amount": amount,
            "currency": currency,
            "payment_method": payment_method,
            "scheduled_date": scheduled_date,
            "reference": reference,
            "notes": notes,
        }


def match_invoice_to_po_and_receipt(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a deterministic three-way match summary for AP approval."""

    with _SideEffectGuard("match_invoice_to_po_and_receipt"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}

        invoice_id = _text(normalized_inputs.get("invoice_id") or normalized_inputs.get("id"))
        if not invoice_id:
            invoice_id = _infer_from_context(normalized_context, ("invoice_id", "invoice_number")) or "invoice"

        po_id = _text(normalized_inputs.get("po_id") or normalized_inputs.get("purchase_order_id"))
        if not po_id:
            po_id = _infer_from_context(normalized_context, ("po_id", "purchase_order_id", "po_number")) or "PO-TBD"

        receipt_ids = _string_list(normalized_inputs.get("receipt_ids"))
        if not receipt_ids:
            receipt_ids = _infer_receipt_ids_from_context(normalized_context)

        invoice_lines = _normalize_match_lines(
            normalized_inputs.get("invoice_lines") or normalized_inputs.get("lines"),
            default_prefix="INV",
            quantity_keys=("qty", "quantity", "billed_qty"),
            price_keys=("unit_price", "price", "unit_cost"),
            tax_keys=("tax_rate", "tax"),
        )
        po_lines = _normalize_match_lines(
            normalized_inputs.get("po_lines") or normalized_inputs.get("purchase_order_lines"),
            default_prefix="PO",
            quantity_keys=("qty", "quantity", "ordered_qty"),
            price_keys=("unit_price", "price", "unit_cost"),
            tax_keys=("tax_rate", "tax"),
        )
        receipt_lines = _normalize_match_lines(
            normalized_inputs.get("receipt_lines") or normalized_inputs.get("receipts"),
            default_prefix="RCPT",
            quantity_keys=("accepted_qty", "received_qty", "qty"),
            price_keys=("unit_price", "price"),
            tax_keys=(),
        )

        po_lookup = _build_match_lookup(po_lines)
        receipt_lookup = _build_match_lookup(receipt_lines)

        mismatches: List[Dict[str, Any]] = []
        for line in invoice_lines:
            line_key = line["line_reference"]
            po_line = _resolve_match_line(line, po_lookup)
            if not po_line:
                mismatches.append(
                    {
                        "type": "missing_line",
                        "line_reference": line_key,
                        "severity": "risk",
                        "detail": f"Invoice line {line_key} not found on PO {po_id}.",
                    }
                )
                continue

            qty_diff = line["qty"] - po_line["qty"]
            qty_threshold = max(0.01, po_line["qty"] * 0.02)
            if abs(qty_diff) > qty_threshold:
                mismatches.append(
                    {
                        "type": "qty",
                        "line_reference": line_key,
                        "severity": "warning" if abs(qty_diff) <= po_line["qty"] * 0.1 else "risk",
                        "detail": f"Invoice qty {line['qty']:.2f} vs PO {po_line['qty']:.2f}",
                        "expected": round(po_line["qty"], 4),
                        "actual": round(line["qty"], 4),
                    }
                )

            receipt_line = _resolve_match_line(line, receipt_lookup)
            if receipt_line:
                receipt_diff = line["qty"] - receipt_line["qty"]
                receipt_threshold = max(0.01, receipt_line["qty"] * 0.02)
                if receipt_diff > receipt_threshold:
                    mismatches.append(
                        {
                            "type": "qty",
                            "line_reference": line_key,
                            "severity": "warning",
                            "detail": f"Invoice qty exceeds received qty ({line['qty']:.2f} vs {receipt_line['qty']:.2f})",
                            "expected": round(receipt_line["qty"], 4),
                            "actual": round(line["qty"], 4),
                        }
                    )

            price_diff = line["unit_price"] - po_line["unit_price"]
            price_threshold = max(0.01, po_line["unit_price"] * 0.01)
            if abs(price_diff) > price_threshold:
                mismatches.append(
                    {
                        "type": "price",
                        "line_reference": line_key,
                        "severity": "warning" if abs(price_diff) <= po_line["unit_price"] * 0.05 else "risk",
                        "detail": f"Unit price {line['unit_price']:.2f} vs PO {po_line['unit_price']:.2f}",
                        "expected": round(po_line["unit_price"], 4),
                        "actual": round(line["unit_price"], 4),
                    }
                )

            tax_diff = line["tax_rate"] - po_line["tax_rate"]
            if abs(tax_diff) > 0.005:
                mismatches.append(
                    {
                        "type": "tax",
                        "line_reference": line_key,
                        "severity": "info" if abs(tax_diff) <= 0.02 else "warning",
                        "detail": f"Tax rate {line['tax_rate']:.2%} vs PO {po_line['tax_rate']:.2%}",
                        "expected": round(po_line["tax_rate"], 4),
                        "actual": round(line["tax_rate"], 4),
                    }
                )

        match_score = max(0.0, 1.0 - min(0.8, 0.15 * len(mismatches)))
        recommendation_status = "approve" if not mismatches else "hold"
        if mismatches:
            explanation = "Hold for review: " + "; ".join(mismatch["detail"] for mismatch in mismatches[:3])
        else:
            explanation = "Invoice quantities, pricing, and tax rates align with PO and receipts."

        insights = _contextual_insights(normalized_context, limit=2)
        analysis_notes = insights if insights else ["Three-way match executed deterministically."]

        return {
            "invoice_id": invoice_id,
            "matched_po_id": po_id,
            "matched_receipt_ids": receipt_ids,
            "match_score": round(match_score, 4),
            "mismatches": mismatches,
            "recommendation": {
                "status": recommendation_status,
                "explanation": explanation,
            },
            "analysis_notes": analysis_notes,
        }


def resolve_invoice_mismatch(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Recommend the best resolution for invoice mismatches."""

    with _SideEffectGuard("resolve_invoice_mismatch"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}

        invoice_id = _text(normalized_inputs.get("invoice_id")) or _infer_from_context(
            normalized_context,
            ("invoice_id", "invoice_number", "id"),
        )
        if not invoice_id:
            invoice_id = "invoice"

        raw_mismatches = normalized_inputs.get("mismatches")
        if not raw_mismatches:
            match_result = _mapping(normalized_inputs.get("match_result"))
            raw_mismatches = match_result.get("mismatches") or _extract_context_mismatches(normalized_context)

        mismatches = _normalize_mismatch_summaries(raw_mismatches)
        severity_score = _score_mismatch_severity(mismatches)

        preferred_resolution = _text(normalized_inputs.get("preferred_resolution"))
        resolution_type = _determine_resolution_type(mismatches, preferred_resolution)

        summary = _text(normalized_inputs.get("summary"))
        if not summary:
            summary = _default_resolution_summary(resolution_type, invoice_id, mismatches)

        reason_codes = _string_list(normalized_inputs.get("reason_codes"))
        if not reason_codes:
            reason_codes = sorted({
                f"{entry['type']}_{entry.get('severity') or 'info'}"
                for entry in mismatches
            })[:6]

        actions = _build_resolution_actions(resolution_type, invoice_id)
        impacted_lines = _build_impacted_line_entries(mismatches, resolution_type)
        next_steps = _build_resolution_next_steps(resolution_type, invoice_id)

        notes = _string_list(normalized_inputs.get("notes"))
        if not notes:
            notes = _contextual_insights(normalized_context, limit=2)
        if not notes:
            notes = [summary]

        return {
            "invoice_id": invoice_id,
            "resolution": {
                "type": resolution_type,
                "summary": summary,
                "reason_codes": reason_codes,
                "confidence": round(max(0.15, 1.0 - severity_score), 4),
            },
            "actions": actions,
            "impacted_lines": impacted_lines,
            "next_steps": next_steps,
            "notes": notes[:10],
        }


def build_invoice_dispute_draft(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a dispute draft referencing invoice, PO, and receipt context."""

    with _SideEffectGuard("build_invoice_dispute_draft"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        match_result = _mapping(normalized_inputs.get("match_result"))

        invoice_reference = _resolve_invoice_reference_block(normalized_context, normalized_inputs, match_result)
        purchase_order_reference = _resolve_purchase_order_reference_block(
            normalized_context,
            normalized_inputs,
            match_result,
        )
        receipt_reference = _resolve_receipt_reference_block(normalized_context, normalized_inputs, match_result)

        invoice_label = invoice_reference.get("number") or invoice_reference.get("id") or "invoice"
        raw_mismatches = (
            normalized_inputs.get("mismatches")
            or match_result.get("mismatches")
            or _extract_context_mismatches(normalized_context)
        )
        mismatches = _normalize_mismatch_summaries(raw_mismatches)
        if not mismatches:
            fallback_issue = _text(normalized_inputs.get("issue_summary")) or "Variance detected during invoice review."
            mismatches = [
                {
                    "type": "qty",
                    "severity": "warning",
                    "line_reference": "general",
                    "detail": fallback_issue,
                    "expected": None,
                    "actual": None,
                    "variance": 0.0,
                    "impact_score": 0.1,
                }
            ]

        resolution_pref = _text(
            normalized_inputs.get("resolution_type") or normalized_inputs.get("preferred_resolution")
        )
        resolution_type = _determine_resolution_type(mismatches, resolution_pref)

        issue_summary = _text(normalized_inputs.get("issue_summary")) or _build_dispute_issue_summary(
            invoice_label,
            mismatches,
        )
        issue_category = _text(normalized_inputs.get("issue_category")) or _infer_issue_category(
            resolution_type,
            mismatches,
        )

        reason_codes = _derive_dispute_reason_codes(
            mismatches,
            issue_category,
            normalized_inputs.get("reason_codes"),
        )

        normalized_actions = _normalize_dispute_actions(normalized_inputs.get("actions"))
        if not normalized_actions:
            normalized_actions = _normalize_dispute_actions(_build_resolution_actions(resolution_type, invoice_label))
        if not normalized_actions:
            normalized_actions = [
                {
                    "type": "investigate_variance",
                    "description": "Investigate invoice variance and share summary with supplier.",
                    "owner_role": "buyer_admin",
                    "due_in_days": 3,
                    "requires_hold": True,
                }
            ]

        owner_role = _text(normalized_inputs.get("owner_role")) or next(
            (action.get("owner_role") for action in normalized_actions if action.get("owner_role")),
            None,
        )
        owner_role = owner_role or "buyer_admin"

        requires_hold_value = normalized_inputs.get("requires_hold")
        if isinstance(requires_hold_value, bool):
            requires_hold = requires_hold_value
        else:
            requires_hold = resolution_type in {"hold", "request_credit_note"}
            if not requires_hold:
                requires_hold = any(bool(action.get("requires_hold")) for action in normalized_actions)

        due_in_days_value = normalized_inputs.get("due_in_days")
        if due_in_days_value is not None:
            due_in_days = max(0, min(120, int(round(_safe_float(due_in_days_value, default=0.0)))))
        else:
            due_in_days = 3 if requires_hold else 5

        normalized_impacts = _normalize_dispute_impacts(normalized_inputs.get("impacted_lines"))
        if not normalized_impacts:
            normalized_impacts = _normalize_dispute_impacts(_build_impacted_line_entries(mismatches, resolution_type))

        next_steps = _string_list(normalized_inputs.get("next_steps"))
        if not next_steps:
            next_steps = _build_resolution_next_steps(resolution_type, invoice_label)

        notes = _string_list(normalized_inputs.get("notes"))
        if not notes:
            notes = _contextual_insights(normalized_context, limit=2)
        if not notes:
            notes = [issue_summary]

        dispute_reference = _build_dispute_reference_payload(
            invoice_reference,
            purchase_order_reference,
            receipt_reference,
        )

        return {
            "dispute_reference": dispute_reference,
            "invoice_id": invoice_reference.get("id"),
            "invoice_number": invoice_reference.get("number"),
            "purchase_order_id": purchase_order_reference.get("id"),
            "purchase_order_number": purchase_order_reference.get("number"),
            "receipt_id": receipt_reference.get("id"),
            "receipt_number": receipt_reference.get("number"),
            "issue_summary": issue_summary,
            "issue_category": issue_category or "workflow_dispute",
            "owner_role": owner_role,
            "requires_hold": requires_hold,
            "due_in_days": due_in_days,
            "actions": normalized_actions[:8],
            "impacted_lines": normalized_impacts[:8],
            "next_steps": next_steps[:10],
            "notes": notes[:10],
            "reason_codes": reason_codes[:10],
        }


def build_award_quote(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a deterministic award recommendation for the selected quote."""

    with _SideEffectGuard("build_award_quote"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        selected_quote = _mapping(
            normalized_inputs.get("selected_quote")
            or normalized_inputs.get("quote")
            or normalized_inputs.get("recommendation")
        )
        supplier_block = _mapping(
            normalized_inputs.get("supplier")
            or normalized_inputs.get("awardee")
            or selected_quote.get("supplier")
        )

        rfq_id = _text(normalized_inputs.get("rfq_id") or selected_quote.get("rfq_id")) or "rfq"
        supplier_id = (
            _text(normalized_inputs.get("supplier_id"))
            or _text(selected_quote.get("supplier_id"))
            or _text(supplier_block.get("supplier_id") or supplier_block.get("id"))
            or "supplier"
        )
        selected_quote_id = _text(normalized_inputs.get("selected_quote_id") or selected_quote.get("id")) or "quote"

        supplier_name = _text(supplier_block.get("name")) or supplier_id
        delivery_date = (
            _text(normalized_inputs.get("delivery_date"))
            or _text(selected_quote.get("delivery_date") or selected_quote.get("need_by"))
        )
        if not delivery_date:
            delivery_date = (datetime.now(timezone.utc).date() + timedelta(days=21)).isoformat()

        justification = _text(normalized_inputs.get("justification"))
        if not justification:
            summary = _contextual_insights(normalized_context, limit=1)
            citations = _format_source_labels(normalized_context, limit=2)
            insight = f" Insight: {summary[0]}" if summary else ""
            citation_note = f" Sources: {' '.join(citations)}" if citations else ""
            justification = (
                f"Award {supplier_name} based on best total value, quality, and lead time.{insight}{citation_note}"
            ).strip()

        terms = _string_list(
            normalized_inputs.get("terms")
            or selected_quote.get("terms")
            or normalized_inputs.get("commercial_terms")
        )
        if not terms:
            terms = [
                "Award contingent on supplier maintaining quoted pricing and capacity.",
                "Payment terms Net 30; expedite fees require written approval.",
            ]

        return {
            "rfq_id": rfq_id,
            "supplier_id": supplier_id,
            "selected_quote_id": selected_quote_id,
            "justification": justification,
            "delivery_date": delivery_date,
            "terms": terms[:10],
        }


def review_rfq(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return a readiness checklist for an RFQ so reviewers can spot gaps."""

    with _SideEffectGuard("review_rfq"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        rfq_id = _text(normalized_inputs.get("rfq_id"))
        if not rfq_id:
            raise ValueError("rfq_id is required for review_rfq tool")

        seed = _review_seed(rfq_id)
        line_items = max(1, (seed % 5) + 3)
        awaiting_responses = seed % 3
        due_date = _text(normalized_inputs.get("due_date")) or (
            datetime.now(timezone.utc).date() + timedelta(days=10 - (seed % 5))
        ).isoformat()
        stakeholder_notes = _contextual_insights(normalized_context, limit=1)
        status = normalized_inputs.get("status") or "draft"

        checklist = [
            _build_review_item(
                label="Line items captured",
                value=f"{line_items} items",
                detail="Ensure every target part has clear specs and target dates.",
                status="ok" if line_items >= 3 else "warning",
            ),
            _build_review_item(
                label="Supplier responses pending",
                value=f"{awaiting_responses} vendors",
                detail="Follow up with suppliers who have not acknowledged the RFQ yet.",
                status="warning" if awaiting_responses >= 2 else "ok",
            ),
            _build_review_item(
                label="Evaluation rubric ready",
                value="Weighted rubric present",
                detail="Scoring rubric includes cost, quality, and delivery criteria.",
                status="ok",
            ),
            _build_review_item(
                label="Schedule",
                value=f"Due {due_date}",
                detail="Confirm buyers have availability the day bids close.",
                status="warning" if _is_date_imminent(due_date, threshold_days=3) else "ok",
            ),
        ]

        highlights = [
            f"RFQ {rfq_id} is in {status} status.",
            *(stakeholder_notes if stakeholder_notes else []),
        ][:3]

        return _build_review_payload(
            entity_type="rfq",
            entity_id=rfq_id,
            title=f"RFQ #{rfq_id}",
            summary="RFQ checklist updated from workspace context.",
            checklist=checklist,
            highlights=highlights,
            metadata={"status": status, "due_date": due_date},
        )


def review_quote(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return pricing, delivery, and compliance checks for a quote."""

    with _SideEffectGuard("review_quote"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        quote_id = _text(normalized_inputs.get("quote_id"))
        if not quote_id:
            raise ValueError("quote_id is required for review_quote tool")

        supplier_name = _text(normalized_inputs.get("supplier") or normalized_inputs.get("supplier_name")) or "Supplier"
        target_price = _safe_float(normalized_inputs.get("target_price"), default=125.0)
        quoted_price = _safe_float(normalized_inputs.get("unit_price") or normalized_inputs.get("price"), default=target_price * 1.02)
        lead_time_days = int(max(1, _safe_float(normalized_inputs.get("lead_time_days"), default=14)))
        compliance_flags = normalized_inputs.get("compliance_flags")
        compliance_count = len(compliance_flags) if isinstance(compliance_flags, Sequence) else 0
        warranty_years = max(1, int(_safe_float(normalized_inputs.get("warranty_years"), default=1.0)))

        price_delta = (quoted_price - target_price) / max(target_price, 1.0)
        checklist = [
            _build_review_item(
                label="Pricing delta",
                value=f"{price_delta:+.1%}",
                detail=f"Quoted ${quoted_price:,.2f} vs. target ${target_price:,.2f}.",
                status="risk" if price_delta >= 0.1 else ("warning" if price_delta >= 0.03 else "ok"),
            ),
            _build_review_item(
                label="Lead time",
                value=f"{lead_time_days} days",
                detail="Matches production need date with 5-day buffer.",
                status="warning" if lead_time_days > 21 else "ok",
            ),
            _build_review_item(
                label="Compliance gaps",
                value=f"{compliance_count} items",
                detail="Verify PPAP, RoHS, and conflict mineral attestations are uploaded.",
                status="warning" if compliance_count else "ok",
            ),
            _build_review_item(
                label="Warranty",
                value=f"{warranty_years} year warranty",
                detail="Confirm terms cover workmanship and material defects.",
                status="ok" if warranty_years >= 1 else "warning",
            ),
        ]

        highlights = [
            f"Quote {quote_id} from {supplier_name} evaluated.",
            *( _format_source_labels(normalized_context, limit=1) ),
        ]

        return _build_review_payload(
            entity_type="quote",
            entity_id=quote_id,
            title=f"Quote {quote_id} · {supplier_name}",
            summary="Supplier quote review with pricing, delivery, and compliance notes.",
            checklist=checklist,
            highlights=[note for note in highlights if note],
            metadata={"supplier": supplier_name, "unit_price": quoted_price, "lead_time_days": lead_time_days},
        )


def review_po(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return PO health metrics before release or receiving."""

    with _SideEffectGuard("review_po"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        po_id = _text(normalized_inputs.get("po_id") or normalized_inputs.get("purchase_order_id"))
        if not po_id:
            raise ValueError("po_id is required for review_po tool")

        supplier_name = _text(normalized_inputs.get("supplier") or normalized_inputs.get("supplier_name")) or "Supplier"
        line_items = normalized_inputs.get("line_items")
        line_count = len(line_items) if isinstance(line_items, Sequence) else 3
        total_value = _safe_float(normalized_inputs.get("total_value"), default=25000.0)
        approvals = int(max(1, _safe_float(normalized_inputs.get("approval_count"), default=2)))
        receipts = int(max(0, _safe_float(normalized_inputs.get("receipt_count"), default=0)))
        last_delivery = _text(normalized_inputs.get("next_delivery")) or (
            datetime.now(timezone.utc).date() + timedelta(days=7)
        ).isoformat()

        checklist = [
            _build_review_item(
                label="Total value",
                value=f"${total_value:,.0f}",
                detail="Matches budget tolerance for this sourcing event.",
                status="warning" if total_value > 50000 else "ok",
            ),
            _build_review_item(
                label="Line coverage",
                value=f"{line_count} lines",
                detail="Confirm ship-to and incoterms per line before release.",
                status="ok" if line_count >= 2 else "warning",
            ),
            _build_review_item(
                label="Approvals",
                value=f"{approvals} approvals logged",
                detail="Finance and sourcing approvals recorded in workflow.",
                status="ok" if approvals >= 2 else "warning",
            ),
            _build_review_item(
                label="Receipts posted",
                value=f"{receipts} receipts",
                detail="Receiving entries reconcile with supplier ASN.",
                status="warning" if receipts == 0 else "ok",
            ),
            _build_review_item(
                label="Next delivery",
                value=last_delivery,
                detail="Update logistics if delivery shifts by >2 days.",
                status="warning" if _is_date_imminent(last_delivery, threshold_days=2) else "ok",
            ),
        ]

        highlights = [
            f"PO {po_id} for {supplier_name} ready for review.",
            *( _contextual_insights(normalized_context, limit=1) ),
        ][:3]

        return _build_review_payload(
            entity_type="po",
            entity_id=po_id,
            title=f"PO {po_id}",
            summary="Purchase order review summary.",
            checklist=checklist,
            highlights=[note for note in highlights if note],
            metadata={"supplier": supplier_name, "next_delivery": last_delivery},
        )


def review_invoice(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
) -> Dict[str, Any]:
    """Return invoice approval checks including due dates and match status."""

    with _SideEffectGuard("review_invoice"):
        normalized_context = _normalize_context(context)
        normalized_inputs = inputs or {}
        invoice_id = _text(normalized_inputs.get("invoice_id")) or _text(normalized_inputs.get("id"))
        if not invoice_id:
            raise ValueError("invoice_id is required for review_invoice tool")

        currency = _text(normalized_inputs.get("currency")) or "USD"
        total = _safe_float(normalized_inputs.get("total_amount"), default=18250.0)
        paid = _safe_float(normalized_inputs.get("amount_paid"), default=total * 0.25)
        discrepancies = int(max(0, _safe_float(normalized_inputs.get("discrepancy_count"), default=1)))
        due_date = _text(normalized_inputs.get("due_date")) or (
            datetime.now(timezone.utc).date() + timedelta(days=5)
        ).isoformat()
        match_score = _safe_float(normalized_inputs.get("match_score"), default=0.82)

        open_balance = max(total - paid, 0.0)
        checklist = [
            _build_review_item(
                label="Amount due",
                value=f"{currency} {open_balance:,.2f}",
                detail="Open balance after recorded payments.",
                status="warning" if open_balance > 0 else "ok",
            ),
            _build_review_item(
                label="Due date",
                value=due_date,
                detail="Flag invoices due within 3 days for escalation.",
                status="risk" if _is_date_imminent(due_date, threshold_days=2) else ("warning" if _is_date_imminent(due_date, 5) else "ok"),
            ),
            _build_review_item(
                label="3-way match",
                value=f"{match_score:.0%} match",
                detail="Compare PO, receipt, and invoice quantities.",
                status="warning" if match_score < 0.9 else "ok",
            ),
            _build_review_item(
                label="Discrepancies",
                value=f"{discrepancies} issue(s)",
                detail="Review tax, freight, or price variances before approval.",
                status="risk" if discrepancies >= 2 else ("warning" if discrepancies == 1 else "ok"),
            ),
        ]

        highlights = [
            f"Invoice {invoice_id} review queued.",
            *( _format_source_labels(normalized_context, limit=1) ),
        ]

        return _build_review_payload(
            entity_type="invoice",
            entity_id=invoice_id,
            title=f"Invoice {invoice_id}",
            summary="Invoice review checklist generated for AP.",
            checklist=checklist,
            highlights=[note for note in highlights if note],
            metadata={"currency": currency, "due_date": due_date},
        )


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


def _slugify_identifier(value: str, fallback: str = "supplier") -> str:
    normalized = re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-")
    return normalized or fallback


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


    def _item_description_from_context(
        context: Sequence[MutableMapping[str, Any]],
        fallback: str,
    ) -> str:
        for block in context:
            snippet = _text(block.get("snippet"))
            if snippet:
                return snippet[:320]
            metadata = block.get("metadata") or {}
            summary = _text(metadata.get("summary") or metadata.get("description"))
            if summary:
                return summary[:320]
        return f"{fallback} drafted from Copilot context."


    def _normalize_item_status(value: str) -> str:
        if not value:
            return "active"
        normalized = value.lower()
        if normalized in {"inactive", "retired", "archived", "obsolete", "discontinued"}:
            return "inactive"
        return "active"


    def _build_item_attributes(
        raw_attributes: Any,
        context: Sequence[MutableMapping[str, Any]],
    ) -> Optional[Dict[str, Any]]:
        attributes = _normalize_attribute_mapping(raw_attributes)
        if attributes:
            return attributes

        for block in context:
            metadata = block.get("metadata") or {}
            for key in ("attributes", "specs", "dimensions"):
                candidate = metadata.get(key)
                normalized = _normalize_attribute_mapping(candidate)
                if normalized:
                    attributes.update(normalized)
            if attributes:
                break

        return attributes or None


    def _normalize_attribute_mapping(candidate: Any) -> Dict[str, Any]:
        attributes: Dict[str, Any] = {}
        if isinstance(candidate, MutableMapping):
            iterator = candidate.items()
        elif isinstance(candidate, Iterable) and not isinstance(candidate, (str, bytes)):
            iterator = []
            for entry in candidate:
                if isinstance(entry, MutableMapping):
                    key = _text(entry.get("name") or entry.get("key") or entry.get("attribute"))
                    if not key or key in attributes:
                        continue
                    value = entry.get("value") or entry.get("val") or entry.get("content")
                    if isinstance(value, (MutableMapping, list, tuple)):
                        continue
                    attributes[key] = value if isinstance(value, (int, float)) else _text(value) or value
            return dict(list(attributes.items())[:12])
        else:
            return {}

        for key, value in iterator:
            text_key = _text(key)
            if not text_key or text_key in attributes:
                continue
            if isinstance(value, (MutableMapping, list, tuple)):
                continue
            attributes[text_key] = value if isinstance(value, (int, float)) else _text(value) or value

        return dict(list(attributes.items())[:12])


    def _build_preferred_suppliers(
        inputs: MutableMapping[str, Any],
        context: Sequence[MutableMapping[str, Any]],
    ) -> List[Dict[str, Any]]:
        suppliers = _normalize_preferred_supplier_entries(inputs.get("preferred_suppliers"))

        if not suppliers:
            for block in context:
                metadata = block.get("metadata") or {}
                meta_suppliers = metadata.get("preferred_suppliers") or metadata.get("suppliers")
                suppliers = _normalize_preferred_supplier_entries(meta_suppliers)
                if suppliers:
                    break
                supplier_meta = metadata.get("supplier")
                if supplier_meta:
                    suppliers = _normalize_preferred_supplier_entries([supplier_meta])
                    if suppliers:
                        break

        return suppliers[:5]


    def _normalize_preferred_supplier_entries(raw: Any) -> List[Dict[str, Any]]:
        if raw is None:
            return []

        if isinstance(raw, MutableMapping):
            iterable: Iterable[Any] = [raw]
        elif isinstance(raw, Iterable) and not isinstance(raw, (str, bytes)):
            iterable = raw
        else:
            return []

        normalized: List[Dict[str, Any]] = []
        seen_keys: set[str] = set()

        for idx, entry in enumerate(iterable):
            if not isinstance(entry, MutableMapping):
                continue
            supplier_id = _text(entry.get("supplier_id") or entry.get("id") or entry.get("supplier")) or None
            name = _text(entry.get("name") or entry.get("supplier_name") or entry.get("company"))
            if not name and not supplier_id:
                continue

            key = supplier_id or name
            if key in seen_keys:
                continue
            seen_keys.add(key)

            priority = _normalize_priority(entry.get("priority"), idx + 1)
            notes = _text(entry.get("notes") or entry.get("summary") or entry.get("reason"))

            normalized.append(
                {
                    "supplier_id": supplier_id,
                    "name": name or key,
                    "priority": priority,
                    "notes": notes or None,
                }
            )

        return normalized


def _build_document_requirements(
    raw_requirements: Any,
    context: Sequence[MutableMapping[str, Any]],
) -> List[Dict[str, Any]]:
    requirements: List[Dict[str, Any]] = []
    seen: set[str] = set()
    next_priority = 1

    def append_requirement(
        doc_type: str,
        description: Optional[str],
        *,
        required: bool = True,
        due_in_days: Optional[int] = None,
        notes: Optional[str] = None,
        priority: Optional[int] = None,
    ) -> None:
        normalized_type = doc_type.lower()
        if not normalized_type or normalized_type in seen:
            return
        seen.add(normalized_type)
        entry: Dict[str, Any] = {
            "type": normalized_type,
            "description": description or None,
            "required": required,
        }
        if due_in_days is not None and due_in_days > 0:
            entry["due_in_days"] = due_in_days
        if priority is not None:
            entry["priority"] = priority
        if notes:
            entry["notes"] = notes
        requirements.append(entry)

    def handle_entry(entry: Any) -> None:
        nonlocal next_priority
        prior_len = len(requirements)
        if isinstance(entry, MutableMapping):
            doc_type = _text(entry.get("type") or entry.get("document_type") or entry.get("code"))
            if not doc_type:
                return
            description = _text(entry.get("description")) or None
            notes = _text(entry.get("notes") or entry.get("instructions")) or None
            due_value = entry.get("due_in_days") or entry.get("due_days")
            due_in_days: Optional[int] = None
            if due_value is not None:
                due_in_days = int(
                    _clamp(
                        _safe_float(due_value, default=0.0),
                        1,
                        365,
                    )
                )
            required_flag = entry.get("required")
            required = bool(required_flag) if required_flag is not None else True
            priority = _normalize_priority(entry.get("priority"), next_priority)
            append_requirement(
                doc_type,
                description,
                required=required,
                due_in_days=due_in_days,
                notes=notes,
                priority=priority,
            )
        else:
            doc_type = _text(entry)
            if doc_type:
                append_requirement(
                    doc_type,
                    f"Provide {doc_type.upper()} documentation.",
                    priority=next_priority,
                )
        if len(requirements) > prior_len:
            next_priority += 1

    if isinstance(raw_requirements, MutableMapping):
        iterable: Iterable[Any] = [raw_requirements]
    elif isinstance(raw_requirements, Iterable) and not isinstance(raw_requirements, (str, bytes)):
        iterable = raw_requirements
    elif raw_requirements is None:
        iterable = []
    else:
        iterable = _string_list(raw_requirements)

    for entry in iterable:
        handle_entry(entry)

    if not requirements:
        contextual_entries: List[Any] = []
        extractor_keys = (
            "documents_needed",
            "required_documents",
            "document_requirements",
            "certifications",
        )
        for block in context:
            if not isinstance(block, MutableMapping):
                continue
            sources = [block]
            metadata = block.get("metadata")
            if isinstance(metadata, MutableMapping):
                sources.append(metadata)
            for source in sources:
                for key in extractor_keys:
                    candidate = source.get(key) if isinstance(source, MutableMapping) else None
                    if not candidate:
                        continue
                    if isinstance(candidate, MutableMapping):
                        contextual_entries.append(candidate)
                    elif isinstance(candidate, Iterable) and not isinstance(candidate, (str, bytes)):
                        contextual_entries.extend(candidate)
                    else:
                        contextual_entries.append(candidate)
        if contextual_entries:
            for entry in contextual_entries:
                handle_entry(entry)

    if not requirements:
        defaults = [
            {
                "type": "iso9001",
                "description": "ISO 9001 certificate",
                "due_in_days": 45,
                "required": True,
            },
            {
                "type": "insurance",
                "description": "Certificate of insurance",
                "due_in_days": 30,
                "required": True,
            },
            {
                "type": "nda",
                "description": "Signed mutual NDA",
                "due_in_days": 7,
                "required": True,
            },
        ]
        for entry in defaults:
            handle_entry(entry)

    return requirements[:8]


def _normalize_priority(value: Any, default: int) -> Optional[int]:
        try:
            candidate = int(value)
        except (TypeError, ValueError):
            candidate = None

        if candidate is not None and 1 <= candidate <= 5:
            return candidate

        return min(max(default, 1), 5) if default else None


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


def _infer_from_context(
    context: Sequence[MutableMapping[str, Any]],
    keys: Sequence[str],
) -> str:
    for block in context:
        metadata = block.get("metadata") or {}
        for key in keys:
            candidate = _text(metadata.get(key))
            if candidate:
                return candidate
    return ""


def _infer_receipt_ids_from_context(context: Sequence[MutableMapping[str, Any]]) -> List[str]:
    for block in context:
        metadata = block.get("metadata") or {}
        for key in ("receipt_ids", "receipts"):
            candidates = metadata.get(key)
            ids = _string_list(candidates)
            if ids:
                return ids
    return []


def _normalize_match_lines(
    raw_lines: Any,
    *,
    default_prefix: str,
    quantity_keys: Sequence[str],
    price_keys: Sequence[str],
    tax_keys: Sequence[str],
) -> List[Dict[str, Any]]:
    lines: List[Dict[str, Any]] = []
    if isinstance(raw_lines, MutableMapping):
        iterable: Iterable[Any] = raw_lines.values()
    elif isinstance(raw_lines, Iterable) and not isinstance(raw_lines, (str, bytes)):
        iterable = raw_lines
    else:
        iterable = []

    for idx, entry in enumerate(iterable):
        if not isinstance(entry, MutableMapping):
            continue
        line_ref = _text(
            entry.get("line_reference")
            or entry.get("line_number")
            or entry.get("line_id")
            or entry.get("item_code")
            or entry.get("sku")
        ) or f"{default_prefix}-{idx + 1}"
        item_code = _text(entry.get("item_code") or entry.get("sku") or entry.get("part_number")) or None
        qty_value = _safe_float(_first_present(entry, quantity_keys), default=0.0)
        price_value = _safe_float(_first_present(entry, price_keys), default=0.0) if price_keys else 0.0
        tax_value = _safe_float(_first_present(entry, tax_keys), default=0.0) if tax_keys else 0.0
        lines.append(
            {
                "line_reference": line_ref,
                "item_code": item_code,
                "qty": round(max(qty_value, 0.0), 4),
                "unit_price": round(max(price_value, 0.0), 4),
                "tax_rate": round(max(tax_value, 0.0), 4),
            }
        )
    return lines


def _first_present(entry: MutableMapping[str, Any], keys: Sequence[str]) -> Any:
    for key in keys:
        if key in entry:
            return entry.get(key)
    return None


def _build_match_lookup(lines: Sequence[Dict[str, Any]]) -> Dict[str, Dict[str, Any]]:
    lookup: Dict[str, Dict[str, Any]] = {}
    for line in lines:
        lookup[line["line_reference"]] = line
        item_code = line.get("item_code")
        if item_code and item_code not in lookup:
            lookup[item_code] = line
    return lookup


def _resolve_match_line(line: Dict[str, Any], lookup: Dict[str, Dict[str, Any]]) -> Optional[Dict[str, Any]]:
    candidate = lookup.get(line["line_reference"])
    if candidate:
        return candidate
    item_code = line.get("item_code")
    if item_code:
        return lookup.get(item_code)
    return None


def _extract_context_mismatches(context: Sequence[MutableMapping[str, Any]]) -> List[Any]:
    extracted: List[Any] = []
    mismatch_keys = ("mismatches", "invoice_mismatches", "invoice_variances", "match_mismatches")
    for block in context:
        if not isinstance(block, MutableMapping):
            continue
        sources: List[Any] = [block]
        metadata = block.get("metadata")
        if isinstance(metadata, MutableMapping):
            sources.append(metadata)
        for source in sources:
            for key in mismatch_keys:
                candidate = source.get(key) if isinstance(source, MutableMapping) else None
                if not candidate:
                    continue
                if isinstance(candidate, MutableMapping):
                    if any(k in candidate for k in ("type", "line_reference", "mismatch_type")):
                        extracted.append(candidate)
                    else:
                        nested = candidate.get("items") or candidate.get("mismatches")
                        if isinstance(nested, MutableMapping):
                            extracted.append(nested)
                        elif isinstance(nested, Iterable) and not isinstance(nested, (str, bytes)):
                            extracted.extend(nested)
                elif isinstance(candidate, Iterable) and not isinstance(candidate, (str, bytes)):
                    extracted.extend(candidate)
                else:
                    extracted.append(candidate)
    return extracted


def _normalize_mismatch_summaries(raw_mismatches: Any) -> List[Dict[str, Any]]:
    if isinstance(raw_mismatches, MutableMapping):
        iterable: Iterable[Any] = raw_mismatches.get("mismatches") or raw_mismatches.get("items") or [raw_mismatches]
    elif isinstance(raw_mismatches, Iterable) and not isinstance(raw_mismatches, (str, bytes)):
        iterable = raw_mismatches
    elif raw_mismatches:
        iterable = [raw_mismatches]
    else:
        iterable = []

    normalized: List[Dict[str, Any]] = []
    for idx, entry in enumerate(iterable):
        if not isinstance(entry, MutableMapping):
            continue
        mismatch_type = _text(entry.get("type") or entry.get("mismatch_type") or entry.get("code")) or "qty"
        severity = _text(entry.get("severity")) or "warning"
        line_reference = _text(
            entry.get("line_reference")
            or entry.get("line")
            or entry.get("line_number")
            or entry.get("item")
        ) or f"line-{idx + 1}"
        detail = _text(entry.get("detail") or entry.get("description") or entry.get("reason"))
        expected = entry.get("expected") or entry.get("expected_value")
        actual = entry.get("actual") or entry.get("actual_value")
        variance = entry.get("variance") or entry.get("difference") or entry.get("delta")
        if variance is None:
            expected_num = _safe_float(expected, default=None)
            actual_num = _safe_float(actual, default=None)
            if expected_num is not None and actual_num is not None:
                variance = round(actual_num - expected_num, 4)
        impact_score = _safe_float(entry.get("impact") or entry.get("impact_score"), default=None)
        if impact_score is None and isinstance(variance, (int, float)):
            impact_score = min(0.5, abs(float(variance)))
        normalized.append(
            {
                "type": mismatch_type,
                "severity": severity,
                "line_reference": line_reference,
                "detail": detail or f"{mismatch_type.title()} variance detected",
                "expected": expected,
                "actual": actual,
                "variance": variance,
                "impact_score": impact_score or 0.0,
            }
        )

    return normalized


def _score_mismatch_severity(mismatches: Sequence[Dict[str, Any]]) -> float:
    if not mismatches:
        return 0.0

    severity_weights = {
        "info": 0.05,
        "warning": 0.18,
        "risk": 0.35,
    }
    score = 0.0
    for mismatch in mismatches:
        severity = mismatch.get("severity") or "warning"
        base = severity_weights.get(severity, 0.2)
        variance = _safe_float(mismatch.get("variance"), default=0.0) or 0.0
        impact = _safe_float(mismatch.get("impact_score"), default=0.0) or 0.0
        score += base + min(0.2, abs(variance) * 0.05) + min(0.1, abs(impact))
    score += 0.05 * max(0, len(mismatches) - 2)
    return min(0.85, round(score, 4))


def _determine_resolution_type(
    mismatches: Sequence[Dict[str, Any]],
    preferred_resolution: Optional[str],
) -> str:
    allowed = {"hold", "partial_approve", "request_credit_note", "adjust_po"}
    alias_map = {
        "credit": "request_credit_note",
        "credit_note": "request_credit_note",
        "request_credit": "request_credit_note",
        "adjust": "adjust_po",
        "change_po": "adjust_po",
        "partial": "partial_approve",
    }

    normalized_preference = (_text(preferred_resolution) or "").replace(" ", "_")
    if normalized_preference in allowed:
        return normalized_preference
    if normalized_preference in alias_map:
        return alias_map[normalized_preference]

    if not mismatches:
        return "partial_approve"

    has_risk = any(entry.get("severity") == "risk" for entry in mismatches)
    over_billed = any((_safe_float(entry.get("variance"), default=0.0) or 0.0) > 0 for entry in mismatches)
    under_billed = any((_safe_float(entry.get("variance"), default=0.0) or 0.0) < 0 for entry in mismatches)
    qty_only = all(entry.get("type") == "qty" for entry in mismatches)

    if has_risk or len(mismatches) >= 3:
        return "hold"
    if over_billed:
        return "request_credit_note"
    if qty_only or under_billed:
        return "adjust_po"
    return "partial_approve"


def _default_resolution_summary(
    resolution_type: str,
    invoice_id: str,
    mismatches: Sequence[Dict[str, Any]],
) -> str:
    if mismatches:
        headline_issue = mismatches[0].get("detail") or f"{mismatches[0].get('type', 'variance')} variance"
    else:
        headline_issue = "detected variance"

    if resolution_type == "hold":
        return f"Hold invoice {invoice_id} until {headline_issue} is reviewed."
    if resolution_type == "request_credit_note":
        return f"Request supplier credit for invoice {invoice_id} due to {headline_issue}."
    if resolution_type == "adjust_po":
        return f"Adjust PO before approving invoice {invoice_id} because of {headline_issue}."
    return f"Approve clean lines on invoice {invoice_id} and short-pay for {headline_issue}."


def _build_resolution_actions(resolution_type: str, invoice_id: str) -> List[Dict[str, Any]]:
    label = invoice_id or "invoice"
    action_templates = {
        "hold": [
            {
                "type": "place_hold",
                "description": f"Place {label} on AP hold until discrepancies are resolved.",
                "owner_role": "ap_specialist",
                "due_in_days": 1,
                "requires_hold": True,
            },
            {
                "type": "notify_buyer",
                "description": "Notify buyer and receiving team to investigate mismatched lines.",
                "owner_role": "buyer",
                "due_in_days": 2,
                "requires_hold": True,
            },
        ],
        "partial_approve": [
            {
                "type": "short_pay",
                "description": f"Approve matched lines on {label} and short-pay disputed amounts.",
                "owner_role": "ap_specialist",
                "due_in_days": 2,
                "requires_hold": False,
            },
            {
                "type": "document_variance",
                "description": "Document variance reasons for audit readiness.",
                "owner_role": "ap_manager",
                "due_in_days": 3,
                "requires_hold": False,
            },
        ],
        "request_credit_note": [
            {
                "type": "request_credit_note",
                "description": f"Request supplier credit note referencing {label} for over-billed amounts.",
                "owner_role": "buyer",
                "due_in_days": 2,
                "requires_hold": True,
            },
            {
                "type": "pause_payment",
                "description": "Pause payment until the credit memo is received and applied.",
                "owner_role": "ap_specialist",
                "due_in_days": 1,
                "requires_hold": True,
            },
        ],
        "adjust_po": [
            {
                "type": "adjust_po",
                "description": "Issue a PO change order so quantities and pricing reflect actual receipts.",
                "owner_role": "buyer",
                "due_in_days": 3,
                "requires_hold": False,
            },
            {
                "type": "sync_receiving",
                "description": "Coordinate with receiving and planning to update downstream systems.",
                "owner_role": "planner",
                "due_in_days": 4,
                "requires_hold": False,
            },
        ],
    }
    return action_templates.get(resolution_type, action_templates["hold"])


def _build_impacted_line_entries(
    mismatches: Sequence[Dict[str, Any]],
    resolution_type: str,
) -> List[Dict[str, Any]]:
    impacted: List[Dict[str, Any]] = []

    def action_phrase(mismatch_type: str) -> str:
        base = {
            "qty": "Reconcile received vs invoiced quantities.",
            "price": "Validate price variance against PO.",
            "tax": "Confirm tax configuration with supplier.",
        }
        phrase = base.get(mismatch_type, "Document variance and follow resolution plan.")
        if resolution_type == "request_credit_note":
            return f"{phrase} Request credit note if over-billed."
        if resolution_type == "adjust_po":
            return f"{phrase} Update PO or receipts to mirror actuals."
        if resolution_type == "partial_approve":
            return f"{phrase} Short-pay disputed amount."
        return f"{phrase} Keep invoice on hold until resolved."

    for idx, mismatch in enumerate(mismatches[:8]):
        line_reference = mismatch.get("line_reference") or f"line-{idx + 1}"
        issue = mismatch.get("detail") or f"{mismatch.get('type', 'variance')} variance"
        severity = mismatch.get("severity") or "info"
        variance_value = mismatch.get("variance")
        if not isinstance(variance_value, (int, float)):
            variance_value = _safe_float(variance_value, default=None)
        impacted.append(
            {
                "line_reference": line_reference,
                "issue": issue,
                "severity": severity,
                "variance": None if variance_value is None else round(float(variance_value), 4),
                "recommended_action": action_phrase(mismatch.get("type", "variance")),
            }
        )

    if not impacted:
        impacted.append(
            {
                "line_reference": "general",
                "issue": "Variance not tied to a specific line",
                "severity": "info",
                "variance": None,
                "recommended_action": action_phrase("summary"),
            }
        )

    return impacted


def _build_resolution_next_steps(resolution_type: str, invoice_id: str) -> List[str]:
    label = invoice_id or "the invoice"
    next_steps = {
        "hold": [
            f"Flag {label} with an AP hold code so payment cannot be released.",
            "Share mismatch summary with buyer and receiving stakeholders.",
            "Resume processing once updated invoice or credit memo is received.",
        ],
        "partial_approve": [
            f"Approve clean lines on {label} in the ERP.",
            "Record the short-pay amount with supporting reason codes.",
            "Notify supplier that payment excludes disputed variances.",
        ],
        "request_credit_note": [
            f"Send credit note request referencing {label} to the supplier.",
            "Pause payment until the credit memo posts to the account.",
            "Attach credit memo to invoice workflow for audit traceability.",
        ],
        "adjust_po": [
            "Raise a PO change order that reflects actual receipts/invoice values.",
            "Sync updated PO quantities with receiving and planning teams.",
            f"Requeue {label} for approval after PO updates are confirmed.",
        ],
    }
    return next_steps.get(resolution_type, next_steps["hold"])


def _resolve_invoice_reference_block(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
    match_result: MutableMapping[str, Any],
) -> Dict[str, Optional[str]]:
    invoice_block = _mapping(
        inputs.get("invoice")
        or inputs.get("invoice_reference")
        or inputs.get("invoice_details")
        or inputs.get("entity")
        or {}
    )
    invoice_id = _text(
        inputs.get("invoice_id")
        or invoice_block.get("id")
        or invoice_block.get("invoice_id")
        or match_result.get("invoice_id")
        or inputs.get("entity_id")
    )
    invoice_number = _text(
        inputs.get("invoice_number")
        or invoice_block.get("invoice_number")
        or invoice_block.get("number")
        or match_result.get("invoice_number")
    )
    if not invoice_id:
        invoice_id = _infer_from_context(context, ("invoice_id", "invoice", "invoice_number"))
    if not invoice_number:
        invoice_number = _infer_from_context(context, ("invoice_number", "invoice"))
    if not invoice_id:
        invoice_id = invoice_number or "invoice"
    return {"id": invoice_id, "number": invoice_number or None}


def _resolve_purchase_order_reference_block(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
    match_result: MutableMapping[str, Any],
) -> Dict[str, Optional[str]]:
    po_block = _mapping(inputs.get("purchase_order") or inputs.get("po") or {})
    po_id = _text(
        inputs.get("purchase_order_id")
        or inputs.get("po_id")
        or po_block.get("id")
        or po_block.get("purchase_order_id")
        or match_result.get("matched_po_id")
    )
    po_number = _text(
        inputs.get("purchase_order_number")
        or inputs.get("po_number")
        or po_block.get("po_number")
        or po_block.get("number")
    )
    if not po_id:
        po_id = _infer_from_context(context, ("purchase_order_id", "po_id"))
    if not po_number:
        po_number = _infer_from_context(context, ("purchase_order_number", "po_number"))
    if not po_id and po_number:
        po_id = po_number
    return {"id": po_id or None, "number": po_number or None}


def _resolve_receipt_reference_block(
    context: Sequence[MutableMapping[str, Any]],
    inputs: MutableMapping[str, Any],
    match_result: MutableMapping[str, Any],
) -> Dict[str, Optional[str]]:
    receipt_block = _mapping(inputs.get("receipt") or inputs.get("receipt_reference") or {})
    receipt_candidates = _infer_receipt_ids_from_context(context)
    receipt_id = _text(
        inputs.get("receipt_id")
        or receipt_block.get("id")
        or match_result.get("matched_receipt_id")
    )
    matched_receipts = match_result.get("matched_receipt_ids")
    if not receipt_id and isinstance(matched_receipts, Sequence):
        for item in matched_receipts:
            candidate = _text(item)
            if candidate:
                receipt_id = candidate
                break
    if not receipt_id and receipt_candidates:
        receipt_id = receipt_candidates[0]
    receipt_number = _text(inputs.get("receipt_number") or receipt_block.get("number"))
    if not receipt_number and receipt_candidates:
        receipt_number = receipt_candidates[0]
    return {"id": receipt_id or None, "number": receipt_number or None}


def _build_dispute_reference_payload(
    invoice_reference: Dict[str, Optional[str]],
    purchase_order_reference: Dict[str, Optional[str]],
    receipt_reference: Dict[str, Optional[str]],
) -> Dict[str, Any]:
    invoice_payload: Dict[str, Any] = {"id": invoice_reference.get("id") or "invoice"}
    if invoice_reference.get("number"):
        invoice_payload["number"] = invoice_reference["number"]

    payload: Dict[str, Any] = {"invoice": invoice_payload}

    if purchase_order_reference.get("id") or purchase_order_reference.get("number"):
        po_block: Dict[str, Any] = {}
        if purchase_order_reference.get("id"):
            po_block["id"] = purchase_order_reference["id"]
        if purchase_order_reference.get("number"):
            po_block["number"] = purchase_order_reference["number"]
        payload["purchase_order"] = po_block

    if receipt_reference.get("id") or receipt_reference.get("number"):
        receipt_block: Dict[str, Any] = {}
        if receipt_reference.get("id"):
            receipt_block["id"] = receipt_reference["id"]
        if receipt_reference.get("number"):
            receipt_block["number"] = receipt_reference["number"]
        payload["receipt"] = receipt_block

    return payload


def _normalize_dispute_actions(raw_actions: Any) -> List[Dict[str, Any]]:
    if isinstance(raw_actions, MutableMapping):
        iterable: Iterable[Any] = raw_actions.values()
    elif isinstance(raw_actions, Iterable) and not isinstance(raw_actions, (str, bytes)):
        iterable = raw_actions
    else:
        return []

    normalized: List[Dict[str, Any]] = []
    for entry in iterable:
        if not isinstance(entry, MutableMapping):
            continue
        action_type = _text(entry.get("type") or entry.get("action"))
        description = _text(entry.get("description") or entry.get("detail"))
        if not action_type or not description:
            continue
        owner_role = _text(entry.get("owner_role") or entry.get("owner")) or None
        due_value = entry.get("due_in_days") or entry.get("due_days") or entry.get("due")
        if due_value is not None:
            due_in_days = max(0, min(120, int(round(_safe_float(due_value, default=0.0)))))
        else:
            due_in_days = None
        requires_hold = entry.get("requires_hold")
        if not isinstance(requires_hold, bool):
            requires_hold = bool(entry.get("hold") or entry.get("requiresHold"))
        normalized.append(
            {
                "type": action_type,
                "description": description,
                "owner_role": owner_role,
                "due_in_days": due_in_days,
                "requires_hold": bool(requires_hold),
            }
        )
    return normalized


def _normalize_dispute_impacts(raw_impacts: Any) -> List[Dict[str, Any]]:
    if isinstance(raw_impacts, MutableMapping):
        iterable: Iterable[Any] = raw_impacts.values()
    elif isinstance(raw_impacts, Iterable) and not isinstance(raw_impacts, (str, bytes)):
        iterable = raw_impacts
    else:
        return []

    normalized: List[Dict[str, Any]] = []
    for idx, entry in enumerate(iterable):
        if not isinstance(entry, MutableMapping):
            continue
        reference = _text(entry.get("reference") or entry.get("line_reference") or entry.get("line"))
        if not reference:
            reference = f"line-{idx + 1}"
        issue = _text(entry.get("issue") or entry.get("detail")) or "Variance detected"
        severity = _text(entry.get("severity")) or None
        variance_value = entry.get("variance")
        if variance_value is None:
            variance = None
        elif isinstance(variance_value, (int, float)):
            variance = float(variance_value)
        else:
            try:
                variance = float(variance_value)
            except (TypeError, ValueError):
                variance = None
        recommended_action = _text(
            entry.get("recommended_action")
            or entry.get("action")
            or entry.get("next_step")
        ) or "Document variance and align on corrective action."
        normalized.append(
            {
                "reference": reference,
                "issue": issue,
                "severity": severity,
                "variance": None if variance is None else round(variance, 4),
                "recommended_action": recommended_action,
            }
        )

    if not normalized:
        normalized.append(
            {
                "reference": "general",
                "issue": "Variance not tied to a specific line",
                "severity": "info",
                "variance": None,
                "recommended_action": "Investigate mismatch and notify supplier.",
            }
        )

    return normalized


def _build_dispute_issue_summary(
    invoice_label: str,
    mismatches: Sequence[Dict[str, Any]],
) -> str:
    if mismatches:
        headline_issue = mismatches[0].get("detail") or f"{mismatches[0].get('type', 'variance')} variance"
        additional = len(mismatches) - 1
        suffix = f" (+{additional} more variance)" if additional > 0 else ""
        return f"Invoice {invoice_label} has {headline_issue}{suffix}."
    return f"Invoice {invoice_label} requires a supplier dispute due to detected variance."


def _infer_issue_category(
    resolution_type: str,
    mismatches: Sequence[Dict[str, Any]],
) -> str:
    type_map = {
        "qty": "quantity_variance",
        "quantity": "quantity_variance",
        "quantity_variance": "quantity_variance",
        "price": "price_variance",
        "cost": "price_variance",
        "tax": "tax_exception",
        "missing_line": "missing_line",
        "duplicate": "duplicate_invoice",
    }
    primary_type = ""
    if mismatches:
        primary_type = _text(mismatches[0].get("type")).replace(" ", "_").lower()

    if resolution_type == "request_credit_note":
        return "overbilling"
    if resolution_type == "adjust_po" and not primary_type:
        return "po_alignment"
    if resolution_type == "hold" and not primary_type:
        return "requires_review"

    return type_map.get(primary_type, "workflow_dispute")


def _derive_dispute_reason_codes(
    mismatches: Sequence[Dict[str, Any]],
    issue_category: Optional[str],
    provided_codes: Any,
) -> List[str]:
    ordered: List[str] = []

    def _append(code: Optional[str]) -> None:
        if not code:
            return
        normalized = code.strip().replace(" ", "_")
        if not normalized:
            return
        if normalized not in ordered:
            ordered.append(normalized)

    for code in _string_list(provided_codes):
        _append(code)

    _append(issue_category)

    for entry in mismatches[:5]:
        mismatch_type = _text(entry.get("type")) or "variance"
        severity = _text(entry.get("severity")) or "info"
        _append(f"{mismatch_type}_{severity}")

    if not ordered:
        _append("workflow_dispute")

    return ordered


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


def _build_invoice_line_items(
    raw_items: Any,
    context: List[MutableMapping[str, Any]],
) -> List[Dict[str, Any]]:
    items: List[Dict[str, Any]] = []
    if isinstance(raw_items, MutableMapping):
        iterable: Iterable[Any] = raw_items.values()
    elif isinstance(raw_items, Iterable) and not isinstance(raw_items, (str, bytes)):
        iterable = raw_items
    else:
        iterable = []

    for entry in iterable:
        if not isinstance(entry, MutableMapping):
            continue
        description = _text(entry.get("description") or entry.get("item") or entry.get("item_code")) or "Pending invoice line"
        qty = _clamp(_safe_float(entry.get("qty") or entry.get("quantity"), default=1.0), 0.01, 9_999_999)
        unit_price = max(_safe_float(entry.get("unit_price") or entry.get("price"), default=1.0), 0.0)
        tax_rate = _clamp(_safe_float(entry.get("tax_rate") or entry.get("tax"), default=0.0), 0.0, 1.0)
        items.append(
            {
                "description": description,
                "qty": qty,
                "unit_price": round(unit_price, 2),
                "tax_rate": round(tax_rate, 4),
            }
        )

    if not items:
        insights = _contextual_insights(context, limit=1)
        items.append(
            {
                "description": insights[0] if insights else "Auto-generated line item",
                "qty": 1.0,
                "unit_price": 1.0,
                "tax_rate": 0.0,
            }
        )
    return items[:25]


def _build_receipt_line_items(
    raw_items: Any,
    context: List[MutableMapping[str, Any]],
) -> List[Dict[str, Any]]:
    items: List[Dict[str, Any]] = []
    if isinstance(raw_items, MutableMapping):
        iterable: Iterable[Any] = raw_items.values()
    elif isinstance(raw_items, Iterable) and not isinstance(raw_items, (str, bytes)):
        iterable = raw_items
    else:
        iterable = []

    for idx, entry in enumerate(iterable):
        if not isinstance(entry, MutableMapping):
            continue
        po_line_id = _text(entry.get("po_line_id") or entry.get("line_id") or entry.get("id")) or f"LINE-{idx + 1}"
        description = _text(entry.get("description") or entry.get("item") or entry.get("part_number")) or "Received line"
        expected_qty = max(_safe_float(entry.get("expected_qty") or entry.get("ordered_qty") or entry.get("qty"), default=0.0), 0.0)
        received_qty = max(_safe_float(entry.get("received_qty") or entry.get("qty") or entry.get("accepted_qty"), default=1.0), 0.0)
        accepted_seed = _safe_float(entry.get("accepted_qty"), default=received_qty)
        accepted_qty = min(received_qty, max(accepted_seed, 0.0))
        rejected_seed = _safe_float(entry.get("rejected_qty"), default=received_qty - accepted_qty)
        rejected_qty = max(rejected_seed, 0.0)
        uom = _text(entry.get("uom") or entry.get("unit")) or "ea"
        issues = _string_list(entry.get("issues") or entry.get("defects") or entry.get("warnings"))
        notes = _text(entry.get("notes") or entry.get("quality_notes"))

        items.append(
            {
                "po_line_id": po_line_id,
                "line_number": int(max(1, round(_safe_float(entry.get("line_number") or entry.get("line_no"), default=float(idx + 1))))),
                "description": description,
                "uom": uom,
                "expected_qty": round(expected_qty, 4),
                "received_qty": round(received_qty, 4),
                "accepted_qty": round(accepted_qty, 4),
                "rejected_qty": round(rejected_qty, 4),
                "issues": issues,
                "notes": notes or "",
            }
        )

    if not items:
        insights = _contextual_insights(context, limit=1)
        items.append(
            {
                "po_line_id": "LINE-1",
                "line_number": 1,
                "description": insights[0] if insights else "Auto-generated receipt line",
                "uom": "ea",
                "expected_qty": 1.0,
                "received_qty": 1.0,
                "accepted_qty": 1.0,
                "rejected_qty": 0.0,
                "issues": [],
                "notes": "Placeholder generated from workflow context.",
            }
        )
    return items[:25]


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


def _parse_iso_date(value: str) -> date | None:
    if not value:
        return None
    try:
        return datetime.fromisoformat(value).date()
    except ValueError:
        return None


def _review_seed(identifier: str) -> int:
    return sum(ord(char) for char in identifier) or 1


def _build_review_payload(
    *,
    entity_type: str,
    entity_id: str,
    title: str,
    summary: str,
    checklist: List[Dict[str, Any]],
    highlights: Sequence[str] | None = None,
    metadata: Optional[Dict[str, Any]] = None,
) -> Dict[str, Any]:
    payload = {
        "entity_type": entity_type,
        "entity_id": entity_id,
        "title": title,
        "summary": summary,
        "checklist": checklist,
    }
    if highlights:
        filtered = [note for note in highlights if isinstance(note, str) and note.strip()]
        if filtered:
            payload["highlights"] = filtered
    if metadata:
        payload["metadata"] = metadata
    return payload


def _build_review_item(*, label: str, value: Any, detail: str, status: str) -> Dict[str, Any]:
    normalized_status = status if status in {"ok", "warning", "risk"} else "ok"
    return {
        "label": label,
        "value": value,
        "detail": detail,
        "status": normalized_status,
    }


def _is_date_imminent(value: str, threshold_days: int) -> bool:
    parsed = _parse_iso_date(value)
    if parsed is None:
        return False
    delta = parsed - datetime.now(timezone.utc).date()
    return delta.days <= threshold_days


def _extract_numeric_series(
    context: List[MutableMapping[str, Any]],
    *,
    limit: int = 180,
) -> List[float]:
    series: List[float] = []
    for block in context:
        candidates: List[Any] = []
        if "data" in block:
            candidates.append(block.get("data"))
        metadata = block.get("metadata") or {}
        for key in ("history", "series", "values"):
            if key in metadata:
                candidates.append(metadata.get(key))
        for candidate in candidates:
            for entry in _iter_numeric_candidates(candidate):
                numeric = _coerce_numeric(entry)
                if numeric is not None:
                    series.append(numeric)
                    if len(series) >= limit:
                        return series
    return series


def _iter_numeric_candidates(value: Any) -> Iterable[Any]:
    if isinstance(value, MutableMapping):
        return value.values()
    if isinstance(value, Iterable) and not isinstance(value, (str, bytes)):
        return value
    return [value]


def _coerce_numeric(value: Any) -> Optional[float]:
    if isinstance(value, (int, float)) and not math.isnan(float(value)):
        return float(value)
    if isinstance(value, MutableMapping):
        preferred_keys = (
            "value",
            "amount",
            "total",
            "quantity",
            "actual",
            "forecast",
            "usage",
            "score",
        )
        for key in preferred_keys:
            if key in value:
                numeric = _coerce_numeric(value.get(key))
                if numeric is not None:
                    return numeric
        for nested in value.values():
            numeric = _coerce_numeric(nested)
            if numeric is not None:
                return numeric
    return None


def _series_mean(values: Sequence[float]) -> float:
    if not values:
        return 0.0
    return sum(values) / float(len(values))


def _series_stddev(values: Sequence[float]) -> float:
    if len(values) < 2:
        return 0.0
    mean_value = _series_mean(values)
    variance = sum((value - mean_value) ** 2 for value in values) / float(len(values))
    return math.sqrt(max(variance, 0.0))


def _build_confidence_interval(
    center: float,
    margin: float,
    *,
    minimum: float = float("-inf"),
    maximum: Optional[float] = None,
) -> Dict[str, float]:
    lower = center - margin
    upper = center + margin
    if maximum is not None:
        lower = min(lower, maximum)
        upper = min(upper, maximum)
    lower = max(lower, minimum)
    upper = max(upper, minimum)
    return {
        "lower": round(lower, 4),
        "upper": round(upper, 4),
    }


def _select_help_section(topic: str) -> Dict[str, Any]:
    sections = _load_help_sections()
    if not sections:
        return {
            "title": "Copilot help center",
            "summary": "Here is how to proceed in the workspace.",
            "steps": _fallback_help_steps(topic),
            "source": "help",
            "reference": "[help]",
            "url": f"{HELP_DOC_BASE_URL.rstrip('/')}/copilot",
            "cta_label": "Open help center",
            "level": 1,
            "slug": "help::fallback",
            "anchor": "fallback",
            "locale": HELP_DEFAULT_LOCALE,
        }

    keywords = [token for token in re.split(r"[^a-z0-9]+", topic.lower()) if len(token) > 2]
    best_section = sections[0]
    best_score = float("-inf")
    for section in sections:
        score = 0.0
        title = section.get("title", "").lower()
        body = section.get("body", "").lower()
        for keyword in keywords:
            if keyword and keyword in title:
                score += 3.0
            if keyword and keyword in body:
                score += 1.0
        score += max(0, 3 - int(section.get("level", 1)))
        if score > best_score:
            best_score = score
            best_section = section

    return best_section


@lru_cache(maxsize=1)
def _load_help_sections() -> List[Dict[str, Any]]:
    sections: List[Dict[str, Any]] = []
    for slug, path in HELP_DOC_SOURCES:
        try:
            raw = path.read_text(encoding="utf-8")
        except OSError:
            continue
        sections.extend(_split_markdown_sections(slug, raw))
    return sections


def _split_markdown_sections(source: str, document: str) -> List[Dict[str, Any]]:
    sections: List[Dict[str, Any]] = []
    current_title = source.replace("_", " ").title()
    current_lines: List[str] = []
    current_level = 1
    for line in document.splitlines():
        stripped = line.strip()
        if stripped.startswith("#"):
            if current_lines:
                sections.append(_build_help_entry(source, current_title, current_level, current_lines))
                current_lines = []
            level = len(stripped) - len(stripped.lstrip("#"))
            current_level = max(1, level)
            current_title = stripped.lstrip("# ").strip() or current_title
            continue
        current_lines.append(line)

    if current_lines:
        sections.append(_build_help_entry(source, current_title, current_level, current_lines))
    return sections


def _build_help_entry(source: str, title: str, level: int, lines: Sequence[str]) -> Dict[str, Any]:
    body = "\n".join(line.rstrip() for line in lines).strip()
    anchor = _slugify_anchor(title or source)
    summary = _summarize_help_body(body)
    steps = _extract_help_steps(body)
    url = _build_help_url(source, anchor)
    slug = _compose_help_slug(source, anchor)
    return {
        "source": source,
        "title": title or source.replace("_", " ").title(),
        "body": body,
        "level": level,
        "summary": summary,
        "steps": steps,
        "reference": f"[{source}:{anchor}]" if anchor else f"[{source}]",
        "url": url,
        "cta_label": "Open help center",
        "anchor": anchor,
        "slug": slug,
        "locale": HELP_DEFAULT_LOCALE,
    }


def _localize_help_section(section: Dict[str, Any], locale: Optional[str]) -> Dict[str, Any]:
    normalized_locale = _normalize_locale(locale)
    localized = dict(section)
    slug = localized.get("slug") or _compose_help_slug(
        localized.get("source", "help"),
        localized.get("anchor", "section"),
    )
    localized["slug"] = slug
    translations = HELP_TRANSLATIONS.get(normalized_locale, {})
    translation = translations.get(slug)

    if translation:
        for field in ("title", "summary", "cta_label"):
            if translation.get(field):
                localized[field] = translation[field]
        if translation.get("steps"):
            localized["steps"] = translation["steps"]
        if translation.get("cta_url"):
            localized["url"] = translation["cta_url"]
        localized["locale"] = normalized_locale
    else:
        localized["locale"] = HELP_DEFAULT_LOCALE

    localized["available_locales"] = _available_help_locales(slug)

    return localized


def _available_help_locales(slug: Optional[str]) -> List[str]:
    base = [HELP_DEFAULT_LOCALE]

    if not slug:
        return base

    for locale_code, catalog in HELP_TRANSLATIONS.items():
        if slug in catalog and locale_code not in base:
            base.append(locale_code)

    return base


def _compose_help_slug(source: str, anchor: str) -> str:
    normalized_source = source or "help"
    normalized_anchor = anchor or "section"
    return f"{normalized_source}::{normalized_anchor}"


def _normalize_locale(value: Optional[str]) -> str:
    if not value:
        return HELP_DEFAULT_LOCALE

    normalized = re.sub(r"[^a-z-]", "", value.lower())

    if normalized.startswith("es"):
        return "es"

    return HELP_DEFAULT_LOCALE


def _summarize_help_body(body: str) -> str:
    cleaned_lines = [line.strip() for line in body.splitlines() if line.strip()]
    if not cleaned_lines:
        return "Here is how to proceed in the workspace."
    first_sentence = re.split(r"(?<=[.!?])\s+", cleaned_lines[0])[0]
    return first_sentence or "Here is how to proceed in the workspace."


def _extract_help_steps(body: str) -> List[str]:
    steps: List[str] = []
    for line in body.splitlines():
        stripped = line.strip()
        if not stripped:
            continue
        if re.match(r"^\d+[.)]\s+", stripped):
            entry = re.sub(r"^\d+[.)]\s+", "", stripped).strip()
        elif stripped.startswith(('- ', '* ')):
            entry = stripped[2:].strip()
        else:
            continue
        if entry:
            steps.append(entry)
        if len(steps) >= 8:
            break
    return steps


def _fallback_help_steps(topic: str) -> List[str]:
    normalized = topic.strip() or "the requested action"
    return [
        f"Open the Copilot dock and describe that you want to {normalized}.",
        "Review the action links Copilot suggests for that module.",
        "Follow the in-app breadcrumbs or documentation link to continue.",
    ]


def _build_help_url(source: str, anchor: str) -> str:
    base = HELP_DOC_BASE_URL.rstrip("/")
    path = source.replace("_", "-")
    if anchor:
        return f"{base}/{path}#{anchor}"
    return f"{base}/{path}"


def _slugify_anchor(value: str) -> str:
    normalized = re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-")
    return normalized or "section"


__all__ = [
    "SideEffectBlockedError",
    "build_rfq_draft",
    "build_supplier_message",
    "build_maintenance_checklist",
    "run_inventory_whatif",
    "compare_quotes",
    "draft_purchase_order",
    "build_invoice_draft",
    "build_invoice_dispute_draft",
    "build_item_draft",
    "build_receipt_draft",
    "build_payment_draft",
    "match_invoice_to_po_and_receipt",
    "resolve_invoice_mismatch",
    "build_award_quote",
    "forecast_spend",
    "forecast_supplier_performance",
    "forecast_inventory",
    "get_help",
    "review_rfq",
    "review_quote",
    "review_po",
    "review_invoice",
]
