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

3. ✅ Add a "tool contract" layer (no direct DB writes from LLM)

Implemented ai_microservice/tools_contract.py with side-effect guard plus pure functions for RFQ drafts, supplier messages, maintenance checklists, and inventory what-if simulations; each returns schema-shaped payloads only.

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

4. ✅ Implement deterministic "what-if" simulation tool (no LLM needed)

run_inventory_whatif() inside ai_microservice/tools_contract.py now models service-level coverage vs. forecast snapshots and produces risk/holding-cost estimates deterministically.

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

5. ✅ Implement RFQ draft tool (structured draft, not sending)

build_rfq_draft() lives in ai_microservice/tools_contract.py and is now wired through /actions/plan so RFQ payloads come from the deterministic tool before LLM refinements.

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

6. ✅ Implement supplier message tool (email draft only)

build_supplier_message() produces professional drafts in ai_microservice/tools_contract.py; /actions/plan merges tool payloads with LLM suggestions.

Prompt

"Implement build_supplier_message():
Inputs: supplier_name, goal (price reduction/lead time/quality), context (quote_id optional), constraints, tone
Output payload:
- subject
- message_body
- negotiation_points[]
- fallback_options[]
Keep message professional. Never claim facts not in citations."

7. ✅ Implement maintenance checklist tool (grounded + safe)

build_maintenance_checklist() is available via the tool contract and feeds the action router’s responses.

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

8. ✅ Add ai_action_drafts table (store drafts + approvals)

Created database/migrations/2025_12_19_010000_create_ai_action_drafts_table.php with tenant-scoped columns, approval metadata, JSON blobs, soft deletes, and indices on company/status/created_at plus entity lookups.

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

10. ✅ Add converters (draft → entity) as services (clean separation)

Implemented dedicated converters in app/Services/Ai/Converters/* (RFQ, supplier message, maintenance checklist, and inventory what-if) plus AiDraftConversionService, all invoked from AiActionsController@approve inside a DB transaction. Each converter validates its schema payloads, persists the correct domain record (RFQ via CreateRfqAction, SupplierMessageDraft, MaintenanceTask, InventoryWhatIfSnapshot), copies citations/confidence/warnings, and stamps AiActionDraft.entity_type/id after creation.

Prompt

"Create service classes:
- RfqDraftConverter
- SupplierMessageDraftConverter
- MaintenanceChecklistDraftConverter
Each takes ai_action_drafts.output_json and writes to DB.
Must validate required fields and reject if schema mismatch."

11. ✅ Build "Copilot Actions" panel

Added resources/js/components/ai/CopilotActionsPanel.tsx with a card-based workflow that lets users pick a Copilot action type, fill tailored forms (RFQ line items, supplier negotiation context, maintenance safety notes, inventory policies), and submit `/v1/ai/actions/plan`. The component renders Copilot's structured payload (tables/lists per action), citations with document open buttons, confidence + status chips, and a guarded approval/rejection area wired to the new ai.ts helpers. Context filters (source type, doc id, tags) and form reset support were included per spec.

Prompt

"Create resources/js/components/ai/CopilotActionsPanel.tsx:
- Dropdown for action_type (RFQ Draft, Supplier Message, Maintenance Checklist, Inventory What-If)
- Dynamic form for inputs
- Button 'Generate Draft'
- Render structured output + citations
- Buttons: Approve / Reject
Show status chips. Never auto-approve."

12. ✅ Add "Draft Review" modal with diff/changes preview

Shipped resources/js/components/ai/AiDraftReviewModal.tsx and wired it into CopilotActionsPanel so approvals are blocked behind an explicit review flow. The modal mirrors the structured payload preview, citations, warnings, and shows per-action impact callouts (e.g., RFQ approval creates a Draft RFQ). Users must acknowledge a confirmation checkbox before the Approve action fires, and the dialog reuses the existing document opener for citation drill-downs.

Prompt

"Create AiDraftReviewModal.tsx:
- Shows summary + payload fields in readable layout
- Shows citations list
- Highlights ‘will create’ effect (e.g., RFQ draft → RFQ record in Draft status)
- Requires explicit confirmation checkbox before Approve."

13. ✅ Add policy rules: never mutate without approval

Strengthened AiDraftConversionService with a status gate so converters only execute when the draft row is already marked `approved`, and re-ordered the controller workflow so approval metadata is persisted before conversions run. Added AiActionDraftFactory plus new Pest coverage (tests/Feature/Api/Ai/AiActionsControllerTest.php) to prove planning writes drafts, unauthorized approvals fail, converters are blocked until status=approved, and approved runs invoke the conversion pipeline exactly once.

Prompt

"Add server-side guardrails:
- Microservice endpoints must never write to app DB
- Laravel must never create/update domain entities unless draft status = approved
Add tests proving no approve → no entity creation."

14. ✅ Add evaluation hooks (measure usefulness)

Introduced ai_action_feedback (migration + model + factory) plus AiActionFeedbackRequest/Resource and wired `POST /api/v1/ai/actions/{draft}/feedback` through AiActionsController. The endpoint enforces tenant scoping + role gates, stores rating/comment snapshots, records `copilot_action_feedback` AiEvents for metrics, and responds with the normalized resource. Coverage lives in tests/Feature/Api/Ai/AiActionsControllerTest.php to confirm success, validation, and authorization behaviour.

Prompt

"Add feedback endpoint:
- POST /api/v1/ai/actions/{draft}/feedback with rating 1–5 and comment
Store in ai_action_feedback table.
Add to admin dashboard metrics."

15. ✅ Write feature tests for action flow

Extended tests/Feature/Api/Ai/AiActionsControllerTest.php with end-to-end coverage: planning now asserts AiEvents are recorded, RBAC blocking is verified for unauthorized roles, approvals exercise the real inventory what-if converter to persist snapshots, rejections leave drafts untouched, and feedback payload validation/RBAC rules are enforced. These feature tests run against the HTTP layer with mocked AiClient responses so the entire Copilot workflow is guarded per spec.

Prompt

"Write Pest feature tests:
- plan creates ai_action_drafts row + ai_events row
- approve creates domain entity in Draft status
- reject sets status rejected and does not create entity
- RBAC: unauthorized users get 403
Use mocked AiClient responses."