# Najm Hoda / EarthCoop Dependency Remediation Plan

Branch: `agent/najm-hoda-hardening`

## Verified CI baseline

GitHub Actions run 11 (`31182039456`) completed successfully on the hardening branch.

Verified in CI:

- PHP 8.2
- MySQL 8.0
- Composer install
- Composer validation
- full database migrations
- user-import boundary tests
- all `tests/Feature/NajmHoda` regression tests

This gives us a stable regression baseline before changing dependency versions.

## Current Composer audit evidence

The same CI run reports **56 security advisories affecting 11 packages** and two abandoned packages.

The audit is currently informational so dependency remediation can proceed without hiding test failures. It must not stay informational indefinitely for production release.

## Priority 0 — active high-risk legacy import chain

### `maatwebsite/excel:^1.1` -> `phpoffice/phpexcel`

Status: active application dependency; cannot be removed blindly.

Why it is high priority:

- abandoned package chain;
- multiple high-severity advisories;
- XLSX parsing advisories include XXE / XML scanner bypass classes;
- the package processes administrator-uploaded spreadsheet files.

Remediation:

1. keep the existing `UserImportSpreadsheetReader` boundary and regression tests;
2. migrate spreadsheet parsing to maintained PhpSpreadsheet/current Laravel Excel;
3. regenerate `composer.lock` with Composer, never by hand;
4. rerun import tests and full Najm Hoda CI;
5. remove obsolete PHPExcel advisory ignores only after the package disappears from the lock file.

This migration should be isolated from framework upgrades.

## Priority 1 — HTTP client and protocol stack

### `guzzlehttp/guzzle`

`composer.json` allows `^7.2`, while current audit output reports vulnerabilities fixed in newer 7.x releases, including a high-severity noncanonical-host bypass and multiple cookie/proxy/header issues.

### `guzzlehttp/psr7`

Audit output includes URI host-validation and CRLF-related advisories.

Remediation approach:

- prefer a compatible patch/minor update inside the existing Guzzle 7 major line;
- update Guzzle + PSR-7 together through Composer dependency resolution;
- run the complete CI suite;
- specifically smoke-test Najm Hoda provider HTTP calls and external integration calls.

This is likely the best first dependency update because it may remove several advisories without a framework-major migration.

## Priority 1 — Markdown parser

### `league/commonmark`

Audit reports multiple newly published 2026 advisories, including several high-severity denial-of-service cases and unsafe-link/raw-HTML bypasses in affected versions below 2.9.0.

This package is transitive in the current application dependency graph.

Remediation approach:

- identify the parent package constraining CommonMark;
- resolve to a maintained version >= the patched line where compatible;
- review any user-controlled Markdown/rendering surfaces before production.

Because these advisories were published on 2026-08-06, they should be treated as current high-priority findings rather than legacy noise.

## Priority 1 — Laravel framework security line

### `laravel/framework:^9.19`

Laravel 9 is an old framework major and current audit output includes security advisories affecting the installed line, including file-validation and email/CRLF classes.

Remediation must be split into two questions:

1. Is there a patched release available within the currently permitted Laravel 9 dependency graph for each advisory?
2. Which findings require moving to a newer supported Laravel major?

Do not combine a Laravel-major migration with the Excel parser migration.

## Priority 2 — Symfony transitive components

Current audit output includes findings in:

- `symfony/http-foundation`
- `symfony/mailer`
- `symfony/mime`
- `symfony/polyfill-intl-idn`
- `symfony/routing`

Most are transitive through Laravel and related packages. Resolve them through Composer as part of the framework/dependency refresh, not by pinning individual Symfony components against Laravel's supported dependency constraints.

## Priority 2 — phpseclib

Audit output includes several 2026 advisories, including high-severity ASN.1/OID and AES-CBC issues plus SSRF-related certificate-validation behavior.

Remediation:

- determine which direct/transitive package introduces phpseclib;
- upgrade the parent dependency or phpseclib within compatible constraints;
- identify whether EarthCoop actively exercises SSH/X.509/crypto paths before release.

## Abandoned packages

CI reports:

- `phpoffice/phpexcel` — replacement: `phpoffice/phpspreadsheet`
- `doctrine/cache` — no direct replacement indicated by Composer

`phpoffice/phpexcel` is an active security migration target. `doctrine/cache` should be traced to its parent dependency before removal decisions.

## Release-gate progression

### Phase A — current state

- regression tests: blocking
- migrations: blocking
- Composer audit: reporting only

### Phase B — after first dependency remediation pass

Make high-severity **new/unaccepted** advisories release-blocking while maintaining an explicit, reviewed baseline for unresolved legacy findings.

### Phase C — production autonomy candidate

Production release should require:

- green Najm Hoda CI;
- no unreviewed high-severity Composer advisories;
- no abandoned active parser handling untrusted uploads;
- `php artisan najm-hoda:production-readiness --strict` => GO;
- server evidence for scheduler, queue, cache/Redis, migrations and runtime event storage.

## Recommended execution order

1. Guzzle/PSR-7 compatible update.
2. Re-run audit + all CI tests.
3. CommonMark parent/dependency update if resolvable independently.
4. PHPExcel -> maintained spreadsheet parser migration.
5. Laravel/framework + Symfony security refresh as a dedicated upgrade stream.
6. phpseclib parent-chain remediation.
7. Turn Composer security policy from report-only into an enforced release gate.

## Safety rule

Never make Composer dependency changes by manually editing `composer.lock`. Every dependency update must be generated by Composer, committed separately, and followed by the full hardening CI suite.
