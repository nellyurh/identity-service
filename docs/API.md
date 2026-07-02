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
| GET | `/.well-known/jwks.json` | public | — | Public RS256 verification keys (JWKS) |
| POST | `/identity/change-password` | actor | ✅ | Self-service password change → `200 { data: UserProfile }` |
| GET | `/identity/users/{id}` | actor | — | Fetch a user → `200 { data: UserProfile }` |
| GET | `/identity/users?email=\|username=` | actor | — | Look up a user → `200 { data: UserProfile }` |
| POST | `/identity/users/{id}/disable` | actor | ✅ | Disable → `200 { data: UserProfile }` |
| POST | `/identity/users/{id}/enable` | actor | ✅ | Re-enable → `200 { data: UserProfile }` |
| DELETE | `/identity/users/{id}` | actor | ✅ | Soft-delete → `200 { data: UserProfile }` |

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

## Refresh & sessions
`login` also issues a **rotating refresh token** — an opaque 256-bit secret returned once and
stored only as a SHA-256 hash, belonging to a new session **family** (30-day default TTL).
`/auth/refresh` exchanges it for a fresh access + refresh pair and rotates the old one out;
presenting an already-rotated token is **reuse** (a replayed/stolen token) and revokes the entire
family (AUTH_012). `/auth/logout` revokes the family. Both are public and carry no idempotency
key — the refresh token is itself single-use. The short-lived access token is not yet actively
revoked on logout; it self-expires within the access TTL (active revocation via introspection +
`jti` blacklist arrives next).

## Not yet issued
Access-token `jti` blacklist (Redis), token introspection, multi-key rotation (`signing_keys`),
and explicit session listing arrive in the next slice.
