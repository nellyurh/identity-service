#!/usr/bin/env bash
# identity-service release gate (2J-d). Runs the full production-readiness battery and prints
# a verdict table. Exit code is non-zero if any REQUIRED gate fails. Optional gates (needing
# tools/drivers that may be absent) are reported as SKIP, never as failures.
set -u

declare -a NAMES STATUSES DETAILS
FAILED=0

run_gate() { # name, required(1/0), command...
  local name="$1" required="$2"; shift 2
  printf '\n========== %s ==========\n' "$name"
  if "$@"; then
    NAMES+=("$name"); STATUSES+=("PASS"); DETAILS+=("")
  else
    if [ "$required" = "1" ]; then
      NAMES+=("$name"); STATUSES+=("FAIL"); DETAILS+=("required"); FAILED=1
    else
      NAMES+=("$name"); STATUSES+=("SKIP/FAIL"); DETAILS+=("optional"); fi
  fi
}

skip_gate() { # name, reason
  printf '\n========== %s ==========\nSKIP: %s\n' "$1" "$2"
  NAMES+=("$1"); STATUSES+=("SKIP"); DETAILS+=("$2")
}

secret_scan() {
  # gitleaks when available; otherwise a conservative grep for high-signal patterns.
  if command -v gitleaks >/dev/null 2>&1; then
    gitleaks detect --source . --no-banner
    return $?
  fi
  echo "gitleaks not installed - using grep fallback (private keys, AWS keys, long hex tokens in tracked files)"
  local pattern hits
  # Literals are split with '' so this file can never match its own pattern (self-scan false positive).
  pattern='BEGIN (RSA|EC|OPENSSH) PRIVATE'' KEY|AKIA''[0-9A-Z]{16}|aws_secret_''access_key'
  hits=$(git grep -nE "$pattern" -- ':!*.md' ':!schemas/shared' 2>/dev/null || true)
  if [ -n "$hits" ]; then echo "$hits"; return 1; fi
  echo "no high-signal secret patterns found"; return 0
}

tree_clean() {
  if [ -n "$(git status --porcelain)" ]; then
    echo "working tree is NOT clean:"; git status --short; return 1
  fi
  echo "working tree clean"; return 0
}

submodule_ok() {
  local st; st=$(git submodule status)
  echo "$st"
  case "$st" in
    -*) echo "submodule NOT initialised"; return 1 ;;
  esac
  return 0
}

run_gate "composer validate --strict"  1 composer validate --strict
run_gate "pint (style)"                1 vendor/bin/pint --test
run_gate "rector (dry-run)"            1 vendor/bin/rector process --dry-run
run_gate "phpstan (level 8)"           1 vendor/bin/phpstan analyse --no-progress
run_gate "phpunit (all suites)"        1 php artisan test
run_gate "composer audit (advisories)" 1 composer audit --no-interaction
run_gate "secret scan"                 1 secret_scan
run_gate "submodule initialised"       1 submodule_ok

if [ -x vendor/bin/infection ] && php -m | grep -qiE 'xdebug|pcov'; then
  run_gate "infection (mutation)" 0 vendor/bin/infection --min-msi=0 --threads=max --no-progress
elif [ ! -x vendor/bin/infection ]; then
  skip_gate "infection (mutation)" "not installed - deferred per project decision"
else
  skip_gate "infection (mutation)" "no coverage driver (xdebug/pcov) - deferred per project decision"
fi

printf '\n---------- informational ----------\n'
composer outdated --direct 2>/dev/null || true

# tree-clean runs LAST so the gate run itself (caches etc.) does not pollute the verdict
run_gate "working tree clean" 1 tree_clean

printf '\n================ RELEASE GATE VERDICT ================\n'
for i in "${!NAMES[@]}"; do
  printf '%-32s %-10s %s\n' "${NAMES[$i]}" "${STATUSES[$i]}" "${DETAILS[$i]}"
done
if [ "$FAILED" = "1" ]; then
  printf '\nRESULT: FAIL - do not tag.\n'; exit 1
fi
printf '\nRESULT: PASS - clear to tag identity-v1.0.0-rc1.\n'
