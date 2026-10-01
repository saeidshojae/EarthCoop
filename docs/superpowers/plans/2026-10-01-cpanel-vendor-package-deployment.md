# cPanel Vendor Package Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace file-by-file Composer `vendor/` FTP deployment with a verified single-package delivery and controlled server-side activation flow that works on the current cPanel/FTPS host without SSH.

**Architecture:** CI continues to build Production dependencies from the committed `composer.lock`, but packages the generated `vendor/` tree into one ZIP plus a manifest with SHA-256 metadata. The normal FTP sync excludes both `vendor/**` and `storage/**`; a second dedicated FTP sync uploads only the package and manifest into `storage/deployment/vendor-packages/`. A purpose-built Laravel installer validates the package, extracts to staging, atomically swaps the active `vendor/`, performs a bounded bootstrap check, restores the previous vendor on failure, and persists a sanitized installed-state marker only after success. Deployment Console exposes only fixed allowlisted status/install operations.

**Tech Stack:** Laravel 12 / PHP 8.2, `ZipArchive`, Laravel Artisan/Filesystem, PHPUnit 11, GitHub Actions, SamKirkland/FTP-Deploy-Action v4.4.0, cPanel FTPS.

**Spec:** `docs/superpowers/specs/2026-10-01-cpanel-vendor-package-deployment-design.md`

## Global Constraints

- No SSH assumption.
- No arbitrary shell, PHP, Composer command, archive path, SQL, or user-controlled Artisan command.
- `.env` and Production `storage` runtime data remain protected from the normal FTP sync.
- Direct file-by-file `vendor/**` FTP upload is forbidden.
- Package integrity uses SHA-256 and the package must match the deployed `composer.lock` SHA-256.
- Package activation must stage first, swap by same-filesystem rename, bootstrap-check, and roll back on failure.
- Installed dependency state must be recorded only after successful activation/bootstrap verification; pending manifest data is never treated as proof of installation.
- The installer must fail closed if ZIP support, checksum validation, safe extraction, staging, rename, or bootstrap verification cannot be completed safely.
- Production database migrations remain a separate explicit operation; the installer never performs destructive database work.
- Development uses targeted tests first. Integration Full Validation runs once at the final release gate.
- The first recovery activation must replace the entire active `vendor/` tree because FTP Deploy #84 may have partially transferred dependencies.

## Review Focus

1. **Partially transferred current Production vendor:** recovery must not assume the live tree is complete; successful package activation must replace it wholesale.
2. **Malicious or malformed ZIP entries:** absolute paths, `..` traversal, backslash traversal, and symlink-like unsafe entries must be rejected before extraction.
3. **Interrupted second FTP sync:** a missing package, missing manifest, checksum mismatch, or stale pair must leave the current live vendor untouched.
4. **Cross-filesystem or failed rename:** installer must fail closed and restore the previous vendor rather than recursively copying over the live tree.
5. **Bootstrap failure after swap:** the previous vendor must be restored, installed-state must remain unchanged, and the command must return failure with an audit-safe message.

---

## File Structure

**Create**
- `app/Services/Deployment/VendorPackageInstaller.php` — manifest validation, checksum/lock verification, safe ZIP extraction, staged activation, bootstrap verification, rollback, cleanup, pending/installed package status.
- `app/Console/Commands/InstallVendorPackage.php` — fixed command that activates only the canonical pending manifest with exact confirmation.
- `app/Console/Commands/VendorPackageStatus.php` — read-only command for pending/installed package metadata.
- `tests/Unit/Deployment/VendorPackageInstallerTest.php` — package validation, extraction, activation, installed-state and rollback contracts.
- `tests/Architecture/DeploymentVendorPackageBoundaryTest.php` — deployment workflow and forbidden-surface source contracts.
- `docs/operations/CPANEL_VENDOR_PACKAGE_DEPLOYMENT_RUNBOOK.md` — recovery and normal production procedure.

