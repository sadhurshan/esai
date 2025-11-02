# POs & Orders — Deep Spec

## Data Model
- PurchaseOrder: id, company_id, rfq_id, quote_id, supplier_id, number, status enum(draft,sent,acknowledged,confirmed,cancelled), currency, subtotal, tax, total, incoterm, ship_to, bill_to, pdf_doc_id, acknowledged_at.
- PoLine: po_id, line_no, rfq_item_id, description, qty, uom, unit_price, line_total.
- PoChangeOrder: po_id, number, reason, changes_json, status enum(draft,approved,rejected), approved_at.
- Order: id, company_id, po_id, status enum(pending,in_production,in_transit,delivered,cancelled), timeline json.

## API
POST /quotes/{id}/award → creates PO (status=draft) + lines; optional auto-send.
POST /pos/{id}/send, POST /pos/{id}/acknowledge, POST /pos/{id}/change-orders.
GET /orders — filters by status/supplier.

## UI
- PO List & Detail (PDF preview, timeline).
- Order Detail timeline with status chips.

## Notifications
- PO sent/acknowledged/changed; order status updates.

## Tests
- Award flow creates PO & lines; acknowledge sets timestamp.
