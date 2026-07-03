# ADR-006 — Atomic one-time credentials via conditional updates

**Context.** Verification tokens, reset deliveries/tokens, MFA challenges, and recovery codes
were consumed by SELECT-then-UPDATE: two concurrent requests could both pass the check.
For reset materialisation, two callers could silently clobber each other's token hash.

**Decision.** Every consume/materialise is a **conditional update**, rows-affected checked:
`UPDATE ... SET used_at = now WHERE id = ? AND used_at IS NULL AND expires_at > now`.
`rows == 1` wins; `rows == 0` means a concurrent request won (or expiry) → the credential's
generic invalid error. Use cases run the guard **first**, inside the transaction, so a lost
race aborts before any mutation.

**Consequences.** True single-use semantics under concurrency for every one-shot credential;
of two concurrent password-reset completions exactly one changes the password. Failed-attempt
*counters* remain full-row saves (may undercount under concurrency — accepted; the hard
guarantee is consumption).
