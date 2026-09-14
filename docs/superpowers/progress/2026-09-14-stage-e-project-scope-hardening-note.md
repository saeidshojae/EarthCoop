# Stage E project scope hardening note

This bounded hardening task protects the Production Stage E Projects cutover.

Approved product rule: a user-owned project may select any official, active `GovernanceArea` in EarthCoop; it is not restricted to the user's current residence ancestry.

Required regression contract before Production activation:
- canonical create accepts only official + active Governance Areas;
- canonical update accepts only official + active Governance Areas;
- legacy `geographic_*` payload cannot override canonical project scope while Stage E is enabled;
- disabling `LOCATION_GOVERNANCE_PROJECTS_ENABLED` preserves the legacy project form for rollback;
- no migration, backfill, legacy deletion, or C14 retirement work is part of this change.

Production Projects flag must remain OFF until RED is observed, the minimal fix is GREEN, the exact candidate passes Full Validation, the PR is merged/deployed, and Production Stage E smoke is explicitly accepted.
