#!/usr/bin/env bash
# Deploy identity-service to ECS by immutable image digest (ADR-023).
# Usage: ./scripts/deploy.sh <env> <image-digest>
# Requires (from unero-platform-terraform outputs, injected as CI env):
#   ECS_CLUSTER, ECS_SERVICE   — cluster + service names for <env>
set -euo pipefail

ENV="${1:?usage: deploy.sh <env> <image-digest>}"
DIGEST="${2:?usage: deploy.sh <env> <image-digest>}"

: "${ECS_CLUSTER:?ECS_CLUSTER not set — wire unero-platform-terraform outputs for ${ENV}}"
: "${ECS_SERVICE:?ECS_SERVICE not set — wire unero-platform-terraform outputs for ${ENV}}"

echo "Deploying identity-service to ${ENV}: ${DIGEST}"
aws ecs update-service \
  --cluster "${ECS_CLUSTER}" \
  --service "${ECS_SERVICE}" \
  --force-new-deployment \
  --query 'service.deployments[0].id' --output text
aws ecs wait services-stable --cluster "${ECS_CLUSTER}" --services "${ECS_SERVICE}"
echo "Deploy to ${ENV} stable."
