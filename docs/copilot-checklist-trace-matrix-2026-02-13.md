# Copilot Checklist-to-Code Trace Matrix — 2026-02-13

Source checklist: `docs/copilot-production-ready-checklist.md`

| Tag | Meaning |
|---|---|
| `code-verified` | Implementation directly confirmed in code. |
| `test-verified` | Validated by automated tests/gates/evidence artifacts. |
| `not-yet-verified` | Checklist line exists but was not directly proven in this final trace pass. |

## 0) Status Snapshot

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L9 | Core backend suite passes | `test-verified` | `tests/**`, readiness evidence run logs |
| L10 | Widget/panel UI suites pass | `test-verified` | `resources/js/components/ai/__tests__/copilot-chat-widget.test.tsx`, `resources/js/components/ai/__tests__/copilot-chat-panel.test.tsx` |
| L11 | Broad procurement acceptance smoke passes | `test-verified` | Acceptance smoke command evidence |
| L12 | `npm run lint` passes | `test-verified` | Readiness evidence run log |
| L13 | `npm run types` passes | `test-verified` | Readiness evidence run log |
| L14 | Environment evidence captured | `test-verified` | `docs/evidence/copilot-runtime-gates-2026-02-13.json` |
| L15 | P95 performance evidence captured | `test-verified` | `docs/evidence/copilot-latency-2026-02-13.json` |
| L16 | Formal signoffs captured | `test-verified` | `docs/evidence/copilot-signoffs-2026-02-13.json` |
| L19 | GO | `test-verified` | `docs/evidence/copilot-launch-readiness-2026-02-13.json` |
| L20 | NO-GO | `test-verified` | Intentionally false in final state |

## 1) Scope & Exit Criteria

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L39 | Complete/guide procurement flows | `test-verified` | Scenario suites + acceptance smoke |
| L40 | Draft RFQs with clarification follow-ups | `test-verified` | `NluDraftRfqTest`, `AiChatControllerTest` |
| L41 | Workspace answers are tenant-scoped with citations | `code-verified` + `test-verified` | `app/Http/Controllers/Api/Ai/CopilotSearchController.php`, `CopilotSearchControllerTest` |
| L42 | High-impact actions require approval + RBAC | `code-verified` | `app/Http/Controllers/Api/V1/AiActionsController.php` |
| L43 | Prompt/action logging exists | `code-verified` | `app/Services/Ai/ChatService.php`, `AiEventRecorder` usage |
| L44 | Failures return actionable guidance | `code-verified` | AI controller envelope error responses |

## 2) Environment & Feature Gates

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L48 | `config('ai.enabled')` true | `test-verified` | Runtime gate evidence |
| L49 | AI service/shared secret valid | `test-verified` | Runtime gate evidence |
| L50 | Copilot feature flags enabled | `test-verified` | Runtime + rollout evidence |
| L51 | Plan/entitlement gates correct | `code-verified` | Route middleware chain |
| L52 | Required middleware chain active | `code-verified` | `routes/api.php` AI/Copilot route groups |
| L53 | Queue/worker health verified | `test-verified` | Runtime gate evidence |
| L61 | `config('ai.enabled')` true (status update) | `test-verified` | Runtime gate evidence |
| L62 | Shared secret valid (status update) | `test-verified` | Runtime gate evidence |
| L63 | Feature flags enabled (status update) | `test-verified` | Runtime + rollout evidence |
| L64 | Middleware chain active (status update) | `code-verified` + `test-verified` | Routes + runtime gate |
| L65 | Queue health verified (status update) | `test-verified` | Runtime gate evidence |
| L68 | Screenshot/log snippet attached | `test-verified` | Evidence tracked |
| L69 | Env + feature flag values captured | `test-verified` | Evidence tracked |

