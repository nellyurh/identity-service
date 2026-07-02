# API — identity-service

Base URL (internal): `https://identity.internal.unero.com`. All responses use the shared
error envelope on failure (`{ error: { code, message, detail? }, request_id }`). See
`docs/ERROR_CATALOG.md`. The machine-readable contract is `openapi/openapi.yaml`.

## Conventions

| Concern | Rule |
|---|---|
| Actor | Internal admin endpoints require `X-Actor-Id` (+ optional `X-Actor-Type: user\|service`), injected by the gateway. Missing ⇒ `AUTH_001` (401). |
| Idempotency | Every mutation requires `Idempotency-Key`. Missing ⇒ `IDEMPOTENCY_001` (400); same key + different body ⇒ `IDEMPOTENCY_002` (409). |
| Correlation | `X-Request-Id` echoed on every response; generated if absent. |
| IDs | User ids are ULIDs. Malformed id in a path ⇒ 404 (route-level constraint). |
| Success shape | `{ "data": { … } }`. |

## Endpoints

| Method | Path | Auth | Idempotent | Purpose |
|---|---|---|---|---|
| POST | `/identity/register` | public | ✅ | Register a user → `201 { data: { user_id } }` |
| POST | `/identity/login` | public | — | Verify credentials → `200 { data: Principal + access_token, refresh_token, token_type, expires_in, refresh_expires_in }` |
| POST | `/identity/auth/refresh` | public | — | Rotate the token pair → `200 { data: TokenPair }` (reuse → AUTH_012, family revoked) |
| POST | `/identity/auth/logout` | public | — | Revoke the session family → `200 { data: { user_id } }` (idempotent) |
| POST | `/identity/tokens/introspect` | service | — | Verify + denylist-check an access token → `200 { data: { active, sub?, jti?, token_use?, exp? } }` |
| GET | `/.well-known/jwks.json` | public | — | Public RS256 verification keys (JWKS) |
| POST | `/identity/change-password` | actor | ✅ | Self-service password change → `200 { data: UserProfile }` |
| GET | `/identity/users/{id}` | actor | — | Fetch a user → `200 { data: UserProfile }` |
| GET | `/identity/users?email=\|username=` | actor | — | Look up a user → `200 { data: UserProfile }` |
| GET | `/identity/permissions` | actor | — | List the permission catalog → `200 { data: [PermissionView] }` |
| POST | `/identity/permissions` | actor | ✅ | Define a (non-system) permission → `201 { data: PermissionView }` |
| GET | `/identity/roles` | actor | — | List roles → `200 { data: [RoleView] }` |
| GET | `/identity/roles/{id}` | actor | — | Read a role → `200 { data: RoleView }` |
| POST | `/identity/roles` | actor | ✅ | Create a (non-system) role → `201 { data: RoleView }` |
| PATCH | `/identity/roles/{id}` | actor | ✅ | Rename/re-describe (system names protected) → `200 { data: RoleView }` |
| POST | `/identity/roles/{id}/permissions` | actor | ✅ | Grant a permission → `200 { data: RoleView }` |
| DELETE | `/identity/roles/{id}/permissions/{name}` | actor | ✅ | Revoke a permission → `200 { data: RoleView }` |
| POST | `/identity/users/{id}/disable` | actor | ✅ | Disable → `200 { data: UserProfile }` |
| POST | `/identity/users/{id}/enable` | actor | ✅ | Re-enable → `200 { data: UserProfile }` |
| DELETE | `/identity/users/{id}` | actor | ✅ | Soft-delete → `200 { data: UserProfile }` |
| POST | `/identity/users/{id}/roles` | actor | ✅ | Assign a role → `200 { data: UserProfile }` (bumps authz_version) |
| DELETE | `/identity/users/{id}/roles/{roleId}` | actor | ✅ | Revoke a role → `200 { data: UserProfile }` (bumps authz_version) |

## Error codes (this surface)

