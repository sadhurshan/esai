"""Intent planner powered by OpenAI function-calling."""
from __future__ import annotations

import json
import logging
import os
import re
from typing import Any, Dict, List, Mapping, MutableMapping, Sequence

import httpx

LOGGER = logging.getLogger(__name__)

SYSTEM_PROMPT = (
    "You are a procurement assistant. Your job is to understand the user's intent and choose "
    "the best tool to execute it. Always produce JSON tool calls when possible. Ask clarifying "
    "questions when required information is missing. Avoid hallucination; only call tools "
    "defined in FUNCTION_SPECS. When a user requests multiple actions in a single turn, respond "
    "with a copilot.plan function call that lists the ordered steps using those same tools."
)

DEFAULT_MODEL = os.getenv("AI_INTENT_MODEL", "gpt-4.1-mini")
OPENAI_BASE_URL = os.getenv("OPENAI_BASE_URL", "https://api.openai.com/v1").rstrip("/")
OPENAI_TIMEOUT = float(os.getenv("AI_INTENT_TIMEOUT_SECONDS", "30"))
MAX_CONTEXT_MESSAGES = int(os.getenv("AI_INTENT_CONTEXT_LIMIT", "8"))

DRAFT_VERB_PATTERN = re.compile(
    r"^\s*(?:please\s+|can you\s+|could you\s+|would you\s+|help me\s+|let's\s+)?(draft|create|make|start)\b",
    re.IGNORECASE,
)
SEARCH_VERB_PATTERN = re.compile(
    r"^\s*(?:please\s+|can you\s+|could you\s+|would you\s+|help me\s+|let's\s+)?(find|search|list|show)\b",
    re.IGNORECASE,
)
DRAFT_TOOL_KEYWORDS = [
    (re.compile(r"\brfq(s)?\b", re.IGNORECASE), "build_rfq_draft"),
    (re.compile(r"\bpo\b|purchase order", re.IGNORECASE), "draft_purchase_order"),
    (re.compile(r"\binvoice(s)?\b", re.IGNORECASE), "build_invoice_draft"),
    (re.compile(r"\bitem(s)?\b|\bpart(s)?\b|\bsku\b", re.IGNORECASE), "build_item_draft"),
    (re.compile(r"(onboard|add|setup)\s+(a\s+)?(supplier|vendor)", re.IGNORECASE), "build_supplier_onboard_draft"),
]
WORKSPACE_SEARCH_PREFIX = "workspace.search_"
PLAN_FUNCTION_NAME = "copilot.plan"