**Modify**
- `config/deployment-console.php` — canonical deployment package/staging/installed-state paths and backup retention settings.
- `app/Console/Kernel.php` — register the two deployment package commands.
- `app/Services/Deployment/DeploymentConsoleService.php` — fixed `vendor_package_status` and `vendor_package_install` operations only.
- `app/Http/Controllers/Admin/DeploymentConsoleController.php` — expose pending/installed package metadata to the existing view without accepting paths.
- `resources/views/admin/deployment-console/index.blade.php` — show package state/hash and fixed install action.
- `tests/Unit/Deployment/DeploymentConsoleServiceTest.php` — exact operation catalog and confirmation.
- `tests/Feature/Admin/DeploymentConsoleSecurityTest.php` — secret/confirmation/no-free-path behavior.
- `tests/Feature/Admin/DeploymentConsoleExecutionTest.php` — exact Artisan invocation and sanitized audit.
- `tests/Feature/Admin/DeploymentConsoleSourceContractTest.php` — keep forbidden execution surfaces locked.
- `.github/workflows/deploy.yml` — restore `vendor/**` exclusion, build ZIP+manifest, normal app FTP sync, second package-only FTP sync.
- `.github/workflows/integration-full-validation.yml` — include new deployment package targeted tests in the existing Deployment Console regression gate.
- `docs/operations/COMMUNICATION_CENTER_RUNBOOK.md` — reference dependency-package activation before Communication Center migrations/runtime activation.

---

### Task 1: Lock the installer security contract RED

**Files:**
- Create: `tests/Unit/Deployment/VendorPackageInstallerTest.php`
- Create: `tests/Architecture/DeploymentVendorPackageBoundaryTest.php`

**Interfaces:**
- Produces expected service API for Task 2:
  - `status(): array`
  - `install(): array{success:bool,message:string,lock_hash:?string,source_sha:?string}`
- Canonical paths come from config; tests never pass arbitrary package paths into `install()`.

- [ ] **Step 1: Write failing unit tests for manifest and checksum validation**

Add tests named:
- `test_install_rejects_missing_manifest_without_touching_live_vendor`
- `test_install_rejects_manifest_with_missing_required_fields`
- `test_install_rejects_package_checksum_mismatch`
- `test_install_rejects_composer_lock_hash_mismatch`

Assertions must prove the live vendor fixture and installed-state fixture remain unchanged on every failure.

- [ ] **Step 2: Write failing archive-safety tests**

Create ZIP fixtures containing:
- `../escape.php`
- `/absolute.php`
- `vendor/../../escape.php`
- backslash traversal equivalent

`install()` must fail before extraction and no file may appear outside the staging directory.

- [ ] **Step 3: Write failing activation/rollback/state tests**

Tests:
- `test_successful_install_replaces_entire_vendor_tree_from_staging`
- `test_successful_install_writes_installed_state_from_verified_manifest`
- `test_status_distinguishes_pending_package_from_installed_package`
- `test_failed_post_swap_bootstrap_restores_previous_vendor_and_preserves_installed_state`
- `test_failed_live_vendor_rename_does_not_fall_back_to_recursive_overwrite`
- `test_missing_autoload_or_composer_metadata_rejects_staged_vendor`
- `test_old_backup_cleanup_keeps_only_configured_retention_count`
- `test_zip_extension_unavailable_fails_closed_before_touching_live_vendor`

Use isolated temporary application/vendor paths through config overrides so tests never touch the repository's real vendor tree.

- [ ] **Step 4: Write workflow/source RED test**

`DeploymentVendorPackageBoundaryTest` must assert the future workflow contains:
- normal FTP exclusion `**/vendor/**`;
- normal FTP exclusion `storage/**`;
- package build from production `vendor/` after `composer install --no-dev`;
- SHA-256 of `composer.lock` and package;
- a separate package-only FTP action targeting `storage/deployment/vendor-packages/`;
- a distinct FTP state file for package sync;
- no arbitrary shell/exec/eval surface in deployment package PHP classes.

- [ ] **Step 5: Run focused tests and verify RED**

