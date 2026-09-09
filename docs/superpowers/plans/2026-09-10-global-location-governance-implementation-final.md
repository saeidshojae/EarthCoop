# EarthCoop Global Location & Governance Implementation Plan — Final

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the fixed Iran-specific geography runtime with a global Location Tree, independent Governance Tree, historical Residency model, and deterministic multidimensional Membership Engine, while preserving all mature EarthCoop capabilities and deferring every destructive production action to explicit human approval.

**Architecture:** Develop target-first and additively. Freeze current behavior with regression contracts; build Location → Governance → Residency → Membership as isolated domains; then cut registration/profile, Groups, Elections, Secretariat, Projects, Polls, communities, geolocation, admin, and Najm Hoda over behind reversible flags. Legacy geography becomes rollback-only after the canonical path is proven, and is retired only after a separate post-cutover audit and approval.

**Tech Stack:** PHP ^8.2, Laravel ^12.0, Eloquent, PHPUnit ^11.5, Blade/Vite/JavaScript, existing EarthCoop CI.

**Spec:** `docs/superpowers/specs/2026-09-10-global-location-governance-architecture-design.md`

**Supersedes planning draft:** `docs/superpowers/plans/2026-09-10-global-location-governance-implementation.md`

## Global Constraints

- Baseline: `main@767a276b962a62234f70878071d007930903577a`.
- Approved Spec checkpoint: `2217d2e95c36d7f8760a95f12c23ebc4d6e8926c`.
- Do not modify `main` directly; implementation starts in an isolated worktree/branch from the approved planning checkpoint.
- No production `migrate:fresh`, truncation, table drop, destructive reverse migration, or production bootstrap without a separate verified backup checkpoint and explicit user approval.
- `Location`, `GovernanceArea`, `Group`, and `UserLocationRelationship` remain separate domain entities.
- Canonical runtime never requires globally fixed `province_id/county_id/city_id/...` columns.
- Formal elections use Official Governance topology, not government geography tables.
- Primary Residence alone grants official geographic governance/voting membership.
- Work/study/other relationships never grant parallel official geographic voting rights.
- Location identity is stable ID + provenance/external IDs, never display name.
- Rename/merge/split/inactivation preserve historical referents.
- Reference geography is versioned, idempotent, auditable, diffable, and imported outside normal giant production Seeders.
- Crowdsourced verification counts distinct users; threshold means `ready_for_review`, never automatic approval.
- GPS/reverse geocoding is optional evidence and may not override declared residence.
- Group materialization modes are exactly `automatic`, `threshold`, `on_demand`, `disabled`.
- Initial dimensions are exactly `public`, `profession`, `specialty`, `age`, `gender`.
- Mature Governance, Elections, Groups/Group Chat/Admin, Secretariat, Najm Hoda, Najm Bahar, Stock, JavaScript, Responsive and Full PHPUnit gates remain release gates.

## Shared test support created before domain tests

The plan uses only fixtures/factories explicitly created below:

- `tests/Support/LocationGovernance/LegacySpatialFixture.php`
- `tests/Support/LocationGovernance/LocationFixture.php`
- `tests/Support/LocationGovernance/RegistrationFixture.php`
- `tests/Support/LocationGovernance/MembershipFixture.php`
- `database/factories/LocationFactory.php`
- `database/factories/LocationTypeFactory.php`
- `database/factories/LocationSchemaFactory.php`
- `database/factories/GovernanceAreaFactory.php`
- `database/factories/UserLocationRelationshipFactory.php`

No later task may refer to an undefined fixture, exception, enum, DTO, or interface.

## Checkpoints

| Checkpoint | Deliverable | Risk |
|---|---|---|
| C0 | baseline contracts + consumer inventory | none |
| C1 | generic Location Core | additive |
| C2 | Governance + capability policy | additive |
| C3 | Residency/history/quota | additive |
| C4 | Iran reference importer | dev/UAT additive |
| C5 | schema-driven selection + registration/profile | reversible flag |
| C6 | dimensions + Membership Engine | additive |
| C7 | Group canonical scope | reversible flag |
| C8 | Election canonical topology | reversible flag |
| C9 | Secretariat/Projects/Polls scope cutover | reversible flag |
| C10 | Community + crowdsourcing | additive |
| C11 | geolocation + admin + Najm Hoda review | approval-gated |
| C12 | fresh bootstrap + full validation + UAT | non-production |
| C13 | production cutover package | explicit approval required |
| C14 | legacy retirement | second explicit approval required |

---

### Task 1 — C0: Freeze current spatial behavior

**Files**
- Create `tests/Support/LocationGovernance/LegacySpatialFixture.php`
- Create `tests/Feature/LocationGovernance/LegacySpatialBaselineContractTest.php`
- Create `tests/Feature/LocationGovernance/LegacyRegistrationLocationContractTest.php`
- Create `tests/Feature/LocationGovernance/LegacyGroupMembershipContractTest.php`
- Create `tests/Feature/LocationGovernance/LegacyElectionScopeContractTest.php`
- Create `docs/location-governance/LEGACY_SPATIAL_INVENTORY.md`
- Inspect `app/Models/Address.php`, `app/Models/Group.php`, `app/Services/GroupService.php`, `app/Http/Controllers/Auth/Register/StartController.php`, `app/Http/Controllers/Profile/ProfileController.php`, `app/Services/ProfileCompletionService.php`, `app/Http/Controllers/HomeController.php`, `app/Http/Middleware/Authenticate.php`, current Election/Secretariat/Project/Poll scope consumers.

**Produces:** executable behavior contracts and an exact file-level inventory of every `Address FK`, legacy geography model, `location_level`, `address_id`, and hard-coded tier dependency.

- [ ] Write characterization tests for one urban Sari user and one rural Chahardangeh/village user.

```php
public function test_legacy_rural_path_can_resolve_village_without_city(): void
{
    $user = LegacySpatialFixture::ruralUserWithoutCity();
    $levels = app(\App\Services\GroupService::class)->getLocationLevels($user);
    $this->assertTrue(collect($levels)->contains(fn (array $row) => $row['level'] === 'village'));
}
```

