Repository: identity-service
Status: Milestone 1 (Scaffolding & Shared Kernel) — authored; runtime proof runs on developer/CI
Build order: 5 of 6
Template: config-service v1.0-template

Engine: Identity Service (authentication, authorization, tokens, keys)
Dependencies:
  - unero-platform-terraform  (infra: Aurora, Redis, EventBridge)
  - unero-shared-schemas      (event envelope + event payload contracts)  [submodule]
  - platform-github           (reusable CI/CD @v1)

Provides (this milestone):
  - Ports & Adapters skeleton (Domain/Application/Infrastructure/Interfaces)
  - Domain\Shared kernel: Actor, DomainException base, DomainEvent contract
  - Transactional outbox writer + EventBridge relay (producer: identity-service)
  - Append-only audit writer; idempotency middleware; service-auth + audit-context middleware
  - Shared error envelope on every error path (domain/validation/HTTP/unhandled)
  - Health endpoints (liveness + DB/Redis readiness); outbox:relay command (scheduled)
  - 3 migrations (outbox_entries, audit_events, idempotency_keys)

Surface (this milestone):
  - 2 HTTP endpoints (healthz, readyz)
  - 1 console command (outbox:relay), scheduled every minute

Tests (6 suites): Unit, Feature, Integration, Contract, Architecture, Mutation
  - Unit: Actor value object
  - Architecture: domain purity + ports-are-interfaces
  - Contract: emitted event envelope conforms to shared schema (via submodule, not duplicated)
  - Integration: outbox + audit writers against the DB
  - Feature: health endpoints

Deltas from raw config-service template (carried from the certification review, applied here
so identity-service is green and production-correct from milestone 1):
  - scripts/coverage-gate.php added and bucketed to app/Domain + app/Application (B2/H3)
  - phpunit.xml declares the Feature suite the reusable CI invokes (B1)
  - scripts/{deploy,smoke,canary,deploy-marker}.sh added; fail loudly without infra env (B3)
  - Contract test consumes schemas/shared submodule; no in-repo schema duplication (B4)
  - Error envelope applied to HTTP/validation/unhandled errors, not only DomainException (H1)
  - readyz checks Redis in addition to DB (identity's hot path) (M5)

NOT run in this authoring environment (PHP absent, Packagist blocked) — run on your Mac / CI:
  - composer install && git submodule update --init
  - php artisan migrate && php artisan test
  - vendor/bin/phpstan analyse ; vendor/bin/pint --test ; vendor/bin/rector process --dry-run

Verified in this environment:
  - repository structure + PSR-4 namespace/path alignment (all app/ files)
  - PHP brace/paren balance across every .php file
  - phpunit.xml (6 suites) + all JSON/YAML parse
