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

The remediation stream has reduced the production Composer audit from **56 advisories affecting 11 packages** to **0 security vulnerability advisories** after resolving the Laravel 12 dependency baseline.

The Laravel 12 resolver selected `laravel/framework 12.65.0`, `nunomaduro/collision 8.9.5`, and `phpunit/phpunit 11.5.56`, completed a clean install, and reported no production security advisories.

## Active remediation status

Completed or verified on the hardening branch:

- Guzzle / PSR-7 compatible security update;
- CommonMark update to the patched line;
- PHPExcel removal and PhpSpreadsheet migration with import regression coverage;
- Symfony and phpseclib compatible security updates;
- Laravel 9 -> 10 upgrade with full CI green;
- Laravel 10 -> 11 upgrade with full CI green;
- Laravel 11 -> 12 dependency resolution to `laravel/framework 12.65.0`, with Collision 8.9.5 and PHPUnit 11.5.56 aligned and production Composer audit clean.

No dependency lock file is hand-edited.

## Deferred compatibility cleanup — Najm Bahar PSR-4

During clean framework installs, Composer reported PSR-4 autoload warnings for three Najm Bahar API controllers because the filesystem path uses `app/Http/Controllers/API/...` while the declared namespace uses `App\\Http\\Controllers\\Api`.

Affected controllers:

- `NajmBaharController`
- `NajmBaharSubAccountController`
- `NajmBaharTransactionController`

Status: **deferred, non-blocking for framework dependency resolution, but must be corrected before production readiness is considered complete.**

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

Laravel 9 -> 10 and Laravel 10 -> 11 are full-CI verified. Laravel 12 dependencies are now resolved and clean-installed at `laravel/framework 12.65.0` with a zero-advisory production Composer audit.

Current gate:

1. run the complete hardening CI suite on the committed Laravel 12 lockfile;
2. fix any application-level compatibility regressions surfaced by migrations/import/tests;
3. after Laravel 12 is green, enforce the Composer security release gate;
4. complete deferred Najm Bahar PSR-4 cleanup before final production readiness.

## Priority 2 — Symfony transitive components

Status: **substantially remediated** through compatible updates and the Laravel framework upgrade stream. Symfony 7 components are present in the Laravel 12 baseline.

## Priority 2 — phpseclib

Status: **remediated within the compatible 3.x line** (`3.0.56` in the Laravel 12 resolved baseline).

## Abandoned packages

- `phpoffice/phpexcel` — **removed**; replaced by `phpoffice/phpspreadsheet`.
- `doctrine/cache` — still reported abandoned and must be traced to its parent dependency before a removal decision.

## Release-gate progression

### Phase A — current state

- regression tests: blocking
- migrations: blocking
- Composer audit: reporting only

### Phase B — after Laravel 12 full-CI verification

Make Composer production security advisories release-blocking. The current Laravel 12 resolved baseline reports zero advisories, so the desired steady state is a clean audit rather than an accepted-vulnerability baseline.

### Phase C — production autonomy candidate

Production release should require:

- green Najm Hoda CI;
- zero unreviewed Composer security advisories;
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
6. Laravel 10 -> 11. **Done + full CI green.**
7. Laravel 11 -> 12. **Dependency baseline committed at 12.65.0; full CI verification in progress.**
8. Najm Bahar PSR-4 casing cleanup before final production readiness.
9. Turn Composer security policy from report-only into an enforced release gate.

## Safety rule

Never make Composer dependency changes by manually editing `composer.lock`. Every dependency update must be generated by Composer, committed separately, and followed by the full hardening CI suite.
