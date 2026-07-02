# Events — identity-service

Identity domain events are written to the outbox in the same transaction as the state
change and relayed to EventBridge wrapped in the shared event envelope
(`unero-shared-schemas/schemas/envelopes/event-envelope.schema.json`).

Envelope: `event_id` (UUIDv7), `event_type` (PascalCase), `event_version`, `emitted_at`
(RFC3339), `producer` (`identity-service`), `correlation_id`, `causation_id`,
`schema_version`. Source: `unero.identity-service`.

## Emitted (planned catalog, landing per milestone)
UserCreated, UserUpdated, UserDeleted, PasswordChanged, EmailVerified, MFAEnabled,
MFADisabled, SessionRevoked, ApiKeyCreated, ApiKeyRevoked, RoleAssigned, RoleRemoved,
PermissionGranted, PermissionRevoked. Payloads validate against the corresponding
`unero-shared-schemas` event schema (e.g. `UserCreated.schema.json`).

## Consumed
None at this milestone. Identity is a source of truth for principals and access.
