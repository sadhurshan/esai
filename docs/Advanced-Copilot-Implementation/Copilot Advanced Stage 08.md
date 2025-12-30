1. [x] Add Item Master tools (search/get)

Prompt:

Add tool names + schemas for: workspace.search_items, workspace.get_item.
Implement in WorkspaceToolResolver:
- search_items: query, category[], uom, status[], limit → items + status_counts
- get_item: item header + specs + preferred suppliers + last purchase price (if available)
Add feature tests mirroring search_rfqs/get_rfq.

- Documented `workspace.search_items` and `workspace.get_item` in [docs/openapi/fragments/copilot.yaml](../openapi/fragments/copilot.yaml) so the Copilot contract exposes the new tool enum entries plus strict argument schemas (query/status/category/uom filters + item_id/part_number selectors).

2. [x] Add Supplier Master tools (search/get + risk snapshot)

Prompt:

Add tools: workspace.search_suppliers, workspace.get_supplier, workspace.supplier_risk_snapshot.
Implement resolver methods with tenant scoping.
supplier_risk_snapshot should return on-time %, defect %, dispute %, open POs, unpaid invoices, and a computed risk score.
Add tests for each tool.

- Documented `workspace.search_suppliers`, `workspace.get_supplier`, and `workspace.supplier_risk_snapshot` argument contracts in [docs/openapi/fragments/copilot.yaml](../openapi/fragments/copilot.yaml) and synced the compiled spec in [storage/api/contract-openapi.json](../../storage/api/contract-openapi.json) so Copilot planner + SDKs see the supplier filters and required identifiers already implemented server-side.

3. [x] Add Supplier Master tools (search/get + risk snapshot)

Prompt:

Add tools: workspace.search_suppliers, workspace.get_supplier, workspace.supplier_risk_snapshot.
Implement resolver methods with tenant scoping.
supplier_risk_snapshot should return on-time %, defect %, dispute %, open POs, unpaid invoices, and a computed risk score.
Add tests for each tool.

- Duplicate of Item 2; all supplier master schemas/tests are covered by the OpenAPI + resolver/test work noted above.

4. [x] Item create/update draft tool + converter

Prompt:

In ai_microservice/schemas.py, add ITEM_DRAFT_SCHEMA (item_code, name, uom, category, attributes/specs, preferred_suppliers[]).
In tools_contract.py, implement build_item_draft(context, inputs) and register tool.
In Laravel, add ItemDraftConverter that validates and creates/updates the Item + related preferred supplier links on approval.
Add E2E test: “Create item Rotar Blades” produces draft with correct name and item_code.

5. [x] Supplier onboarding draft tool + converter

Prompt:

Add SUPPLIER_ONBOARD_DRAFT_SCHEMA (legal_name, country, email, phone, payment_terms, tax_id, documents_needed[]).
Implement build_supplier_onboard_draft() in microservice.
Add SupplierOnboardDraftConverter that creates supplier in “pending” state and opens required document tasks.
Add tests for approval + created supplier state.

6. [x] Add policy check tool (preflight)

Prompt:

Add tool workspace.policy_check that accepts { action_type, payload, user_id } and returns { allowed, reasons[], required_approvals[], suggested_changes[] }.
Implement in Laravel using your RBAC + thresholds (e.g., PO total > X requires finance approval; supplier risk > Y requires extra approval).
Add tests for at least 3 rules.

7. [x] Integrate policy_check before draft approval

Prompt:

Before approving any draft_action, call workspace.policy_check.
If policy_check returns allowed=false, block approval and return an assistant guided_resolution with reasons and next steps.
Add tests: approving high-value PO is blocked without finance permission.

8. [x] Implement dynamic approval routing for workflows

Prompt:

Add a workflow feature: step approval permissions can be dynamic based on payload (e.g., PO total, supplier risk).
Implement a method WorkflowService::resolveRequiredApprovals(step, payload) that returns required permission scopes + approver roles.
Update workflow execution to enforce this list.
Add tests for dynamic routing.

9. [x] Add “request approval” tool

Prompt:

Add tool workspace.request_approval that creates an approval request record with { entity_type, entity_id, step_type, approver_role, message }.
Implement resolver + DB model + migration if missing.
Add UI: show “Approval requested” state in WorkflowPanel.

