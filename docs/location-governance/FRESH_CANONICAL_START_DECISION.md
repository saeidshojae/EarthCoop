# Location / Governance — Fresh Canonical Start Decision

## Decision

For the initial EarthCoop Production cutover, migration of the small set of existing users' legacy spatial/profile geography into the new canonical Location/Governance model is **not a blocking prerequisite**.

The canonical runtime may start fresh for Location/Governance after the approved additive schema/bootstrap/reference-import procedure. Existing users may establish or correct their canonical Primary Residence through the new profile flow after cutover.

This is an explicit launch policy for the current early-stage Production population. It is **not** a general rule that user data may be discarded in future migrations.

## What this decision allows

- Do not build or run a risky one-off bulk conversion solely to map every existing user's legacy Address/geography into canonical `Location` / `Primary Residence` before launch.
- Treat an empty `user_location_relationships` population as valid at cutover readiness time.
- Bootstrap canonical schemas/types/policies and import the reviewed reference geography independently of existing user geography.
- Let existing users establish canonical residence through the normal canonical profile path after the relevant rollout stage is enabled.
- Keep legacy geography available as temporary fallback/rollback scaffolding during C13 observation.

## What this decision does NOT allow

This decision does **not** authorize:

- `migrate:fresh`, database reset, truncate, drop, or destructive schema rebuild in Production;
- deletion of users, authentication data, groups, elections, Najm Bahar accounts/ledger data, stock data, messages, projects, or other mature subsystem state;
- deletion of legacy Address/geography tables during C13;
- destructive legacy retirement (C14);
- enabling canonical runtime feature flags without the separate explicit activation approval required by the cutover runbook.

The existing Production database remains valuable because it contains state beyond the small existing user geography dataset. Normal backup protection therefore remains required before Production-changing schema/import operations.

## Readiness interpretation

`php artisan location-governance:readiness` intentionally checks the canonical platform prerequisites rather than requiring a pre-populated canonical residence row for every existing user. In Fresh Canonical Start mode, readiness may be `READY` when:

- required additive migrations are applied;
- canonical schema/types/policies exist;
- the approved reference dataset is imported successfully with no unresolved conflict;
- Governance mappings exist;
- rollout flags have valid states;
- no fatal proposal conflict exists;
- validation SHA and UAT evidence identify the approved release.

The absence of existing user canonical residence rows alone must not be treated as a cutover failure.

## Existing-user behavior after activation

After the registration/profile canonical stage is explicitly approved and enabled:

1. New users use the canonical schema-driven location flow.
2. Existing users without canonical Primary Residence are guided to establish/correct it through their profile.
3. No work/study/other relationship may substitute for Primary Residence as the official geographic voting basis.
4. Legacy geography remains available only as controlled compatibility/rollback support until a later audited retirement decision.

## Production safety boundary

This decision removes **legacy user-geography conversion** from the critical path. It does not remove the safety boundary around the rest of Production.

Before schema/import writes on Production, the operator must still have a recoverable database backup or provider snapshot appropriate to the hosting environment. If the hosting environment cannot support a practical isolated restore rehearsal, that limitation must be recorded explicitly before the write window and the owner must make the final risk decision; it must not be silently represented as verified restore evidence.

## Authorization boundary

The owner has approved continuing preparation on the basis of Fresh Canonical Start for the current small, known user population.

This decision **does not itself authorize activation of**:

- `LOCATION_GOVERNANCE_RUNTIME_ENABLED`
- `LOCATION_GOVERNANCE_REGISTRATION_ENABLED`
- `LOCATION_GOVERNANCE_GROUPS_ENABLED`
- `LOCATION_GOVERNANCE_ELECTIONS_ENABLED`
- `LOCATION_GOVERNANCE_PROJECTS_ENABLED`

Activation remains a separate explicit approval checkpoint after the Production preparation/preflight state is reported.