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

Planned (landing per milestone): `RoleAssigned`, `RoleRemoved`, `PermissionGranted`,
`PermissionRevoked` (2F), `ApiKeyCreated`, `ApiKeyRevoked` (2G), `SessionRevoked`,
`MFAEnabled`, `MFADisabled`, `TokenIssued`, `TokenRevoked` (2E/2H).

## Consumed
None. Identity is a source of truth for principals and access.
