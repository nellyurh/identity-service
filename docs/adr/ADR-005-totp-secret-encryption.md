# ADR-005 — TOTP secrets encrypted at rest via a SecretCipher port

**Context.** Unlike passwords, TOTP secrets must be **recoverable** — the server recomputes
codes to verify them — so hashing is impossible.

**Decision.** Secrets are encrypted with authenticated symmetric encryption behind a
`SecretCipher` port; the adapter uses Laravel's encrypter (AES-256, keyed by `APP_KEY`,
sourced from Secrets Manager in production). Plaintext exists only transiently during
enrollment (shown once) and verification.

**Consequences.** A database dump alone does not yield MFA secrets. Key custody rides the
existing `APP_KEY` lifecycle; a KMS-envelope adapter can replace the binding without domain
changes.
