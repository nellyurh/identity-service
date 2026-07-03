# ADR-003 — Stateful opaque MFA challenge (not a JWT)

**Context.** After a correct password, an MFA user must complete a second factor before any
tokens are issued. A signed "challenge JWT" was considered.

**Decision.** The challenge is an **opaque, high-entropy, single-use, short-TTL (5 min)** token
stored only as a hash in `mfa_challenges`. It proves "password already verified" and is
exchangeable only at `POST /login/mfa`.

**Consequences.** Being opaque, it can never be mistaken for (or replayed as) an access token
by any verifier. Costs one table and a lookup. Gains a natural home for the per-challenge
attempt cap (`failed_attempts`) and atomic consumption (ADR-006).
