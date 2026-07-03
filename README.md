# identity-service

The Unero **Identity Service**: authentication, authorization (RBAC), service accounts,
API keys, sessions, and tokens for the platform. Generated from the `config-service`
(`v1.0-template`) engineering template — same layering, tooling, testing strategy, and
conventions.

## Architecture (layers)

```
app/
  Domain/          framework-free: aggregates, value objects, events, exceptions
  Application/     use cases + ports (Clock, AuditWriter, repositories, ...)
  Infrastructure/  Eloquent repos, outbox, EventBridge, Redis, audit, clock
  Interfaces/      HTTP controllers/requests/middleware/error-envelope, console (relay)
```

The domain has zero `Illuminate\*` imports (enforced by `tests/Architecture` and the
platform Semgrep rule). Framework lives only in `Infrastructure/` and `Interfaces/`.

## Capabilities (v1.0.0-rc)

| Area | Surface |
|------|---------|
| Authentication | login (+ MFA challenge), RS256 access tokens, rotating refresh tokens w/ family reuse detection, logout, introspection, JWKS (cacheable) |
| Authorization | explicit RBAC (users → roles → permissions), permissions-in-token + `authz_ver`, `permission:` middleware |
| Service auth | service accounts, client-credentials service tokens, API keys (prefix lookup, scopes, zero-downtime rotation, `apikey:` middleware) |
| Account security | email verification, two-phase password reset (event + authenticated materialise callback), TOTP MFA (enroll/confirm/disable) + one-time recovery codes |
| Hardening | no-enumeration + timing parity, brute-force lockout (`UserLocked`), IP+identifier rate limiting, per-challenge MFA attempt cap, **atomic single-use for every one-time credential**, security headers |

## What it guarantees (foundation)

- **Transactional outbox.** Domain events are written to `outbox_entries` in the same
  transaction as the change and relayed to EventBridge, matching `unero-shared-schemas`.
- **Append-only audit.** Every mutation — including failed logins — writes an append-only
  `audit_events` row (verified: 33/33 mutating use cases, see `docs/RELEASE_AUDIT.md`).
- **Idempotent mutations.** Every admin mutation requires an `Idempotency-Key`; public
  exceptions each have an explicit replay story (single-use tokens, rotation).
- **Atomic one-time credentials.** Verification tokens, reset tokens/deliveries, MFA
  challenges, and recovery codes are consumed by conditional update — never SELECT-then-UPDATE.
- **Shared error envelope.** Every error path returns one shape with a stable `error.code`.

## Run it

```bash
composer install
git submodule update --init          # shared schemas
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan test          # Unit, Feature, Integration, Contract, Architecture
php -S 0.0.0.0:8080 -t public
# or: docker compose up -d --build
```

## CI/CD

`.github/workflows/ci.yml` and `cd.yml` are three-line callers into the reusable pipelines
in `nellyurh/platform-github@v1`. The gate is inherited, not redefined here.

See `docs/` for ARCHITECTURE, RUNBOOK, API, EVENTS, RELEASE_AUDIT, and `docs/adr/` for the
service-level decision record. Security posture: `SECURITY.md`. Sibling repositories are in
`UNERO_LINKS.md`.
