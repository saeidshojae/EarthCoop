#!/usr/bin/env bash
set -euo pipefail
umask 077
cd "$(dirname "$0")/../../apps/mobile/android"
logs="$(mktemp -d)"
trap 'rm -rf "$logs"' EXIT
expect_blocked() {
  label="$1"
  shift
  if env -u EARTHCOOP_RELEASE_CHANNEL \
    -u EARTHCOOP_UAT_KEYSTORE_PATH -u EARTHCOOP_UAT_KEY_ALIAS \
    -u EARTHCOOP_UAT_STORE_PASSWORD -u EARTHCOOP_UAT_KEY_PASSWORD \
    -u EARTHCOOP_PRODUCTION_KEYSTORE_PATH -u EARTHCOOP_PRODUCTION_KEY_ALIAS \
    -u EARTHCOOP_PRODUCTION_STORE_PASSWORD -u EARTHCOOP_PRODUCTION_KEY_PASSWORD \
    "$@" ./gradlew :app:assembleRelease --dry-run > "$logs/$label.log" 2>&1; then
    echo "FAIL: release task was accepted without valid signing policy ($label)."
    exit 1
  fi
  if ! grep -Fq 'EARTHCOOP_RELEASE_SIGNING_REQUIRED' "$logs/$label.log"; then
    echo "FAIL: release failed before the signing policy was checked ($label)."
    tail -40 "$logs/$label.log"
    exit 1
  fi
  echo "PASS: blocked release signing case $label."
}
expect_blocked missing-channel
expect_blocked unknown-channel EARTHCOOP_RELEASE_CHANNEL=unknown
expect_blocked missing-uat EARTHCOOP_RELEASE_CHANNEL=uat
expect_blocked missing-production EARTHCOOP_RELEASE_CHANNEL=production
expect_blocked partial-production EARTHCOOP_RELEASE_CHANNEL=production EARTHCOOP_PRODUCTION_KEY_ALIAS=placeholder