- [ ] Run focused contracts; document pre-existing defects rather than silently changing them.

```bash
php artisan test tests/Feature/LocationGovernance/LegacySpatialBaselineContractTest.php tests/Feature/LocationGovernance/LegacyRegistrationLocationContractTest.php tests/Feature/LocationGovernance/LegacyGroupMembershipContractTest.php tests/Feature/LocationGovernance/LegacyElectionScopeContractTest.php
```

- [ ] Run baseline release gates before schema changes.

```bash
php artisan test tests/Feature/Elections
php artisan test tests/Feature/GroupChat
php artisan test tests/Feature/NajmHoda
php artisan test
```

- [ ] Commit.

```bash
git add tests/Support/LocationGovernance tests/Feature/LocationGovernance docs/location-governance/LEGACY_SPATIAL_INVENTORY.md
git commit -m "test: freeze legacy spatial behavior"
```

---

### Task 2 — C1-A: Generic Location schema, models, factories

**Files**
- Create `database/migrations/2026_09_10_000001_create_location_core_tables.php`
- Create `app/Models/Location.php`
- Create `app/Models/LocationType.php`
- Create `app/Models/LocationSchema.php`
- Create `app/Models/LocationSchemaType.php`
- Create `app/Models/LocationTypeRelation.php`
- Create `app/Models/LocationExternalId.php`
- Create `app/Models/LocationRelation.php`
- Create `database/factories/LocationFactory.php`
- Create `database/factories/LocationTypeFactory.php`
- Create `database/factories/LocationSchemaFactory.php`
- Create `tests/Support/LocationGovernance/LocationFixture.php`
- Create `tests/Unit/LocationGovernance/LocationSchemaContractTest.php`
- Create `tests/Feature/LocationGovernance/LocationPersistenceTest.php`

**Produces:** canonical location tree with one parent, country/schema context, type, canonical/localized names, lifecycle/status, optional centroid, validity interval, provenance, external IDs and predecessor/successor relations.

- [ ] Write RED branching-schema persistence test.

```php
public function test_section_may_branch_to_city_or_rural_district(): void
{
    $fixture = LocationFixture::iranSchema();
    $this->assertContains('city', $fixture->allowedChildTypeKeys('section'));
    $this->assertContains('rural_district', $fixture->allowedChildTypeKeys('section'));
}
```

- [ ] Verify RED.

```bash
php artisan test tests/Unit/LocationGovernance/LocationSchemaContractTest.php tests/Feature/LocationGovernance/LocationPersistenceTest.php
```

- [ ] Implement additive migration/models/factories. No legacy table is altered.
- [ ] Verify GREEN and inspect generated SQL.

```bash
php artisan test tests/Unit/LocationGovernance/LocationSchemaContractTest.php tests/Feature/LocationGovernance/LocationPersistenceTest.php
php artisan migrate --pretend
```

- [ ] Commit.

```bash
git add database/migrations/2026_09_10_000001_create_location_core_tables.php app/Models/Location*.php database/factories/Location*.php tests/Support/LocationGovernance/LocationFixture.php tests/Unit/LocationGovernance tests/Feature/LocationGovernance/LocationPersistenceTest.php
git commit -m "feat: add generic location core"
```

---

### Task 3 — C1-B: Location schema resolver, ancestry, endpoint and lifecycle

**Files**
- Create `app/Exceptions/InvalidLocationHierarchy.php`
- Create `app/Services/LocationGovernance/LocationSchemaResolver.php`
- Create `app/Services/LocationGovernance/LocationTreeResolver.php`
- Create `app/Services/LocationGovernance/LocationLifecycleService.php`
- Create `tests/Unit/LocationGovernance/LocationSchemaResolverTest.php`
- Create `tests/Unit/LocationGovernance/LocationTreeResolverTest.php`
- Create `tests/Feature/LocationGovernance/LocationLifecycleHistoryTest.php`

**Produces**
```php
LocationSchemaResolver::allowedChildTypes(Location $parent): Collection
LocationSchemaResolver::assertValidParentChild(Location $parent, LocationType $childType): void
LocationTreeResolver::ancestors(Location $location): Collection
LocationTreeResolver::residenceEndpointAllowed(Location $location): bool
LocationLifecycleService::rename(Location $location, array $localizedNames, User $actor): Location
LocationLifecycleService::supersede(Location $old, Collection $successors, string $relationType, User $actor): void
```

- [ ] Test urban branch, rural branch, village-without-neighborhood endpoint, street→complex, alley→complex, rename, merge and split.
- [ ] Verify RED then implement minimal resolvers.

```bash
php artisan test tests/Unit/LocationGovernance/LocationSchemaResolverTest.php tests/Unit/LocationGovernance/LocationTreeResolverTest.php tests/Feature/LocationGovernance/LocationLifecycleHistoryTest.php
```

- [ ] Tests must prove old IDs remain queryable after lifecycle changes.
- [ ] Commit.

```bash
git add app/Exceptions/InvalidLocationHierarchy.php app/Services/LocationGovernance tests/Unit/LocationGovernance tests/Feature/LocationGovernance/LocationLifecycleHistoryTest.php
git commit -m "feat: resolve location topology and lifecycle"
```

---

### Task 4 — C2: Independent Governance tree and capability policy

**Files**
- Create `database/migrations/2026_09_10_000002_create_governance_core_tables.php`
- Create `app/Models/GovernanceArea.php`
- Create `app/Models/GovernanceAreaLocation.php`
- Create `app/Models/GovernanceCapabilityPolicy.php`
- Create `app/Models/GovernanceAreaOverride.php`
- Create `database/factories/GovernanceAreaFactory.php`
- Create `app/Data/LocationGovernance/GovernanceCapabilities.php`
- Create `app/Services/LocationGovernance/GovernanceResolver.php`
- Create `app/Services/LocationGovernance/GovernanceCapabilityResolver.php`
- Create `tests/Unit/LocationGovernance/GovernanceResolverTest.php`
- Create `tests/Unit/LocationGovernance/GovernanceCapabilityResolverTest.php`
- Create `tests/Feature/LocationGovernance/GovernanceMappingTest.php`

