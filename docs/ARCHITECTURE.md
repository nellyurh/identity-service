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

## Application layer — use cases (milestone 2B)

Application services are thin, `final readonly` orchestrators that depend only on ports
(repository interfaces, `PasswordHasher`, `AuditWriter`, `Clock`) — never on Eloquent or
HTTP. Each takes a `Command` DTO and runs inside a single `DB::transaction`, so the
aggregate change, the drained domain events (to the outbox), and the audit row commit
together or not at all.

User lifecycle use cases (`app/Application/User/`):

| Use case | Emits | Audit action | Notes |
|----------|-------|--------------|-------|
| `RegisterUser` | `UserRegistered` | `user.registered` | email + username uniqueness; password hashed via port |
| `AuthenticateUser` | — | `user.authenticated` / `user.authentication_failed` | credential + status check only; no enumeration; token issuance is a later milestone |
| `ChangePassword` | `PasswordChanged` | `user.password_changed` | current password proven first |
| `DisableUser` | `UserDisabled` | `user.disabled` | |
| `EnableUser` | `UserActivated` | `user.enabled` | a deleted user cannot be re-enabled |
| `DeleteUser` | — | `user.deleted` | soft delete; retained for audit |
| `GetUser` | — | — | read-side `UserProfile` projection (by id / email / username) |

Commands live in `app/Application/User/Command/`, result DTOs in
`app/Application/User/Result/`. The password plaintext exists only inside a command and is
handed straight to the `PasswordHasher` port; the domain only ever receives a
`HashedPassword`. The Argon2id adapter and the Eloquent repositories that back these ports
arrive in milestone 2C.
