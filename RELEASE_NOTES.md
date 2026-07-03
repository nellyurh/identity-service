# identity-service — Release Notes

## v1.0.0-rc1 (pending)
The Unero platform Identity Provider, generated from the `config-service` `v1.0-template`.

**Capabilities.** Login (+ TOTP MFA with recovery codes), RS256 access tokens + JWKS,
rotating refresh tokens with family reuse detection, introspection, explicit RBAC with
permissions-in-token, service accounts + client-credentials tokens, API keys with
zero-downtime rotation, email verification, two-phase password reset.

**Hardening.** No-enumeration + timing parity; brute-force lockout (`UserLocked`);
IP + identifier rate limiting; per-challenge MFA attempt cap; atomic single-use for every
one-time credential; security headers with cacheable JWKS.

**Assurance.** All gates green (Pint, Rector, PHPStan L8, PHPUnit across Unit / Feature /
Integration / Contract / Architecture, `composer audit`); repository & conformance audit
PASS (`docs/RELEASE_AUDIT.md`); every event contract-tested against `unero-shared-schemas`.
The Architecture suite now runs in CI (fixed in `platform-github` during the audit).

**Decision record.** `docs/adr/` — including two surfaced deviations (temporal lockout,
middleware-level rate limiting) and the platform-owner-selected reset delivery model.