**Produces**
```php
GovernanceResolver::officialAreasForResidence(Location $location): Collection
GovernanceResolver::baseOfficialAreaForResidence(Location $location): GovernanceArea
GovernanceResolver::officialAncestors(GovernanceArea $area): Collection
GovernanceCapabilityResolver::capabilities(GovernanceArea $area): GovernanceCapabilities
```

- [ ] Test that Location != GovernanceArea and one GovernanceArea may map multiple Locations.
- [ ] Test base official scope for urban neighborhood, village neighborhood and village without neighborhood.
- [ ] Test policy inheritance `EarthCoop default → country/type → area override`.
- [ ] Implement additively and run.

```bash
php artisan test tests/Unit/LocationGovernance/GovernanceResolverTest.php tests/Unit/LocationGovernance/GovernanceCapabilityResolverTest.php tests/Feature/LocationGovernance/GovernanceMappingTest.php
```

- [ ] Commit.

```bash
git add database/migrations/2026_09_10_000002_create_governance_core_tables.php app/Models/Governance*.php database/factories/GovernanceAreaFactory.php app/Data/LocationGovernance app/Services/LocationGovernance/Governance*.php tests/Unit/LocationGovernance/Governance* tests/Feature/LocationGovernance/GovernanceMappingTest.php
git commit -m "feat: add governance topology and capability policy"
```

---

### Task 5 — C3: Historical Residency and transfer policy

**Files**
- Create `database/migrations/2026_09_10_000003_create_user_location_relationships.php`
- Create `app/Models/UserLocationRelationship.php`
- Create `database/factories/UserLocationRelationshipFactory.php`
- Create `app/Exceptions/ResidenceTransferLimitExceeded.php`
- Create `app/Services/LocationGovernance/ResidenceTransferPolicy.php`
- Create `app/Services/LocationGovernance/ResidenceService.php`
- Modify `app/Models/User.php`
- Create `tests/Unit/LocationGovernance/ResidenceTransferPolicyTest.php`
- Create `tests/Feature/LocationGovernance/PrimaryResidenceHistoryTest.php`
- Create `tests/Feature/LocationGovernance/NonResidenceRelationshipVotingTest.php`

**Produces**
```php
ResidenceService::setInitialPrimaryResidence(User $user, Location $location, array $evidence): UserLocationRelationship
ResidenceService::transferPrimaryResidence(User $user, Location $to, User $actor, string $reason, bool $override = false): UserLocationRelationship
ResidenceTransferPolicy::remainingExplicitTransfers(User $user, CarbonInterface $at): int
```

- [ ] Write RED test for default maximum 2 explicit transfers per rolling 12 months and audited override.
- [ ] Write work/study test proving only Primary Residence enters official Governance resolution.
- [ ] Implement without deleting `addresses`.

```bash
php artisan test tests/Unit/LocationGovernance/ResidenceTransferPolicyTest.php tests/Feature/LocationGovernance/PrimaryResidenceHistoryTest.php tests/Feature/LocationGovernance/NonResidenceRelationshipVotingTest.php
```

- [ ] Commit.

```bash
git add database/migrations/2026_09_10_000003_create_user_location_relationships.php app/Models/User.php app/Models/UserLocationRelationship.php database/factories/UserLocationRelationshipFactory.php app/Exceptions/ResidenceTransferLimitExceeded.php app/Services/LocationGovernance/Residence* tests/Unit/LocationGovernance/ResidenceTransferPolicyTest.php tests/Feature/LocationGovernance/PrimaryResidenceHistoryTest.php tests/Feature/LocationGovernance/NonResidenceRelationshipVotingTest.php
git commit -m "feat: add historical residence relationships"
```

---

### Task 6 — C4: Versioned Iran reference importer

**Files**
- Create `database/migrations/2026_09_10_000004_create_location_import_audit_tables.php`
- Create `app/Data/LocationGovernance/ReferenceDataset.php`
- Create `app/Data/LocationGovernance/ReferenceImportResult.php`
- Create `app/Services/LocationGovernance/Import/ReferenceGeographyNormalizer.php`
- Create `app/Services/LocationGovernance/Import/ReferenceGeographyValidator.php`
- Create `app/Services/LocationGovernance/Import/ReferenceGeographyImporter.php`
- Create `app/Console/Commands/LocationReferenceImportCommand.php`
- Create `database/reference/ir/v1/schema.json`
- Create `database/reference/ir/v1/locations.jsonl`
- Create `tests/Unit/LocationGovernance/ReferenceGeographyNormalizerTest.php`
- Create `tests/Feature/LocationGovernance/ReferenceGeographyImportTest.php`
- Create `tests/Feature/LocationGovernance/ReferenceGeographyImportIdempotencyTest.php`

**Command contract**
```bash
php artisan location:reference-import IR --version=v1 --dry-run
php artisan location:reference-import IR --version=v1 --apply
```

- [ ] Test dry-run performs no writes and reports create/update/deactivate/conflict counts.
- [ ] Test same source/version is idempotent.
- [ ] Dataset must permanently include Sari urban path, Chahardangeh rural-district→village path, and a village without neighborhood.
- [ ] Legacy numeric IDs may be provenance metadata only.

```bash
php artisan test tests/Unit/LocationGovernance/ReferenceGeographyNormalizerTest.php tests/Feature/LocationGovernance/ReferenceGeographyImportTest.php tests/Feature/LocationGovernance/ReferenceGeographyImportIdempotencyTest.php
php artisan location:reference-import IR --version=v1 --dry-run
```

- [ ] Commit.

