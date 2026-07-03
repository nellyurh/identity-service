# ADR-002 — Password-reset delivery: event without token + authenticated materialise callback

**Context.** Reset-request is public and must always return 202 with no token (no enumeration),
yet events must not carry plaintext tokens. The token has to reach the notification service
somehow. Options considered: token-in-event (scoped exception), KMS-encrypted token in event,
event-without-token + callback. **Chosen explicitly by the platform owner.**

**Decision.** Two-phase. Request creates a `PasswordReset` carrying only an opaque
`delivery_ref`; `PasswordResetRequested {user_id, delivery_ref}` is emitted — no email, no
token, no hash. The notification service exchanges the ref via the authenticated
`POST /internal/password-reset/deliveries/{ref}/materialize`, and **only then** is the token
minted: hash stored, raw value + recipient email returned once.

**Consequences.** The raw token never exists at request time and never enters an event.
Materialisation is single-use per ref and atomic (ADR-006). Adds one internal round-trip for
the notification service.
