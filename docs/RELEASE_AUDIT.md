# identity-service — 2J-b Repository & Conformance Audit

**Scope:** every repository, application service, and layer boundary, audited against the platform
invariants ahead of `v1.0.0-rc1`. Method: mechanical sweeps over the tree (write-site analysis,
event-drain analysis, audit-call analysis, route analysis, import analysis) — evidence, not opinion.
**Result: PASS**, with one platform-level defect found and fixed (CI never ran the Architecture
suite) and two documented-by-design patterns noted.

## Invariant 1 — every write inside a transaction: PASS
All 33 mutating application services execute their writes inside `TransactionManager::transactional`.
Two are shared helpers documented as "must run inside the caller's transaction", and every caller
was verified to wrap them:

| Helper | Callers (all wrapped) |
|---|---|
| `Auth/IssueUserSession` | `LoginUser` (both branches), `CompleteMfaLogin` |
| `Mfa/GenerateRecoveryCodes` | `ConfirmTotp`, `RegenerateRecoveryCodes` |

## Invariant 2 — every event through the outbox; outbox the only publisher: PASS
7 aggregates record events (`User`, `Role`, `ServiceAccount`, `ApiKey`, `RefreshToken`,
`PasswordReset`, `TotpCredential`); all 7 Eloquent repositories drain `releaseEvents()` to
`OutboxWriter` in `save()`. No `Event::dispatch`, queue, bus, or direct EventBridge publishing exists
outside `Infrastructure/Outbox` (the relay). Events originate only from aggregates; entities without
domain significance (verification tokens, challenges, recovery codes) deliberately record none.

## Invariant 3 — audit record on every mutation: PASS (32/33 direct; 1 covered by callers)
Every mutating use case writes an audit record, except `GenerateRecoveryCodes` — a helper whose two
callers each audit at the use-case level (`mfa.totp_enabled`, `mfa.recovery_codes_regenerated`), so
every HTTP-reachable mutation path is audited. Failed paths are audited too (`login.failed` reasons,
`login.mfa_failed` with factor, `account_locked`).

## Invariant 4 — mutation idempotency: PASS (explicit exceptions, each with a replay story)
All admin-surface mutations sit under the `idempotency` middleware group. Public mutations outside it
are deliberate; replay is defined by the credential, not a client key:

| Route | Replay story |
|---|---|
| `login`, `login/mfa` | each success legitimately mints a new session; failures counted (lockout, challenge cap) |
| `auth/refresh`, `auth/logout` | refresh token is single-use with rotation + family reuse detection |
| `email/verify`, `auth/password/reset` | consume an atomic single-use token (2J-a) |
| `internal/.../materialize` | atomic per `delivery_ref` — exactly one caller wins (2J-a) |
| `auth/password/reset-request` | supersedes prior resets in one tx; always 202 |
| `service/token` | stateless issuance; each call mints a token by design |
| `tokens/introspect` | read-only despite POST |

## Architecture conformance: PASS, with one platform fix
| Check | Result | Enforced by |
|---|---|---|
| Domain imports no Laravel | ✅ | `DomainPurityTest` + Semgrep + per-slice static check |
| Application depends only on ports | ✅ | per-slice static check (no framework imports) |
| Infrastructure holds all adapters | ✅ | import sweep (Eloquent/JWT/Redis/AWS only under `Infrastructure/`) |
| Controllers contain no business logic | ✅ | zero `Persistence`/`Eloquent`/model imports in controllers |
| Events originate only from aggregates | ✅ | invariant 2 sweep |
| Outbox is the only publisher | ✅ | invariant 2 sweep |
| No layer bypasses Application | ✅ | controllers call use cases only |
| No TODO / FIXME / stubs | ✅ | tree-wide scan clean |
| **Architecture suite runs in CI** | ⚠️ → ✅ | **FOUND: `php-service-ci.yml@v1` ran only Unit/Feature/Integration/Contract — the Architecture suite was declared but never executed in CI (config-service review item H2, inherited). Fixed in `platform-github` this slice: a dedicated CI step now runs it. Local `php artisan test` always ran it, which is why every slice's local gate covered it.** |

## Residual risks (tracked, not blocking)
- Failed-attempt counter increments (lockout, challenge cap) are full-row saves, not atomic
  increments — concurrent failures can undercount by design; the hard guarantee (single-use
  consumption) is atomic.
- `platform-github@v1` tag must be re-pointed (or re-tagged) for the CI fix to reach consumers.
