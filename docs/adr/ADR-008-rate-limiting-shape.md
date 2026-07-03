# ADR-008 — Rate limiting at HTTP middleware, keyed by IP + identifier; blocks not audited

**Context.** The design places a RateLimiter check inside the login use case, taking the IP.
IP is an HTTP concern; passing it into commands would ripple through the application layer.

**Decision.** Throttling is an HTTP middleware (`ratelimit:<bucket>,<max>,<decay>`) applied
per route, counting **two keys per request**: caller IP, and the targeted identifier
(email / client_id, normalised + hashed) when present. Backing store is the `RateLimiter`
port (cache-backed adapter — Redis in production). Blocked requests are **not** written to
the audit table: per-block writes would make the limiter its own DoS write-amplification
vector; blocks belong to edge/infra metrics.

**Consequences.** Throttling happens before any credential or database work; the application
layer stays IP-free. Neither IP rotation (identifier key) nor single-IP spraying (IP key)
evades it. Limits are inline in `routes/api.php`, reviewable at a glance.
