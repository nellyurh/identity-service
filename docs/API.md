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
| POST | `/identity/login` | public | — | Verify credentials → `200 { data: Principal }` (tokens land in the JWT milestone) |
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

## Not yet issued
`login` returns the principal only. Access/refresh tokens, JWKS, sessions, MFA, email
verification, and password reset arrive in later milestones; `POST /identity/register` is the
only write that currently emits a domain event (`UserRegistered`).
