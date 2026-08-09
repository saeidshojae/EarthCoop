# CommonMark Remediation Verification

This marker commit exists to trigger the full Najm Hoda hardening CI after the Composer-generated CommonMark lockfile update.

Expected dependency state:

- league/commonmark 2.9.0
- nette/schema 1.3.5
- nette/utils 4.1.5
- previously verified Guzzle security updates retained

Security audit evidence from the resolver run:

- before Guzzle remediation: 56 advisories / 11 packages
- after Guzzle remediation: 43 advisories / 9 packages
- after CommonMark remediation: 35 advisories / 8 packages

The dependency update is considered accepted only after the full MySQL 8 migration, user import boundary, and Najm Hoda regression suite pass on this branch.