| Code | HTTP | When |
|---|---|---|
| `VALIDATION_422` | 422 | Request body fails validation (fields in `detail.fields`) |
| `AUTH_001` | 401 | Missing/invalid actor on an admin endpoint |
| `AUTH_002` | 401 | Invalid credentials / unknown account on login (no enumeration) |
| `USER_001` | 404 | User not found |
| `USER_004` | 409 | Email already registered |
| `USER_005` | 409 | Username already taken |
| `IDEMPOTENCY_001` | 400 | Idempotency-Key header missing on a mutation |
| `IDEMPOTENCY_002` | 409 | Idempotency-Key reused with a different body |
| `AUTH_010` | 401 | Access token invalid (signature, expiry, audience, malformed) |
| `AUTH_011` | 401 | Refresh token invalid (unknown, revoked, or expired) |
| `AUTH_012` | 401 | Refresh token reuse detected — the session family was revoked |
| `PERMISSION_001` | 404 | Permission not found |
| `PERMISSION_002` | 409 | Permission already exists |
| `ROLE_001` | 404 | Role not found |
| `ROLE_002` | 403 | System role cannot be renamed or deleted |
| `ROLE_003` | 409 | Role name already taken |
| `AUTHZ_001` | 403 | Access token lacks the permission required by the endpoint |

## Tokens
`login` issues a short-lived **RS256 access token** (15 min default), signed with the current
key; other services verify offline against `/.well-known/jwks.json`. The algorithm is pinned to
RS256 and verification uses the public key only. Signing PEMs come from
`IDENTITY_JWT_PRIVATE_KEY` / `IDENTITY_JWT_PUBLIC_KEY` (env / Secrets Manager) — never committed.

Generate a dev keypair:
```
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out jwt-private.pem
openssl rsa -in jwt-private.pem -pubout -out jwt-public.pem
export IDENTITY_JWT_PRIVATE_KEY="$(cat jwt-private.pem)"
export IDENTITY_JWT_PUBLIC_KEY="$(cat jwt-public.pem)"
```

**Key rotation (zero-downtime).** The token header carries a `kid`; verifiers resolve the key by
`kid`, so several keys can be trusted at once. To rotate: promote a new current key
(`IDENTITY_JWT_KID` / `IDENTITY_JWT_PRIVATE_KEY` / `IDENTITY_JWT_PUBLIC_KEY`) and move the previous
**public** key into `IDENTITY_JWT_VERIFY_ONLY_PUBLIC_KEYS` — a JSON object `{ "<kid>": "<pem>" }`.
JWKS then publishes the whole set; tokens signed by the old key keep verifying until they expire,
after which its entry is dropped. (An automated DB-backed `signing_keys` lifecycle can replace the
env wiring later without changing the token/JWKS contract.)

## Authorization (permissions in token)
At issuance (login and each refresh) the user's roles are resolved to a deduplicated, name-ordered
set of `resource.action` permissions and baked into the access token as a `permissions` claim,
alongside `authz_ver` (the user's authorization version). Verifiers authorize **offline** on the
`permissions` claim — no callback to identity-service. `authz_ver` bumps on every role change, so a
refreshed token reflects the latest grants and a stale token is detectable. Introspection
(`/tokens/introspect`) echoes both fields for callers that want the current view.

The `permission:<name>` route middleware is the guard primitive: it verifies the Bearer token and
returns `403 AUTHZ_001` if the required permission is absent (`401 AUTH_010` if the token is
missing or invalid). Revocation is not checked on this path — routine authorization trusts the
short access TTL; high-value operations additionally introspect.

## Refresh & sessions
`login` also issues a **rotating refresh token** — an opaque 256-bit secret returned once and
stored only as a SHA-256 hash, belonging to a new session **family** (30-day default TTL).
`/auth/refresh` exchanges it for a fresh access + refresh pair and rotates the old one out;
presenting an already-rotated token is **reuse** (a replayed/stolen token) and revokes the entire
family (AUTH_012). `/auth/logout` revokes the family. Both are public and carry no idempotency
key — the refresh token is itself single-use. On logout and on reuse detection, every still-valid
access `jti` in the family is added to a **denylist** (cache/Redis, self-expiring at the token's
own TTL); `/tokens/introspect` consults it, so a service doing a high-value operation sees the
revocation near-real-time. Routine stateless verification does not check the denylist — it trusts
the short access TTL.

## Not yet issued
An automated DB-backed signing-key lifecycle (`signing_keys`, scheduled promotion/retirement),
password-change-triggered session revocation, and explicit session listing arrive in later slices.
