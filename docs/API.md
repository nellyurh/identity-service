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
| POST | `/identity/login` | public | — | Verify credentials → tokens, **or** `200 { data: mfa_required, challenge_token, expires_in }` if MFA is active |
| POST | `/identity/login/mfa` | public | — | Complete MFA login → `200 { data: Principal + tokens }` |
| POST | `/identity/service/token` | public | — | Client-credentials grant → `200 { data: access_token, token_type, expires_in, scope }` (service token) |
| POST | `/identity/email/verify` | public | — | Verify email with a token → `200 { data: user_id, verified }` |
| POST | `/identity/auth/password/reset-request` | public | — | Start a reset → `202 { data: status }` (always, no enumeration) |
| POST | `/identity/auth/password/reset` | public | — | Complete a reset → `200 { data: user_id, reset }` (revokes all sessions) |
| POST | `/identity/internal/password-reset/deliveries/{ref}/materialize` | actor | — | Exchange a delivery_ref → `200 { data: email, token, expires_at }` (token minted, shown once) |
| POST | `/identity/users/{id}/email/verification-request` | actor | ✅ | Issue a verification token → `200 { data: token, expires_at }` (token shown once) |
| POST | `/identity/users/{id}/mfa/totp/enroll` | actor | ✅ | Begin TOTP enrollment → `200 { data: secret, provisioning_uri }` (secret shown once) |
| POST | `/identity/users/{id}/mfa/totp/confirm` | actor | ✅ | Confirm with a code → `200 { data: enabled }` (activates, emits MFAEnabled) |
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
| GET | `/identity/service-accounts` | actor | — | List service accounts → `200 { data: [ServiceAccountView] }` (no secrets) |
| GET | `/identity/service-accounts/{id}` | actor | — | Read a service account → `200 { data: ServiceAccountView }` |
| POST | `/identity/service-accounts` | actor | ✅ | Create → `201 { data: ServiceAccountView + secret }` (secret shown once) |
| POST | `/identity/service-accounts/{id}/rotate` | actor | ✅ | Rotate secret → `200 { data: ServiceAccountView + secret }` |
| POST | `/identity/service-accounts/{id}/disable` | actor | ✅ | Disable → `200 { data: ServiceAccountView }` |
| GET | `/identity/api-keys?owner_type=&owner_id=` | actor | — | List an owner's API keys → `200 { data: [ApiKeyView] }` (no secrets) |
| POST | `/identity/api-keys` | actor | ✅ | Create → `201 { data: ApiKeyView + key }` (full key shown once) |
| POST | `/identity/api-keys/{id}/rotate` | actor | ✅ | Rotate → `200 { data: ApiKeyView + key, replaced_key_id, grace_expires_at }` |
| DELETE | `/identity/api-keys/{id}` | actor | ✅ | Revoke → `200 { data: ApiKeyView }` |

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
| `SERVICE_001` | 404 | Service account not found |
| `SERVICE_002` | 401 | Service account cannot authenticate (disabled) |
| `SERVICE_003` | 409 | Service account name already taken |
| `SERVICE_004` | 401 | Invalid client credentials (client-credentials grant; generic, no enumeration) |
| `APIKEY_001` | 404 | API key not found |
| `APIKEY_002` | 403 | API key lacks the scope required by the endpoint |
| `APIKEY_003` | 401 | Invalid API key (missing/malformed/unknown/expired/revoked; generic, no enumeration) |
| `APIKEY_004` | 409 | A revoked API key cannot be rotated |
| `VERIFICATION_001` | 400 | Email verification token is invalid, used, or expired |
| `RESET_001` | 404 | Password reset delivery reference is invalid, already materialised, or expired |
| `RESET_002` | 400 | Password reset token is invalid, used, unmaterialised, or expired |
| `MFA_001` | 409 | MFA is already enabled for this user |
| `MFA_002` | 422 | TOTP verification code is incorrect |
| `MFA_003` | 404 | No pending MFA enrollment to confirm |
| `MFA_004` | 401 | MFA challenge is invalid, used, or expired |

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

## Email verification
Requesting verification (`POST /users/{id}/email/verification-request`, gateway-authenticated) mints a
single-use, hashed, TTL-bound token (`IDENTITY_EMAIL_VERIFICATION_TTL`, default 24h), invalidates any
outstanding token for that user, and returns the **raw** token once to the trusted caller (the
notification orchestrator) to email. The raw token never enters an event or the outbox — only its
SHA-256 hash is stored. Requesting for an already-verified user is `409 USER_003`.

Verifying (`POST /email/verify {token}`, public — the token is the proof) looks the token up by hash,
marks the user's email verified (emitting `EmailVerified`), and burns the token. Unknown/used/expired
tokens fail generically with `400 VERIFICATION_001`. It's idempotent for an already-verified user (the
token is consumed, no second event).

