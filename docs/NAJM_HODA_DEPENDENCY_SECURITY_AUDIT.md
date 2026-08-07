# Najm Hoda / EarthCoop Dependency Security Audit

Status: Audit-only. No dependency versions or Composer ignore entries changed in this step.
Branch: `agent/najm-hoda-hardening`

## Executive summary

The current `composer.json` contains 21 ignored Composer security advisory IDs.

Initial mapping shows:

- 20 advisories are associated with the legacy `phpoffice/phpexcel` 1.8.x line.
- 1 advisory (`PKSA-8qx3-n5y5-vvnd`) affects `laravel/framework` versions including the Laravel 9 line used by EarthCoop.

The PHPExcel exposure appears to be introduced by the direct dependency `maatwebsite/excel:^1.1`. Laravel Excel 1.1 is a legacy package generation and depends on `phpoffice/phpexcel ~1.8.0`.

Repository code search did not find direct application usage of `Maatwebsite\\Excel` or `Excel::`. This makes `maatwebsite/excel:^1.1` a strong candidate for an unused legacy dependency, but this must be verified against dynamic/service-provider usage and production behavior before removal.

## Current Composer configuration

The project directly requires:

- `laravel/framework:^9.19`
- `maatwebsite/excel:^1.1`
- other application dependencies

Composer audit currently has `block-insecure` disabled and an explicit ignore list with 21 advisory IDs.

## Advisory grouping

### Group A — Laravel framework

- `PKSA-8qx3-n5y5-vvnd`

Risk disposition: **requires framework-level review**.

The advisory affects Laravel framework release lines that include Laravel 9. Because EarthCoop is still on Laravel 9, this cannot be resolved safely by deleting an ignore entry alone. The correct remediation path is to determine the exact locked Laravel version, identify whether a patched release exists within a compatible upgrade path, and test the application before changing production dependencies.

### Group B — PHPExcel legacy chain

The following ignored advisories map to `phpoffice/phpexcel` 1.8.x:

- `PKSA-kxx5-ph1r-5bg2`
- `PKSA-bx2k-kfb8-w1zm`
- `PKSA-j2jw-2hjb-39hn`
- `PKSA-2vt6-y6jz-crs9`
- `PKSA-j343-tkpg-k39h`
- `PKSA-1p4c-ysfb-v9ph`
- `PKSA-gn6r-3fbg-vpq7`
- `PKSA-b7bk-6mnf-vvf4`
- `PKSA-xzzd-fyzv-1nm2`
- `PKSA-yb2q-9cbc-scfr`
- `PKSA-n1xd-9q81-6m2k`
- `PKSA-5bk7-32wt-w1f6`
- `PKSA-wd5y-fztj-66t8`
- `PKSA-996h-kvqc-cdky`
- `PKSA-p4bt-rmgm-ynz5`
- `PKSA-79jx-g5m1-5ybs`
- `PKSA-hn36-2kk8-hb3y`
- `PKSA-s9h9-dzpw-9hsj`
- `PKSA-xj88-cdcs-bgkr`
- `PKSA-81dj-mb26-861s`

Risk disposition: **high-priority legacy dependency cleanup candidate**.

`maatwebsite/excel` 1.1 belongs to the old Laravel Excel generation and pulls PHPExcel 1.8.x. Modern Laravel Excel uses PhpSpreadsheet instead.

## Repository usage assessment

Code search performed for:

- `Maatwebsite\\Excel`
- `Excel::`
- PHPExcel-specific application usage

No direct application references were found in the repository search results.

Interpretation:

- This does **not** prove the package is unused.
- It does make removal/migration substantially more promising.
- Before changing Composer dependencies, verify service provider configuration, aliases, queued jobs, console commands, views, exports/imports, and any runtime/dynamic class resolution.

## Recommended remediation order

1. Confirm whether Laravel Excel is actually used by EarthCoop.
2. If unused, remove `maatwebsite/excel:^1.1` in the hardening branch and regenerate the lock file, then run the full relevant test suite.
3. If used, inventory all import/export entry points and migrate them to a maintained Laravel Excel 3.x-compatible implementation rather than retaining PHPExcel.
4. Re-run `composer audit` after the PHPExcel chain is removed/migrated.
5. Handle the Laravel framework advisory independently, using an explicit framework upgrade plan and regression tests.
6. Only remove advisory ignore IDs after the underlying vulnerable dependency/version is actually gone or patched.

## Safety constraints for this sprint

- Do not modify `main`.
- Do not change the current production `vendor` deployment decision as part of this audit.
- Do not delete Composer advisory ignores merely to make audit output green.
- Do not upgrade Laravel and Excel dependencies in the same commit.
- Each dependency change must have its own testable commit and rollback point.

## Next audit task

Verify whether `maatwebsite/excel` is truly unused by checking providers/aliases and export/import-related application code. If confirmed unused, prepare a dedicated dependency-removal commit on the hardening branch, without touching `main`.