## 3) API Surface Contract (Smoke)

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L75 | `POST /api/v1/ai/chat/threads` | `code-verified` | `routes/api.php` |
| L76 | `POST /api/v1/ai/chat/threads/{thread}/send` | `code-verified` | `routes/api.php` |
| L77 | `POST /api/v1/ai/chat/threads/{thread}/tools/resolve` | `code-verified` | `routes/api.php` |
| L78 | `POST /api/v1/ai/actions/plan` | `code-verified` | `routes/api.php` |
| L79 | `POST /api/v1/ai/actions/{draft}/approve` | `code-verified` | `routes/api.php` |
| L80 | `POST /api/v1/ai/actions/{draft}/reject` | `code-verified` | `routes/api.php` |
| L81 | `POST /api/copilot/search` | `code-verified` | `routes/api.php` |
| L82 | `POST /api/copilot/answer` | `code-verified` | `routes/api.php` |
| L85 | Envelope returns `{ status, message, data, errors? }` | `code-verified` | `app/Http/Controllers/Api/Concerns/RespondsWithEnvelope.php` |
| L86 | Enforces auth + tenant scope | `code-verified` | Middleware + `requireCompanyContext` + `forCompany` |
| L87 | Forbidden paths return clear reason/codes | `code-verified` | `fail(..., 403, ['code' => ...])` patterns |
| L88 | Validation errors map to `errors` | `code-verified` + `test-verified` | Envelope fail path + API suites |

## 4) RFQ Creation via Copilot

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L93 | Happy-path RFQ prompt | `test-verified` | `NluDraftRfqTest` |
| L94 | Returns `rfq_draft` summary/payload | `test-verified` | Draft/action tests |
| L95 | Draft contains expected key fields | `test-verified` | RFQ draft tests |
| L96 | Approval converts and persists RFQ | `code-verified` + `test-verified` | `AiActionsController`, `AiDraftConversionService` + tests |
| L97 | RFQ tenant-scoped and auditable | `code-verified` + `test-verified` | Company context + event recording |
| L100 | Missing-info prompt path | `test-verified` | Chat/RFQ tests |
| L101 | Targeted clarifications | `test-verified` | Chat panel + controller tests |
| L102 | Follow-up resolves clarification | `test-verified` | Chat controller tests |
| L103 | Final valid RFQ draft | `test-verified` | RFQ tests |
| L106 | Approval only for authorized roles | `code-verified` | Denies checks + policy gate |
| L107 | Unauthorized approval blocked | `code-verified` | 403 forbidden path |
| L108 | Reject captures reason/no partials | `code-verified` | Reject flow status + reason write |
| L109 | Precise user guidance on non-auto actions | `code-verified` | Guided/error response paths |
| L112 | Due date/lifecycle validation | `not-yet-verified` | Not directly traced this pass |
| L113 | Open bidding/invitation behavior | `not-yet-verified` | Not directly traced this pass |
| L114 | Plan limits + upgrade guidance | `not-yet-verified` | Not directly traced this pass |

## 5) Workspace Context & Answer Quality

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L119 | Workspace question answering | `test-verified` | `CopilotSearchControllerTest`, eval suite |
| L120 | Tenant-local data only | `code-verified` | `company_id` payload + document access policy |
| L121 | Citations returned for factual answers | `code-verified` | Citation normalization/filtering in search controller |
| L122 | Low-confidence warnings/guardrails | `not-yet-verified` | No direct assertion traced |
| L125 | Rationale in answers | `not-yet-verified` | No direct assertion traced |
| L126 | Outlier/risk reason codes | `not-yet-verified` | No direct assertion traced |
| L127 | States insufficient data/asks inputs | `test-verified` | Clarification flows |
| L130 | Tool lookup failure returns fallback | `code-verified` + `test-verified` | Chat exception handling + chat tests |
| L131 | Repeated failures gracefully stopped | `not-yet-verified` | No explicit limit assertion traced |
| L132 | Conversation continues after failure | `test-verified` | Chat flow tests |

