# Najm Hoda Guzzle Remediation Status

Branch: `agent/najm-hoda-hardening`

Composer resolved the Guzzle security refresh automatically in GitHub Actions.

Updated packages:

- `guzzlehttp/guzzle` 7.10.0 -> 7.15.3
- `guzzlehttp/promises` 2.3.0 -> 2.5.2
- `guzzlehttp/psr7` 2.8.0 -> 2.13.0
- `symfony/deprecation-contracts` 3.6.0 -> 3.7.1
- `symfony/polyfill-php80` 1.33.0 -> 1.37.0

The Composer audit count dropped from 56 advisories affecting 11 packages to 43 advisories affecting 9 packages.

This commit intentionally triggers the full hardening CI against the Composer-generated lockfile, because GitHub Actions pushes made with `GITHUB_TOKEN` do not trigger another workflow run by default.