FUNCTION_SPECS: List[Dict[str, Any]] = [
    {
        "name": "build_rfq_draft",
        "description": "Create a structured RFQ draft including title, scope, key requirements, and line items.",
        "parameters": {
            "type": "object",
            "properties": {
                "rfq_title": {
                    "type": "string",
                    "description": "Title of the RFQ that buyers will recognize.",
                },
                "scope_summary": {
                    "type": "string",
                    "description": "Short paragraph describing what is being sourced and why.",
                },
                "items": {
                    "type": "array",
                    "description": "Line items or parts that should appear in the RFQ",
                    "items": {
                        "type": "object",
                        "properties": {
                            "part_id": {"type": "string", "description": "Internal part or item id."},
                            "description": {"type": "string"},
                            "quantity": {"type": "number"},
                            "target_date": {"type": "string", "description": "Desired delivery date"},
                        },
                    },
                },
                "questions_for_suppliers": {
                    "type": "array",
                    "description": "Specific clarifications suppliers must answer.",
                    "items": {"type": "string"},
                },
            },
            "required": ["rfq_title"],
        },
    },
    {
        "name": "build_supplier_message",
        "description": "Draft a supplier-facing email summarizing goals, tone, and negotiation asks.",
        "parameters": {
            "type": "object",
            "properties": {
                "supplier_name": {"type": "string", "description": "Supplier contact or company name."},
                "goal": {"type": "string", "description": "What we need from the supplier."},
                "tone": {"type": "string", "description": "Communication tone such as professional or friendly."},
                "subject": {"type": "string", "description": "Email subject line."},
                "constraints": {"type": "string", "description": "Key constraints to mention."},
                "negotiation_points": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Bulleted negotiation talking points.",
                },
            },
            "required": ["supplier_name", "goal"],
        },
    },
    {
        "name": "compare_quotes",
        "description": "Compare multiple quotes and return a ranked recommendation including pricing context.",
        "parameters": {
            "type": "object",
            "properties": {
                "rfq_id": {"type": "string", "description": "Related RFQ identifier if available."},
                "quotes": {
                    "type": "array",
                    "description": "Quotes to rank with price, lead time, and quality info.",
                    "items": {
                        "type": "object",
                        "properties": {
                            "supplier_id": {"type": "string"},
                            "supplier_name": {"type": "string"},
                            "price": {"type": "number"},
                            "lead_time_days": {"type": "number"},
                            "quality_rating": {"type": "number", "description": "Value 0-1"},
                            "risk_score": {"type": "number", "description": "Value 0-1"},
                        },
                        "required": [
                            "supplier_id",
                            "supplier_name",
                            "price",
                            "lead_time_days",
                            "quality_rating",
                            "risk_score",
                        ],
                    },
                },
                "supplier_risk_scores": {
                    "type": "object",
                    "description": "Optional overrides for supplier risk score keyed by supplier_id.",
                    "additionalProperties": {"type": "number"},
                },
            },
            "required": ["quotes"],
        },
    },
    {
        "name": "build_award_quote",
        "description": "Prepare an award recommendation for a selected supplier quote including justification.",
        "parameters": {
            "type": "object",
            "properties": {
                "rfq_id": {"type": "string", "description": "Related RFQ id."},
                "selected_quote_id": {"type": "string", "description": "Quote id that wins."},
                "supplier_id": {"type": "string", "description": "Supplier receiving the award."},
                "delivery_date": {"type": "string", "description": "Requested delivery date."},
                "justification": {"type": "string", "description": "Reason for selection."},
                "terms": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Commercial terms or contingencies.",
                },
            },
            "required": ["supplier_id", "selected_quote_id"],
        },
    },
    {
        "name": "draft_purchase_order",
        "description": "Draft a purchase order with supplier info, line items, schedule, and terms.",
        "parameters": {
            "type": "object",
            "properties": {
                "supplier": {
                    "type": "object",
                    "description": "Supplier to issue the PO to.",
                    "properties": {
                        "supplier_id": {"type": "string"},
                        "name": {"type": "string"},
                        "contact": {"type": "string"},
                    },
                },
                "rfq_id": {"type": "string"},
                "currency": {"type": "string"},
                "line_items": {
                    "type": "array",
                    "items": {
                        "type": "object",
                        "properties": {
                            "item_id": {"type": "string"},
                            "description": {"type": "string"},
                            "quantity": {"type": "number"},
                            "unit_price": {"type": "number"},
                        },
                    },
                },
                "delivery_schedule": {
                    "type": "array",
                    "items": {
                        "type": "object",
                        "properties": {
                            "ship_date": {"type": "string"},
                            "quantity": {"type": "number"},
                        },
                    },
                },
            },
            "required": ["supplier", "line_items"],
        },
    },
    {
        "name": "build_invoice_draft",
        "description": "Draft an invoice matched to a purchase order, including key dates and line items.",
        "parameters": {
            "type": "object",
            "properties": {
                "po_id": {"type": "string", "description": "Purchase order identifier."},
                "invoice_date": {"type": "string", "description": "Invoice issue date."},
                "due_date": {"type": "string", "description": "Payment due date."},
                "line_items": {
                    "type": "array",
                    "items": {
                        "type": "object",
                        "properties": {
                            "description": {"type": "string"},
                            "quantity": {"type": "number"},
                            "unit_price": {"type": "number"},
                        },
                    },
                },
                "notes": {"type": "string", "description": "Additional instructions or references."},
            },
            "required": ["po_id", "line_items"],
        },
    },
    {
        "name": "build_item_draft",
        "description": "Draft an inventory item definition including specs, attributes, and preferred suppliers.",
        "parameters": {
            "type": "object",
            "properties": {
                "item_code": {
                    "type": "string",
                    "description": "Unique part number or SKU for the item.",
                },
                "name": {
                    "type": "string",
                    "description": "Human-readable item name.",
                },
                "uom": {
                    "type": "string",
                    "description": "Unit of measure such as ea, kg, ft.",
                },
                "status": {
                    "type": "string",
                    "enum": ["active", "inactive"],
                    "description": "Lifecycle state for the item.",
                },
                "category": {
                    "type": "string",
                    "description": "Commodity or item category.",
                },
                "description": {
                    "type": "string",
                    "description": "Short description for the catalog entry.",
                },
                "spec": {
                    "type": "string",
                    "description": "Primary specification, drawing, or revision notes.",
                },
                "attributes": {
                    "type": "object",
                    "description": "Key/value attribute pairs for materials, finish, etc.",
                    "additionalProperties": True,
                },
                "preferred_suppliers": {
                    "type": "array",
                    "description": "Ranked list of suppliers aligned to this item.",
                    "items": {
                        "type": "object",
                        "properties": {
                            "supplier_id": {
                                "type": ["string", "integer"],
                                "description": "Internal supplier identifier if known.",
                            },
                            "name": {
                                "type": "string",
                                "description": "Supplier name.",
                            },
                            "priority": {
                                "type": "integer",
                                "minimum": 1,
                                "maximum": 5,
                                "description": "Priority ranking where 1 is primary.",
                            },
                            "notes": {
                                "type": "string",
                                "description": "Rationale or sourcing guidance.",
                            },
                        },
                        "required": ["name"],
                        "additionalProperties": False,
                    },
                },
            },
            "required": ["item_code", "name", "uom"],
            "additionalProperties": False,
        },
    },
    {
        "name": "build_supplier_onboard_draft",
        "description": "Draft a supplier onboarding packet with contact data, payment terms, and required documents.",
        "parameters": {
            "type": "object",
            "properties": {
                "legal_name": {
                    "type": "string",
                    "description": "Supplier legal entity name.",
                },
                "country": {
                    "type": "string",
                    "description": "Country code or country name (ISO alpha-2 preferred).",
                },
                "email": {
                    "type": "string",
                    "description": "Primary contact email for onboarding.",
                },
                "phone": {
                    "type": "string",
                    "description": "Primary contact phone including country code.",
                },
                "payment_terms": {
                    "type": "string",
                    "description": "Negotiated payment terms (e.g., Net 30).",
                },
                "tax_id": {
                    "type": "string",
                    "description": "Tax identifier, VAT, or EIN provided by the supplier.",
                },
                "website": {
                    "type": "string",
                    "description": "Optional supplier website URL.",
                },
                "address": {
                    "type": "string",
                    "description": "Headquarters mailing address.",
                },
                "documents_needed": {
                    "type": "array",
                    "description": "List of certificates or paperwork required before approval.",
                    "items": {
                        "type": "object",
                        "properties": {
                            "type": {
                                "type": "string",
                                "description": "Document code such as iso9001, insurance, nda.",
                            },
                            "description": {
                                "type": "string",
                                "description": "Reason the document is required.",
                            },
                            "required": {
                                "type": "boolean",
                                "description": "Whether the document blocks approval if missing.",
                            },
                            "due_in_days": {
                                "type": "integer",
                                "description": "Days until the document must be provided.",
                            },
                            "priority": {
                                "type": "integer",
                                "description": "Relative priority (1-5).",
                            },
                            "notes": {
                                "type": "string",
                                "description": "Internal guidance for the reviewer.",
                            },
                        },
                        "required": ["type"],
                        "additionalProperties": False,
                    },
                },
                "notes": {
                    "type": "string",
                    "description": "Internal onboarding notes or reminders.",
                },
            },
            "required": [
                "legal_name",
                "country",
                "email",
                "phone",
                "payment_terms",
                "tax_id",
                "documents_needed",
            ],
            "additionalProperties": False,
        },
    },
    {
        "name": "get_help",
        "description": "Share help center guidance, steps, and references for a procurement task.",
        "parameters": {
            "type": "object",
            "properties": {
                "topic": {"type": "string", "description": "Topic or question the user asked."},
                "locale": {"type": "string", "description": "Preferred locale such as en or es."},
            },
            "required": ["topic"],
        },
    },
    {
        "name": "workspace.search_receipts",
        "description": "Search goods receipts by receipt number, supplier, or linked PO context.",
        "parameters": {
            "type": "object",
            "properties": {
                "query": {
                    "type": "string",
                    "description": "Keyword applied to receipt numbers, PO numbers, or supplier names.",
                },
                "statuses": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Optional list of receipt workflow statuses to include.",
                },
                "date_from": {
                    "type": "string",
                    "format": "date-time",
                    "description": "ISO 8601 timestamp for the earliest receipt to include.",
                },
                "date_to": {
                    "type": "string",
                    "format": "date-time",
                    "description": "ISO 8601 timestamp for the latest receipt to include.",
                },
                "limit": {
                    "type": "integer",
                    "minimum": 1,
                    "maximum": 50,
                    "default": 10,
                    "description": "Maximum number of receipts to return (max 50).",
                },
                "cursor": {
                    "type": "string",
                    "description": "Opaque cursor from the previous search_receipts call.",
                },
            },
            "required": [],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.get_receipt",
        "description": "Fetch a specific goods receipt by internal id or receipt number.",
        "parameters": {
            "type": "object",
            "properties": {
                "receipt_id": {
                    "type": "integer",
                    "minimum": 1,
                    "description": "Primary key of the goods receipt to fetch.",
                },
                "receipt_number": {
                    "type": "string",
                    "description": "External receipt number if the id is unknown.",
                },
            },
            "anyOf": [
                {"required": ["receipt_id"]},
                {"required": ["receipt_number"]},
            ],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.search_invoices",
        "description": "Search invoices by number, supplier, status, or PO reference.",
        "parameters": {
            "type": "object",
            "properties": {
                "query": {
                    "type": "string",
                    "description": "Keyword filter applied to invoice numbers, suppliers, or PO numbers.",
                },
                "statuses": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Optional invoice status whitelist.",
                },
                "date_from": {
                    "type": "string",
                    "format": "date-time",
                    "description": "Only include invoices updated on/after this timestamp.",
                },
                "date_to": {
                    "type": "string",
                    "format": "date-time",
                    "description": "Only include invoices updated on/before this timestamp.",
                },
                "limit": {
                    "type": "integer",
                    "minimum": 1,
                    "maximum": 50,
                    "default": 10,
                    "description": "Maximum number of invoices to return (max 50).",
                },
                "cursor": {
                    "type": "string",
                    "description": "Opaque pagination cursor from a previous search.",
                },
            },
            "required": [],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.get_invoice",
        "description": "Retrieve an invoice by id or invoice number including totals and match data.",
        "parameters": {
            "type": "object",
            "properties": {
                "invoice_id": {
                    "type": "integer",
                    "minimum": 1,
                    "description": "Internal invoice id to load.",
                },
                "invoice_number": {
                    "type": "string",
                    "description": "External invoice number when id is unavailable.",
                },
            },
            "anyOf": [
                {"required": ["invoice_id"]},
                {"required": ["invoice_number"]},
            ],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.search_payments",
        "description": "Search AP payments using references, statuses, or paid dates.",
        "parameters": {
            "type": "object",
            "properties": {
                "query": {
                    "type": "string",
                    "description": "Keyword filter applied to payment references, invoice numbers, or supplier names.",
                },
                "statuses": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Optional payment status filter such as pending or paid.",
                },
                "date_from": {
                    "type": "string",
                    "format": "date-time",
                    "description": "Filter payments created/paid on or after this ISO timestamp.",
                },
                "date_to": {
                    "type": "string",
                    "format": "date-time",
                    "description": "Filter payments created/paid on or before this ISO timestamp.",
                },
                "limit": {
                    "type": "integer",
                    "minimum": 1,
                    "maximum": 50,
                    "default": 10,
                    "description": "Maximum number of payments to return (max 50).",
                },
                "cursor": {
                    "type": "string",
                    "description": "Opaque cursor for pagination.",
                },
            },
            "required": [],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.get_payment",
        "description": "Retrieve a payment by id or payment reference with invoice linkage.",
        "parameters": {
            "type": "object",
            "properties": {
                "payment_id": {
                    "type": "integer",
                    "minimum": 1,
                    "description": "Internal payment id.",
                },
                "payment_reference": {
                    "type": "string",
                    "description": "Accounting or bank reference if the id is unknown.",
                },
            },
            "anyOf": [
                {"required": ["payment_id"]},
                {"required": ["payment_reference"]},
            ],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.search_contracts",
        "description": "Search supplier or PO-linked contracts stored in the documents module.",
        "parameters": {
            "type": "object",
            "properties": {
                "query": {
                    "type": "string",
                    "description": "Keyword filter applied to contract numbers, filenames, or supplier names.",
                },
                "statuses": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Optional contract status whitelist such as active or expired.",
                },
                "date_from": {
                    "type": "string",
                    "format": "date-time",
                    "description": "Created-on lower bound for contracts to include.",
                },
                "date_to": {
                    "type": "string",
                    "format": "date-time",
                    "description": "Created-on upper bound for contracts to include.",
                },
                "limit": {
                    "type": "integer",
                    "minimum": 1,
                    "maximum": 50,
                    "default": 10,
                    "description": "Maximum number of contracts to return (max 50).",
                },
                "cursor": {
                    "type": "string",
                    "description": "Opaque pagination cursor for search_contracts.",
                },
            },
            "required": [],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.get_contract",
        "description": "Load a contract by id or contract_number including metadata and links.",
        "parameters": {
            "type": "object",
            "properties": {
                "contract_id": {
                    "type": "integer",
                    "minimum": 1,
                    "description": "Internal contract document id.",
                },
                "contract_number": {
                    "type": "string",
                    "description": "External contract number if id is unavailable.",
                },
            },
            "anyOf": [
                {"required": ["contract_id"]},
                {"required": ["contract_number"]},
            ],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.search_items",
        "description": "Search item master records by part number, description, category, or UOM.",
        "parameters": {
            "type": "object",
            "properties": {
                "query": {
                    "type": "string",
                    "description": "Keyword filter applied to part numbers, names, or descriptions.",
                },
                "statuses": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Optional item status filter such as active or inactive.",
                },
                "categories": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Optional category whitelist to narrow the search.",
                },
                "uom": {
                    "type": "string",
                    "description": "Unit of measure filter like ea, kg, or set.",
                },
                "limit": {
                    "type": "integer",
                    "minimum": 1,
                    "maximum": 25,
                    "default": 5,
                    "description": "Maximum number of items to return (max 25).",
                },
            },
            "required": [],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.get_item",
        "description": "Fetch a single item by id or SKU including specs, suppliers, and last purchase data.",
        "parameters": {
            "type": "object",
            "properties": {
                "item_id": {
                    "type": "integer",
                    "minimum": 1,
                    "description": "Internal item or part identifier.",
                },
                "part_number": {
                    "type": "string",
                    "description": "External part number or SKU.",
                },
                "sku": {
                    "type": "string",
                    "description": "Alternate SKU reference if part_number is unknown.",
                },
            },
            "anyOf": [
                {"required": ["item_id"]},
                {"required": ["part_number"]},
                {"required": ["sku"]},
            ],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.search_suppliers",
        "description": "Search supplier master records by name, location, capabilities, or certifications.",
        "parameters": {
            "type": "object",
            "properties": {
                "query": {
                    "type": "string",
                    "description": "Keyword filter applied to supplier names, locations, or emails.",
                },
                "statuses": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Optional workflow statuses such as approved or pending.",
                },
                "methods": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Manufacturing process capabilities to require (e.g., CNC Milling).",
                },
                "materials": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Material expertise filters such as Titanium or ABS.",
                },
                "finishes": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Surface finish capabilities to match.",
                },
                "tolerances": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Tolerance profiles such as ISO 2768.",
                },
                "industries": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Target industries served (e.g., aerospace, medical).",
                },
                "certifications": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Certification types such as iso9001 or itar.",
                },
                "country": {
                    "type": "string",
                    "description": "Country code filter like US or MX.",
                },
                "city": {
                    "type": "string",
                    "description": "City filter for local suppliers.",
                },
                "rating_min": {
                    "type": "number",
                    "minimum": 0,
                    "maximum": 5,
                    "description": "Minimum average rating to include.",
                },
                "lead_time_max": {
                    "type": "integer",
                    "minimum": 1,
                    "description": "Maximum lead time in days.",
                },
                "limit": {
                    "type": "integer",
                    "minimum": 1,
                    "maximum": 25,
                    "default": 5,
                    "description": "Maximum number of suppliers to return (max 25).",
                },
            },
            "required": [],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.get_supplier",
        "description": "Retrieve a supplier profile with capabilities, certifications, and activity feeds.",
        "parameters": {
            "type": "object",
            "properties": {
                "supplier_id": {
                    "type": "integer",
                    "minimum": 1,
                    "description": "Internal supplier identifier.",
                },
                "name": {
                    "type": "string",
                    "description": "Supplier name when the id is unknown.",
                },
            },
            "anyOf": [
                {"required": ["supplier_id"]},
                {"required": ["name"]},
            ],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.supplier_risk_snapshot",
        "description": "Return KPIs such as on-time delivery, defects, disputes, and risk score for a supplier.",
        "parameters": {
            "type": "object",
            "properties": {
                "supplier_id": {
                    "type": "integer",
                    "minimum": 1,
                    "description": "Supplier id to analyze.",
                },
            },
            "required": ["supplier_id"],
            "additionalProperties": False,
        },
    },
    {
        "name": "workspace.procurement_snapshot",
        "description": "Return a compact dashboard of RFQs, quotes, POs, receipts, and invoices in one call.",
        "parameters": {
            "type": "object",
            "properties": {
                "limit": {
                    "type": "integer",
                    "minimum": 1,
                    "maximum": 10,
                    "default": 5,
                    "description": "Number of latest records to pull per module (max 10).",
                },
            },
            "required": [],
            "additionalProperties": False,
        },
    },
    {
        "name": "build_receipt_draft",
        "description": "Draft a receiving report for a purchase order with received quantities and issues.",
        "parameters": {
            "type": "object",
            "properties": {
                "po_id": {
                    "type": "string",
                    "description": "Purchase order identifier the receipt belongs to.",
                },
                "received_date": {
                    "type": "string",
                    "format": "date",
                    "description": "ISO 8601 date when the goods were received or inspected.",
                },
                "reference": {
                    "type": "string",
                    "description": "Optional ASN or shipment reference.",
                },
                "inspected_by": {
                    "type": "string",
                    "description": "Receiving agent responsible for the inspection.",
                },
                "status": {
                    "type": "string",
                    "description": "Workflow status such as draft or submitted.",
                },
                "line_items": {
                    "type": "array",
                    "description": "Detailed receipt lines with accepted and rejected quantities.",
                    "items": {
                        "type": "object",
                        "properties": {
                            "po_line_id": {
                                "type": "string",
                                "description": "Source PO line identifier.",
                            },
                            "line_number": {
                                "type": "integer",
                                "minimum": 1,
                                "description": "Display order for the receipt line.",
                            },
                            "description": {
                                "type": "string",
                                "description": "What was received on this line.",
                            },
                            "uom": {
                                "type": "string",
                                "description": "Unit of measure such as ea or kg.",
                            },
                            "expected_qty": {
                                "type": "number",
                                "description": "Quantity ordered on the PO for comparison.",
                            },
                            "received_qty": {
                                "type": "number",
                                "description": "Quantity physically received.",
                            },
                            "accepted_qty": {
                                "type": "number",
                                "description": "Quantity approved after inspection.",
                            },
                            "rejected_qty": {
                                "type": "number",
                                "description": "Quantity rejected for defects.",
                            },
                            "issues": {
                                "type": "array",
                                "items": {"type": "string"},
                                "description": "Any quality issues detected for this line.",
                            },
                            "notes": {
                                "type": "string",
                            },
                        },
                        "required": ["po_line_id", "received_qty"],
                        "additionalProperties": False,
                    },
                },
                "notes": {
                    "type": "string",
                    "description": "General receiving observations.",
                },
            },
            "required": ["po_id", "received_date", "line_items"],
            "additionalProperties": False,
        },
    },
    {
        "name": "build_payment_draft",
        "description": "Draft a payment proposal for an invoice with method, amount, and schedule.",
        "parameters": {
            "type": "object",
            "properties": {
                "invoice_id": {
                    "type": "string",
                    "description": "Invoice identifier the payment will settle.",
                },
                "amount": {
                    "type": "number",
                    "description": "Payment amount in major currency units.",
                },
                "currency": {
                    "type": "string",
                    "description": "ISO currency code (defaults to USD).",
                },
                "payment_method": {
                    "type": "string",
                    "description": "Method such as ach, wire, or check.",
                },
                "scheduled_date": {
                    "type": "string",
                    "format": "date",
                    "description": "Date the payment should be executed.",
                },
                "reference": {
                    "type": "string",
                    "description": "Reference or memo for the payment.",
                },
                "notes": {
                    "type": "string",
                },
            },
            "required": ["invoice_id", "amount", "payment_method"],
            "additionalProperties": False,
        },
    },
    {
        "name": "match_invoice_to_po_and_receipt",
        "description": "Perform a deterministic three-way match across invoice, PO, and receipts.",
        "parameters": {
            "type": "object",
            "properties": {
                "invoice_id": {
                    "type": "string",
                    "description": "Invoice identifier being reviewed.",
                },
                "po_id": {
                    "type": "string",
                    "description": "Purchase order identifier for comparison.",
                },
                "receipt_ids": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Optional list of goods receipt ids to include in the match.",
                },
                "invoice_lines": {
                    "type": "array",
                    "description": "Invoice line details with qty, price, and tax.",
                    "items": {
                        "type": "object",
                        "properties": {
                            "line_reference": {"type": "string"},
                            "qty": {"type": "number"},
                            "unit_price": {"type": "number"},
                            "tax_rate": {"type": "number"},
                        },
                        "required": ["line_reference", "qty", "unit_price"],
                        "additionalProperties": False,
                    },
                },
                "po_lines": {
                    "type": "array",
                    "description": "PO baseline lines for comparison.",
                    "items": {
                        "type": "object",
                        "properties": {
                            "line_reference": {"type": "string"},
                            "qty": {"type": "number"},
                            "unit_price": {"type": "number"},
                            "tax_rate": {"type": "number"},
                        },
                        "required": ["line_reference", "qty", "unit_price"],
                        "additionalProperties": False,
                    },
                },
                "receipt_lines": {
                    "type": "array",
                    "description": "Received line quantities to validate against.",
                    "items": {
                        "type": "object",
                        "properties": {
                            "line_reference": {"type": "string"},
                            "qty": {"type": "number"},
                        },
                        "required": ["line_reference", "qty"],
                        "additionalProperties": False,
                    },
                },
            },
            "required": ["invoice_id", "po_id"],
            "additionalProperties": False,
        },
    },
    {
        "name": "resolve_invoice_mismatch",
        "description": "Recommend the best resolution for invoice mismatches including hold or partial approval steps.",
        "parameters": {
            "type": "object",
            "properties": {
                "invoice_id": {
                    "type": "string",
                    "description": "Invoice identifier that needs a resolution plan.",
                },
                "match_result": {
                    "type": "object",
                    "description": "Output from match_invoice_to_po_and_receipt for additional context.",
                    "additionalProperties": True,
                },
                "mismatches": {
                    "type": "array",
                    "description": "Explicit mismatch entries if match_result is unavailable.",
                    "items": {
                        "type": "object",
                        "properties": {
                            "type": {"type": "string"},
                            "line_reference": {"type": "string"},
                            "severity": {"type": "string"},
                            "detail": {"type": "string"},
                        },
                        "additionalProperties": True,
                    },
                },
                "preferred_resolution": {
                    "type": "string",
                    "description": "Preferred resolution such as hold, partial_approve, or request_credit_note.",
                },
                "summary": {
                    "type": "string",
                    "description": "Optional summary to seed the recommendation.",
                },
                "reason_codes": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Optional list of reason codes gathered by the user.",
                },
                "notes": {
                    "type": "array",
                    "items": {"type": "string"},
                    "description": "Additional reviewer notes to include in the decision.",
                },
            },
            "required": ["invoice_id"],
            "additionalProperties": False,
        },
    },
    {
        "name": "start_procure_to_pay_workflow",
        "description": "Kick off the procure-to-pay workflow template (RFQ through payment).",
        "parameters": {
            "type": "object",
            "properties": {
                "goal": {
                    "type": "string",
                    "description": "Business objective or context for the workflow plan.",
                },
                "rfq_id": {
                    "type": "string",
                    "description": "Existing RFQ identifier to seed the workflow (optional).",
                },
                "inputs": {
                    "type": "object",
                    "description": "Additional key/value metadata for downstream workflow steps.",
                    "additionalProperties": True,
                },
            },
            "required": ["goal"],
            "additionalProperties": False,
        },
    },
    {
        "name": PLAN_FUNCTION_NAME,
        "description": "Return an ordered list of tool calls whenever the user requests multiple actions in a single turn.",
        "parameters": {
            "type": "object",
            "properties": {
                "steps": {
                    "type": "array",
                    "description": "Ordered steps that Copilot will execute.",
                    "minItems": 2,
                    "items": {
                        "type": "object",
                        "properties": {
                            "tool": {
                                "type": "string",
                                "description": "Tool name to invoke (must match FUNCTION_SPECS entries).",
                            },
                            "args": {
                                "type": "object",
                                "description": "Arguments to pass to the tool.",
                                "additionalProperties": True,
                            },
                            "target_tool": {
                                "type": "string",
                                "description": "If this step asks for clarification, which tool needs more info.",
                            },
                            "missing_args": {
                                "type": "array",
                                "items": {"type": "string"},
                                "description": "List of missing argument names.",
                            },
                            "question": {
                                "type": "string",
                                "description": "Clarification question to show the user if needed.",
                            },
                        },
                        "required": ["tool"],
                        "additionalProperties": True,
                    },
                },
            },
            "required": ["steps"],
            "additionalProperties": False,
        },
    },
]

