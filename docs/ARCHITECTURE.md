# Architecture — identity-service

## Position
The Identity Service is the authentication and authorization backbone of the Unero
platform. It issues and verifies tokens, owns users/roles/permissions, service accounts,
and API keys, and emits identity domain events other services consume. It never calls
other engines synchronously on the auth hot path.

## Layering (Ports & Adapters)
- **Domain** — framework-free aggregates, value objects, domain events, domain exceptions.
  A `Domain/Shared` kernel holds the cross-aggregate `Actor`, base `DomainException`, and
  the `DomainEvent` contract. No `Illuminate\*` imports (enforced by `tests/Architecture`
  and the platform Semgrep rule).
- **Application** — use cases + ports (`Clock`, `AuditWriter`, repositories, and — as they
  land — password hashing, token issuance, key providers). Depends only on interfaces.
- **Infrastructure** — Eloquent repositories, the outbox writer + EventBridge relay, the
  database audit writer, the system clock, Redis adapters. Implements the ports.
- **Interfaces** — HTTP (controllers, form requests, middleware, error envelope) and
  console (`outbox:relay`).

## Key mechanisms (present at this milestone)
- **Transactional outbox** — domain events are drained to `outbox_entries` inside the
  business transaction; a scheduled relay publishes to EventBridge wrapped in the shared
  envelope and stamps `published_at`.
- **Append-only audit** — `audit_events`; on Postgres, `UPDATE`/`DELETE` are rewritten to
  no-ops by rule. Every privileged action writes a row before the response returns.
- **Idempotent mutations** — every mutating endpoint requires an `Idempotency-Key`.
- **Shared error envelope** — every error path (domain, validation, HTTP, unhandled)
  renders `{ error: { code, message, detail? }, request_id, docs_url }`.

## Data stores
PostgreSQL (Aurora Serverless v2) for state; Redis (ElastiCache) for sessions, token cache,
and rate limiting; EventBridge for egress. All provisioned by `unero-platform-terraform`.

## Domain map (v1.0.0-rc)

| Aggregate / entity | Emits | Notes |
|---|---|---|
| `User` | UserRegistered, EmailVerified, PasswordChanged, RoleAssigned/Removed, UserActivated/Disabled, **UserLocked** | lockout counters (`failed_login_count`, temporal `locked_until` — ADR-007) |
| `Role`, `Permission` | RoleCreated, PermissionGranted/Revoked, PermissionDefined | explicit RBAC; system roles protected |
| `ServiceAccount` | ServiceAccountCreated/CredentialRotated/Disabled | client-credentials grant |
| `ApiKey` | ApiKeyCreated/Rotated/Revoked | prefix lookup; grace-window rotation |
| `RefreshToken` | TokenIssued/Rotated/Revoked/ReuseDetected | family rotation + reuse detection |
| `PasswordReset` | PasswordResetRequested | two-phase: token minted at materialise time (ADR-002) |
| `TotpCredential` | MFAEnabled/MFADisabled | secret encrypted at rest (`SecretCipher`) |
| `EmailVerificationToken`, `MfaChallenge`, `RecoveryCode` | — | one-time credentials; no domain events |

## Ports (Application) → Adapters (Infrastructure)

Repositories per aggregate (Eloquent), plus: `PasswordHasher` → Argon2id; `TokenIssuer` /
`TokenVerifier` / `SigningKeyProvider` → lcobucci JWT + key config; `TokenGenerator` →
random; `TotpProvider` → self-contained RFC 6238 (ADR-004); `SecretCipher` → Laravel
encrypter; `RateLimiter` → cache-backed (Redis in prod); `AuditWriter` → append-only DB;
`Clock`, `TransactionManager`, `AuthorizationResolver`, `ApiKeyGenerator`.

## Security layering (outer → inner)

1. **Rate limiter** — IP + identifier keys, before any credential/DB work (`RATE_001`).
2. **Account lockout** — 5 consecutive failures → temporal lock, `UserLocked` emitted; all
   attempts while locked fail generically.
3. **Enumeration & timing parity** — unknown email burns equivalent hash time; account
   state revealed only after the password is proven.
4. **MFA** — opaque single-use challenge, per-challenge attempt cap, recovery codes.
5. **Atomic one-time credentials** — every consume/materialise is a conditional update;
   of two concurrent requests exactly one wins (see `docs/RELEASE_AUDIT.md`).
6. **Headers** — nosniff, frame-deny, no-referrer, deny-all CSP, HSTS; `no-store`
   everywhere except the deliberately cacheable JWKS.

Decision record: `docs/adr/`. Invariant evidence: `docs/RELEASE_AUDIT.md`.
