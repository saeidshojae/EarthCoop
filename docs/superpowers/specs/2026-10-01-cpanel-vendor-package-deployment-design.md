# cPanel Vendor Package Deployment Design

Date: 2026-10-01
Status: Proposed
Owner: EarthCoop

## 1. Problem Statement

EarthCoop currently deploys application files to cPanel through `SamKirkland/FTP-Deploy-Action`. The workflow builds a production Composer `vendor/` tree on GitHub Actions, but historically excluded `vendor/**` from FTP sync. That made deployments fast but allowed Production dependencies to drift from the audited `composer.lock` used by CI.

On 2026-10-01, newly published Composer security advisories blocked Production deployment. The vulnerable dependency set was upgraded and the security gate passed. To ensure those secured dependencies would actually reach Production, the `vendor/**` exclusion was removed. The subsequent FTP deployment then attempted to upload the complete Composer vendor tree file-by-file and exceeded the practical time budget, leaving the deployment cancelled while `Sync files to cPanel via FTP` remained in progress.

The root cause is therefore not the Communication Center itself. The root cause is that the existing FTP deployment architecture has no efficient and atomic mechanism for delivering Composer dependency changes to cPanel.

## 2. Goals

The deployment design must:

1. Keep the existing cPanel/FTPS deployment model working without requiring SSH.
2. Ensure Production `vendor/` exactly corresponds to the audited `composer.lock` used by CI.
3. Avoid uploading thousands of Composer files individually over FTP.
4. Preserve the existing security posture of the browser Deployment Console: no arbitrary shell, no arbitrary PHP, no arbitrary Composer commands.
5. Make dependency activation controlled, verifiable, and recoverable.
6. Keep normal deployments fast when dependencies have not changed.
7. Prevent partial dependency activation from leaving Production unusable.
8. Preserve `.env`, storage data, and other Production-only state.
9. Provide auditable evidence that the package installed on Production matches the package produced by CI.

## 3. Non-Goals

This change does not:

- redesign application release/versioning generally;
- add SSH access assumptions;
- expose shell access through the browser;
- move Production secrets into GitHub or the database;
- run destructive database operations automatically;
- replace the existing Deployment Console with a general command runner;
- introduce Docker or container deployment on the current cPanel host.

## 4. Selected Architecture

### 4.1 High-level flow

The selected flow is:

`CI gates -> production composer install -> vendor package -> package checksum/manifest -> normal FTP app sync + single vendor package -> controlled server-side package activation -> migration/cache maintenance -> smoke verification`

`vendor/**` remains excluded from ordinary FTP synchronization.

GitHub Actions transfers one dependency package instead of thousands of individual Composer files.

### 4.2 Package format

CI creates a deterministic production dependency bundle after:

```bash
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts
```

The bundle contains only the generated `vendor/` tree. The preferred archive format is `tar.gz` because it is broadly supported by PHP/cPanel environments and preserves directory layout efficiently. The implementation must confirm runtime extraction support before activation; if native Phar/Tar extraction is unavailable on the target PHP build, ZIP is an acceptable fallback.

The package filename must be release-specific and immutable, for example:

`storage/deployment/vendor-packages/vendor-<composer-lock-hash>.tar.gz`

The package must never be written directly inside the active `vendor/` directory.

### 4.3 Manifest and integrity

CI produces a small manifest alongside the package containing at least:

- schema version;
- package filename;
- SHA-256 of the package;
- SHA-256 of `composer.lock`;
- source Git commit SHA;
- creation timestamp;
- expected top-level directory (`vendor`);
- PHP major/minor used to build the package.

The manifest is not trusted merely because it arrived over FTP. The server-side installer recomputes the package SHA-256 and verifies it against the manifest before extraction.

The installer also verifies that the repository's deployed `composer.lock` hash matches the manifest's lock hash. A mismatched package must be rejected.

### 4.4 Safe extraction

The server-side installer must extract into a staging directory outside active `vendor/`, for example:

