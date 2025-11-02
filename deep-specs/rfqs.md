# RFQs — Deep Spec

## Scope & Goals
Create/publish RFQs (direct or open bidding), manage items, clarifications, invitations, and versions.

## Data Model
- Rfq: id, company_id, title, method enum(cnc, sheet_metal, injection_molding, 3d_printing, casting, other), material, tolerance, finish, quantity_total, delivery_location, incoterm, currency, notes, open_bidding boolean, status enum(draft,open,closed,awarded,cancelled), publish_at, due_at, close_at, rfq_version int, attachments_count, meta(json).
- RfqItem: rfq_id, line_no, part_number, description, qty, uom, target_price, cad_doc_id (documents morph), specs_json.
- RfqInvitation: rfq_id, supplier_id, invited_by, invited_at, status enum(pending,accepted,declined).
- RfqClarification: rfq_id, user_id, message, attachments, created_at.
- Indexes: FULLTEXT(rfqs.title), rfqs(status, open_bidding, due_at), rfq_items(rfq_id, line_no).

## API Surface
GET /rfqs — filters: status, open_bidding, due_range, method, material, text; sort by created_at|due_at.
POST /rfqs — validates fields; stores attachments as documents; default status=draft.
PUT /rfqs/{id} — version bumps on structural change.
POST /rfqs/{id}/publish — transitions to open with due_at required; queues notifications.
POST /rfqs/{id}/invite — suppliers[] unique per rfq.
GET /rfqs/{id}/clarifications; POST /rfqs/{id}/clarifications.
POST /rfqs/{id}/cancel — audit + notifications.

## UI States
- RFQ List: filters + badges for status; table with due chip; skeleton/empty.
- RFQ Create (Wizard): Step1 Specs → Step2 Items → Step3 Attachments → Step4 Review/Publish.
- RFQ Detail: Tabs Overview, Items, Clarifications, Documents, Audit.

## Workflows
- Open bidding RFQs appear in supplier inboxes.
- Deadlines: due_at must be in future; close_at = due_at by default.

## Notifications
- RFQ published; invitation sent; clarification posted; RFQ cancelled/closed.

## Permissions
- buyer_* create/update/publish/cancel within company.
- supplier_* can view only if invited or open_bidding.

## Tests
- Publish enforces due_at; invitations unique; list paginated & tenant-scoped.