_FUNCTION_SPEC_MAP: Dict[str, Dict[str, Any]] = {spec["name"]: spec for spec in FUNCTION_SPECS}
CLARIFICATION_QUESTIONS: Dict[tuple[str, str], str] = {
    ("build_rfq_draft", "rfq_title"): "What should be the title of the RFQ?",
    ("resolve_invoice_mismatch", "invoice_id"): "Which invoice should I resolve for you?",
}


class IntentPlannerError(RuntimeError):
    """Raised when the intent planner cannot complete a request."""


def plan_action_from_prompt(prompt: str, context: Sequence[Mapping[str, Any]]) -> Dict[str, Any]:
    """Return the best tool call or fallback reply for the given prompt."""

    normalized_prompt = (prompt or "").strip()
    if not normalized_prompt:
        return {"tool": None, "message": "I didn't catch that. Could you restate the request?"}

    messages = _build_messages(normalized_prompt, context)
    try:
        response_body = _call_openai(messages)
    except IntentPlannerError as exc:
        LOGGER.warning("intent_planner_error", extra={"error": str(exc)})
        return {"tool": None, "message": "I'm having trouble planning that. Let's try again in a moment."}

    plan = _extract_plan(response_body)
    return _apply_prompt_routing_guard(normalized_prompt, plan)