`storage/deployment/vendor-staging/<lock-hash>/vendor`

Before extraction, every archive entry must be validated to prevent path traversal. Absolute paths and any entry containing traversal outside the staging root are rejected. Symlink behavior must be explicit and safe; if safe validation is not possible, archive symlinks are rejected.

After extraction the installer validates at minimum:

- `vendor/autoload.php` exists;
- `vendor/composer/installed.php` or equivalent Composer metadata exists;
- the extracted tree is non-empty;
- package manifest and lock hash still match.

### 4.5 Activation and rollback

Activation must not progressively overwrite the live `vendor/` tree.

The intended activation sequence is:

1. Extract and validate the staged vendor tree.
2. Rename current `vendor/` to a temporary backup path such as `vendor.__previous_<timestamp>`.
3. Rename staged `vendor/` into the active `vendor/` path.
4. Verify Laravel can bootstrap using the new autoloader.
5. If verification fails, restore the previous vendor tree immediately.
6. On success, retain only a bounded previous backup or remove it after the deployment is fully confirmed.

The implementation must account for cPanel filesystem semantics. Directory rename is preferred because it is local and much faster than file-by-file copying. If same-filesystem atomic rename cannot be guaranteed, the installer must fail closed rather than silently perform an unsafe partial overwrite.

### 4.6 Controlled server-side command

A dedicated Artisan command will perform the package activation, for example:

`php artisan deployment:install-vendor-package --manifest=<known-relative-path> --confirm=<fixed-confirmation>`

The command must:

- accept only a package beneath the dedicated deployment package directory;
- reject arbitrary filesystem paths;
- validate manifest schema and checksum;
- validate `composer.lock` hash;
- safely extract to staging;
- activate using rename/rollback semantics;
- run a bounded post-activation bootstrap check;
- return non-zero on any failure;
- log structured results without secrets.

This command is purpose-built. It is not a wrapper around arbitrary shell or Composer execution.

### 4.7 Deployment Console integration

The existing `DeploymentConsoleService` remains allowlist-based. A new explicit operation may be added to trigger vendor package activation after the package has arrived on the server.

The operation must require a secondary confirmation phrase such as:

`INSTALL-VENDOR-PACKAGE`

No user-supplied shell command, package path outside the deployment directory, or arbitrary archive name is permitted.

If automatic server-side invocation cannot be performed safely from GitHub Actions because the deployment channel is FTP-only, the first implementation may expose activation through the protected browser Deployment Console. In that case the UI must show the pending package metadata and checksum so the administrator can verify and activate it intentionally.

A future SSH-capable environment may automate the same Artisan command without changing package semantics.

## 5. Workflow Changes

### 5.1 Restore vendor exclusion

`.github/workflows/deploy.yml` must again exclude:

`**/vendor/**`

from FTP synchronization.

This prevents recurrence of the 90-minute file-by-file vendor upload.

### 5.2 Build the package

After production Composer install, the workflow:

1. computes the `composer.lock` SHA-256;
2. creates the vendor archive;
3. computes archive SHA-256;
4. writes the manifest;
5. places both package and manifest under a dedicated deployment path included in FTP sync.

The normal application files and built frontend assets continue through the existing FTP action.

### 5.3 Incremental behavior

The workflow may skip creating or activating a new vendor package when the deployed lock hash is already current, but correctness takes priority over optimization. The first production-safe version may always create the single archive on each deploy; this is acceptable because the transfer is one compressed file rather than thousands of files.

Later optimization may make package generation conditional on `composer.lock` changes.

## 6. Database and Application Activation

Dependency package activation and database migration are separate operations.

The vendor installer must not run destructive database commands. Existing forward-only migration controls remain unchanged.

For releases requiring migrations, the safe sequence is:

1. Application files and dependency package arrive.
2. Vendor package is activated and bootstrap-verified.
3. Forward migrations are run through the existing controlled migration operation.
4. Laravel caches are cleared/rebuilt using explicit allowlisted commands if required.
5. Smoke tests verify key routes and authentication/email behavior.

