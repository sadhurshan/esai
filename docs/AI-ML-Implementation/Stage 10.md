1. ✅ Create "Copilot Actions" schema pack (single source of truth)

Completed in ai_microservice/schemas.py with strict wrapper + payload schemas for RFQ drafts, supplier messages, maintenance checklists, and inventory what-if results.

Prompt

"In ai_microservice/schemas.py, add JSON Schemas for action outputs:
- RFQ_DRAFT_SCHEMA
- SUPPLIER_MESSAGE_SCHEMA
- MAINTENANCE_CHECKLIST_SCHEMA
- INVENTORY_WHATIF_SCHEMA
Each schema must be strict: required fields, no additionalProperties.
Include a shared top-level wrapper:
{ action_type, summary, payload, citations, confidence, needs_human_review, warnings }."

2. ✅ Add an action router endpoint in the microservice

Added POST /actions/plan in ai_microservice/app.py with schema-aware routing, semantic grounding, logging, and deterministic fallbacks for empty context or LLM failures.

Prompt

"In ai_microservice/app.py, add POST /actions/plan endpoint:
Input: {company_id, user_context, action_type, query, inputs, top_k, filters}
- Run semantic search to gather grounding context
- Pack context with budget rules
- Call LLM provider with the right schema based on action_type
- Return strict JSON matching the wrapper schema
- If context insufficient, return needs_human_review=true + warnings
Add request id logging + latency + safe error handling."

3. Add a "tool contract" layer (no direct DB writes from LLM)

Prompt

"Create ai_microservice/tools_contract.py:
- Define allowed tools as pure functions that only compute or format:
    - build_rfq_draft(context, inputs)
    - build_supplier_message(context, inputs)
    - build_maintenance_checklist(context, inputs)
    - run_inventory_whatif(context, inputs)
- Each tool returns a dict shaped exactly like its schema payload
Add a guard that prevents any network/DB calls from tool functions."

(This keeps the microservice safe: LLM proposes, tools format/compute deterministically where possible.)

4. Implement deterministic "what-if" simulation tool (no LLM needed)

Prompt

"Implement run_inventory_whatif():
Inputs: part_id, current_policy, proposed_policy (reorder_point/safety_stock/lead_time), forecast_snapshot, service_level_target
Output payload:
- projected_stockout_risk (0..1)
- expected_stockout_days
- expected_holding_cost_change (estimate)
- recommendation (text)
- assumptions (array)
Ensure it’s deterministic and uses existing forecast outputs."

5. Implement RFQ draft tool (structured draft, not sending)

Prompt

"Implement build_rfq_draft():
Inputs: category, items[{part_id, description, qty, target_date}], commercial_terms, delivery_location, evaluation_criteria
Output payload:
- rfq_title
- scope_summary
- line_items[]
- terms_and_conditions[]
- questions_for_suppliers[]
- evaluation_rubric[]
Must include citations for any technical claims sourced from documents."

6. Implement supplier message tool (email draft only)

Prompt

"Implement build_supplier_message():
Inputs: supplier_name, goal (price reduction/lead time/quality), context (quote_id optional), constraints, tone
Output payload:
- subject
- message_body
- negotiation_points[]
- fallback_options[]
Keep message professional. Never claim facts not in citations."

7. Implement maintenance checklist tool (grounded + safe)

Prompt

"Implement build_maintenance_checklist():
Inputs: asset_id, symptom, environment, urgency
Output payload:
- safety_notes[]
- diagnostic_steps[]
- likely_causes[]
- recommended_actions[]
- when_to_escalate[]
Must be grounded in retrieved maintenance/manual chunks; if insufficient sources, set needs_human_review=true and explain."

8. Add ai_action_drafts table (store drafts + approvals)

Prompt

"Create migration ai_action_drafts:
- company_id, user_id
- action_type
- input_json, output_json (JSON)
- citations_json (JSON)
- status: drafted|approved|rejected|expired
- approved_by, approved_at, rejected_reason
- entity_type/entity_id nullable (e.g., rfq, maintenance_task)
- timestamps
Add indexes for company_id/status/created_at."

9. Add Laravel controller endpoints for actions (plan + approve)

Prompt

"Create app/Http/Controllers/Api/V1/AiActionsController.php:
- POST /api/v1/ai/actions/plan → calls microservice /actions/plan, stores draft row, returns draft_id + output
- POST /api/v1/ai/actions/{draft}/approve → converts draft into real entity:
- if RFQ draft: create RFQ in DB as ‘Draft’ status
- if supplier message: save as ‘Message Draft’ (no sending)
- if maintenance checklist: create maintenance task draft
- if what-if: store result snapshot only
- POST /api/v1/ai/actions/{draft}/reject
Enforce RBAC and record ai_events for plan/approve/reject."

10. Add converters (draft → entity) as services (clean separation)

Prompt

"Create service classes:
- RfqDraftConverter
- SupplierMessageDraftConverter
- MaintenanceChecklistDraftConverter
Each takes ai_action_drafts.output_json and writes to DB.
Must validate required fields and reject if schema mismatch."

11. Build "Copilot Actions" panel

Prompt

"Create resources/js/components/ai/CopilotActionsPanel.tsx:
- Dropdown for action_type (RFQ Draft, Supplier Message, Maintenance Checklist, Inventory What-If)
- Dynamic form for inputs
- Button 'Generate Draft'
- Render structured output + citations
- Buttons: Approve / Reject
Show status chips. Never auto-approve."

12. Add "Draft Review" modal with diff/changes preview

Prompt

"Create AiDraftReviewModal.tsx:
- Shows summary + payload fields in readable layout
- Shows citations list
- Highlights ‘will create’ effect (e.g., RFQ draft → RFQ record in Draft status)
- Requires explicit confirmation checkbox before Approve."

13. Add policy rules: never mutate without approval

Prompt

"Add server-side guardrails:
- Microservice endpoints must never write to app DB
- Laravel must never create/update domain entities unless draft status = approved
Add tests proving no approve → no entity creation."

14. Add evaluation hooks (measure usefulness)

Prompt

"Add feedback endpoint:
- POST /api/v1/ai/actions/{draft}/feedback with rating 1–5 and comment
Store in ai_action_feedback table.
Add to admin dashboard metrics."

15. Write feature tests for action flow

Prompt

"Write Pest feature tests:
- plan creates ai_action_drafts row + ai_events row
- approve creates domain entity in Draft status
- reject sets status rejected and does not create entity
- RBAC: unauthorized users get 403
Use mocked AiClient responses."