```bash
git add database/migrations/2026_09_10_000004_create_location_import_audit_tables.php app/Data/LocationGovernance/Reference* app/Services/LocationGovernance/Import app/Console/Commands/LocationReferenceImportCommand.php database/reference/ir/v1 tests/Unit/LocationGovernance/ReferenceGeographyNormalizerTest.php tests/Feature/LocationGovernance/ReferenceGeographyImport*.php
git commit -m "feat: add versioned Iran geography importer"
```

---

### Task 7 — C5-A: Schema-driven location API + rollback flags

**Files**
- Create `config/location-governance.php`
- Create `app/Http/Requests/LocationChildrenRequest.php`
- Create `app/Http/Resources/LocationOptionResource.php`
- Create `app/Http/Controllers/Location/LocationSelectionController.php`
- Modify `routes/web.php`
- Create `tests/Feature/LocationGovernance/LocationSelectionApiTest.php`

**Flags default false**
```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED
LOCATION_GOVERNANCE_REGISTRATION_ENABLED
LOCATION_GOVERNANCE_GROUPS_ENABLED
LOCATION_GOVERNANCE_ELECTIONS_ENABLED
```

**API contract**
```text
GET /location/options/root?country=IR
GET /location/options/{location}/children
→ id, type_key, label, is_residence_endpoint, has_children, status
```

- [ ] Test branching, localized labels, residence endpoint, and pending visibility.
- [ ] Implement only through Location resolvers.

```bash
php artisan test tests/Feature/LocationGovernance/LocationSelectionApiTest.php
```

- [ ] Commit.

```bash
git add config/location-governance.php app/Http/Requests/LocationChildrenRequest.php app/Http/Resources/LocationOptionResource.php app/Http/Controllers/Location/LocationSelectionController.php routes/web.php tests/Feature/LocationGovernance/LocationSelectionApiTest.php
git commit -m "feat: expose schema driven location selection"
```

---

### Task 8 — C5-B: Registration/Profile canonical Primary Residence behind flag

**Files**
- Create `tests/Support/LocationGovernance/RegistrationFixture.php`
- Modify `app/Http/Controllers/Auth/Register/StartController.php`
- Modify `app/Http/Controllers/Profile/ProfileController.php`
- Modify `app/Services/ProfileCompletionService.php`
- Modify exact registration/profile Blade paths confirmed in C0 inventory
- Create `resources/js/location-selector.js`
- Modify `resources/js/app.js`
- Create `tests/Feature/LocationGovernance/RegistrationPrimaryResidenceTest.php`
- Create `tests/Feature/LocationGovernance/ProfileResidenceTransferTest.php`
- Extend `tests/Feature/RegistrationTermsGateTest.php`
- Extend `tests/Feature/Invitation/InvitationLaunchContractTest.php`

**Consumes:** Location Selection API + `ResidenceService`.

- [ ] Test urban Sari registration, rural village branch, village-without-neighborhood, invalid endpoint rejection, and feature-flag rollback.
- [ ] Server validates endpoint with `LocationTreeResolver`; client IDs are never trusted alone.
- [ ] Profile residence change uses `ResidenceService`, not direct FK replacement.

```bash
php artisan test tests/Feature/LocationGovernance/RegistrationPrimaryResidenceTest.php tests/Feature/LocationGovernance/ProfileResidenceTransferTest.php tests/Feature/RegistrationTermsGateTest.php tests/Feature/Invitation/InvitationLaunchContractTest.php
npm test --if-present
```

- [ ] Commit.

```bash
git add tests/Support/LocationGovernance/RegistrationFixture.php app/Http/Controllers/Auth/Register/StartController.php app/Http/Controllers/Profile/ProfileController.php app/Services/ProfileCompletionService.php resources/views resources/js/location-selector.js resources/js/app.js tests/Feature/LocationGovernance tests/Feature/RegistrationTermsGateTest.php tests/Feature/Invitation/InvitationLaunchContractTest.php
git commit -m "feat: add canonical residence registration flow"
```

---

### Task 9 — C6-A: Explicit dimensions and Group materialization policy

**Files**
- Create `database/migrations/2026_09_10_000005_create_membership_dimension_tables.php`
- Create `app/Enums/Membership/GroupCreationMode.php`
- Create `app/Models/MembershipDimension.php`
- Create `app/Models/GroupCreationPolicy.php`
- Create `app/Contracts/Membership/DimensionResolver.php`
- Create `app/Services/Membership/PublicDimensionResolver.php`
- Create `app/Services/Membership/ProfessionDimensionResolver.php`
- Create `app/Services/Membership/SpecialtyDimensionResolver.php`
- Create `app/Services/Membership/AgeDimensionResolver.php`
- Create `app/Services/Membership/GenderDimensionResolver.php`
- Create `tests/Unit/Membership/DimensionResolverTest.php`
- Create `tests/Unit/Membership/GroupCreationPolicyTest.php`

**Interface**
```php
interface DimensionResolver {
    public function dimensionKey(): string;
    public function valuesFor(User $user): Collection;
}
```

- [ ] Test exactly the five initial dimensions and all four creation modes.
- [ ] Geography must not appear inside dimension resolvers.

```bash
php artisan test tests/Unit/Membership
```

- [ ] Commit.

```bash
git add database/migrations/2026_09_10_000005_create_membership_dimension_tables.php app/Enums/Membership app/Models/MembershipDimension.php app/Models/GroupCreationPolicy.php app/Contracts/Membership app/Services/Membership tests/Unit/Membership
git commit -m "feat: add membership dimensions and creation policy"
```

---

### Task 10 — C6-B: Deterministic Membership Engine

**Files**
- Create `tests/Support/LocationGovernance/MembershipFixture.php`
- Create `app/Data/Membership/MembershipIntent.php`
- Create `app/Data/Membership/MembershipResolution.php`
- Create `app/Services/Membership/MembershipAuditService.php`
- Create `app/Services/Membership/MembershipEngine.php`
- Create `database/migrations/2026_09_10_000006_create_membership_resolution_audits.php`
- Create `tests/Unit/Membership/MembershipEngineTest.php`
- Create `tests/Feature/LocationGovernance/MultidimensionalMembershipTest.php`
- Create `tests/Feature/LocationGovernance/GroupExplosionPreventionTest.php`

