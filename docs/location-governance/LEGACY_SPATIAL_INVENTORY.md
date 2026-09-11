# Legacy Spatial Inventory — C0

**Program:** Global Location/Governance Architecture  
**Implementation branch:** `agent/global-location-governance-implementation-20260910`  
**Planning checkpoint:** `15e4e95e74402de2f6dae0975c5c725b78a847a0`  
**Baseline:** `main@767a276b962a62234f70878071d007930903577a`

## Purpose

This inventory records legacy spatial dependencies that must be protected or deliberately replaced while EarthCoop moves to `Location -> GovernanceArea -> Residency -> Membership`.

This document is a C0 characterization artifact, not authorization to delete or rewrite any legacy table. Production data remains untouched.

## Dependency vocabulary

- **Address FK** — fixed columns such as `province_id`, `county_id`, `city_id`, `village_id`.
- **Legacy geography model** — `Continent`, `Country`, `Province`, `County`, `District`, `City`, `Rural`, `Village`, `Region`, `Neighborhood`, `Street`, `Alley`.
- **Group spatial key** — `groups.location_level + groups.address_id`.
- **Hard-coded tier semantics** — behavior depending on literal levels such as `neighborhood`, `street`, `alley`, `global`.

## High-risk runtime consumers

| Category | File | Legacy dependency | Required target boundary |
|---|---|---|---|
| core address | `app/Models/Address.php` | fixed geography FKs and legacy geography relations | `UserLocationRelationship -> Location` |
| user relation | `app/Models/User.php` | `hasOne(Address::class)` | canonical residence/history relation |
| registration | `app/Http/Controllers/Auth/Register/Step3Controller.php` | validates/persists country/province/county/section plus urban/rural leaf columns | schema-driven location selection + Primary Residence |
| welcome stats | `app/Http/Controllers/Auth/Register/StartController.php` | country count derived from `addresses.country_id` | canonical residence/location query |
| profile | `app/Http/Controllers/Profile/ProfileController.php` | legacy geography models, fixed FK validation, old/new group lookup by `location_level/address_id` | residence transfer service + Membership Engine |
| profile UI | `resources/views/profile/edit.blade.php` | encodes city/rural selection from fixed Address columns | schema-driven selector |
| completion | `app/Services/ProfileCompletionService.php` | Address-based completion semantics | canonical residence completeness |
| home | `app/Http/Controllers/HomeController.php` | imports/uses Address | canonical residency/membership read model |
| auth gate | `app/Http/Middleware/Authenticate.php` | imports/uses Address for onboarding/completion behavior | canonical residence status |
| groups core | `app/Models/Group.php` | fillable `location_level`, `address_id`; `address()` relation | explicit `governance_area_id` + dimension identity |
| grouping engine | `app/Services/GroupService.php` | fixed Address hierarchy; city/rural branching; group creation by `location_level/address_id`; literal micro-local levels | deterministic Membership Engine |
| group schema | `database/migrations/2025_03_08_160750_create_groups_table.php` | stores `location_level` and `address_id` | additive canonical scope migration before retirement |
| group admin | `app/Http/Controllers/Admin/GroupController.php` | filters by `location_level` | governance/dimension filters |
| global role admin | `app/Http/Controllers/Admin/GlobalGroupRoleController.php` | role filters include `location_level` | canonical governance scope/tier policy |
| group reporting | `app/Http/Controllers/Group/ReportController.php` | role semantics depend on literal neighborhood/street/alley | capability policy |
| group table | `resources/views/partials/group-table.blade.php` | renders/interprets `location_level` | governance-area presentation |
| group hero | `resources/views/groups/partials/group_hero.blade.php` | renders `location_level` | governance-area presentation |
| routes/API | `routes/web.php` | exposes group `location_level`; contains legacy micro-location creation/API behavior | schema-driven Location API |
| elections | `app/Services/Elections/ElectionGroupDomainClassifier.php` | election rank/domain from `Group::location_level` | Governance Area tier/classification |
| elections | `app/Services/Elections/ElectionGroupHierarchyResolver.php` | parent/child resolution from `location_level` and legacy spatial group identity | Official Governance topology resolver |
| election participation | `app/Listeners/AwardElectionAppointmentParticipation.php` | idempotency/event key includes `group->location_level` | stable governance scope key |
| Najm Hoda observation | `app/Observers/NajmHoda/FounderOperationalDomainObserver.php` | emits `group.location_level` | canonical Governance Area context |
| geography admin | `app/Http/Controllers/Admin/AddressController.php` | direct management of legacy geography models | Location/Governance control center |
| geography admin UI | `resources/views/admin/address/index.blade.php` | legacy address/geography table workflow | canonical control-center workflow |

## Legacy behavior that must remain functionally available

1. An urban Sari member can resolve geographic group context.
2. A rural member can resolve a village path without requiring a city node.
3. Registration continues to support both the urban and rural branches during the transition.
4. Existing groups remain reachable while canonical Governance Area scope is introduced additively.
5. Elections remain attached to the correct social/governance scope throughout cutover.
6. Group Chat/Admin, Secretariat, Projects, Polls and Najm Hoda must not regress merely because their spatial key changes underneath them.

## Characterization contracts added in C0

- `LegacySpatialBaselineContractTest` — in-memory urban/rural hierarchy behavior through current `GroupService::getLocationLevels()`.
- `LegacyRegistrationLocationContractTest` — temporary source characterization of fixed Step3 geography dependencies.
- `LegacyGroupMembershipContractTest` — temporary source characterization of `location_level/address_id` group identity.
- `LegacyElectionScopeContractTest` — current Election-to-Group relation contract.

The source-characterization tests are deliberately temporary migration guards. At the relevant cutover checkpoint they must be replaced by canonical behavioral contracts; they must not force permanent preservation of legacy columns.

## Pre-existing architecture defects / risks observed during C0

- `GroupService` mixes location traversal, dimension resolution, group materialization and membership mutation in one service. The target design separates those responsibilities instead of extending this class.
- Rural branching is encoded by substituting `rural_id` when `city_id` is absent and `village_id` when `region_id` is absent. This is Iran-specific behavior and cannot become the global contract.
- Micro-local role behavior is hard-coded to literal `alley`, `street`, and `neighborhood` tiers in multiple group paths. It must become capability/policy-driven.
- Election topology currently consumes Group spatial metadata, so Election cutover must follow canonical Group scope availability; it cannot safely precede C7.

## Verification required before C0 may be called complete

A connected repository runner must execute:

```bash
php artisan test tests/Feature/LocationGovernance/LegacySpatialBaselineContractTest.php \
  tests/Feature/LocationGovernance/LegacyRegistrationLocationContractTest.php \
  tests/Feature/LocationGovernance/LegacyGroupMembershipContractTest.php \
  tests/Feature/LocationGovernance/LegacyElectionScopeContractTest.php

php artisan test tests/Feature/Elections
php artisan test tests/Feature/GroupChat
php artisan test tests/Feature/NajmHoda
php artisan test
```

It must also run a repository-wide search before C0 closure:

```bash
rg -n "location_level|address_id|continent_id|country_id|province_id|county_id|section_id|city_id|rural_id|region_id|village_id|neighborhood_id|street_id|alley_id|App\\\\Models\\\\(Continent|Country|Province|County|District|City|Rural|Village|Region|Neighborhood|Street|Alley)" app routes resources database tests
```

Any additional runtime consumer found by that command must be appended here before C0 is marked complete.

## Safety status

- No production data operation is part of C0.
- No migration has been created.
- No legacy table or column has been modified.
- `main` is not modified.
