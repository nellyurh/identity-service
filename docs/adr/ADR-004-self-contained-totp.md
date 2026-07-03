# ADR-004 — Self-contained RFC 6238 TOTP provider behind a port (no dependency)

**Context.** MFA needs TOTP. The project has prior incidents of dependency constraints authored
without registry verification, and the generation environment cannot run `composer install`.

**Decision.** Implement RFC 6238/4226 (HMAC-SHA1, dynamic truncation, RFC 4648 base32)
in-repo (`Rfc6238TotpProvider`), behind the `TotpProvider` port. Correctness is pinned by the
official RFC 6238 test vectors in the unit suite, cross-checked against an independent
implementation before shipping.

**Consequences.** Zero new dependency risk; swapping to `spomky-labs/otphp` later is a
one-line binding change. We own ~100 lines of well-vectored crypto plumbing.