Run:
```bash
php artisan test tests/Unit/Deployment/VendorPackageInstallerTest.php tests/Architecture/DeploymentVendorPackageBoundaryTest.php
```
Expected: FAIL because installer/workflow contracts are not implemented.

- [ ] **Step 6: Commit RED checkpoint**

```bash
git add tests/Unit/Deployment/VendorPackageInstallerTest.php tests/Architecture/DeploymentVendorPackageBoundaryTest.php
git commit -m "test: lock vendor package deployment boundary"
```

---

### Task 2: Implement safe vendor package validation, staging, activation and rollback GREEN

**Files:**
- Create: `app/Services/Deployment/VendorPackageInstaller.php`
- Modify: `config/deployment-console.php`
- Test: `tests/Unit/Deployment/VendorPackageInstallerTest.php`

**Interfaces:**
- `public function status(): array`
- `public function install(): array`
- Canonical manifest path: `storage/deployment/vendor-packages/manifest.json`.
- Installed-state path: `storage/deployment/vendor-packages/installed.json`.
- Manifest schema version: `1`.
- Package filename must match `vendor-[a-f0-9]{64}.zip` and remain inside `storage/deployment/vendor-packages/`.

- [ ] **Step 1: Add deployment package config**

Add keys under `config/deployment-console.php`:
- `vendor_package_directory` => `storage_path('deployment/vendor-packages')`
- `vendor_manifest` => `storage_path('deployment/vendor-packages/manifest.json')`
- `vendor_installed_state` => `storage_path('deployment/vendor-packages/installed.json')`
- `vendor_staging_directory` => `storage_path('deployment/vendor-staging')`
- `vendor_backup_retention` => `1`

No path is request-controlled.

- [ ] **Step 2: Implement manifest loading and integrity checks**

`status()` and `install()` validate these exact manifest fields:
- `schema_version` integer `1`
- `package_filename`
- `package_sha256`
- `composer_lock_sha256`
- `source_git_sha`
- `created_at`
- `expected_top_level` exact `vendor`
- `php_version`

Reject filenames outside the fixed pattern and compare `hash_file('sha256', base_path('composer.lock'))` to `composer_lock_sha256`.

`status()` must report pending manifest metadata separately from installed-state metadata, plus `zip_supported`, `current_lock_matches_pending`, and `current_lock_matches_installed` booleans.

- [ ] **Step 3: Implement safe ZIP inspection before extraction**

Use `ZipArchive`. If unavailable, return a fail-closed error.

Before `extractTo`, iterate every entry and reject if normalized entry:
- is absolute;
- contains `..` path segments;
- escapes `vendor/`;
- contains Windows drive/UNC semantics;
- is not rooted at `vendor/`.

Do not accept arbitrary symlink entries. If external attributes indicate a symlink, reject the archive.

- [ ] **Step 4: Implement staging validation**

Extract only into `storage/deployment/vendor-staging/<lock-hash>/` and verify:
- `<stage>/vendor/autoload.php` exists;
- `<stage>/vendor/composer/installed.php` or `<stage>/vendor/composer/installed.json` exists;
- staged tree is non-empty.

Any failure cleans the staging directory and leaves live vendor and installed-state untouched.

- [ ] **Step 5: Implement rename activation and rollback**

Activation sequence:
1. rename current `base_path('vendor')` to `base_path('vendor.__previous_<timestamp>')` if it exists;
2. rename staged `vendor` to `base_path('vendor')`;
3. run bounded verification against the new autoloader without shell execution;
4. on verification failure, move failed new vendor aside/remove it and rename previous vendor back;
5. after success, prune old backups to configured retention.

Never recursively merge staged files into live vendor.

- [ ] **Step 6: Implement bounded bootstrap verification**

Verification must at minimum require the new `vendor/autoload.php`, load it, and verify Laravel framework/bootstrap classes expected by the application can be autoloaded. Keep it independent of database access so dependency recovery does not require successful migrations.

- [ ] **Step 7: Persist installed-state only after successful verification**

Write `installed.json` atomically using a temporary file + rename only after the new vendor passes bootstrap verification. Store only sanitized manifest fields plus `installed_at`; never secrets or absolute filesystem paths. A failed installation must not overwrite an existing installed-state marker.