**Produces**
```php
MembershipEngine::resolve(User $user, bool $materialize = false): MembershipResolution
```

`MembershipResolution` contains official governance areas, community areas, materializable intents, suppressed intents with reason, and deterministic audit fingerprint.

- [ ] Same input state + policy version must yield same ordered intents/fingerprint.
- [ ] Test public/profession/specialty/age/gender resolution.
- [ ] Test `threshold`, `on_demand`, `disabled` suppress physical group creation as required.

```bash
php artisan test tests/Unit/Membership/MembershipEngineTest.php tests/Feature/LocationGovernance/MultidimensionalMembershipTest.php tests/Feature/LocationGovernance/GroupExplosionPreventionTest.php
```

- [ ] Commit.

```bash
git add tests/Support/LocationGovernance/MembershipFixture.php app/Data/Membership app/Services/Membership database/migrations/2026_09_10_000006_create_membership_resolution_audits.php tests/Unit/Membership/MembershipEngineTest.php tests/Feature/LocationGovernance/MultidimensionalMembershipTest.php tests/Feature/LocationGovernance/GroupExplosionPreventionTest.php
git commit -m "feat: add deterministic membership engine"
```

---

### Task 11 — C7: Canonical Group scope cutover behind flag

**Files**
- Create `database/migrations/2026_09_10_000007_add_canonical_scope_to_groups.php`
- Modify `app/Models/Group.php`
- Create `app/Services/Groups/GovernanceScopedGroupService.php`
- Modify `app/Services/GroupService.php`
- Create `tests/Feature/LocationGovernance/GovernanceScopedGroupTest.php`
- Create `tests/Feature/LocationGovernance/GroupCutoverFlagTest.php`
- Extend existing Group Chat/Admin tests identified in C0 inventory.

**Target identity:** `governance_area_id + dimension_key + dimension_value identity`; legacy `address_id + location_level` is not authoritative in canonical mode.

**Produces**
```php
GovernanceScopedGroupService::materialize(MembershipIntent $intent): ?Group
```

- [ ] Test idempotent materialization and area isolation.
- [ ] Add nullable canonical columns/indexes only; do not drop legacy columns.
- [ ] `GroupService` delegates only when flag is enabled.
- [ ] Replace hard-coded `alley/street/neighborhood` role logic on canonical path with capability/policy resolution.

```bash
php artisan test tests/Feature/LocationGovernance/GovernanceScopedGroupTest.php tests/Feature/LocationGovernance/GroupCutoverFlagTest.php tests/Feature/GroupChat tests/Feature/Admin
```

- [ ] Commit.

```bash
git add database/migrations/2026_09_10_000007_add_canonical_scope_to_groups.php app/Models/Group.php app/Services/Groups app/Services/GroupService.php tests/Feature/LocationGovernance tests/Feature/GroupChat tests/Feature/Admin
git commit -m "feat: scope groups by governance area"
```

---

### Task 12 — C8: Formal Elections use Official Governance topology

**Files**
- Create `app/Contracts/Governance/OfficialGovernanceTopology.php`
- Create `app/Services/Elections/GovernanceElectionTopology.php`
- Create `database/migrations/2026_09_10_000008_add_governance_scope_to_elections.php`
- Modify exact election topology/policy services listed by C0 inventory
- Create `tests/Feature/LocationGovernance/OfficialElectionGovernanceTopologyTest.php`
- Create `tests/Feature/LocationGovernance/CommunityElectionBoundaryTest.php`
- Extend `tests/Unit/Elections/ElectionPolicyResolverTest.php` and all affected `tests/Feature/Elections/*`.

**Interface**
```php
interface OfficialGovernanceTopology {
    public function parentOf(GovernanceArea $area): ?GovernanceArea;
    public function childrenOf(GovernanceArea $area): Collection;
}
```

- [ ] Test formal traversal reads GovernanceArea parent/children, not legacy geography.
- [ ] Test Community internal officeholders do not become formal managers/inspectors without explicit policy.
- [ ] Add canonical election scope additively and flag resolver.

```bash
php artisan test tests/Feature/LocationGovernance/OfficialElectionGovernanceTopologyTest.php tests/Feature/LocationGovernance/CommunityElectionBoundaryTest.php tests/Unit/Elections tests/Feature/Elections
```

- [ ] Commit.

```bash
git add app/Contracts/Governance app/Services/Elections database/migrations/2026_09_10_000008_add_governance_scope_to_elections.php tests/Feature/LocationGovernance tests/Unit/Elections tests/Feature/Elections
git commit -m "feat: resolve systemic elections through governance topology"
```

---

### Task 13 — C9: Secretariat, Projects, Polls and shared consumers use Governance scope

**Files**
- Modify exact Secretariat, Project and Poll model/service/controller files recorded in `LEGACY_SPATIAL_INVENTORY.md`.
- Create `app/Contracts/Governance/GovernanceScopedResource.php` only if at least two existing subsystems need the same contract.
- Create `tests/Feature/LocationGovernance/SecretariatGovernanceScopeTest.php`
- Create `tests/Feature/LocationGovernance/ProjectGovernanceScopeTest.php`
- Create `tests/Feature/LocationGovernance/PollGovernanceScopeTest.php`
- Extend the exact existing subsystem regression test paths recorded at C0.

**Contract:** each spatially scoped subsystem stores/resolves `governance_area_id` or resolves via its canonical Group; none interprets raw fixed geography on canonical path.

- [ ] Write one failing canonical-scope contract per subsystem.
- [ ] Add nullable canonical FKs/indexes where needed in one additive migration named `database/migrations/2026_09_10_000009_add_governance_scope_to_spatial_consumers.php`.
- [ ] Resolve capability checks through `GovernanceCapabilityResolver`.
- [ ] Run the three new contracts, the exact C0-listed subsystem suites, then full PHPUnit. Do not suppress failures.