Communication Center activation additionally requires queue workers and scheduler configuration according to `docs/operations/COMMUNICATION_CENTER_RUNBOOK.md`; this deployment architecture does not replace those runtime requirements.

## 7. Failure Modes

### Package upload interrupted

No activation occurs. Existing live vendor remains untouched.

### Manifest/package checksum mismatch

Installation is rejected before extraction.

### `composer.lock` mismatch

Installation is rejected because package and application release do not belong together.

### Unsafe archive path

Installation is rejected and staging data is cleaned up.

### Extraction failure

Live vendor remains untouched.

### Activation rename failure

Installer stops and restores any already-renamed previous directory where possible. It never falls back to recursive in-place overwrite.

### Laravel bootstrap failure after activation

Previous vendor is restored and the command fails visibly.

### FTP deployment cancelled after app files but before package finishes

No dependency activation occurs. Production may have new application files with old vendor; therefore application deployments that require dependency changes must not be considered complete until vendor activation succeeds. The workflow/runbook must surface this explicitly.

## 8. Security Requirements

1. No arbitrary shell command execution.
2. No dynamic PHP evaluation.
3. No arbitrary archive path accepted from an HTTP request.
4. SHA-256 verification is mandatory before extraction.
5. Archive traversal protection is mandatory.
6. Operation remains founder/admin protected through existing Deployment Console controls.
7. Installation events are audit logged.
8. Secrets are never written into package or manifest.
9. `.env` remains excluded from FTP.
10. Existing Production storage remains excluded from FTP.

## 9. Testing Strategy

### Unit tests

Test the vendor package installer for:

- valid manifest acceptance;
- checksum mismatch rejection;
- lock hash mismatch rejection;
- missing autoload rejection;
- path traversal rejection;
- out-of-root package path rejection;
- activation rollback on post-bootstrap failure;
- successful staged activation;
- old backup cleanup policy.

### Deployment Console tests

Verify:

- operation is present only as a fixed allowlisted action;
- secondary confirmation is required;
- no arbitrary command/path can be injected;
- audit log entry is produced.

### Workflow/architecture tests

Add a regression assertion that:

- `vendor/**` is excluded from ordinary FTP sync;
- a single vendor package + manifest are generated for deployment;
- the security audit runs before deploy;
- Production package is built from the committed lock.

### Regression gates

During implementation use targeted tests first. Run Integration Full Validation only at the final release gate, consistent with EarthCoop CI-time policy.

## 10. Operational Runbook

The Production deployment runbook must document:

1. how to identify the package/manifest belonging to the deployed commit;
2. how to inspect pending package metadata;
3. how to activate the package through the protected Deployment Console;
4. what success output looks like;
5. how rollback behaves;
6. when to run migrations;
7. how to perform post-deploy smoke tests;
8. how to clean stale package/staging files;
9. how to confirm Communication Center queue/scheduler requirements separately.

## 11. Migration From Current State

The failed/cancelled `FTP Deploy #84` attempted a direct vendor upload. Before the new mechanism is considered complete, Production must be treated as potentially having a partially transferred vendor tree.

The recovery deployment must therefore not assume that Production vendor is pristine. The first successful package activation must replace the entire active vendor directory from a validated package, rather than incrementally patching it.

No database-destructive action is required for this recovery.

## 12. Definition of Done

This deployment redesign is complete only when:

1. direct FTP vendor upload is disabled again;
2. Composer security audit passes;
3. CI creates one validated dependency package and manifest;
4. server-side installer safely validates, stages, activates, and can roll back vendor;
5. Production vendor matches the deployed `composer.lock`;
6. targeted installer/console/workflow tests pass;
7. Integration Full Validation passes on the release candidate;
8. a real Production deployment completes without the previous long-running vendor FTP sync;
9. Production bootstrap and key smoke tests pass;
10. Communication Center can proceed to migrations/runtime activation without dependency drift.
