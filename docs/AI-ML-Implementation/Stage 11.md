## Stage 11 — Multi-Step AI Workflows

### 1. Objectives
- Orchestrate sequenced AI-assisted procurement flows (e.g., RFQ draft → quote comparison → PO draft) without automatically committing business actions.
- Capture every workflow step with names, inputs, draft outputs, reviewer approvals, and audit metadata per tenant and RBAC rules (§SECURITY_AND_RBAC).
- Ensure the AI microservice, Laravel backend, and React UI collaborate so each step executes one-at-a-time with human approval gates.

### 2. Workflow Model
- A workflow = `{ workflow_id, company_id, workflow_type, status, current_step_index, steps[] }` where each step contains `action_type`, `required_inputs`, `draft_output`, `approval_state`, `approved_by`, `approved_at`, and `citations`.
- Supported procurement steps (initial set): `rfq_draft`, `supplier_risk_review`, `compare_quotes`, `po_draft`. Inventory and future steps reuse the same contract.
- Status lifecycle: `pending → in_progress → completed` with terminal states `failed` or `rejected`; aborts capture reason + timestamp.

### 3. Microservice Scope
- Implement `workflow_engine.py` with an in-memory registry plus JSON persistence (`tmp/workflows/{workflow_id}.json`).
- Engine capabilities: `plan_workflow()` builds the ordered step list from `workflow_type` templates, `get_next_step()` enforces approvals, `complete_step()` records output + approval flag, and `abort_workflow()` marks terminal failure.
- Add deterministic tool contracts in `tools_contract.py`:
  - `compare_quotes()` → rankings, summary bullets, recommendation; consumes RFQ + supplier risk data.
  - `draft_purchase_order()` → placeholder PO number, structured line items, terms, delivery schedules that comply with PO draft schema.
- Extend `schemas.py` with strict wrappers for quote comparison and PO drafts (`additionalProperties: false`), reusing the `{ action_type, summary, payload, citations, confidence, needs_human_review, warnings }` envelope.
- Expose REST endpoints in `app.py`:
  - `POST /workflows/plan` → input `{ company_id, user_context, workflow_type, rfq_id, inputs }`, returns `{ workflow_id, first_step }`.
  - `GET /workflows/{workflow_id}/next` → returns next step metadata when prior step is approved.
  - `POST /workflows/{workflow_id}/complete` → body `{ output, approval }`, records approval/rejection and advances or terminates.
- Log correlation IDs for every call and ensure step execution routes to the right tool/LLM plan.

### 4. Laravel Backend Scope
- [x] Add migrations:
  - `ai_workflows` table: `company_id`, `user_id`, `workflow_id (uuid)`, `workflow_type`, `status`, `current_step`, `steps_json`, `last_event_time`, `last_event_type`, timestamps, soft delete, indexes `(workflow_id)`, `(company_id, status)`.
  - `ai_workflow_steps` table: `workflow_id`, `step_index`, `action_type`, `input_json`, `draft_json`, `output_json`, `approval_status`, `approved_by`, `approved_at`, timestamps, soft delete, index `(workflow_id, step_index)`.
- [x] Create `app/Services/Ai/WorkflowService.php` to wrap AiClient calls and keep database state in sync (start, getNextStep, completeStep). Surface circuit-breaker + retry behavior and emit events via `AiEventRecorder` for every state change.
- [x] Add `PurchaseOrderDraftConverter` and `QuoteComparisonDraftConverter` domain services to translate approved drafts into procurement module actions (create PO drafts, update RFQ quote selections, etc.).
- [x] Extend `AiEventRecorder` to emit workflow events (`workflow_start`, `workflow_step_approved`, `workflow_step_rejected`, `workflow_completed`, `workflow_aborted`).

### 5. API & UI Scope
- [x] Build `app/Http/Controllers/Api/V1/AiWorkflowController.php` with routes:
  - `POST /api/v1/ai/workflows/start` → validates tenant + plan + RFQ context, calls `WorkflowService::startWorkflow`, returns envelope `{ status, message, data: { workflow_id } }`.
  - `GET /api/v1/ai/workflows/{workflow_id}/next` → enforces policy, returns next step metadata and draft payload.
  - `POST /api/v1/ai/workflows/{workflow_id}/complete` → validates approval permissions based on step type (buyers vs managers), forwards to service, and returns updated step info.
- React: create `resources/js/components/ai/WorkflowPanel.tsx` to list workflows, show current steps, render skeletons, empty states, and permission-aware Approve/Reject controls that hit the API endpoints.
- Special UX states:
  - Quote comparison view: tabular supplier vs metrics, highlight AI recommendation, allow manual override, send chosen supplier ID with completion payload.
  - PO draft view: collapsible sections for line items, terms, schedule; require explicit approval check before submission.

### 6. Audit, Monitoring, and Analytics
- [x] Persist and surface workflow audit trails (timestamps, actor IDs, approvals) per tenant.
- [x] Update admin dashboards (per `/deep-specs/admin_console.md`) to show counts, completion rates, and average approval times; wire alerts for failed workflows.
- [x] Enforce feature gating via billing plans; deny API/UI access when entitlement missing.
- [x] Add feature tests ensuring unauthorized actors cannot view or approve workflow steps and that audit logs are written for every event.

### 7. Acceptance Criteria
1. Stage 11 documentation (this file) describes objectives, microservice/Laravel/UI scope, and audit expectations for multi-step workflows.
2. Microservice exposes workflow planning/execution endpoints, deterministic quote comparison + PO draft tools, and strict JSON schemas.
3. Laravel stores workflow + step state, provides workflow services/controllers, and logs AI workflow events with proper RBAC + tenant isolation.
4. React WorkflowPanel enables reviewers to inspect steps, run quote comparison overrides, and approve/reject drafts with live API wiring.
5. Approved quote comparisons update procurement selections; approved PO drafts create Draft POs via converter services.
6. Audit dashboards surface workflow metrics; automated tests cover unauthorized access and workflow approvals.