- [ ] **Step 8: Run installer tests GREEN**

Run:
```bash
php artisan test tests/Unit/Deployment/VendorPackageInstallerTest.php
```
Expected: PASS.

- [ ] **Step 9: Commit installer checkpoint**

```bash
git add app/Services/Deployment/VendorPackageInstaller.php config/deployment-console.php tests/Unit/Deployment/VendorPackageInstallerTest.php
git commit -m "feat: add atomic vendor package installer"
```

---

### Task 3: Add fixed Artisan commands and Deployment Console integration

**Files:**
- Create: `app/Console/Commands/InstallVendorPackage.php`
- Create: `app/Console/Commands/VendorPackageStatus.php`
- Modify: `app/Console/Kernel.php`
- Modify: `app/Services/Deployment/DeploymentConsoleService.php`
- Modify: `app/Http/Controllers/Admin/DeploymentConsoleController.php`
- Modify: `resources/views/admin/deployment-console/index.blade.php`
- Modify tests under `tests/Unit/Deployment/` and `tests/Feature/Admin/`.

**Interfaces:**
- Command: `deployment:vendor-package-status`
- Command: `deployment:install-vendor-package --confirm=INSTALL-VENDOR-PACKAGE`
- Console operation key: `vendor_package_status` (read-only)
- Console operation key: `vendor_package_install` (write, confirmation `INSTALL-VENDOR-PACKAGE`)

- [ ] **Step 1: Extend service catalog tests RED**

Update exact operation list to include `vendor_package_status` and `vendor_package_install`; assert only the install operation requires `INSTALL-VENDOR-PACKAGE`.

- [ ] **Step 2: Add security RED tests**

Prove:
- wrong deployment secret never invokes install command;
- wrong confirmation never invokes install command;
- HTTP request has no accepted `manifest`, `package`, `path`, `command`, or shell field that affects execution;
- unknown operation still 404s.

- [ ] **Step 3: Implement commands**

`VendorPackageStatus` calls only `VendorPackageInstaller::status()` and renders sanitized pending/installed metadata.

`InstallVendorPackage`:
- signature contains only `--confirm=`;
- rejects anything other than exact `INSTALL-VENDOR-PACKAGE`;
- calls `VendorPackageInstaller::install()`;
- returns `SUCCESS` or `FAILURE` from result.

- [ ] **Step 4: Register commands and fixed console operations**

Add both command classes to `app/Console/Kernel.php`.

Add only literal command/arguments in `DeploymentConsoleService::OPERATIONS`; no request-derived path or option.

- [ ] **Step 5: Show pending and installed package state in UI**

Controller passes sanitized package status to the view. Display pending and installed source SHA, lock SHA, package SHA, creation/installation time, lock match, ZIP support and installer readiness. Do not display secrets or filesystem absolute paths.

- [ ] **Step 6: Run Deployment Console targeted tests GREEN**

Run:
```bash
php artisan test \
  tests/Unit/Deployment/DeploymentConsoleServiceTest.php \
  tests/Unit/Deployment/VendorPackageInstallerTest.php \
  tests/Feature/Admin/DeploymentConsoleSourceContractTest.php \
  tests/Feature/Admin/DeploymentConsoleSecurityTest.php \
  tests/Feature/Admin/DeploymentConsoleExecutionTest.php
```
Expected: PASS.

- [ ] **Step 7: Commit console checkpoint**

```bash
git add app/Console app/Services/Deployment app/Http/Controllers/Admin/DeploymentConsoleController.php resources/views/admin/deployment-console tests
git commit -m "feat: expose controlled vendor package activation"
```

---

### Task 4: Replace direct vendor FTP with ZIP+manifest and dedicated package sync

**Files:**
- Modify: `.github/workflows/deploy.yml`
- Modify: `tests/Architecture/DeploymentVendorPackageBoundaryTest.php`

