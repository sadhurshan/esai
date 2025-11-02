# Platform Admin Console — Deep Spec

## Features
- Tenants list, subscription status, usage snapshots, plan overrides.
- Audit feed with filters; impersonation (secure token; full audit).

## Permissions
- platform_super (full), platform_support (read-only).

## Tests
- Impersonation emits audit entries; read-only respected.
