# Stage C Canonical Group Cutover Blocker — 2026-09-13

## Production observation

Stage A (runtime) and Stage B (registration/profile + Primary Residence) passed on Production. Stage C was explicitly authorized and `LOCATION_GOVERNANCE_GROUPS_ENABLED=true` was tested.

Observed result: `/groups` continued to show the user's legacy Tehran/Sohanak spatial group memberships after the user's canonical Primary Residence had been saved as the Chahardangeh reference village. Stage C therefore did **not** pass.

## Required Production rollback state

Until remediation is validated/deployed, Production must be held at the last healthy matrix:

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=true
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=true
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false
LOCATION_GOVERNANCE_PROJECTS_ENABLED=false
DEPLOYMENT_CONSOLE_ENABLED=false
```

Do not proceed to elections or projects while Stage C is unresolved.

## Root-cause investigation

The issue is not a single stale-view problem. Four contributing gaps were confirmed in deployed `main@cae358c4b01dabfd45cd588f82bd5f2c1d657956`:

1. `GroupController@index` reads the user's existing `groups` pivot relation directly; it does not invoke canonical membership resolution/materialization when `groups_enabled=true`.
2. `ProfileResidenceController` / `ResidenceService` updates Primary Residence but does not trigger group reconciliation after the canonical residence changes.
3. `GroupService::canonicalGroupsForUser()` materializes eligible canonical groups and attaches them with `syncWithoutDetaching()`, but does not retire/de-authoritize legacy spatial memberships.
4. `LocationGovernanceBootstrapSeeder` currently seeds all five default `GroupCreationPolicy` dimensions (`public`, `profession`, `specialty`, `age`, `gender`) as `OnDemand`. Therefore the Production `MembershipEngine` has no automatic materializable intents under the bootstrap policies even if the canonical group path is called.

Existing integration fixtures intentionally exercise all five dimensions as `Automatic`, confirming the engine/materializer supports the intended auto-grouping behavior; the current Production bootstrap policy is deliberately conservative and needs an explicit Stage C live-policy transition rather than being treated as already live-ready.

## Remediation branch / PR

- Branch: `agent/stage-c-canonical-group-reconciliation-20260913`
- Draft PR: #109 — `Stage C canonical group reconciliation`
- Base: deployed `main@cae358c4b01dabfd45cd588f82bd5f2c1d657956`
- Initial RED test commit: `2f535813688501813170416d3d11905c5cbb3012`
- Additional policy RED test commit: `6600f0a1cdc53c485f0f4df46bbf68aebe2f73cf`
- Full Validation #2477 / run `34749331756` was started for the combined RED head.

No remediation code has been merged or deployed at this checkpoint.

## TDD target behavior

Stage C is not considered ready until automated tests prove at minimum:

- with `groups_enabled=true`, My Groups resolves/materializes canonical groups from current Primary Residence/Governance Area membership instead of treating legacy `address_id + location_level` groups as authoritative;
- the live Stage C group-creation policy supports EarthCoop's intended automatic dimensions while preserving explicit policy semantics;
- no theoretical empty-group explosion occurs;
- legacy memberships/history are not destructively rewritten merely to make the UI look correct;
- Group Chat/Admin and user-created/exclusive group behavior remain intact;
- rollback remains `groups_enabled=false` without undoing Stage A/B.

## Resume prompt

> Continue Stage C debugging from `docs/location-governance/2026-09-13-stage-c-blocker.md` and Draft PR #109. Stage A/B are healthy; Production must be runtime=true, registration=true, groups=false, elections=false, projects=false. Root cause includes GroupController bypassing GroupService, no residence→group reconciliation, attach-only canonical materialization, and bootstrap policies being OnDemand. Continue TDD on `agent/stage-c-canonical-group-reconciliation-20260913`; do not merge/deploy or re-enable groups until RED→GREEN validation is complete and separately approved.