**Interfaces:**
- Local package staging directory in CI: `.deployment/vendor-package/`
- Files: `vendor-<lock-sha256>.zip`, `manifest.json`
- Remote package directory: `storage/deployment/vendor-packages/`
- Package FTP state: `.ftp-deploy-vendor-package-state.json`

- [ ] **Step 1: Restore direct vendor exclusion**

Add `**/vendor/**` back to the normal `Sync files to cPanel via FTP` exclude list. Keep `storage/**` excluded.

- [ ] **Step 2: Build package and manifest after production Composer install**

Workflow must:
1. compute `LOCK_SHA=$(sha256sum composer.lock | awk '{print $1}')`;
2. create `.deployment/vendor-package/`;
3. create `vendor-${LOCK_SHA}.zip` containing top-level `vendor/`;
4. compute package SHA-256;
5. write schema-v1 `manifest.json` with exact fields from Task 2 and `${{ github.sha }}`.

Use an explicit ZIP-producing command available on the runner and fail if the archive cannot be created. Do not include `.env`, tests, storage runtime files, or GitHub secrets in the package.

- [ ] **Step 3: Keep normal application FTP sync unchanged except vendor exclusion**

The first FTP action continues to deploy application files and built assets while excluding `vendor/**`, `storage/**`, tests, `.github`, node_modules and environment files.

- [ ] **Step 4: Add a second dedicated FTPS action**

Use the same server/credentials/protocol, with:
- `local-dir: ./.deployment/vendor-package/`
- `server-dir: ./storage/deployment/vendor-packages/`
- `state-name: .ftp-deploy-vendor-package-state.json`
- `dangerous-clean-slate: false`
- minimal logging.

This action uploads only the archive and manifest, not `vendor/` contents and not `installed.json`.

- [ ] **Step 5: Add workflow sanity assertions before FTP**

Verify locally:
- ZIP exists and is non-empty;
- manifest parses as JSON;
- manifest lock hash equals current `composer.lock` hash;
- manifest package hash equals actual ZIP hash;
- ZIP contains `vendor/autoload.php` and Composer metadata.

- [ ] **Step 6: Run architecture test GREEN**

Run:
```bash
php artisan test tests/Architecture/DeploymentVendorPackageBoundaryTest.php
```
Expected: PASS.

- [ ] **Step 7: Commit workflow checkpoint**

```bash
git add .github/workflows/deploy.yml tests/Architecture/DeploymentVendorPackageBoundaryTest.php
git commit -m "ci: deploy Composer vendor as verified package"
```

---

### Task 5: Wire targeted CI and production recovery runbook

**Files:**
- Modify: `.github/workflows/integration-full-validation.yml`
- Create: `docs/operations/CPANEL_VENDOR_PACKAGE_DEPLOYMENT_RUNBOOK.md`
- Modify: `docs/operations/COMMUNICATION_CENTER_RUNBOOK.md`
- Modify: `tests/Feature/Admin/DeploymentConsoleSourceContractTest.php` or architecture test to assert runbook safety markers.

**Interfaces:**
- Deployment Console regression step includes `VendorPackageInstallerTest.php` and `DeploymentVendorPackageBoundaryTest.php`.
- Recovery sequence is explicitly manual/controlled at the activation boundary.

- [ ] **Step 1: Extend the existing Deployment Console regression gate**

Add the new installer and architecture tests to the current targeted Deployment Console CI step; do not add another full-suite workflow.

- [ ] **Step 2: Write the cPanel recovery runbook**

Document exact order:
1. ensure the new app deployment and package upload completed;
2. open protected Deployment Console;
3. run `vendor_package_status` and compare pending source/lock/package hashes against the release while also recording previous installed-state;
4. if package is valid, enter exact `INSTALL-VENDOR-PACKAGE` confirmation;
5. verify installer reports successful wholesale replacement and bootstrap check;
6. rerun `vendor_package_status` and verify installed-state now matches pending lock/source/package hashes;
7. run `migration_status`;
8. take/confirm required database backup before any write migration;
9. run `migrate` only when appropriate;
10. run post-deploy smoke checks;
11. retain no assumption that the pre-recovery `vendor` from cancelled Deploy #84 was complete.

