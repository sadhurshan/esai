# Digital Twins - Intelligence Rules

## Scope
Defines the auto-linking rules and follow-up prompt triggers for Digital Twin Intelligence.

## Canonical Link
- RFQs can be linked to a Digital Twin by storing `digital_twin_id` in `rfqs.meta`.
- Linking is tenant-scoped and only applies when the RFQ and Digital Twin belong to the same company.

## Auto-Link Rules
- RFQ created from a Digital Twin:
  - Set `rfqs.meta.digital_twin_id` to the selected twin id.
  - Add or update `digital_twins.extra.linked_rfqs[]` entries with `{ rfq_id, linked_at, source: "rfq_create" }`.
- RFQ attachments:
  - If an RFQ has `digital_twin_id`, append attachments to `digital_twins.extra.linked_documents[]` with
    `{ document_id, kind, category, source: "rfq_attachment", linked_at }`.
- Quotes:
  - When a quote is submitted for an RFQ with `digital_twin_id`, append to `digital_twins.extra.linked_quotes[]`
    `{ quote_id, supplier_id, submitted_at, lead_time_days, total_price_minor, currency }`.
- Process notes:
  - Use RFQ clarifications and quote `notes` fields as process notes when linked to a Digital Twin.
  - TODO: clarify whether to include internal RFQ notes or only supplier-facing clarifications.
- QA certificates:
  - If an awarded quote exists for a linked RFQ, include the supplier's valid `SupplierDocument` certificates in
    `digital_twins.extra.linked_documents[]` with `source: "supplier_certificate"`.
- Warranty terms:
  - If a linked quote or PO contains warranty terms in `notes` or `payment_terms`, store a summary string in
    `digital_twins.extra.warranty_summary`.
  - TODO: confirm the canonical field for warranty terms.

## Follow-Up Prompt Triggers
- Warranty reminder: if `warranty_summary` exists, show a reminder prompt when the RFQ is awarded.
- Reorder prompt: if linked quotes are awarded more than once for the same part name, suggest a reorder.
- Preferred supplier prompt: if a supplier wins 2+ awards for the linked twin, suggest adding as preferred supplier.
- QA follow-up: if supplier certificates are expiring within 30 days, suggest requesting updates.

## Guardrails
- Do not auto-apply changes to the Digital Twin without user confirmation.
- Always log linking actions to audit events.
- Respect tenant scoping on all link lookups and updates.

## TODO
- Confirm how `digital_twins.extra` should be versioned and whether a separate join table is preferred.
- Confirm retention policy for linked documents and quote snapshots.
