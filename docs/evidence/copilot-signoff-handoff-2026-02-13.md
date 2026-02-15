# Copilot Launch Signoff Handoff

- Generated: 2026-02-13T12:30:17+00:00
- Current decision: **NO-GO**
- Launch artifact: `docs/evidence/copilot-launch-readiness-2026-02-13.json`
- Signoff artifact: `docs/evidence/copilot-signoffs-2026-02-13.json`

## Technical Gate Snapshot

- Runtime: PASS
- Feature rollout: PASS
- Latency: PASS (samples=12 min=10 p95=4ms target=5000ms)
- Trace coverage: PASS (coverage=0.50 min=0.10)

## Evidence Fingerprints (SHA-256)

- Runtime: `7be733913c9408f262b6597d5f6d091cfd30462979a83fe94402f560f0821e3d`
- Latency: `43533165e33fa4201fddef49bcc75d521e130726ec770f63244518d90822001c`
- Trace: `05f782f70353a376e17e988ccd2cfde0269dbb8d967ee2d02e4f1f643a1c3720`
- Signoffs: `9b199489db8ab2fa4c8a4671c19386bc8218bbc7b37485e6331a43245b2bdcb1`

## Pending Approvals

- Product
- Engineering
- Qa
- Security

## Approval Commands

Use this artifact path: `docs/evidence/copilot-signoffs-2026-02-13.json`

- Product: `php artisan copilot:record-signoff --role=product --approved=1 --by="<approver>" --note="approved" --signoffs=docs/evidence/copilot-signoffs-2026-02-13.json --output=docs/evidence/copilot-signoffs-2026-02-13.json`
- Engineering: `php artisan copilot:record-signoff --role=engineering --approved=1 --by="<approver>" --note="approved" --signoffs=docs/evidence/copilot-signoffs-2026-02-13.json --output=docs/evidence/copilot-signoffs-2026-02-13.json`
- QA: `php artisan copilot:record-signoff --role=qa --approved=1 --by="<approver>" --note="approved" --signoffs=docs/evidence/copilot-signoffs-2026-02-13.json --output=docs/evidence/copilot-signoffs-2026-02-13.json`
- Security: `php artisan copilot:record-signoff --role=security --approved=1 --by="<approver>" --note="approved" --signoffs=docs/evidence/copilot-signoffs-2026-02-13.json --output=docs/evidence/copilot-signoffs-2026-02-13.json`

- One-command finalize (recommended): `php artisan copilot:finalize-launch --product-by="<approver>" --engineering-by="<approver>" --qa-by="<approver>" --security-by="<approver>" --signoffs=docs/evidence/copilot-signoffs-2026-02-13.json --launch-output=docs/evidence/copilot-launch-readiness-2026-02-13.json`

- Final check: `php artisan copilot:launch-readiness --signoffs=docs/evidence/copilot-signoffs-2026-02-13.json --format=json --output=docs/evidence/copilot-launch-readiness-2026-02-13.json`
