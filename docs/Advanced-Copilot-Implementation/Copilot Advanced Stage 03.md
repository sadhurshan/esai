1. Define the AWARD_QUOTE_SCHEMA and Build Award Quote Tool

Prompt:

"Create a new AWARD_QUOTE_SCHEMA in ai_microservice/schemas.py. It should specify rfq_id, supplier_id, selected_quote_id, justification, delivery_date, and terms as required fields.
Then in ai_microservice/tools_contract.py add a function build_award_quote(context, inputs) that returns an object matching this schema with mock data.
Export the function in the TOOLS registry.
Add unit tests in tests/test_tools_contract.py to assert the schema keys and data types."

2. Register Award Quote Action in Backend and OpenAPI

Prompt:

"Extend the AiChatToolCall enum and the OpenAPI copilot.yaml fragment to include a new tool call: workspace.award_quote.
Update app/Services/Ai/ChatService.php’s tool resolver to call the microservice’s /v1/ai/tools/build_award_quote endpoint when award_quote is requested.
Add a new AwardQuoteDraftConverter.php in app/Services/Ai/Converters/Workflow that, upon approval, creates an Award model linked to the RFQ, selected quote, and supplier, and marks the quote as awarded.
Write a Pest feature test that plans an award action, approves it, and asserts that an Award record exists with the right relationships."

3. Extend Workflow Template to Include award_quote Step

Prompt:

"Modify config/ai_workflows.php to add award_quote between compare_quotes and po_draft in the procurement workflow template.
Set its approval_permissions to ['quotes.write'].
Update WorkflowService::runConverter() to route the award_quote step to the new AwardQuoteDraftConverter.
Create a minimal UI renderer in resources/js/components/ai/WorkflowPanel.tsx for award_quote that displays the selected quote details and justification and surfaces Approve/Reject buttons.
Add a test to ensure that starting the procurement workflow now creates four steps."

**Status:** [DONE] Template, converter routing, UI renderer, permissions, and workflow tests updated (2025-12-23).

4. Introduce Invoice Draft and Approval

Prompt:

"Define INVOICE_DRAFT_SCHEMA in ai_microservice/schemas.py with fields: po_id, invoice_date, due_date, line_items[] (each containing description, qty, unit_price, tax_rate), and notes.
Implement build_invoice_draft(context, inputs) in tools_contract.py that generates a draft invoice from a given PO ID with mock line items.
Update AiChatToolCall and OpenAPI to include invoice_draft and approve_invoice actions.
Create InvoiceDraftConverter.php and InvoiceApprovalConverter.php in app/Services/Ai/Converters that, upon approval, create Invoice and Payment records respectively.
Add frontend handling in CopilotChatPanel.tsx to render invoice drafts and to route approvals via useAiDraftApprove or useResolveAiWorkflowStep."

**Status:** [DONE] Invoice schema + tool, converters, Copilot UI handlers, OpenAPI entries, and workspace approve resolver shipped (2025-12-24).

5. Implement Review Helpers for RFQs, Quotes, POs, and Invoices

Prompt:

"Add deterministic helper tools in ai_microservice/tools_contract.py: review_rfq, review_quote, review_po, and review_invoice.
Each should accept an ID and return a checklist of key metrics: for RFQs (items count, statuses), quotes (price, delivery, compliance), POs (line totals, delivery dates), and invoices (amount due, due date, discrepancies).
Register these tools in OpenAPI and create backend resolvers that call them.
Create a simple React component ReviewChecklist.tsx that takes the checklist array and renders it as a bulleted list with risk highlights.
Wire this component into the assistant response renderer when the response type is review_*."

**Status:** [DONE] Review helper endpoints, backend resolvers, OpenAPI + UI renderer shipped with checklist UI (2025-12-24).

6. Update Tests and Permissions

Prompt:

"Ensure all new action types (award_quote, invoice_draft, approve_invoice) have proper RBAC checks by adding them to config/permissions.php and adjusting any middleware.
Write Pest tests to verify that users without the appropriate permissions cannot plan or approve these actions.
Update existing workflow tests to cover the new steps and verify that approvals trigger the correct converters and database writes."

**Status:** [DONE] Added approve_invoice RBAC mapping, seeded workflow middleware overrides, and shipped Pest coverage for invoice + award permissions (2025-12-24).