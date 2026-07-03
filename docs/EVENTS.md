# Events — identity-service

Identity domain events are written to `outbox_entries` in the same transaction as the state
change (opened by the application service via the `TransactionManager` port and executed by
the repository's `save()`), then relayed to EventBridge wrapped in the shared event envelope
(`unero-shared-schemas/schemas/envelopes/event-envelope.schema.json`).

Envelope: `event_id` (UUIDv7), `event_type` (PascalCase, business-intent), `event_version`,
`emitted_at` (RFC3339), `producer` (`identity-service`), `correlation_id`, `causation_id`,
`schema_version`. Source: `unero.identity-service`.

## Naming & privacy

- **Business-intent names, not CRUD.** `UserRegistered`, `EmailVerified`, `PasswordChanged`,
  `RoleAssigned` — never `UserCreated`/`UserUpdated`. Identity defines this vocabulary for
  the whole platform; other services align to it.
- **No PII on the bus.** Events carry `user_id` and status flags, never email, username, or
  any secret. Consumers that need profile data read it from the identity API.

## Emitted (this milestone)

| Event | Payload | Shared schema |
|-------|---------|---------------|
| `UserRegistered` | `user_id`, `email_verified`, `occurred_at` | `events/UserRegistered.schema.json` |
| `EmailVerified` | `user_id`, `occurred_at` | `events/EmailVerified.schema.json` |
| `PasswordChanged` | `user_id`, `occurred_at` | `events/PasswordChanged.schema.json` |
| `UserDisabled` | `user_id`, `occurred_at` | (schema lands with its use case) |
| `UserActivated` | `user_id`, `reason`, `occurred_at` | (schema lands with its use case) |
| `ServiceAccountCreated` | `service_account_id`, `name`, `scopes`, `occurred_at` | (2G) |
| `TokenIssued` | `user_id`, `family_id`, `access_jti`, `occurred_at` | `events/TokenIssued.schema.json` |
| `TokenRevoked` | `user_id`, `family_id`, `reason`, `occurred_at` | `events/TokenRevoked.schema.json` |
| `RoleAssigned` | `user_id`, `role_id`, `authz_version`, `occurred_at` | `events/RoleAssigned.schema.json` |
| `RoleRemoved` | `user_id`, `role_id`, `authz_version`, `occurred_at` | `events/RoleRemoved.schema.json` |
| `RoleCreated` | `role_id`, `name`, `is_system`, `occurred_at` | `events/RoleCreated.schema.json` |
| `PermissionGranted` | `role_id`, `permission_id`, `occurred_at` | `events/PermissionGranted.schema.json` |
| `PermissionRevoked` | `role_id`, `permission_id`, `occurred_at` | `events/PermissionRevoked.schema.json` |
| `ServiceAccountCreated` | `service_account_id`, `name`, `scopes`, `occurred_at` | `events/ServiceAccountCreated.schema.json` |
| `ServiceAccountCredentialRotated` | `service_account_id`, `occurred_at` | `events/ServiceAccountCredentialRotated.schema.json` |
| `ServiceAccountDisabled` | `service_account_id`, `occurred_at` | `events/ServiceAccountDisabled.schema.json` |
| `ApiKeyCreated` | `api_key_id`, `prefix`, `name`, `owner_type`, `owner_id`, `scopes`, `expires_at`, `created_by`, `occurred_at` | `events/ApiKeyCreated.schema.json` |
| `ApiKeyRevoked` | `api_key_id`, `occurred_at` | `events/ApiKeyRevoked.schema.json` |
| `ApiKeyRotated` | `api_key_id`, `replacement_id`, `occurred_at` | `events/ApiKeyRotated.schema.json` |
| `PasswordResetRequested` | `user_id`, `delivery_ref`, `occurred_at` | `events/PasswordResetRequested.schema.json` |
| `MFAEnabled` | `user_id`, `method`, `occurred_at` | `events/MFAEnabled.schema.json` |
| `MFADisabled` | `user_id`, `method`, `occurred_at` | `events/MFADisabled.schema.json` |
| `UserLocked` | `user_id`, `locked_until`, `occurred_at` | `events/UserLocked.schema.json` |

`TokenIssued` fires on login (new family) and on each refresh rotation (same family). `TokenRevoked`
is family-level and fires on logout, on refresh-token **reuse detection**, or when a security change
invalidates sessions; `reason` is one of `logout | reuse_detected | password_change`.

The catalog above is complete for v1.0.0. A separate `SessionRevoked` event was originally
planned but is superseded: session revocation is expressed as `TokenRevoked` (one per refresh
family, with a `reason` — logout, password_change, reuse_detected), which downstream consumers
already receive.

Note: seeding the built-in roles emits `RoleCreated` and one `PermissionGranted` per seeded grant
(e.g. `super_admin` emits 22). This is intentional — the grants genuinely occur — and only runs on
first seed.

## Consumed
None. Identity is a source of truth for principals and access.