```bash
php artisan test tests/Feature/LocationGovernance/SecretariatGovernanceScopeTest.php tests/Feature/LocationGovernance/ProjectGovernanceScopeTest.php tests/Feature/LocationGovernance/PollGovernanceScopeTest.php
php artisan test
```

- [ ] Commit.

```bash
git add app database/migrations/2026_09_10_000009_add_governance_scope_to_spatial_consumers.php tests
git commit -m "feat: unify governance scope consumers"
```

---

### Task 14 — C10-A: Community Areas and micro-location policy

**Files**
- Create `database/migrations/2026_09_10_000010_add_community_policy_to_governance_areas.php` if Task 4 schema does not already contain required classification/policy columns.
- Create `app/Services/LocationGovernance/CommunityCreationPolicy.php`
- Create `app/Services/LocationGovernance/CommunityAreaService.php`
- Create `tests/Feature/LocationGovernance/CommunityAreaCreationTest.php`
- Create `tests/Feature/LocationGovernance/MicroLocationWithoutCommunityTest.php`

**Produces**
```php
CommunityCreationPolicy::mayCreateFor(Location $location, User $actor): bool
CommunityAreaService::createFor(Location $location, User $actor): GovernanceArea
```

- [ ] Test verified complex/building may exist without Community Area.
- [ ] Test on-demand Community creation and inherited capabilities.
- [ ] Test Community stays outside formal systemic topology by default.

```bash
php artisan test tests/Feature/LocationGovernance/CommunityAreaCreationTest.php tests/Feature/LocationGovernance/MicroLocationWithoutCommunityTest.php
```

- [ ] Commit.

```bash
git add database/migrations/2026_09_10_000010_add_community_policy_to_governance_areas.php app/Services/LocationGovernance/Community* tests/Feature/LocationGovernance
git commit -m "feat: add policy driven community areas"
```

---

### Task 15 — C10-B: Crowdsourced locations, dedupe, distinct-user verification

**Files**
- Create `database/migrations/2026_09_10_000011_create_location_proposal_tables.php`
- Create `app/Enums/LocationGovernance/LocationProposalStatus.php`
- Create `app/Models/LocationProposal.php`
- Create `app/Models/LocationProposalEvidence.php`
- Create `app/Services/LocationGovernance/LocationDuplicateDetector.php`
- Create `app/Services/LocationGovernance/LocationProposalService.php`
- Create `app/Http/Controllers/Location/LocationProposalController.php`
- Create `tests/Unit/LocationGovernance/LocationDuplicateDetectorTest.php`
- Create `tests/Feature/LocationGovernance/LocationProposalWorkflowTest.php`
- Create `tests/Feature/LocationGovernance/DistinctVerifierThresholdTest.php`

**Statuses:** `pending`, `ready_for_review`, `approved`, `rejected`, `merged`, `needs_evidence`.

**Produces**
```php
LocationProposalService::support(LocationProposal $proposal, User $user, array $evidence): void
```

- [ ] Test likely duplicate is surfaced/reused before new proposal creation.
- [ ] Test same user cannot inflate verification count.
- [ ] Default threshold 10 distinct users transitions only to `ready_for_review`.
- [ ] Test audited approve/reject/merge/request-more-evidence transitions.

```bash
php artisan test tests/Unit/LocationGovernance/LocationDuplicateDetectorTest.php tests/Feature/LocationGovernance/LocationProposalWorkflowTest.php tests/Feature/LocationGovernance/DistinctVerifierThresholdTest.php
```

- [ ] Commit.

```bash
git add database/migrations/2026_09_10_000011_create_location_proposal_tables.php app/Enums/LocationGovernance app/Models/LocationProposal*.php app/Services/LocationGovernance/LocationDuplicateDetector.php app/Services/LocationGovernance/LocationProposalService.php app/Http/Controllers/Location/LocationProposalController.php tests/Unit/LocationGovernance/LocationDuplicateDetectorTest.php tests/Feature/LocationGovernance
git commit -m "feat: add crowdsourced location review workflow"
```

---

### Task 16 — C11-A: Consent-based geolocation + provider abstraction

**Files**
- Create `app/Contracts/Geocoding/ReverseGeocoder.php`
- Create `app/Data/Geocoding/ReverseGeocodeResult.php`
- Create `app/Data/Geocoding/GeolocationMatch.php`
- Create `app/Services/LocationGovernance/GeolocationMatchService.php`
- Create `app/Http/Controllers/Location/GeolocationController.php`
- Create `resources/js/location-geolocation.js`
- Modify `resources/js/location-selector.js`
- Create `tests/Support/Geocoding/FakeReverseGeocoder.php`
- Create `tests/Unit/LocationGovernance/GeolocationMatchServiceTest.php`
- Create `tests/Feature/LocationGovernance/GeolocationAssistTest.php`

**Interface**
```php
interface ReverseGeocoder {
    public function reverse(float $latitude, float $longitude, string $locale): ReverseGeocodeResult;
}
```

- [ ] Test matching GPS, conflicting GPS/manual selection, and provider failure.
- [ ] Test precise raw coordinates are not persisted by default.
- [ ] Manual selection remains available in every case.

```bash
php artisan test tests/Unit/LocationGovernance/GeolocationMatchServiceTest.php tests/Feature/LocationGovernance/GeolocationAssistTest.php
```

- [ ] Commit.

```bash
git add app/Contracts/Geocoding app/Data/Geocoding app/Services/LocationGovernance/GeolocationMatchService.php app/Http/Controllers/Location/GeolocationController.php resources/js/location-geolocation.js resources/js/location-selector.js tests/Support/Geocoding tests/Unit/LocationGovernance/GeolocationMatchServiceTest.php tests/Feature/LocationGovernance/GeolocationAssistTest.php
git commit -m "feat: add consent based location detection"
```

---