def _build_messages(prompt: str, context: Sequence[Mapping[str, Any]]) -> List[Dict[str, str]]:
    messages: List[Dict[str, str]] = [{"role": "system", "content": SYSTEM_PROMPT}]
    recent_context = list(context)[-MAX_CONTEXT_MESSAGES:]
    for entry in recent_context:
        role = str(entry.get("role") or "").strip().lower()
        content = str(entry.get("content") or "").strip()
        if role not in {"user", "assistant", "system"}:
            continue
        if not content:
            continue
        messages.append({"role": role, "content": content})
    messages.append({"role": "user", "content": prompt})
    return messages


def _call_openai(messages: List[Dict[str, str]]) -> Dict[str, Any]:
    api_key = (
        os.getenv("OPENAI_API_KEY")
        or os.getenv("AI_OPENAI_API_KEY")
        or os.getenv("AI_OPENAI_KEY")
    )
    if not api_key:
        raise IntentPlannerError("OPENAI_API_KEY is not configured")

    payload: Dict[str, Any] = {
        "model": DEFAULT_MODEL,
        "messages": messages,
        "temperature": 0.2,
        "max_tokens": 400,
        "functions": FUNCTION_SPECS,
        "function_call": "auto",
    }

    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json",
    }

    url = f"{OPENAI_BASE_URL}/chat/completions"
    try:
        response = httpx.post(url, json=payload, headers=headers, timeout=OPENAI_TIMEOUT)
    except httpx.TimeoutException as exc:  # pragma: no cover - network failure
        raise IntentPlannerError("OpenAI intent planner request timed out") from exc
    except httpx.RequestError as exc:  # pragma: no cover - network failure
        raise IntentPlannerError(f"OpenAI intent planner request failed: {exc}") from exc

    if response.status_code >= 400:
        preview = response.text[:200]
        raise IntentPlannerError(f"OpenAI intent planner error {response.status_code}: {preview}")

    try:
        return response.json()
    except json.JSONDecodeError as exc:  # pragma: no cover - unexpected payload
        raise IntentPlannerError("OpenAI intent planner response was not valid JSON") from exc


