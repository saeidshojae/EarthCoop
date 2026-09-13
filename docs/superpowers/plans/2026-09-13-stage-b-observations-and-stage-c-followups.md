# Stage B Observations and Stage C Follow-ups — 2026-09-13

This addendum is part of the staged Location/Governance activation plan and records Production observations that must not be lost between chat sessions.

## Current Production flag state

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=true
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=true
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false
LOCATION_GOVERNANCE_PROJECTS_ENABLED=false
DEPLOYMENT_CONSOLE_ENABLED=false
```

## Stage B Production observations

1. `/profile/edit` switched from the legacy location editor to the canonical `Primary Residence` editor after `registration_enabled=true`.
2. Manual canonical selection worked through the available reference path:
   - Iran
   - Mazandaran
   - Sari County
   - Chahardangeh Section
   - Chahardangeh Rural District
   - Chahardangeh Reference Village
3. The terminal node was recognized by the UI as a valid residence endpoint.
4. Saving the residence succeeded. The UI showed a success message and then displayed the persisted current location as `Chahardangeh Reference Village` on `/profile/edit`.
5. No 500, redirect loop, blank page, or visible form failure occurred during this Stage B mutation.
6. Existing group memberships/listing did **not** change after saving Primary Residence while `LOCATION_GOVERNANCE_GROUPS_ENABLED=false`.

## Group behavior — expected until Stage C

The unchanged group list is expected by current code. `GroupService::getGroupsForUser()` and `generateGroupsForUser()` only switch to canonical membership resolution/materialization when `config('location-governance.groups_enabled')` is true. With the flag false, legacy group resolution remains active.

Therefore this is **not a Stage B failure**.

### Stage C mandatory acceptance checks

When Stage C is explicitly authorized and `LOCATION_GOVERNANCE_GROUPS_ENABLED=true` is enabled, verify all of the following before proceeding to Stage D:

- the test user's canonical Primary Residence drives canonical public/spatial membership resolution;
- obsolete legacy-location-derived group exposure does not remain incorrectly authoritative;
- expected canonical governance-scoped public groups are materialized on demand;
- profession/specialty/age/gender memberships still resolve correctly;
- existing mature Group Chat, group listing, and Group Admin/Control Center remain healthy;
- no empty-group explosion or mass meaningless materialization occurs;
- membership changes are deterministic/idempotent on refresh/re-entry;
- if canonical membership behavior is wrong, immediately revert only `GROUPS_ENABLED=false` and stop Stage C.

## Reference geography coverage limitation

The Production selector currently exposes only the small versioned `earthcoop-reference / v1` dataset imported for architecture/UAT. This is intentionally a reference/pilot dataset, not a complete Iran/global gazetteer. The observed path is backed by `database/reference/ir/v1/locations.jsonl` and includes representative Sari/Chahardangeh urban/rural test branches.

This limited choice set is therefore **not treated as a selector UI defect**.

### Blocking task before broad public onboarding

Before EarthCoop uses canonical location selection for general public registration beyond the controlled pilot/reference geography, complete a separate reviewed data-rollout task:

- define the authoritative source/provenance strategy for complete Iran geography;
- import complete required Iran hierarchy through the versioned reference importer, not ad-hoc SQL;
- preserve schema-driven urban/rural branching and stable external IDs;
- dry-run first and review create/update/deactivate/conflict counts;
- apply only after approval, then prove idempotency;
- extend Governance mappings/topology for the intended formal governance scopes;
- test representative provinces/cities/rural districts/villages, including paths with and without neighborhood/street/alley layers;
- only after Iran coverage is production-ready should unrestricted Iranian onboarding rely on the canonical selector;
- global expansion must be country-by-country/schema-by-schema and must not assume Iran's fixed hierarchy.

This coverage-expansion work is separate from simply turning on Stage C and must not be forgotten before broad user acquisition.
