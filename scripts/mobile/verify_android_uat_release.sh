#!/usr/bin/env bash
set -euo pipefail
umask 077
apk="$1"
signer="$(printf '%s\n' "$ANDROID_HOME"/build-tools/*/apksigner | sort -V | tail -1)"
test -x "$signer"
build_tools="$(dirname "$signer")"
"$build_tools/apksigner" verify --print-certs "$apk" > "$RUNNER_TEMP/earthcoop-apk-certificate.txt"
actual="$(awk -F ': ' '/Signer #1 certificate SHA-256 digest:/ {print $2; exit}' "$RUNNER_TEMP/earthcoop-apk-certificate.txt")"
expected="$(keytool -exportcert -keystore "$EARTHCOOP_UAT_KEYSTORE_PATH" -alias "$EARTHCOOP_UAT_KEY_ALIAS" -storepass:env EARTHCOOP_UAT_STORE_PASSWORD | sha256sum | awk '{print $1}')"
test -n "$actual"
test "$actual" = "$expected" || { echo '::error::Release UAT certificate does not match the stable UAT keystore.'; exit 1; }
"$build_tools/aapt" dump badging "$apk" > "$RUNNER_TEMP/earthcoop-apk-badging.txt"
if grep -Fq 'application-debuggable' "$RUNNER_TEMP/earthcoop-apk-badging.txt"; then
  echo '::error::Release UAT APK must not be debuggable.'
  exit 1
fi
grep -Fq "package: name='coop.earthcoop.earthcoop_mobile'" "$RUNNER_TEMP/earthcoop-apk-badging.txt"
grep -Fq "uses-permission: name='android.permission.INTERNET'" "$RUNNER_TEMP/earthcoop-apk-badging.txt"
repo_dir="$(cd "$(dirname "$0")/../.." && pwd)"
version="$(awk '/^version:/ {print $2; exit}' "$repo_dir/apps/mobile/pubspec.yaml")"
grep -Fq "versionCode='${version##*+}'" "$RUNNER_TEMP/earthcoop-apk-badging.txt"
grep -Fq "versionName='${version%+*}'" "$RUNNER_TEMP/earthcoop-apk-badging.txt"
echo 'PASS: stable UAT certificate, non-debuggable Android release, package identity and Internet permission verified.'
