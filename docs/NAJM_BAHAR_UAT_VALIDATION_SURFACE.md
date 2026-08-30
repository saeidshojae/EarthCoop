# Najm Bahar UAT validation surface

This branch is an isolated UAT branch derived from Stock Freeze checkpoint `fb7f3441347b43e7f845c915e63f0e8e955baccb`.

A draft pull request targeting `main` may be opened solely to trigger the repository pull-request validation workflows, following the existing validation-only pattern used by PR #88.

Safety contract:

- DO NOT MERGE the validation-only PR.
- No direct changes to `main` are authorized.
- No production deploy is authorized.
- The production FTP workflow triggers on `push` to `main`, not on pull requests.
- Real Servix/ZarinPal provider UAT remains deferred until real credentials and HTTPS staging exist.
- A validation-only PR is evidence generation only; it is not integration approval.
