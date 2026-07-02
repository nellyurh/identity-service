# API — identity-service

Full contract in `openapi/openapi.yaml`. Mutations require `Idempotency-Key`. Errors use
the shared error envelope; `error.code` matches `^[A-Z]+_[0-9]{3}$`.

## Endpoints (this milestone)
| Method | Path | Purpose | Notes |
|--------|------|---------|-------|
| GET | `/healthz` | liveness | |
| GET | `/readyz` | readiness | 503 if PostgreSQL or Redis down |

Authentication, users, organizations, roles, permissions, sessions, API keys, password
reset, email verification, and MFA endpoints are added per delivery milestone.

## Error codes (shared envelope)
`AUTH_001` unauthenticated (401), `IDEMPOTENCY_001` missing key (400), `IDEMPOTENCY_002`
key reuse with different body (409), `VALIDATION_422` invalid input (422), `HTTP_<status>`
generic HTTP errors, `SERVER_500` unhandled (500, masked in production).
