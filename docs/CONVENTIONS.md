# Conventions
**Structure:** Models, Controllers, Requests, Policies; React pages/components/ui/hooks/services.
**Validation:** FormRequest + client; inline errors; disable submit on pending.
**HTTP:** unified api.ts wrapper; intercept 401/404/500; exponential backoff.
**Queues:** Redis default; queue emails, notifications, CSV imports, webhook handling; retry/backoff policy.
**Files:** Accept pdf/docx/xlsx/stp/iges/dwg/jpg/png; 50MB max; virus‑scan hook; S3 for binaries.
**UI:** Tailwind+shadcn; list/table archetypes; wizards; skeletons; empty states.
**Performance:** <2s load; cache lookups; FULLTEXT where defined.
