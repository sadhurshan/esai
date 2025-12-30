1. Add new workspace tool names + contracts

Prompt:

Add new workspace tool names to the shared tool registry (OpenAPI + AiChatToolCall + any TS enums) for:
workspace.search_pos, workspace.get_po, workspace.search_receipts, workspace.get_receipt, workspace.search_invoices, workspace.get_invoice, workspace.search_payments, workspace.get_payment, workspace.search_contracts, workspace.get_contract.
Follow the pattern used by workspace.search_rfqs and workspace.get_rfq. Ensure each tool has a clear JSON parameter schema (query, status[], date_from/date_to, limit, cursor/id).

2. Implement PO tools in WorkspaceToolResolver

Prompt:

In app/Services/Ai/WorkspaceToolResolver.php, implement handleSearchPos() and handleGetPo().
search_pos should support query, status[], limit and return items plus status_counts.
get_po should return PO header + first 10 line items + totals + supplier basics.
Ensure tenant scoping, sanitizeLimit, sanitizeStatuses, and normalized timestamps.

3. Implement receiving tools (receipts)

Prompt:

In WorkspaceToolResolver, implement handleSearchReceipts() and handleGetReceipt() with tenant scoping.
Return: receipt number, PO reference, supplier name, received date, status, total qty received, and list of first 10 receipt lines.

4. Implement invoice tools

Prompt:

In WorkspaceToolResolver, implement handleSearchInvoices() and handleGetInvoice().
Invoice result should include: invoice number, supplier, status, total, currency, due date, PO link, and exceptions flags if available (e.g., “missing_po”, “qty_mismatch”, “price_mismatch”).

Status: DONE (2025-12-25)

5. Implement payment + contracts tools

Prompt:

In WorkspaceToolResolver, implement handleSearchPayments(), handleGetPayment(), handleSearchContracts(), handleGetContract() using existing models/policies.
Payments should include: payment reference, invoice link, amount, method, status, paid_at.
Contracts should include: contract number, supplier, start/end dates, status, key terms summary.

Status: DONE (2025-12-25)

6. Add a “procurement snapshot” aggregator tool (reduces tool spam)

Prompt:

Add workspace.procurement_snapshot tool that returns a compact dashboard JSON:
- counts of RFQs/Quotes/POs/Receipts/Invoices by status (top-level)
- latest 5 items for each (IDs + titles/numbers + dates)
Implement it in WorkspaceToolResolver so Copilot can answer broad questions with one tool call.

Status: DONE (2025-12-25)

7. Add schemas + tools for receipt and payment drafts (microservice)

Prompt:

In ai_microservice/schemas.py, add RECEIPT_DRAFT_SCHEMA and PAYMENT_DRAFT_SCHEMA.
Then implement tools in ai_microservice/tools_contract.py:
- build_receipt_draft(context, inputs) (requires po_id, received_date, line_items[])
- build_payment_draft(context, inputs) (requires invoice_id, amount, method, notes)
Register in TOOLS and add unit tests verifying required fields are respected.

Status: DONE (2025-12-25)

8. Add a deterministic 3-way match tool

Prompt:

Add INVOICE_MATCH_SCHEMA and implement match_invoice_to_po_and_receipt(context, inputs) that returns:
- matched_po_id, matched_receipt_ids[]
- mismatches[] (qty, price, tax, missing_line)
- recommendation (approve/hold) with explanation
Register as a tool and add tests with mocked context data.

Status: DONE (2025-12-25)

9. Add Laravel converters for receipt, invoice match resolution, payment

Prompt:
Create converters in app/Services/Ai/Converters/ (or Workflow converters if used in workflows):
- GoodsReceiptDraftConverter → creates receipt + receipt lines
- InvoiceMatchConverter → stores match results and sets invoice exception status
- PaymentDraftConverter → creates payment record and links invoice
Mirror validation patterns used in RfqDraftConverter and existing invoice converter code.

10. Add a procure_to_pay workflow template

Prompt:

Update config/ai_workflows.php to add template procure_to_pay with steps:
rfq_draft → compare_quotes → award_quote → po_draft → receiving_quality → receipt_draft → invoice_draft → invoice_match → payment_process
Assign realistic approval_permissions for each step (purchasing/receiving/finance scopes).
Update microservice workflow templates list to mirror it and add a contract test to prevent drift.

11. Implement drafting logic for new workflow steps (microservice)

Prompt:

In the microservice workflow engine, implement drafting handlers for:
receipt_draft, invoice_match, payment_process
Use existing step drafting patterns: step input variables + tool calls + schema validation.
Ensure each step returns structured output that the Laravel workflow step record can store.

12. Render new draft cards in chat

Prompt:

Extend the chat assistant renderer to support new draft_action types: receipt_draft, payment_draft, and invoice_match.
Add compact previews (header fields + top 5 lines + warnings).
Reuse AiDraftReviewModal for approve/reject and keep consistent UX with RFQ drafts.

Status: DONE (2025-12-26)

13. Extend WorkflowPanel for new steps

Prompt:

In resources/js/components/ai/WorkflowPanel.tsx, add render sections for:
receiving_quality, receipt_draft, invoice_match, payment_process.
Each should show: payload summary, warnings, and Approve/Reject controls.
For invoice_match, show mismatches clearly and require a confirmation checkbox before approval.

Status: DONE (2025-12-26)

14. Improve “draft vs search” routing (fix your exact issue class)

Prompt:

In ai_microservice/intent_planner.py, add a deterministic guard:
If user text starts with verbs like draft, create, make, start, prefer draft/workflow tools over search tools unless the user explicitly says “find/search/list/show”.
Add tests verifying:
- “Draft an RFQ” triggers build_rfq_draft (or clarification)
- “Show my draft RFQs” triggers workspace.search_rfqs with status filter
- “Find RFQ Rotar Blades” triggers search, not draft

Status: DONE (2025-12-26)

15. Expand function specs for all new tools/actions

Prompt:

Add function-calling specs for the new tools/actions: receipts, invoices, payments, contracts, procurement_snapshot, receipt draft, payment draft, invoice match, and procure_to_pay workflow start.
Keep parameters minimal and enforce required fields so the model can’t omit key values.

Status: DONE (2025-12-27)

16. Add E2E “Procure to Pay” test

Prompt:

Create a Pest test tests/Feature/Ai/EndToEndProcureToPayTest.php that simulates:
Draft RFQ → compare/award → PO draft approve → receipt draft approve → invoice draft approve → invoice match approve → payment approve.
Assert records exist and are linked correctly at each stage.
Include at least one mismatch scenario for invoice match and ensure workflow blocks until reviewer resolves it.

Status: DONE (2025-12-27)