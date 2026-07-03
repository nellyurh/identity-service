# identity-service — Architecture Decision Records

Service-level decisions that extend the frozen platform architecture and the identity-service
design package. Each records a choice actually made during the build, including deviations
from the original design that were surfaced for review. Format: Context / Decision / Consequences.

| ADR | Title |
|---|---|
| 001 | Email-verification token returned synchronously to the trusted caller |
| 002 | Password-reset delivery: event without token + authenticated materialise callback |
| 003 | Stateful opaque MFA challenge (not a JWT) |
| 004 | Self-contained RFC 6238 TOTP provider behind a port (no dependency) |
| 005 | TOTP secrets encrypted at rest via a SecretCipher port |
| 006 | Atomic one-time credentials via conditional updates |
| 007 | Brute-force lock as temporal `locked_until`, not a `locked` UserStatus (design deviation) |
| 008 | Rate limiting at HTTP middleware, keyed by IP + identifier; blocks not audited |
