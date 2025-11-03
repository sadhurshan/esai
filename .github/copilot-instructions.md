# Copilot Instructions — Elements Supply AI

## Project Bible
The **ultimate source of truth** for this project is the document:
📄 `/docs/ProjectRequirements.pdf`

Treat everything in this document as **the complete system specification**.  
Every feature, table, module, and screen mentioned there **must be implemented**.

When generating code, interfaces, or documentation:
- Assume these requirements are final and complete.
- Do not invent modules, fields, or routes not mentioned there.
- If something is ambiguous, include a `// TODO: clarify with spec` comment — do not guess.
- Cross-reference `/deep-specs/*` for per-module details.
- All architecture, API, and UX rules must align with these specs.

## Product & Architecture
- Multi-tenant procurement platform (RFQ → Quote → PO → Order → Receiving → Invoice) built with Laravel 12 + Inertia React TS.
- Every aggregate carries `company_id`; scope queries with shared `BelongsToCompany` patterns and never leak cross-tenant data.
- Database rules: MySQL InnoDB with `utf8mb4` collation, `{singular}_id` foreign keys, soft deletes on every business table, `company_id` on all tenant rows, and indexed foreign/composite/FULLTEXT columns exactly as §26 specifies.
- REST and Inertia endpoints return the JSON envelope `{ status, message, data, errors? }` with named error maps; collection endpoints default to cursor pagination (returning `data.items`, `meta.next_cursor`, `meta.prev_cursor`, etc.) unless the spec explicitly calls for page/offset behaviour.
- Follow module deep specs in `/deep-specs/*` and data definitions in `/docs/DOMAIN_MODEL.md`; do not invent new tables, routes, or enums beyond those sources.

## Backend Guardrails
- Keep controllers thin (`app/Http/Controllers/**`) and move domain logic into dedicated Actions/Services; add audit log entries for every mutation per `/docs/DEFINITION_OF_DONE.md`.
- Validate with FormRequest classes (`app/Http/Requests/**`) and authorize through Policies; surface validation errors back to Inertia forms.
- Storage rules: binaries to S3, public-facing thumbnails/files to the `public` disk, always enforce module-specific size limits from `config/filesystems.php` and deep specs.
- Redis is the default queue; queue emails, notifications, CSV imports, and webhooks with retry/backoff rather than dispatching synchronously.
- Pagination, sorting, and filtering parameters must match the deep spec for each module (for example RFQ filters in `deep-specs/rfqs.md`) and respect tenant scopes.

## Frontend Guardrails
- React pages live under `resources/js/pages`; wrap content with `AppLayout` or module layouts in `resources/js/layouts/**` (e.g. settings) for breadcrumbs and navigation.
- Use Wayfinder-generated route helpers under `resources/js/actions`/`routes`; call `Controller.method.form()` when wiring `<Form>` components so HTTP verbs and spoofed methods stay in sync with backend routes.
- Keep Tailwind + shadcn/ui as the only design system, reuse React Starter Kit layout/composition patterns, and ensure every collection view ships with skeleton loaders, empty states, and accessible labels/ARIA where required.
- Respect shared client utilities (`resources/js/hooks`, `resources/js/lib`) and keep browser-only APIs behind effects to preserve SSR compatibility (`resources/js/ssr.tsx`).

## UI Branding Rules
- Use the official logos in `/public/`.
- Import via `/resources/js/config/branding.ts` not relative paths.
- Default logo: `Branding.logo.default`
- White background pages → use `Branding.logo.darkBg`
- Dark background pages → use `Branding.logo.whiteText`
- Authentication / Landing → use centered symbol (`Branding.logo.symbol`)
- Never hardcode paths; use Branding constants.

## Data & Integrations
- Implement revision workflows exactly as specced (RFQ versions, Quote revisions, PO change orders, invoice match state) and audit every transition.
- Apply soft deletes to business records and index foreign keys plus required FULLTEXT indices (see `/docs/CONVENTIONS.md`).
- Notifications flow through Laravel events → queued notifications; align entitlements, plan gates, and RBAC with `/docs/billing_plans.md` and `/docs/SECURITY_AND_RBAC.md`.
- Billing & entitlements: wire feature-gating middleware hooks now and leave integration points for Stripe Cashier webhooks/events per §26 so downstream metering can plug in without refactors.
- File/document features follow `/deep-specs/documents.md`; enforce virus scanning and metadata requirements before persisting.

## Developer Workflow
- Bootstrap: `composer run setup` (installs PHP deps, seeds .env, runs migrations, installs npm packages, builds assets).
- Local dev: `composer run dev` starts PHP server, queue listener, and Vite; use `composer run dev:ssr` when touching SSR paths in `resources/js/ssr.tsx`.
- Testing: `composer run test` (Pest) uses `RefreshDatabase`; include at least one success and one forbidden case per feature and mock queues only when unavoidable.
- Linting & types: `npm run lint`, `npm run format:check`, `npm run types`; keep TypeScript definitions in `resources/js/types` aligned with backend resources.
- Migration order mirrors `/docs/DOMAIN_MODEL.md`; apply tenant scopes and indexes before writing seeders or factories.

## Source of Truth & Delivery
- Read `/docs/ProjectRequirements.pdf`, `/docs/REQUIREMENTS.md`, `/docs/CONVENTIONS.md`, `/docs/PROMPTS.md`, `/docs/REQUIREMENTS_FULL.md` and module deep specs before implementing features; these are the authoritative playbooks.
- Everything generated must be production-ready, tenant-safe, and spec-compliant; if requirements are unclear, add `TODO` comments or ask for guidance instead of guessing.
