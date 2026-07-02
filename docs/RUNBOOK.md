# Runbook — identity-service

## Health
- `GET /healthz` — liveness (process up).
- `GET /readyz` — readiness (PostgreSQL + Redis). 503 names the failing component.

## Outbox relay
- Scheduled every minute (`outbox:relay`, `withoutOverlapping`).
- Manual drain: `php artisan outbox:relay --limit=500`.
- Backlog rising (`outbox_entries` where `published_at IS NULL`): check EventBridge
  permissions on the task role and `EVENT_BUS_NAME`. Events are durable and publish once
  egress is restored (at-least-once).

## CI / CD
- CI (`.github/workflows/ci.yml`) calls `platform-github/php-service-ci.yml@v1`: Pint,
  PHPStan L8, Semgrep + Gitleaks, Unit/Feature/Integration/Contract, coverage gate
  (`scripts/coverage-gate.php`), `composer audit`, Docker build + Trivy.
- CD (`.github/workflows/cd.yml`) calls `php-service-cd.yml@v1`. The delivery scripts in
  `scripts/` require infrastructure env from `unero-platform-terraform` outputs
  (`ECS_CLUSTER`, `ECS_SERVICE`, `SMOKE_URL`, `DEPLOYMENT_CONFIG`). Until those are wired
  for an environment, deploy/canary steps fail loudly by design rather than pretend to
  succeed.

## Submodule
Shared schemas are a git submodule at `schemas/shared`. After clone:
`git submodule update --init` (Makefile `install` runs this).
