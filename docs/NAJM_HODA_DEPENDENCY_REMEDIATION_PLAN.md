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

The remediation stream has reduced the production Composer audit from **56 advisories affecting 11 packages** to **3 advisories affecting 1 package** after the Laravel 11 dependency baseline was resolved.

The audit remains informational until the supported Laravel target is finalized and the remaining advisories are reviewed.

## Active remediation status

Completed or verified on the hardening branch:

- Guzzle / PSR-7 compatible security update;
- CommonMark update to the patched line;
- PHPExcel removal and PhpSpreadsheet migration with import regression coverage;
- Symfony and phpseclib compatible security updates;
- Laravel 9 -> 10 upgrade with full CI green;
- Laravel 10 -> 11 dependency resolution to `laravel/framework 11.55.0`, with Sanctum 4, Collision 8 and PHPUnit 10 aligned.

No dependency lock file is hand-edited.

## Deferred compatibility cleanup — Najm Bahar PSR-4

During the clean Laravel 11 install, Composer reported PSR-4 autoload warnings for three Najm Bahar API controllers because the filesystem path uses `app/Http/Controllers/API/...` while the declared namespace uses `App\\Http\\Controllers\\Api`.

Affected controllers:

- `NajmBaharController`
- `NajmBaharSubAccountController`
- `NajmBaharTransactionController`

Status: **deferred, non-blocking for the Laravel 11 dependency resolution, but must be corrected before production readiness is considered complete.**

Planned remediation:

1. choose one canonical casing (`Api` or `API`) consistent with project conventions and PSR-4;
2. align directory names, namespaces and all imports/routes atomically;
3. run Composer autoload validation;
4. run Najm Bahar route/controller smoke tests;
5. rerun the full hardening CI suite.

Do not mix this cleanup into an unrelated feature change.

## Priority 0 — active high-risk legacy import chain

### `maatwebsite/excel:^1.1` -> `phpoffice/phpexcel`

Status: **remediated.** PHPExcel and the legacy Laravel Excel package have been removed from the active dependency graph and spreadsheet parsing now runs through PhpSpreadsheet behind a compatibility bridge. User-import regression tests and full CI have passed on the migrated path.

## Priority 1 — HTTP client and protocol stack

### `guzzlehttp/guzzle` / `guzzlehttp/psr7`

Status: **remediated and full-CI verified.**

## Priority 1 — Markdown parser

### `league/commonmark`

Status: **remediated and full-CI verified** on the patched 2.9 line.

## Priority 1 — Laravel framework security line

The dedicated framework upgrade stream has progressed from Laravel 9 to Laravel 10 with full CI green, and Laravel 11 dependencies have now resolved cleanly to `laravel/framework 11.55.0`.

Next gates:

1. run the complete hardening CI suite on the committed Laravel 11 lockfile;
2. fix any application-level compatibility regressions surfaced by migrations/import/tests;
3. only after Laravel 11 is green, evaluate and perform the supported Laravel 12 upgrade;
4. re-run Composer audit and make remaining unaccepted high-severity findings release-blocking.

## Priority 2 — Symfony transitive components

Status: **substantially remediated** through compatible updates and the Laravel framework upgrade stream. Symfony 7 components are present in the Laravel 11 baseline.

## Priority 2 — phpseclib

Status: **remediated within the compatible 3.x line** (`3.0.56` in the Laravel 11 resolved baseline).

## Abandoned packages

- `phpoffice/phpexcel` — **removed**; replaced by `phpoffice/phpspreadsheet`.
- `doctrine/cache` — still reported abandoned and must be traced to its parent dependency before a removal decision.

## Release-gate progression

### Phase A — current state

- regression tests: blocking
- migrations: blocking
- Composer audit: reporting only

### Phase B — after framework upgrade verification

Make high-severity **new/unaccepted** advisories release-blocking while maintaining an explicit, reviewed baseline for any unresolved legacy findings.

### Phase C — production autonomy candidate

Production release should require:

- green Najm Hoda CI;
- no unreviewed high-severity Composer advisories;
- no abandoned active parser handling untrusted uploads;
- Najm Bahar PSR-4 autoload warnings resolved;
- `php artisan najm-hoda:production-readiness --strict` => GO;
- server evidence for scheduler, queue, cache/Redis, migrations and runtime event storage.

## Recommended execution order

1. Guzzle/PSR-7 compatible update. **Done.**
2. CommonMark update. **Done.**
3. PHPExcel -> PhpSpreadsheet migration. **Done.**
4. Symfony/phpseclib compatible remediation. **Done.**
5. Laravel 9 -> 10. **Done + full CI green.**
6. Laravel 10 -> 11. **Dependency baseline committed; full CI verification next.**
7. Laravel 11 -> 12 after Laravel 11 is green.
8. Najm Bahar PSR-4 casing cleanup before final production readiness.
9. Turn Composer security policy from report-only into an enforced release gate.

## Safety rule

Never make Composer dependency changes by manually editing `composer.lock`. Every dependency update must be generated by Composer, committed separately, and followed by the full hardening CI suite.
