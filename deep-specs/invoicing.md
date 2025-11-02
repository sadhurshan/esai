# Invoicing (Tracking) — Deep Spec

## Data Model
- Invoice: id, company_id, po_id, number, issue_date, due_date, currency, subtotal, tax, total, status enum(pending,paid,overdue,disputed).
- InvoiceLine: invoice_id, po_line_id, qty, unit_price, line_total.
- InvoiceMatch: invoice_id, result enum(matched,qty_mismatch,price_mismatch,unmatched), details_json.

## API
POST /pos/{id}/invoices; GET /invoices; PATCH /invoices/{id} status.

## UI
- Invoice list (status filters); detail with match panel.

## Tests
- Match marks price variance with correct enum.