def _extract_plan(body: Mapping[str, Any]) -> Dict[str, Any]:
    choices = body.get("choices")
    if not isinstance(choices, list) or not choices:
        raise IntentPlannerError("OpenAI intent planner response missing choices")
    message = choices[0].get("message") or {}
    assistant_text = str(message.get("content") or "").strip()
    function_call = message.get("function_call")

    if isinstance(function_call, Mapping):
        tool_name = str(function_call.get("name") or "").strip()
        arguments = _parse_arguments(function_call.get("arguments"))
        if not tool_name:
            return {"tool": None, "message": assistant_text or "Let's tackle that together."}
        if tool_name not in _FUNCTION_SPEC_MAP:
            LOGGER.warning("intent_planner_unknown_tool", extra={"tool": tool_name})
            return {"tool": None, "message": assistant_text or "Let's tackle that together."}
        missing_args = _missing_required_args(tool_name, arguments)
        if missing_args:
            return _build_clarification(tool_name, missing_args, arguments)
        if tool_name == PLAN_FUNCTION_NAME:
            steps = _normalize_plan_steps(arguments.get("steps"))
            if not steps:
                return {"tool": None, "message": assistant_text or "Let's tackle that together."}
            plan: Dict[str, Any] = {"tool": "plan", "steps": steps}
            if assistant_text:
                plan["reply"] = assistant_text
            return plan
        plan: Dict[str, Any] = {"tool": tool_name, "args": arguments}
        if assistant_text:
            plan["reply"] = assistant_text
        return plan

    if assistant_text:
        return {"tool": None, "message": assistant_text}
    return {"tool": None, "message": "I'm not sure yet—could you share more details?"}