### Task 17 — C11-B: Admin control center + approval-gated Najm Hoda review

**Files**
- Create `app/Http/Controllers/Admin/LocationGovernanceController.php`
- Create `resources/views/admin/location-governance/index.blade.php`
- Modify `resources/views/admin/dashboard.blade.php`
- Create `app/Services/NajmHoda/LocationGovernanceReviewService.php`
- Modify `app/Services/NajmHoda/Runtime/NajmHodaCapabilityRegistry.php`
- Modify `app/Services/NajmHoda/Runtime/NajmHodaAutonomySafetyGate.php`
- Modify `config/najm-hoda.php`
- Extend `tests/Feature/NajmHoda/CapabilityRegistryTest.php`
- Create `tests/Feature/Admin/LocationGovernanceControlCenterTest.php`
- Create `tests/Feature/NajmHoda/LocationGovernanceReviewServiceTest.php`

**Rule:** Najm Hoda may surface, summarize, flag duplicate/anomaly, and recommend approve/reject/merge/review; sensitive write actions remain human approval-gated.

- [ ] Write admin authorization tests.
- [ ] Write Najm Hoda capability/safety test proving no autonomous sensitive approval path.
- [ ] Implement coherent workflows for imports, pending proposals, duplicates, evidence, lifecycle, governance mappings and policies.

```bash
php artisan test tests/Feature/Admin/LocationGovernanceControlCenterTest.php tests/Feature/NajmHoda/LocationGovernanceReviewServiceTest.php tests/Feature/NajmHoda/CapabilityRegistryTest.php tests/Feature/NajmHoda
```

- [ ] Commit.

```bash
git add app/Http/Controllers/Admin/LocationGovernanceController.php resources/views/admin/location-governance resources/views/admin/dashboard.blade.php app/Services/NajmHoda/LocationGovernanceReviewService.php app/Services/NajmHoda/Runtime/NajmHodaCapabilityRegistry.php app/Services/NajmHoda/Runtime/NajmHodaAutonomySafetyGate.php config/najm-hoda.php tests/Feature/Admin/LocationGovernanceControlCenterTest.php tests/Feature/NajmHoda
git commit -m "feat: add location governance review center"
```

---

### Task 18 — C11-C: Canonical runtime no longer requires legacy Address

**Files**
- Modify `app/Http/Middleware/Authenticate.php`
- Modify `app/Http/Controllers/HomeController.php`
- Modify `app/Services/ProfileCompletionService.php`
- Modify `app/Http/Controllers/Profile/ProfileController.php`
- Create `tests/Feature/LocationGovernance/CanonicalRuntimeAddressIndependenceTest.php`
- Create `tests/Feature/LocationGovernance/HomeCanonicalResidenceTest.php`

- [ ] Test canonical-mode user with Primary Residence and no Address row can authenticate, render Home, and complete profile checks.
- [ ] New path derives ancestry from `primaryResidence.location` + `LocationTreeResolver`.
- [ ] Legacy path remains flag-based rollback only.

```bash
php artisan test tests/Feature/LocationGovernance/CanonicalRuntimeAddressIndependenceTest.php tests/Feature/LocationGovernance/HomeCanonicalResidenceTest.php tests/Feature/LocationGovernance/LegacySpatialBaselineContractTest.php
```

- [ ] Commit.

```bash
git add app/Http/Middleware/Authenticate.php app/Http/Controllers/HomeController.php app/Services/ProfileCompletionService.php app/Http/Controllers/Profile/ProfileController.php tests/Feature/LocationGovernance
git commit -m "refactor: remove canonical runtime address dependency"
```

---

### Task 19 — C12-A: Fresh bootstrap + permanent acceptance scenarios

**Files**
- Create `database/seeders/LocationGovernanceBootstrapSeeder.php` for small schemas/types/policies only.
- Create `tests/Feature/LocationGovernance/FreshBootstrapLocationGovernanceTest.php`
- Create `tests/Feature/LocationGovernance/GlobalArchitectureScenarioTest.php`
- Create `docs/location-governance/BOOTSTRAP_RUNBOOK.md`
- Create `docs/location-governance/UAT_SCENARIOS.md`

**Permanent scenarios:** urban Sari; Chahardangeh rural branch; village without neighborhood; urban neighborhood/street; alley/complex/building Community; duplicate location proposal; 10-distinct-user review threshold; GPS match/conflict; residence transfer quota; work/study no vote; all five dimensions; threshold/on-demand/disabled creation; formal election topology; Community internal election; rename/merge/split history.

- [ ] Rehearse only against disposable testing DB.

```bash
php artisan migrate:fresh --env=testing
php artisan db:seed --class=LocationGovernanceBootstrapSeeder --env=testing
php artisan location:reference-import IR --version=v1 --apply --env=testing
php artisan test tests/Feature/LocationGovernance/FreshBootstrapLocationGovernanceTest.php tests/Feature/LocationGovernance/GlobalArchitectureScenarioTest.php
```

**Safety:** this is not authorization for `migrate:fresh` on production.

- [ ] Commit.

```bash
git add database/seeders/LocationGovernanceBootstrapSeeder.php tests/Feature/LocationGovernance docs/location-governance/BOOTSTRAP_RUNBOOK.md docs/location-governance/UAT_SCENARIOS.md
git commit -m "test: rehearse global location governance bootstrap"
```

---

### Task 20 — C12-B: Full validation + UAT candidate

**Files**
- Modify `.github/workflows/integration-full-validation.yml` only to add a focused Location/Governance gate; never remove mature gates.
- Create `docs/superpowers/progress/2026-09-10-global-location-governance-progress.md`
- Create `docs/location-governance/CUTOVER_READINESS.md`

- [ ] Add focused gate:

```bash
php artisan test tests/Unit/LocationGovernance tests/Unit/Membership tests/Feature/LocationGovernance
```

- [ ] Run full local validation.

```bash
php artisan test
npm test --if-present
npm run build
```

