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

## What it guarantees (foundation)

- **Transactional outbox.** Domain events are written to `outbox_entries` in the same
  transaction as the change and relayed to EventBridge, matching `unero-shared-schemas`.
- **Append-only audit.** Every privileged action writes an append-only `audit_events` row.
- **Idempotent mutations.** Every mutating endpoint requires an `Idempotency-Key`.
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

See `docs/` for ARCHITECTURE, RUNBOOK, API, and EVENTS. Sibling repositories are in
`UNERO_LINKS.md`.