def _parse_arguments(raw_arguments: Any) -> Dict[str, Any]:
    if isinstance(raw_arguments, Mapping):
        return dict(raw_arguments)
    if not raw_arguments:
        return {}
    if isinstance(raw_arguments, str):
        try:
            return json.loads(raw_arguments) if raw_arguments.strip() else {}
        except json.JSONDecodeError:
            LOGGER.warning("intent_planner_arguments_invalid", extra={"arguments": raw_arguments[:120]})
            return {}
    return {}


def _normalize_plan_steps(raw_steps: Any) -> List[Dict[str, Any]]:
    steps: List[Dict[str, Any]] = []
    if not isinstance(raw_steps, Sequence):
        return steps
    for entry in raw_steps:
        if not isinstance(entry, Mapping):
            continue
        tool_name = str(entry.get("tool") or "").strip()
        if not tool_name:
            continue
        normalized = dict(entry)
        normalized["tool"] = tool_name
        args = entry.get("args")
        normalized["args"] = dict(args) if isinstance(args, Mapping) else {}
        steps.append(normalized)
    return steps


def _missing_required_args(tool_name: str, arguments: MutableMapping[str, Any]) -> List[str]:
    spec = _FUNCTION_SPEC_MAP.get(tool_name)
    if not spec:
        return []
    parameters = spec.get("parameters")
    if not isinstance(parameters, Mapping):
        return []
    required = parameters.get("required") or []
    if not isinstance(required, list):
        return []
    missing: List[str] = []
    for key in required:
        if not isinstance(key, str):
            continue
        value = arguments.get(key)
        if value is None:
            missing.append(key)
            continue
        if isinstance(value, str) and not value.strip():
            missing.append(key)
    return missing


