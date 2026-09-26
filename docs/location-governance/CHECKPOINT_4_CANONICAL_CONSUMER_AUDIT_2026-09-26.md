# Checkpoint 4 — Canonical Location/Governance Consumer Audit

**Date:** 2026-09-26  
**Baseline:** `main@e9ca019199ad9c0b9feae8692634caeb5db774d3`  
**Scope:** active runtime consumers after Checkpoints 1–3. This checkpoint is not C14 legacy retirement.

## Closure contract

All active consumers must derive the same current truth from canonical Residence, GovernanceArea and canonical Membership/Group state while legacy geography remains rollback-only.

Required consumer surfaces:

1. User Profile and profile completion.
2. Admin user residence.
3. My Groups / Home group counts.
4. Group chat and moderation role presentation.
5. Systemic elections: hierarchy, policy, conflict policy and appointment events.
6. Projects: selected target Location/Governance scope.
7. Local Communities: opt-in and separate from official governance.
8. Admin group filters / bulk temporary roles.
9. Najm Bahar role-based salary recipient filtering.
10. Najm Hoda group operational/page context.
11. My Location & Governance and current election surfaces.

## Findings and corrections

### Canonical group identity

Canonical group identity is `governance_area_id + dimension_key + dimension_value_key`.
`location_level`, `address_id`, legacy specialty/experience columns and fixed Address geography remain compatibility/rollback fields only.

A shared `GroupGovernanceContext` now exposes canonical group level/domain/scope identity to consumers that previously read legacy group fields.

### Profile

The canonical runtime profile uses canonical residence completion and canonical materialized memberships grouped by `dimension_key`. The legacy ProfileController residence entrance gate also delegates to `ProfileCompletionService` so it cannot accidentally reject a canonical resident merely because no legacy Address shadow exists.

### Membership role

On canonical groups, `group_user.role` is authoritative. Role 0/1 must never be re-derived from a legacy `location_level`.
Legacy fallback semantics remain available when canonical groups are disabled.

### Elections

Formal election topology remains based on active official GovernanceArea topology.

Election domain is derived from canonical `dimension_key` when elections are enabled. Conflict-policy and GroupSetting boundaries normalize canonical governance types to the mature versioned policy vocabulary:

- `local -> neighborhood`
- `urban_region -> region`
- `rural_district -> rural` for conflict policy
- `rural_district -> city` for GroupSetting
- `village -> region` for GroupSetting
- `section -> district` for GroupSetting

This normalization is only a policy compatibility boundary; canonical GovernanceArea identity is unchanged.

### Admin groups and temporary roles

Admin group filtering and global temporary-role targeting use GovernanceArea and canonical dimensions while canonical groups are enabled. Legacy `location_level/group_type` filtering remains rollback-only.

### Najm Bahar salaries

Role-based salary rules resolve canonical governance types and dimensions instead of treating legacy group fields as authority when canonical groups are enabled.

### Najm Hoda

Founder operational events and page context include canonical governance area, dimensions and stable scope keys. Legacy group level remains only as fallback when canonical groups are disabled.

### Projects

No new correction was required in this checkpoint. Existing project scope contracts already keep project target scope independent from user residence and allow a valid selected stopping point. Canonical fields are `target_location_id` and `governance_area_id`; legacy geographic columns remain rollback compatibility only.

### Communities

No new correction was required. Community membership remains explicit opt-in and Community GovernanceArea never enters formal upstream systemic-election topology.

## Intentionally retained legacy code

The following classes/columns may still exist after this checkpoint and are **not** evidence that canonical runtime still depends on them:

- `Address` and fixed geography models/columns;
- `GroupService` legacy hierarchy path;
- `groups.location_level` / `groups.address_id`;
- legacy geographic project columns;
- legacy geography admin/API surfaces;
- legacy ProfileController election acceptance code that is route-shadowed by the systemic responsibility-offer controller;
- legacy ChatController election-writing code that is route-shadowed by SystemicElectionChatController.

They remain rollback/compatibility scaffolding until a separate C14 legacy-retirement audit and explicit approval. Checkpoint 4 does not delete them.

## Regression evidence

Dedicated regression:
`tests/Feature/LocationGovernance/CanonicalConsumerAlignmentCheckpoint4Test.php`

It protects:

- canonical route ownership;
- authoritative canonical membership roles;
- legacy role fallback while cutover is disabled;
- canonical election domain/level and policy-key normalization;
- canonical admin group filtering;
- canonical bulk temporary-role filtering;
- canonical Najm Hoda group context.

Existing permanent regressions remain authoritative for:

- canonical residence/profile without Address;
- admin residence edit;
- My Groups/Home counts;
- official election topology and electorate;
- project stopping-point persistence;
- Community separation;
- responsive/mobile navigation.

Checkpoint 4 may close only after the dedicated regression, mature subsystem gates, responsive gate and Full Validation are green on one fixed SHA.
