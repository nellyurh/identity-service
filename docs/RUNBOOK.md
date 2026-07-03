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

## Tunables (env)
| Variable | Default | Governs |
|---|---|---|
| `IDENTITY_JWT_ACCESS_TTL` / `REFRESH_TTL` | 900 / 30d | token lifetimes |
| `IDENTITY_LOCKOUT_MAX_ATTEMPTS` / `DURATION` | 5 / 900 | brute-force lockout |
| `IDENTITY_MFA_CHALLENGE_TTL` | 300 | post-password MFA window |
| `IDENTITY_MFA_MAX_CHALLENGE_ATTEMPTS` | 5 | wrong codes per challenge |
| `IDENTITY_MFA_RECOVERY_CODE_COUNT` | 10 | codes per batch |
| `IDENTITY_MFA_PERIOD` / `DIGITS` / `WINDOW` | 30 / 6 / 1 | RFC 6238 parameters |
| `IDENTITY_EMAIL_VERIFICATION_TTL` | 86400 | verification token life |
| `IDENTITY_PASSWORD_RESET_TTL` | 3600 | reset delivery+token life |
| `IDENTITY_API_KEY_ROTATION_GRACE` / `TOUCH_THROTTLE` | 86400 / 3600 | API key ops |
| Rate limits | inline in `routes/api.php` | per-endpoint windows (reviewable at a glance) |

## Procedures
- **Unlock an account early:** the lock is temporal (`users.locked_until`); it self-expires.
  To force-unlock: `UPDATE users SET locked_until = NULL, failed_login_count = 0 WHERE id = ?`
  (audited change-management applies — there is deliberately no unlock endpoint yet).
- **User lost their authenticator:** they log in with a **recovery code** at `POST /login/mfa`
  (`recovery_code` instead of `code`), then disable + re-enroll MFA. If recovery codes are
  also lost, support disables MFA via `POST /users/{id}/mfa/totp/disable` after out-of-band
  identity verification (emits `MFADisabled`, audited).
- **Signing-key rotation:** publish next key alongside current (JWKS serves all non-retired,
  `Cache-Control: public, max-age=300` — verifiers converge within 5 min), switch signing,
  retire the old key after `IDENTITY_JWT_ACCESS_TTL` has elapsed.

## Signals worth alerting on
- `UserLocked` events in the outbox (brute-force activity per account).
- `429 RATE_001` rates at the edge (distributed credential stuffing — not written to audit
  by design; see `docs/API.md` § Rate limiting).
- Audit `login.mfa_failed` bursts and `token.reuse_detected` (refresh-token theft signal).
- Outbox backlog (`published_at IS NULL`) as already described above.