def _build_clarification(
    tool_name: str,
    missing_args: List[str],
    arguments: MutableMapping[str, Any],
) -> Dict[str, Any]:
    question = _clarification_question(tool_name, missing_args[0]) if missing_args else "Could you clarify that?"
    return {
        "tool": "clarification",
        "target_tool": tool_name,
        "missing_args": list(missing_args),
        "question": question,
        "args": dict(arguments),
    }


def _clarification_question(tool_name: str, argument: str) -> str:
    template = CLARIFICATION_QUESTIONS.get((tool_name, argument))
    if template:
        return template
    readable = argument.replace("_", " ")
    return f"Could you provide the {readable} for this {tool_name.replace('_', ' ')}?"


def _apply_prompt_routing_guard(prompt: str, plan: Mapping[str, Any]) -> Dict[str, Any]:
    if not isinstance(plan, Mapping):
        return dict(plan or {})
    tool_name = plan.get("tool")
    if not isinstance(tool_name, str) or not tool_name:
        return dict(plan)
    if not _prompt_prefers_draft(prompt):
        return dict(plan)
    if not _is_workspace_search_tool(tool_name):
        return dict(plan)
    preferred_tool = _detect_draft_tool(prompt)
    if not preferred_tool:
        return dict(plan)
    missing_args = _required_args_for(preferred_tool)
    return _build_clarification(preferred_tool, missing_args or [], {})


def _prompt_prefers_draft(prompt: str) -> bool:
    normalized = (prompt or "").strip()
    if not normalized:
        return False
    if SEARCH_VERB_PATTERN.match(normalized):
        return False
    return bool(DRAFT_VERB_PATTERN.match(normalized))


def _detect_draft_tool(prompt: str) -> str | None:
    normalized = prompt or ""
    for pattern, tool_name in DRAFT_TOOL_KEYWORDS:
        if pattern.search(normalized) and tool_name in _FUNCTION_SPEC_MAP:
            return tool_name
    return None


def _is_workspace_search_tool(tool_name: str) -> bool:
    return tool_name.startswith(WORKSPACE_SEARCH_PREFIX)


def _required_args_for(tool_name: str) -> List[str]:
    spec = _FUNCTION_SPEC_MAP.get(tool_name)
    if not spec:
        return []
    parameters = spec.get("parameters")
    if not isinstance(parameters, Mapping):
        return []
    required = parameters.get("required")
    if not isinstance(required, list):
        return []
    return [arg for arg in required if isinstance(arg, str)]


__all__ = [
    "plan_action_from_prompt",
    "FUNCTION_SPECS",
    "SYSTEM_PROMPT",
]
