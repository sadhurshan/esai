# Architecture
- **Backend:** Laravel 12 (MVC, REST), Fortify, Inertia/SSR, Redis queues, Cashier.
- **Frontend:** React TS (Starter Kit), Tailwind, shadcn/ui, iconify.
- **DB:** MySQL InnoDB utf8mb4.
- **Notifications:** Echo/Pusher + queued mail; single event fan‑out; de‑dupe.
- **Approvals:** up to 5 levels with delegate; tracked & audited.
- **Entitlements:** plan gates via middleware + UI locks; 7‑day grace on past_due.
- **Observability:** logs, error levels, usage snapshots; nightly backups.
