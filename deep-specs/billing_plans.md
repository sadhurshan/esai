# Billing & Entitlements — Deep Spec

## Plans
Starter, Growth, Enterprise with numeric limits: rfqs_per_month, users_max, storage_gb, connectors.

## Mechanics
- Stripe Cashier at company level.
- Middleware EnsureSubscribed enforces gates; read-only grace 7 days on past_due.
- Webhooks: payment succeeded/failed, subscription updated.
- Usage snapshots nightly.

## Tests
- Hitting limit blocks action with 402-like envelope message.
