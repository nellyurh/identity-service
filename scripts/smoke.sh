#!/usr/bin/env bash
# Blocking smoke test after a deploy: readiness must be green.
# Usage: ./scripts/smoke.sh <env>
# Requires: SMOKE_URL (base URL of the deployed service for <env>).
set -euo pipefail

ENV="${1:?usage: smoke.sh <env>}"
: "${SMOKE_URL:?SMOKE_URL not set — base URL of identity-service in ${ENV}}"

echo "Smoke: ${SMOKE_URL}/readyz"
code="$(curl -fsS -o /dev/null -w '%{http_code}' "${SMOKE_URL}/readyz")"
if [ "${code}" != "200" ]; then
  echo "Smoke failed: /readyz returned ${code}" >&2
  exit 1
fi
echo "Smoke passed for ${ENV}."
