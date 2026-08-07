# Najm Hoda / EarthCoop Dependency Security Audit

Status: Audit-only. No dependency versions or Composer ignore entries changed in this step.
Branch: `agent/najm-hoda-hardening`

## Executive summary

The current `composer.json` contains 21 ignored Composer security advisory IDs.

Current mapping:

- 20 advisories are associated with the legacy `phpoffice/phpexcel` 1.8.x line.
- 1 advisory (`PKSA-8qx3-n5y5-vvnd`) affects `laravel/framework` versions including the Laravel 9 line used by EarthCoop.

The PHPExcel exposure is introduced by the direct dependency `maatwebsite/excel:^1.1`. Laravel Excel 1.1 is a legacy package generation and depends on `phpoffice/phpexcel ~1.8.0`.

A deeper repository scan corrected the initial assumption that Laravel Excel might be unused. It is actively used by the admin user-import flow in `App\Http\Controllers\Admin\UserController` via the legacy `Excel::load(...)` API.

Therefore, `maatwebsite/excel:^1.1` must **not** be removed directly. The safe remediation path is a tested migration of the user import capability to a maintained implementation based on PhpSpreadsheet / a current Laravel Excel generation.

## Current Composer configuration

The project directly requires:

- `laravel/framework:^9.19`
- `maatwebsite/excel:^1.1`
- `phpoffice/phpword:1.0`
- other application dependencies

Composer audit currently has `block-insecure` disabled and an explicit ignore list with 21 advisory IDs.

## Advisory grouping

### Group A — Laravel framework

- `PKSA-8qx3-n5y5-vvnd`

Risk disposition: **requires framework-level review**.

The advisory affects Laravel framework release lines that include Laravel 9. Because EarthCoop is still on Laravel 9, this cannot be resolved safely by deleting an ignore entry alone. The correct remediation path is to determine the exact locked Laravel version, identify a patched compatible release or framework-upgrade path, and run regression tests before changing production dependencies.

### Group B — PHPExcel legacy chain

The following ignored advisories map to `phpoffice/phpexcel` 1.8.x:

- `PKSA-kxx5-ph1r-5bg2`
- `PKSA-bx2k-kfb8-w1zm`
- `PKSA-j2jw-2hjb-39hn`
- `PKSA-2vt6-y6jz-crs9`
- `PKSA-j343-tkpg-k39h`
- `PKSA-1p4c-ysfb-v9ph`
- `PKSA-gn6r-3fbg-rpq7`
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

> Note: the repository currently lists `PKSA-gn6r-3fbg-rpq7` as `PKSA-gn6r-3fbg-rpq7`/equivalent audit identifier; preserve the actual Composer identifier when remediation is applied.

Risk disposition: **high-priority active legacy dependency migration**.

`maatwebsite/excel` 1.1 belongs to the old Laravel Excel generation and pulls PHPExcel 1.8.x. Current Laravel Excel releases use PhpSpreadsheet instead of PHPExcel.

## Repository usage assessment

### Confirmed active use

`App\Http\Controllers\Admin\UserController` imports the facade:

```php
use Maatwebsite\Excel\Facades\Excel;
```

The admin user import action validates `xlsx`, `xls`, and `csv` uploads and then calls:

```php
$data = Excel::load($file)->get();
```

This route is exposed in the admin users route group:

- `GET /admin/.../users/import` -> import form
- `POST /admin/.../users/import` -> import execution

The import functionality is therefore operational application behavior, not an unused Composer artifact.

### Export behavior

The user export path does **not** require Laravel Excel. `exportUsers()` builds a UTF-8 CSV stream directly with PHP `fputcsv()` and returns it as a download.

That means the legacy package appears to have a narrow responsibility: **reading uploaded spreadsheet/CSV files for admin user import**.

This is favorable for migration because the security-sensitive legacy dependency can likely be replaced without redesigning all reporting/export code.

## Migration implications

The current import uses the old `Excel::load()` API. Modern Laravel Excel no longer supports this API. A current implementation must use an explicit import object or collection/array import flow.

The migration must preserve at least:

- accepted formats: `xlsx`, `xls`, `csv`
- existing validation rules
- header/column interpretation
- duplicate-user behavior
- row-level error collection
- success/skip counters
- Persian/UTF-8 handling
- current admin-facing success/error messages
- transaction/partial-import semantics currently implemented by the controller

## Recommended remediation order

1. Add regression coverage around the existing admin user-import behavior before changing the spreadsheet library.
2. Extract spreadsheet parsing from `UserController` behind a small application service so controller business rules are not coupled to a specific library API.
3. Replace the legacy `Excel::load()` parser with a maintained implementation using a current Laravel Excel generation or PhpSpreadsheet directly.
4. Update `composer.json` and regenerate `composer.lock` in the hardening branch only.
5. Run import regression tests for CSV/XLSX and the Najm Hoda CI suite.
6. Run `composer audit` and confirm the PHPExcel advisory group disappears.
7. Remove only the PHPExcel advisory ignore IDs that are no longer applicable.
8. Handle the Laravel framework advisory independently in a separate commit/sprint.

## Why direct removal is rejected

Directly removing `maatwebsite/excel` would break `UserController::import()` because the controller has a hard dependency on `Maatwebsite\Excel\Facades\Excel` and the legacy `Excel::load()` method.

This was discovered during the second-pass usage scan. The original audit statement suggesting likely unused status is superseded by this finding.

## Tooling limitation observed during this audit

The connected GitHub API can safely read and write the hardening branch, but the local execution environment used for this review currently cannot access GitHub/Packagist over the network and does not have GitHub CLI available. Therefore `composer.lock` must not be hand-edited. Dependency version changes should only be committed when Composer can regenerate the lock file deterministically (for example through an appropriate CI/manual workflow or another environment with Composer network access).

## Safety constraints for this sprint

- Do not modify `main`.
- Do not change the current production `vendor` deployment decision as part of this audit.
- Do not delete Composer advisory ignores merely to make audit output green.
- Do not upgrade Laravel and Excel dependencies in the same commit.
- Do not hand-edit `composer.lock` to simulate a Composer resolution.
- Each dependency change must have its own testable commit and rollback point.

## Next implementation task

Create regression coverage and a parser boundary for the admin user import flow while keeping the current dependency and behavior intact. Once that seam is in place, migrate the parser implementation and Composer dependency in a separate commit.
