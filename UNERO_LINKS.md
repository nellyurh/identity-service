# Sibling platform repositories

During the foundation phase these are referenced by explicit URL (no GitHub org yet). After
an org is created and repos are transferred, swap the owner and drop this file.

| Repo | Purpose | URL |
|------|---------|-----|
| unero-platform-terraform | AWS infrastructure (VPC, ECS, Aurora, Redis, EventBridge, SQS, KMS) | https://github.com/nellyurh/unero-platform-terraform |
| unero-shared-schemas | Event/error/provider JSON Schemas + OpenAPI components | https://github.com/nellyurh/unero-shared-schemas |
| platform-github | Reusable CI/CD workflows + Unero SAST ruleset (`@v1`) | https://github.com/nellyurh/platform-github |

These are also exposed as `UNERO_PLATFORM_TERRAFORM`, `UNERO_SHARED_SCHEMAS`, and
`UNERO_PLATFORM_GITHUB` in `.env.example` and surfaced under `config('unero.platform')`.