10. [x] Add “resolve mismatch” deterministic action

Prompt:

Add INVOICE_MISMATCH_RESOLUTION_SCHEMA and tool resolve_invoice_mismatch(context, inputs) returning recommended resolution: hold, partial approve, request credit note, adjust PO, etc.
Add Laravel converter InvoiceMismatchResolutionConverter that applies the resolution (set hold flags, create dispute task, record notes).
Add tests with mismatch scenario.

- Deterministic schema + tooling are in place: `INVOICE_MISMATCH_RESOLUTION_PAYLOAD_SCHEMA` in `ai_microservice/schemas.py`, the `resolve_invoice_mismatch()` tool contract, Laravel `InvoiceMismatchResolutionConverter`, and `tests/Feature/Ai/InvoiceMismatchResolutionConverterTest.php` cover hold/partial approval flows.

11. [x] Add dispute tools

Prompt:

Add tools: workspace.create_dispute_draft, workspace.search_disputes, workspace.get_dispute.
Implement a dispute draft action + converter to log supplier disputes linked to invoice/receipt/PO.
Add UI rendering for dispute draft cards.

12. [x] Add deep-link navigation tool

Prompt:

Add tool workspace.navigate that returns the best UI route for an intent: { module, entity_id?, action? } → { url, label, breadcrumbs[] }.
Implement mapping centrally (RFQ, Quote, PO, Receipt, Invoice, Payment, Supplier, Item).
Use this tool inside get_help to produce accurate CTAs.
Add tests for route mapping.

13. [x] Add “next_best_action” tool

Prompt:

Add tool workspace.next_best_action that accepts { user_id, context_entity? } and returns 3–5 recommended next steps with reason + deep links.
Logic should use procurement_snapshot, current workflow state, pending approvals, and holds.
Render results as an assistant card in chat + WorkflowPanel.

14. [x] Multi-intent planning + tool chaining

Prompt:

Update intent_planner.py to support multi-intent: if user asks “Draft RFQ and add 3 suppliers”, return a plan with ordered tool calls (draft RFQ → suggest suppliers → attach suppliers draft).
Add a “plan” response format: { steps:[{tool,args}] }.
Update backend to execute steps sequentially with stop-on-clarification behavior.
Add tests for a 2-step plan.

15. [x] Add disambiguation when multiple entities match

Prompt:

When a tool search returns multiple matches (e.g., “Invoice 100”), have Copilot return clarification with top 5 candidates and require user selection.
Implement a reusable EntityPicker chat bubble UI component to select one.
Add tests for “Find invoice ABC” returning clarification.

- Backend entity picker + selection routing implemented in ChatService with feature tests. Frontend EntityPicker prompt now renders in CopilotChatPanel with Vitest coverage to ensure selections send the correct context payload.

16. [x] Confirmations for risky actions

Prompt:

Add an “unsafe_action_confirmation” response type for anything that changes money/status: awarding quote, approving invoice, creating payment.
Require user to confirm with a checkbox + “Confirm” button before executing approval.
Add tests ensuring confirmation required.

- ChatService now re-tags approve-invoice, payment, and award drafts as `unsafe_action_confirmation`, returns an `unsafe_action` payload, and the Copilot chat UI renders a dedicated confirmation card with an acknowledgement checkbox before running the approval mutation (covered by new PHP + Vitest suites).

17. [x] Create a deterministic evaluation suite

Prompt:

Create tests/Feature/Ai/EvalSuiteTest.php reading scenarios from docs/ai_eval_cases.json.
Add at least 30 cases covering: draft/search/review/forecast/help/next_best_action, and your reported failures (“Draft an RFQ”, “Draft RFQ named Rotar Blades”).
Each case should assert the expected tool (or clarification) and key extracted fields (e.g., rfq_title contains “Rotar Blades”).
Make CI fail if any regression happens.

- Added docs/ai_eval_cases.json with 31 curated planner scenarios (drafts, search, reviews, forecasts, help, next-best-action) including the previously reported RFQ failures.
- Introduced tests/Feature/Ai/CopilotEvalSuiteTest.php that loads the dataset, mocks AiClient + WorkspaceToolResolver interactions, and asserts each case’s response type plus key fields so any regression fails deterministically in CI.