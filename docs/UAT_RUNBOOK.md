# UAT Runbook (Backlog-Driven)

Use the backlog to drive UAT coverage and automation. This keeps scope aligned with shipped stories and acceptance criteria.

## 1) Generate the checklist
Run tools/uat/generate-uat-checklist.mjs to produce docs/UAT_BACKLOG_CHECKLIST.md from the backlog CSV.

## 2) Generate the UAT environment file
Run tools/uat/generate-uat-env.mjs to build .env.uat from docs/Stable Staging Data.md.

## 3) Select UAT scope
Mark a story as UAT-required if it is:
- Phase 1 core procurement
- A security, RBAC, or tenant isolation requirement
- A workflow dependency (RFQ → Quote → PO → Receiving → Invoice)
- A launch readiness requirement from REQUIREMENTS_FULL

## 4) Map stories to tests
For each UAT-required story, link at least one test case:
- Backend: Pest tests under tests/Feature
- Frontend: Playwright tests under tests/e2e
- Unit/UI coverage where relevant under resources/js/**/__tests__

## 5) Execute automated UAT
Run the test suites against staging with seeded tenants and role-based credentials.
Use tools/uat/run-uat.mjs for the smoke suite.

## 6) Go/No-Go criteria
Launch only if:
- No P0/P1 defects
- All UAT-required stories pass
- Audit logging, RBAC, and tenant isolation pass

## References
- docs/REQUIREMENTS_FULL.md
- docs/ACCEPTANCE.md
- tests/e2e
- tests/Feature