> Delivery-channel decision (surfaced for ARB, ref. design open-question #4): verification uses a
> **synchronous return-to-trusted-caller** model rather than putting the token in an event, honoring
> "no plaintext tokens in event payloads." Password reset (2H-b) is event-driven with a 202/no-token
> response for enumeration resistance, so its delivery bridge will need explicit confirmation.

## Password reset (request + delivery)
Two-phase, so the token honours "no plaintext tokens in events" while still returning `202` with no
token (no enumeration). `POST /auth/password/reset-request {email}` always returns `202`: if the email
matches a user, any outstanding reset is superseded and a new one is created carrying an opaque
`delivery_ref`, and `PasswordResetRequested {user_id, delivery_ref}` is emitted (no email, no token).
The token does **not** exist yet.

The notification service consumes that event and calls
`POST /internal/password-reset/deliveries/{ref}/materialize` (gateway-authenticated). Only then is the
raw token minted — its hash stored, the raw value returned once alongside the recipient email so the
service can send the link. A `delivery_ref` materialises exactly once; unknown/used/expired refs
return `404 RESET_001`.

Completing the reset is public — the token is the proof: `POST /auth/password/reset {token,
new_password}` redeems the token by hash, sets the new password (emitting `PasswordChanged`), burns
the token, and **revokes every one of the user's refresh families** (`RevocationReason::PasswordChange`,
one `TokenRevoked` per family) so all existing sessions die. No current-password check.
Unknown/used/unmaterialised/expired tokens fail generically with `400 RESET_002`.

## MFA (TOTP)
Two-step enrollment. `POST /users/{id}/mfa/totp/enroll` generates a base32 secret, stores it
**encrypted at rest** (`SecretCipher`) in a *pending* credential, and returns the secret + an
`otpauth://` provisioning URI once (to key in or render as a QR code). The secret is encrypted, not
hashed — the server must recompute codes to verify them. Enrolling when MFA is already active is
`409 MFA_001`; enrolling again while pending supersedes the previous secret.

`POST /users/{id}/mfa/totp/confirm {code}` verifies the code (RFC 6238, ±1 step of skew) against the
decrypted secret and, on success, activates the credential and emits `MFAEnabled`. No pending
enrollment → `404 MFA_003`; wrong code → `422 MFA_002`.

**At login.** When a user has an active TOTP credential, `POST /login` verifies the password and then,
instead of tokens, returns `{ mfa_required: true, challenge_token, expires_in }`. The `challenge_token`
is opaque, single-use, short-lived (`IDENTITY_MFA_CHALLENGE_TTL`, default 5 min), and stored only as a
hash — being opaque it can never be mistaken for an access token. The client completes login with
`POST /login/mfa { challenge_token, code }`: the code is verified against the active credential, the
challenge is consumed, and a normal session (access + refresh) is issued via the same path as a direct
login. Invalid/used/expired challenge → `401 MFA_004`; wrong code → `422 MFA_002`. (Per-attempt MFA
lockout is deferred to the 2I hardening pass.)

## Service tokens (client credentials)
Platform services authenticate to each other with their own rotatable credential rather than a
shared secret. `POST /identity/service/token` takes `client_id` (the service account name) and
`client_secret` and returns a short-lived RS256 token with `token_use=service`, `sub` = the service
account id, and a `scopes` claim (array) carrying the account's granted scopes. There is no refresh
token — a service simply re-authenticates when the token expires. Unknown client, wrong secret, and
disabled account all fail identically with `401 SERVICE_004` (the secret is compared against a dummy
hash when the account is absent, so timing does not reveal existence).

## API keys (programmatic access)
Long-lived credentials for external/programmatic callers, owned by a user or a service account. The
key is `unero_<env>_<prefix>.<secret>`: the `prefix` is public and uniquely indexed (O(1) lookup, and
a leaked key is scannable by prefix), while only the SHA-256 hash of the `<secret>` is stored. The
full key is shown **once** at creation (`data.key`) and never again. Keys carry scopes and an
optional `expires_at`, record a throttled `last_used_at`, and can be revoked (immediate, permanent).
Creation validates that the owner exists (`USER_001` / `SERVICE_001` otherwise).

**Authenticating with a key.** The `apikey` middleware guards an endpoint:
`Authorization: ApiKey unero_<env>_<prefix>.<secret>`. It looks the key up by prefix (O(1)),
verifies the secret constant-time, and rejects missing/malformed/unknown/expired/revoked keys
identically with `401 APIKEY_003` (no enumeration). Applied as `apikey:<scope>`, it additionally
requires that scope, returning `403 APIKEY_002` if absent. On success it advances `last_used_at`
(throttled by `IDENTITY_API_KEY_TOUCH_THROTTLE`, default 1h) and records the acting principal
(`type=api_key`). The prefix is public by design, so lookup is not treated as sensitive; the secret
comparison is the constant-time gate.

**Rotation (zero downtime).** `POST /api-keys/{id}/rotate` issues a fresh key that inherits the
rotated key's owner, name and scopes, and returns its full string once. The rotated key isn't killed
immediately — its expiry is capped at `now + IDENTITY_API_KEY_ROTATION_GRACE` (default 24h) and it
keeps authenticating until then, so in-flight clients migrate without an outage; after the window it
passively expires. A revoked key cannot be rotated (`APIKEY_004`). Rotation emits `ApiKeyRotated`
(old → replacement) and `ApiKeyCreated` (new).

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