- [ ] Run exact existing Governance, Elections, Group Chat/Admin, Secretariat, Najm Hoda, Najm Bahar, Stock, JavaScript, Responsive and Full Project gates from `.github/workflows/integration-full-validation.yml`.
- [ ] Push candidate; record immutable SHA and all GitHub Actions run IDs.
- [ ] Execute `UAT_SCENARIOS.md` with test users only; any defect gets a regression test before fix.
- [ ] Commit evidence.

```bash
git add .github/workflows/integration-full-validation.yml docs/superpowers/progress/2026-09-10-global-location-governance-progress.md docs/location-governance/CUTOVER_READINESS.md
git commit -m "docs: record location governance validation readiness"
```

---

### Task 21 — C13: Production cutover package — HARD STOP

**Files**
- Create `app/Console/Commands/LocationGovernanceReadinessCommand.php`
- Create `tests/Feature/LocationGovernance/ProductionReadinessCommandTest.php`
- Create `docs/location-governance/PRODUCTION_CUTOVER_RUNBOOK.md`
- Create `docs/location-governance/PRODUCTION_ROLLBACK_RUNBOOK.md`

**Read-only command**
```bash
php artisan location-governance:readiness
```

It exits non-zero if required migrations, reference dataset/version, required mappings, known flag state, unresolved fatal import/proposal conflicts, validation SHA, or UAT evidence are missing.

- [ ] Write readiness tests.
- [ ] Runbook order: maintenance window → DB backup → verified restore rehearsal → current SHA capture → dataset version capture → readiness command → staged flag activation `runtime → registration/profile → groups → elections → remaining consumers` → smoke tests after each step → rollback by flags/application SHA.
- [ ] Initial cutover explicitly does **not** drop legacy tables.
- [ ] Commit preparation.

```bash
php artisan test tests/Feature/LocationGovernance/ProductionReadinessCommandTest.php
git add app/Console/Commands/LocationGovernanceReadinessCommand.php tests/Feature/LocationGovernance/ProductionReadinessCommandTest.php docs/location-governance/PRODUCTION_CUTOVER_RUNBOOK.md docs/location-governance/PRODUCTION_ROLLBACK_RUNBOOK.md
git commit -m "ops: prepare location governance cutover"
```

- [ ] **STOP.** Present candidate SHA, backup/restore evidence, UAT, CI and runbooks. Obtain explicit user approval for the exact production action. Approval of this plan is not production cutover approval.

---

### Task 22 — C14: Legacy retirement — SECOND HARD STOP

**Files**
- Create `tests/Architecture/NoLegacyGeographyRuntimeDependencyTest.php`
- Create `docs/location-governance/LEGACY_RETIREMENT_AUDIT.md`
- A destructive cleanup migration is deliberately **not named or authored in this plan** because its creation itself is gated by the post-cutover audit and explicit approval.

- [ ] Audit repository/runtime for direct canonical dependencies on legacy `Continent`, `Country`, `Province`, `County`, `Section`, `City`, `Rural`, `Village`, `Region`, `Neighborhood`, `Street`, `Alley`, fixed `Address` geography FKs, and Group `location_level/address_id` authority.
- [ ] Architecture test must fail if any canonical runtime path still uses those dependencies.
- [ ] Run full validation and record post-cutover observation period evidence.
- [ ] **STOP.** Ask explicit permission to author and test destructive retirement migration.
- [ ] After approval only, create the cleanup migration, first run it against a restored production-backup clone, then run full tests/build and produce a separate retirement PR/commit series.

```bash
php artisan test
npm test --if-present
npm run build
```

---

## Acceptance coverage matrix

| Spec acceptance criterion | Task/test |
|---|---|
| country-specific branching registration | T7–T8 / `RegistrationPrimaryResidenceTest` |
| urban+rural same contract | T3–T5,T10 / `GlobalArchitectureScenarioTest` |
| village without neighborhood base scope | T3–T4 / `GovernanceResolverTest` |
| all five group dimensions use governance scope | T9–T11 / `MultidimensionalMembershipTest` |
| prevent empty-group explosion | T9–T10 / `GroupExplosionPreventionTest` |
| micro-location can exist without Community | T14 / `MicroLocationWithoutCommunityTest` |
| Primary Residence sole official voting basis | T5 / `NonResidenceRelationshipVotingTest` |
| formal election no fixed geography dependency | T12 / `OfficialElectionGovernanceTopologyTest` |
| Community election distinct | T12 / `CommunityElectionBoundaryTest` |
| rename/merge/split historical integrity | T3 / `LocationLifecycleHistoryTest` |
| versioned reference importer | T6 / importer tests |
| dedupe + distinct-user review threshold | T15 / proposal tests |
| GPS assistive only | T16 / `GeolocationAssistTest` |
| mature capabilities stay green | T1,T20 / existing gates |
| clean DB bootstrap + urban/rural UAT | T19–T20 |

## Execution discipline

1. Start from the final planning checkpoint, not `main`.
2. Use isolated worktree/branch.
3. Every task follows RED → minimal implementation → GREEN → focused regression → commit.
4. Do not combine later checkpoint work with a failing/unreviewed checkpoint.
5. Keep schema additive through C13.
6. Feature flags are rollback scaffolding, not a permanent dual-runtime architecture.
7. A mature regression is blocking unless the approved Spec explicitly changes that contract.
8. Production is untouched through C12.
9. C13 and C14 each require a fresh explicit user approval.

## Definition of Done

A clean database can bootstrap the canonical architecture and Iran reference data; both urban and rural residents resolve through Location → Governance → Membership; Primary Residence alone drives official geographic voting membership; public/profession/specialty/age/gender groups share Governance Area scope without empty-group explosion; formal elections use Governance topology while Community elections remain separate; reference imports, crowdsourcing and lifecycle changes are auditable; geolocation remains assistive; mature EarthCoop validation stays green; representative UAT passes; production cutover is explicitly approved and reversible; and legacy fixed geography is retired only after a second approved audit proves it is no longer authoritative at runtime.
