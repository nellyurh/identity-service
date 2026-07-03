# Security — identity-service

This service is the trust anchor of the Unero platform. Its posture, outer → inner:

| Layer | Control |
|---|---|
| Transport | HSTS (edge + app), TLS-only; deny-all CSP, nosniff, frame-deny, no-referrer; `Cache-Control: no-store` on every token-bearing response (JWKS deliberately cacheable) |
| Abuse | fixed-window rate limits per **IP and per identifier** on all public credential endpoints, before any credential/DB work |
| Accounts | brute-force lockout (temporal `locked_until`, `UserLocked` event); all attempts while locked fail generically |
| Enumeration | unknown email, wrong password, locked account, and disabled-account-with-wrong-password are indistinguishable (`AUTH_002`); unknown-email path burns equivalent Argon2id time (timing parity) |
| Credentials | Argon2id password hashing; opaque high-entropy refresh tokens stored as SHA-256, single-use with rotation + family reuse detection; API-key secrets hashed, constant-time compare |
| MFA | RFC 6238 TOTP (secret **encrypted at rest**), opaque single-use login challenge, per-challenge attempt cap, one-time recovery codes (hashed) |
| One-time credentials | every consume/materialise is an atomic conditional update (`... WHERE used_at IS NULL AND expires_at > now`, rows-affected checked) — no SELECT-then-UPDATE anywhere |
| Events | no plaintext token, secret, hash, or PII ever enters an event payload (contract-tested); password-reset tokens are minted only at the authenticated materialise callback |
| Audit | every mutation — including failed logins, with reasons — writes an append-only, DB-enforced `audit_events` row |

Verification evidence: `docs/RELEASE_AUDIT.md`. Decision record: `docs/adr/`.

## Reporting
Report suspected vulnerabilities privately to the Unero platform security contact
(see the organisation profile); do not open public issues for security reports.