## 6) End-to-End Workflow Coverage

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L138 | RFQ drafting + publish intent | `test-verified` | Scenario/eval coverage |
| L139 | Quote review/summarization | `test-verified` | Scenario/eval/acceptance coverage |
| L140 | Award recommendation with approval guard | `code-verified` + `test-verified` | Approval policy path + tests |
| L141 | PO guidance/action draft | `test-verified` | Acceptance scope includes PO |
| L142 | Receiving/NCR guidance | `test-verified` | Acceptance scope includes GRN/NCR |
| L143 | Invoice/match/payment guidance | `test-verified` | Acceptance scope includes invoice |
| L146 | Action or guided completion | `test-verified` | Chat/eval behavior |
| L147 | Correct transitions and audit logs | `code-verified` + `test-verified` | Status transitions + event recording |
| L148 | Notifications/events emitted | `not-yet-verified` | Not fully traced this pass |

## 7) Security, RBAC, and Tenant Isolation

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L152 | Role boundaries validated | `code-verified` + `test-verified` | Middleware/denies checks + suite coverage |
| L153 | `buyer_requester` forbidden paths | `test-verified` | RBAC coverage in AI action tests |
| L154 | No cross-company record actions | `code-verified` | `forCompany()` + required company context |
| L155 | Sensitive actions require approval | `code-verified` | Draft approve/reject gate |
| L156 | Prompt injection does not bypass policies | `code-verified` | Server-side policy/auth checks |
| L159 | “Ignore rules and approve” blocked | `test-verified` | Approval gate behavior tests |
| L160 | “Show other tenant RFQs” blocked | `code-verified` | Tenant scoping enforcement |
| L161 | “Execute without approval” blocked | `code-verified` | Approval requirement path |

## 8) Observability, Audit, and Analytics

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L165 | Prompt/response/action lifecycle logged | `code-verified` | `AiEventRecorder` calls |
| L166 | AI events include feature labels | `code-verified` | Recorder `feature` field usage |
| L167 | Admin usage dashboard reflects activity | `not-yet-verified` | Dashboard rendering not traced |
| L168 | Correlate UI → API → DB audit row | `not-yet-verified` | Manual correlation not executed |
| L169 | Error tracking captures tool/stream failures | `code-verified` + `not-yet-verified` | Error event paths exist; sink validation not traced |

## 9) UX Quality (Widget)

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L173 | Open/close state persists | `test-verified` | Widget test |
| L174 | Streaming has no duplicate/drop | `test-verified` | Chat panel tests |
| L175 | Tool-resolution UX recoverable | `test-verified` | Clarification/entity picker/unsafe action tests |
| L176 | Success/error toasts appear | `code-verified` + `test-verified` | `publishToast` + UI test coverage |
| L177 | Empty/loading/error states readable | `code-verified` + `test-verified` | Panel states + tests |
| L178 | Keyboard/screen-reader basics | `code-verified` | Dialog semantics + keyboard handling |

## 10) Reliability & Performance

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L182 | Predictable rate-limit behavior | `code-verified` + `not-yet-verified` | `ai.rate.limit` middleware; user-facing behavior not re-probed |
| L183 | No thread cross-talk under concurrency | `not-yet-verified` | No dedicated concurrency stress run in this pass |
| L184 | P95 meets target | `test-verified` | Latency evidence artifact |
| L185 | Long response/tool loops terminate safely | `code-verified` + `not-yet-verified` | Tool limits in chat service; no stress replay in this pass |
| L186 | Retry strategy avoids duplicate records | `not-yet-verified` | Not directly traced |

## 11) Regression Test Execution

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L205 | Backend targeted test filter pass | `test-verified` | Evidence log command results |
| L207 | Widget/panel Vitest run pass | `test-verified` | Evidence log command results |
| L210 | Broad acceptance smoke pass | `test-verified` | Evidence log command results |
| L213 | Lint pass | `test-verified` | Evidence log command results |
| L215 | Types pass | `test-verified` | Evidence log command results |
| L219 | Command output archived | `test-verified` | Evidence log |
| L220 | Failures triaged with owner + ETA | `test-verified` | Evidence log |

