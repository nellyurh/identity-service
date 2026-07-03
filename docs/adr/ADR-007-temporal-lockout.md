# ADR-007 — Brute-force lock as temporal `locked_until`, not a `locked` UserStatus (deviation)

**Context.** The design package lists `locked` as a `UserStatus` case. **This ADR records a
deliberate deviation, surfaced during the build.**

**Decision.** The lock is a nullable `locked_until` timestamp plus a `failed_login_count`,
checked as `isLocked(now)`. Status remains `active|disabled|deleted`.

**Consequences.** The lock self-expires with **no write** required to unlock; a status-based
lock would need an unlock transition and would leak through every place status is serialized
(user reads, login responses, events). While locked, all attempts fail with the generic
`AUTH_002` — right or wrong password — so the lock neither confirms guesses nor reveals state.
`UserLocked` is still emitted at lock time for downstream visibility. Revisit only if the ARB
wants the status enum instead.