Include a HARD STOP if Deployment Console itself cannot boot: do not attempt further FTP vendor patching. In that case use cPanel File Manager to extract the already-verified archive into a temporary directory and perform the documented whole-directory replacement/rollback procedure manually; never merge files into the active vendor directory.

- [ ] **Step 3: Cross-link Communication Center runbook**

State that Communication Center migrations/runtime activation occur only after vendor package installed-state is verified. Preserve queue worker, scheduler, provider and DNS requirements unchanged.

- [ ] **Step 4: Run targeted deployment tests**

Run:
```bash
php artisan test \
  tests/Unit/Deployment \
  tests/Feature/Admin/DeploymentConsoleSourceContractTest.php \
  tests/Feature/Admin/DeploymentConsoleSecurityTest.php \
  tests/Feature/Admin/DeploymentConsoleExecutionTest.php \
  tests/Architecture/DeploymentVendorPackageBoundaryTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit runbook/CI checkpoint**

```bash
git add .github/workflows/integration-full-validation.yml docs/operations tests
git commit -m "docs: add vendor package production recovery procedure"
```

---

### Task 6: Final branch review, full validation, merge and real Production recovery

**Files:** no new feature files unless review finds a defect.

**Interfaces:** consumes all previous tasks.

- [ ] **Step 1: Review branch diff against spec**

Confirm the net change contains no unrelated application refactor, no arbitrary execution surface, no direct `vendor/**` FTP sync, and no path where pending manifest metadata is mistaken for installed state.

- [ ] **Step 2: Run Composer security gate**

Run:
```bash
composer audit --no-dev --abandoned=report
```
Expected: zero security advisory failures.

- [ ] **Step 3: Run targeted deployment gate one final time**

Run the exact targeted command from Task 5. Expected: PASS.

- [ ] **Step 4: Run Integration Full Validation once**

Trigger the repository's Integration Full Validation on the final candidate. Do not re-run unless code changes after the gate.

Expected: all regression sections and Full Project PHPUnit green.

- [ ] **Step 5: Open final PR to `main` and review exact diff**

PR must describe:
- root cause of Deploy #84;
- package/manifest/installed-state architecture;
- recovery treatment of potentially partial Production vendor;
- targeted and full validation evidence.

Do not merge if head SHA changes after review without rechecking the diff/gates.

- [ ] **Step 6: Merge with expected head SHA and observe Production FTP run**

Verify:
- Safety Gate green;
- Strict Production Readiness green;
- normal FTP app sync completes without long vendor upload;
- second package-only FTP sync completes;
- run concludes success.

- [ ] **Step 7: Production activation checkpoint**

Because Production state is real and Deploy #84 may have partially modified vendor, do not automatically perform package activation or database migration without the explicit operational checkpoint required by the project. Present pending package source/lock/package hashes, previous installed-state (if any), ZIP support status and exact Deployment Console action to the user before the Production write.

- [ ] **Step 8: After explicit Production approval, recover vendor and verify**

Run the fixed `vendor_package_install` operation through the protected Deployment Console, then verify:
- successful bootstrap;
- `vendor_package_status` installed-state matches pending lock/source/package hashes;
- current deployed `composer.lock` matches installed lock hash;
- key web routes load;
- authentication page loads;
- no server error caused by dependency loading.

- [ ] **Step 9: Apply forward migrations only through existing controlled migration flow**

Take/confirm the required backup checkpoint first. Run `migration_status`, then `migrate` only if pending migrations exist and approval requirements are satisfied.

- [ ] **Step 10: Communication Center runtime smoke verification**

After dependency/migration state is healthy, follow `COMMUNICATION_CENTER_RUNBOOK.md`: scheduler, queue workers, provider environment, SPF/DKIM/DMARC and controlled critical/normal email smoke checks.

- [ ] **Step 11: Close the incident with evidence**

Record final `main` SHA, PR number, Full Validation run ID, Production FTP run ID, package lock SHA, installed-state lock/source SHA, and successful activation/smoke evidence.
