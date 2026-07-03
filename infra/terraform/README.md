# identity-service — staging infrastructure

Composes onto the platform substrate (unero-platform-terraform, staging): consumes
vpc/subnets/app-SG/cluster/roles/log-group/secret-containers via remote state; owns its
ECR repository, ALB, target group, task definition, and ECS service. Deployed by
.github/workflows/deploy-staging.yml — manual dispatch, staging Environment approval,
OIDC role unero-staging-identity-service-deploy, image deployed BY DIGEST.

## CONFIRM before first dispatch (variables.tf)
1. container_port  — default 8080. Must match the port the Dockerfile serves.
2. health_check_path — default /health/ready. Route must exist and return 200 WITHOUT
   auth and (initially) without DB/Redis being fully seeded, or ECS will kill tasks in a loop.
3. Dockerfile at repo ROOT (the workflow runs `docker build .`).

## Known gaps (deliberate, staging)
- HTTP :80 only — no domain/ACM cert yet. TLS + WAF are production blockers, tracked.
- desired_count=1, no autoscaling — smoke-test posture.
- Secrets containers must be POPULATED (WP-2) before tasks can boot: database, redis,
  jwt-signing-keys, app-key under unero/staging/identity-service/*.
