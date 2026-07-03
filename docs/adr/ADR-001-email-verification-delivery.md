# ADR-001 — Email-verification token returned synchronously to the trusted caller

**Context.** Platform rule: no plaintext tokens in event payloads. The verification token must
still reach the notification orchestrator for delivery.

**Decision.** `POST /users/{id}/email/verification-request` (gateway-authenticated) returns the
raw token **once** in the response to the trusted caller; only its SHA-256 hash is stored, and
no event is emitted at request time (`EmailVerified` fires on verification only).

**Consequences.** Simple single call; the token never touches the outbox. Requires the caller
to be trusted (gateway-authenticated). Password reset could not use this shape because its
request endpoint is public and must return 202 with no token — see ADR-002.
