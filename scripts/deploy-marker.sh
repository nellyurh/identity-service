#!/usr/bin/env bash
# Record a deploy marker for observability correlation (best-effort, non-blocking).
# Usage: ./scripts/deploy-marker.sh <service-name> <git-sha>
set -euo pipefail

SERVICE="${1:?usage: deploy-marker.sh <service-name> <git-sha>}"
SHA="${2:?usage: deploy-marker.sh <service-name> <git-sha>}"

if [ -z "${MARKER_WEBHOOK:-}" ]; then
  echo "deploy-marker: MARKER_WEBHOOK unset; skipping (best-effort)."
  exit 0
fi

curl -fsS -X POST "${MARKER_WEBHOOK}" \
  -H 'Content-Type: application/json' \
  -d "{\"service\":\"${SERVICE}\",\"sha\":\"${SHA}\",\"at\":\"$(date -u +%FT%TZ)\"}" >/dev/null
echo "deploy-marker: recorded ${SERVICE}@${SHA}."
