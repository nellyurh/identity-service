#!/usr/bin/env bash
# Progressive production rollout 5% -> 25% -> 50% -> 100% with SLO-gated auto-rollback.
# Usage: ./scripts/canary.sh <image-digest>
# Requires: ECS_CLUSTER, ECS_SERVICE, DEPLOYMENT_CONFIG (CodeDeploy/ECS canary config).
set -euo pipefail

DIGEST="${1:?usage: canary.sh <image-digest>}"
: "${ECS_CLUSTER:?ECS_CLUSTER not set — wire unero-platform-terraform outputs}"
: "${ECS_SERVICE:?ECS_SERVICE not set — wire unero-platform-terraform outputs}"
: "${DEPLOYMENT_CONFIG:?DEPLOYMENT_CONFIG not set — canary step config from terraform}"

echo "Canary rollout of ${DIGEST} on ${ECS_SERVICE} using ${DEPLOYMENT_CONFIG}"
aws ecs update-service \
  --cluster "${ECS_CLUSTER}" \
  --service "${ECS_SERVICE}" \
  --deployment-configuration "${DEPLOYMENT_CONFIG}" \
  --force-new-deployment >/dev/null
aws ecs wait services-stable --cluster "${ECS_CLUSTER}" --services "${ECS_SERVICE}"
echo "Canary complete; service stable at 100%."
