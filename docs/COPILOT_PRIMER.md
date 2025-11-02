# Copilot Primer
1) Multi‑tenant: always company_id scope. 2) API envelope + HTTP names. 3) Pagination default 25 / max 100. 4) S3 for binaries; thumbnails on public disk only. 5) Redis queue for emails/notifications/imports/webhooks with retry/backoff. 6) FormRequest validation; show inline errors. 7) Tailwind+shadcn only. 8) Every mutation audited. 9) When unsure, follow /docs not guesses.