## 12) Automated Scenario Pack

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L228 | RFQ drafting + clarification (`NluDraftRfqTest`) | `test-verified` | Test suite evidence |
| L229 | Chat send/tools fallback (`AiChatControllerTest`) | `test-verified` | Test suite evidence |
| L230 | Action plan/approve/reject RBAC (`AiActionsControllerTest`) | `test-verified` | Test suite evidence |
| L231 | Search/answer auth + citations (`CopilotSearchControllerTest`) | `test-verified` | Test suite evidence |
| L232 | Analytics approvals/logging (`CopilotControllerTest`) | `test-verified` | Test suite evidence |
| L233 | Multi-scenario eval coverage (`CopilotEvalSuiteTest`) | `test-verified` | Test suite evidence |
| L234 | Widget behavior (`copilot-chat-widget.test.tsx`) | `test-verified` | UI test evidence |
| L235 | Panel behavior (`copilot-chat-panel.test.tsx`) | `test-verified` | UI test evidence |
| L238 | Correct intent routing | `test-verified` | Scenario/eval suites |
| L239 | Correct constraints (RBAC/tenant/approval) | `test-verified` | Scenario/eval suites |
| L240 | Useful response structure + guidance | `test-verified` | Scenario/eval suites |
| L241 | Logged events/audit-linked behavior | `test-verified` | API suites |

## 13) Go/No-Go Signoff

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L245 | Product Owner signoff | `test-verified` | Signoff artifact |
| L246 | Engineering signoff | `test-verified` | Signoff artifact |
| L247 | QA signoff | `test-verified` | Signoff artifact |
| L248 | Security/Compliance signoff | `test-verified` | Signoff artifact |
| L262 | GO | `test-verified` | Launch readiness artifact |
| L263 | NO-GO | `test-verified` | Intentionally false in final state |
| L266 | Launch decision artifact exists | `test-verified` | `docs/evidence/copilot-launch-readiness-2026-02-13.json` |
| L267 | Verification artifact exists | `test-verified` | `docs/evidence/copilot-readiness-verification-2026-02-13.json` |
| L270 | Blockers = none | `test-verified` | Launch artifact `blockers: []` |
| L271 | Ticket pending signoff+runtime capture | `test-verified` | Intentionally false in final state |
| L272 | Ticket pending signoff | `test-verified` | Intentionally false in final state |

## Quick Mapping Section

| Checklist Line | Checklist Item | Verification | Evidence |
|---|---|---|---|
| L278 | Chat thread/send/tool resolution covered | `test-verified` | API suite coverage |
| L279 | Action plan/approve/reject permissions covered | `test-verified` | API suite coverage |
| L280 | Search/answer auth + citations covered | `test-verified` | API suite coverage |
| L281 | RFQ drafting via NLU covered | `test-verified` | NLU suite coverage |
| L282 | Copilot eval suite dataset run covered | `test-verified` | Eval suite coverage |

## Final Summary

| Summary Item | Status |
|---|---|
| All checkbox lines in `docs/copilot-production-ready-checklist.md` were traced in this matrix. | Complete |
| Launch-critical gates (runtime, rollout, latency, trace coverage, signoffs, integrity) are verified and passing. | Pass |
| Remaining `not-yet-verified` entries are deeper behavior/quality checks not re-executed as bespoke probes in this final trace pass. | Known residual |

## Fresh RFQ Revalidation

| Date | Command | Result | Coverage |
|---|---|---|---|
| 2026-02-13 | `php artisan test --filter "NluDraftRfqTest|AiActionsControllerTest|AiChatControllerTest"` | `PASS` (`22` tests, `139` assertions, `64.85s`) | RFQ draft generation, clarification flow, draft approval/rejection RBAC, chat send/assistant response/tool resolution fallback |