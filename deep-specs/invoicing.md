# Invoicing — Deep Spec (Supplier Authored)

## Data Model
- **Invoice**: `id`, `company_id`, `supplier_company_id`, `purchase_order_id`, `number`, `issue_date`, `due_date`, `currency`, `subtotal_minor`, `tax_minor`, `total_minor`, `status` enum (`draft`, `submitted`, `buyer_review`, `approved`, `rejected`, `paid`), `created_by_type` enum (`buyer`, `supplier`), `created_by_id`, `submitted_at`, `reviewed_at`, `reviewed_by_id`, `review_note`, `payment_reference`, `matched_status`, timestamps, soft delete.
- **InvoiceLine**: `id`, `invoice_id`, `po_line_id`, `description`, `qty`, `uom`, `unit_price_minor`, `tax_code_id`, `line_total_minor`.
- **InvoiceAttachment**: `id`, `invoice_id`, `document_id`, `kind` (`supporting`, `credit`, etc.), metadata, virus-scan status.
- **InvoiceMatch**: `invoice_id`, `result` enum (`matched`, `qty_mismatch`, `price_mismatch`, `unmatched`), `details_json`, `evaluated_at`.
- **Events/Audit**: every transition appends `invoice_events` entry (`action`, `actor_type`, `actor_id`, `notes`, `occurred_at`).
- **TODO:** Confirm supplier upload allowance (count + MB per invoice) so attachment validation and storage planning can be finalized.

## API
- **Supplier (auth scope: supplier persona + invited PO)**  
	- `GET /supplier/invoices` list with status filters and search by PO number.  
	- `POST /supplier/purchase-orders/{po}/invoices` create draft tied to PO.  
	- `PUT /supplier/invoices/{invoice}` update draft fields/lines/attachments.  
	- `POST /supplier/invoices/{invoice}/submit` transitions draft → submitted, records note, locks editing.
- **Buyer (auth scope: buyer finance roles)**  
	- `GET /invoices` existing endpoint gains filters for `created_by_type`, `status`, `supplier_company_id`.  
	- `GET /invoices/{invoice}` returns timeline, attachments, match preview.  
	- `POST /invoices/{invoice}/approve` sets status → approved, records `review_note`, kicks off payment workflow hooks.  
	- `POST /invoices/{invoice}/reject` sets status → rejected, captures reason, reopens supplier editing, notifies supplier.  
	- `POST /invoices/{invoice}/request-changes` optional intermediate that keeps status `buyer_review` but pushes comment to supplier.  
	- `PATCH /invoices/{invoice}/mark-paid` updates status → paid, sets `payment_reference`. 
- All endpoints enforce `BelongsToCompany` scoping and emit audit events; submission/approval actions queue notifications + webhooks per notification spec.

## UI
- **Supplier Portal**  
	- `resources/js/pages/suppliers/invoices/index.tsx`: list with status pills, filters by PO, quick view of buyer feedback.  
	- `.../create.tsx` + `.../edit.tsx`: multi-step wizard (header, line items, tax, attachments) with autosave indicator, validation, and submission confirmation modal.  
	- Detail view shows timeline, buyer comments, and resubmission controls when rejected.
- **Buyer Workspace**  
	- Invoice list highlights `supplier_submission` badge, provides filters for review queue, and surfaces column for `created_by`.  
	- Detail page adds review drawer containing Approve/Reject/Request changes buttons, comment box, audit timeline, and match summary vs PO + GRNs.  
	- PO detail sidebar shows “Supplier invoice pending review” chip with deep link.  
- Skeleton states, empty states (“No supplier invoices yet”), and error toasts follow shared UI patterns; all actions wired through Form helpers to keep verbs accurate.

## Tests
- **Backend (Pest)**: supplier create/submit success, unauthorized supplier editing other company invoice (403), buyer approve path, buyer reject path reopens supplier editing, audit log entries per transition, InvoiceMatch tolerance cases.  
- **Frontend**: React tests for supplier form validation, buyer review component actions, status badge rendering.  
- **Playwright**: end-to-end scenario for draft → submit → buyer approve, plus rejection/resubmit loop.  
- **Seeders/Storybook**: fixture invoices for each status to support visual QA.
