# cPanel Vendor Package Deployment Runbook

This runbook is the operational contract for delivering Composer dependencies to EarthCoop Production on the current cPanel/FTPS host without SSH.

## 1. Why this flow exists

The normal application FTP sync deliberately excludes `vendor/**` and `storage/**`. Production dependencies are built from the committed `composer.lock` in GitHub Actions, compressed into one ZIP, accompanied by a SHA-256 manifest, and transferred in a second package-only FTPS sync to:

`storage/deployment/vendor-packages/`

Do not restore file-by-file FTP upload of `vendor/`. It is slow, non-atomic, and can leave Production with a partially updated dependency tree.

## 2. Release artifacts

For each deployment CI creates:

- `vendor-<composer-lock-sha256>.zip`
- `manifest.json`

The manifest contains schema version, package filename/hash, `composer.lock` hash, source Git SHA, creation time, expected top-level directory and PHP major/minor.

The server-side installer recomputes checksums. A package is not trusted merely because it arrived through FTPS.

## 3. Deployment order

1. GitHub Safety Gate passes, including `composer audit --no-dev --abandoned=report`.
2. GitHub builds Production dependencies from the committed lock.
3. CI creates and locally verifies the vendor ZIP and manifest.
4. Normal application files sync by FTPS with `vendor/**`, `storage/**`, `.env`, tests and CI files excluded.
5. A second FTPS sync transfers only the ZIP and manifest into `storage/deployment/vendor-packages/`.
6. The release is **not dependency-complete yet**. Open the protected Deployment Console.
7. Run `vendor_package_status` and verify the pending package matches the current `composer.lock`.
8. Run `vendor_package_install` only with exact secondary confirmation `INSTALL-VENDOR-PACKAGE`.
9. Confirm the result is successful and the installed-state record now matches the current lock.
10. Only after vendor activation, run any required forward migrations through the existing `migrate` operation.
11. Perform application smoke checks.

## 4. Deployment Console checks

The console is available only when the existing deployment-console controls are intentionally enabled. It remains founder/admin protected and requires the temporary deployment secret.

The package status panel exposes only sanitized metadata:

- source Git SHA;
- `composer.lock` SHA-256;
- package SHA-256;
- package creation time;
- installed time;
- current-lock match indicators.

It does not expose absolute filesystem paths or accept a package/manifest path from the browser.

## 5. What the installer does

The installer:

1. loads the canonical `manifest.json` only;
2. validates schema and exact package filename;
3. verifies package SHA-256;
4. verifies the deployed `composer.lock` SHA-256;
5. rejects unsafe ZIP paths and symlinks;
6. extracts to a lock-specific staging directory;
7. verifies `vendor/autoload.php` and Composer installed metadata;
8. requires staging and live vendor to be on the same filesystem;
9. renames the current vendor to a bounded backup;
10. renames staged vendor into the active path;
11. verifies the activated Composer autoloader without database access;
12. restores the previous vendor on post-swap verification failure;
13. writes `installed.json` only after successful activation;
14. retains only the configured number of previous vendor backups.

There is no recursive in-place vendor merge fallback.

## 6. Failure handling

### Package or manifest did not arrive

Do not install. The current vendor remains untouched. Re-run the deployment/package transfer after diagnosing FTPS.

### Checksum or lock mismatch

Do not override the check. The code release and dependency package do not belong together. Rebuild/redeploy the correct commit.

### Extraction or unsafe-path rejection

Do not manually relax path validation. Rebuild the package from CI.

### Rename or bootstrap verification failure

The installer fails closed and restores the previous vendor where activation had begun. Inspect the sanitized result and deployment audit log before retrying.

## 7. Recovery from FTP Deploy #84

FTP Deploy #84 attempted to transfer `vendor` file-by-file and did not complete. Treat the existing Production vendor tree as potentially mixed/partial until a verified package activation succeeds.

The first successful activation must replace the complete live vendor directory. Do not patch individual dependency files.

### If Laravel still boots

Use the Deployment Console flow above. This is the preferred recovery path.

### If Laravel cannot boot because vendor is too damaged

Do **not** add an unauthenticated recovery endpoint, arbitrary PHP runner, or shell surface.

Use cPanel File Manager as the break-glass recovery path:

1. preserve/rename the current `vendor` directory as a temporary backup;
2. use the exact CI-generated package whose manifest lock hash matches the deployed `composer.lock`;
3. extract that package locally within the application filesystem so it creates one complete `vendor/` directory;
4. verify `vendor/autoload.php` and `vendor/composer/installed.php` or `installed.json` exist;
5. reload the application;
6. once Laravel boots, use `vendor_package_status` and normal operational checks to confirm the release state;
7. do not delete the backup until the application is verified.

This manual fallback requires matching the hashes shown by the manifest. Never mix files from multiple vendor packages.

## 8. Post-activation checks

At minimum verify:

- home/login page loads without PHP/autoload errors;
- authentication routes boot;
- Deployment Console reports current lock matches installed state;
- `migration_status` works;
- any release-specific smoke tests pass.

For Communication Center releases additionally follow `docs/operations/COMMUNICATION_CENTER_RUNBOOK.md`: migrations, queue workers, scheduler, provider configuration, DNS alignment and controlled email smoke tests are separate operational requirements.

## 9. Hard stops

Stop the release if any of these are true:

- Composer security audit is not clean;
- pending package lock hash does not match deployed `composer.lock`;
- package checksum fails;
- installer reports unsafe archive entries;
- staged/live paths are not on the same filesystem;
- vendor activation or bootstrap verification fails;
- installed-state does not match current lock after activation.

Never solve a hard stop by disabling integrity checks or re-enabling file-by-file vendor FTP deployment.
