# EarthCoop Global Location & Governance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace EarthCoop's Iran-specific fixed geography runtime with a global generic Location Tree, independent Governance Tree, historical Primary Residence model, and policy-driven multidimensional Membership Engine while preserving mature EarthCoop capabilities and keeping production data safe until an explicitly approved cutover.

**Architecture:** Build the target model additively and behind explicit runtime boundaries. First freeze current behavior with regression contracts; then implement Location → Governance → Residency → Membership as independently testable domains; only after those contracts are green cut Groups, Elections, Secretariat, Projects, registration/profile, community workflows, geolocation, and Najm Hoda over to canonical scopes. Legacy geography remains rollback-only until fresh-bootstrap, UAT, repository scans, backups, and explicit production cutover approval are complete.

**Tech Stack:** PHP ^8.2, Laravel ^12.0, Eloquent, MySQL-compatible production schema, PHPUnit ^11.5, Blade/Vite/JavaScript, Laravel commands/jobs/queues, existing EarthCoop CI workflows.

**Spec:** `docs/superpowers/specs/2026-09-10-global-location-governance-architecture-design.md`

## Global Constraints

- Baseline is `main@767a276b962a62234f70878071d007930903577a`; planning branch starts at spec commit `2217d2e95c36d7f8760a95f12c23ebc4d6e8926c`.
- Never modify `main` directly. Execution must begin on an isolated implementation branch/worktree created from the approved planning checkpoint.
- No destructive production migration, `migrate:fresh`, truncation, legacy-table drop, or production bootstrap without a separate checkpoint, verified backup, dry-run evidence, and the user's explicit approval.
- Location, Governance Area, Group, and Residency are distinct domain concepts. Do not collapse them into one model.
- Runtime code must not require globally fixed `province_id/county_id/city_id/...` geography columns.
- Official systemic election topology must resolve through Official Governance Areas, not government geography tables.
- Primary Residence is the sole basis for official geographic governance/voting membership.
- Work/study/other relationships must never create parallel official geographic voting rights.
- Location names are display data, not identity keys; external identifiers and imports are source/version aware.
- Used locations are historical records; rename/merge/split/inactivation must preserve referential history.
- Reference geography import is versioned, idempotent, auditable, dry-run/diff capable, and is not a giant production Seeder.
- User-created location verification counts distinct users and reaching threshold means ready-for-review, not auto-approval.
- GPS/reverse geocoding is optional assistive evidence and never authoritative proof of residence.
- Group creation policy must support `automatic`, `threshold`, `on_demand`, and `disabled` to prevent empty-group explosion.
- Public, profession, specialty, age, and gender groups use Governance Areas as their spatial scope.
- Community internal elections remain distinct from formal EarthCoop systemic elections unless explicit policy opts a Community Area into formal topology.
- Existing Governance, Elections, Groups/Group Chat/Admin, Secretariat, Najm Hoda, Najm Bahar, Stock, JavaScript, Responsive, and Full Project PHPUnit gates remain release gates.
- Prefer additive rollout and feature-flag rollback before final cutover; destructive rollback is not the default strategy.

---

## Repository reality to preserve

The current runtime has mature but geography-coupled paths that must be treated as regression surfaces:

- `app/Models/Address.php` stores fixed geography FKs.
- `app/Services/GroupService.php` derives location levels from fixed Address fields and creates groups from `location_level + address_id`.
- `app/Models/Group.php` exposes the legacy Address relationship.
- `app/Http/Controllers/Auth/Register/StartController.php` owns the public registration entry flow.
- `app/Http/Controllers/Profile/ProfileController.php`, `app/Services/ProfileCompletionService.php`, `app/Http/Controllers/HomeController.php`, and `app/Http/Middleware/Authenticate.php` consume legacy Address semantics.
- `app/Http/Controllers/Admin/AddressController.php` and `resources/views/admin/address/index.blade.php` expose legacy geography administration.
- Election and group tests already provide mature regression surfaces; they must stay green while topology changes underneath them.

The implementation order below is intentionally designed so none of those consumers is rewritten before its replacement contract exists.

---

## Checkpoint map

| Checkpoint | Deliverable | Production risk |
|---|---|---|
| C0 | Baseline inventory + regression contracts | none |
| C1 | Location Core schema + tree resolver | additive only |
| C2 | Governance + capability policy | additive only |
| C3 | Residency + transfer history/quota | additive only |
| C4 | Iran reference importer + representative dataset | additive/dev-UAT only |
| C5 | Schema-driven location API + registration/profile behind flag | reversible flag |
| C6 | Dimensions + deterministic Membership Engine | additive only |
| C7 | Groups canonical governance scope cutover | reversible flag |
| C8 | Elections canonical topology cutover | reversible flag |
| C9 | Secretariat/Projects/Polls/other scope consumers | reversible flag |
| C10 | Community + crowdsourcing + verification | additive only |
| C11 | Geolocation + admin review + Najm Hoda assistance | additive/approval-gated |
| C12 | Fresh bootstrap + full validation + UAT | non-production |
| C13 | Production cutover package | **explicit approval required** |
| C14 | Legacy runtime retirement | **post-cutover explicit approval required** |

Each checkpoint is a review boundary. Do not stack later checkpoint work onto an unreviewed failing checkpoint.

---

### Task 1: Freeze the current spatial behavior with regression contracts (C0)

**Files:**
- Create: `tests/Feature/LocationGovernance/LegacySpatialBaselineContractTest.php`
- Create: `tests/Feature/LocationGovernance/LegacyRegistrationLocationContractTest.php`
- Create: `tests/Feature/LocationGovernance/LegacyGroupMembershipContractTest.php`
- Create: `tests/Feature/LocationGovernance/LegacyElectionScopeContractTest.php`
- Create: `docs/location-governance/LEGACY_SPATIAL_INVENTORY.md`
- Inspect only: `app/Models/Address.php`, `app/Services/GroupService.php`, `app/Models/Group.php`, `app/Http/Controllers/Auth/Register/StartController.php`, `app/Http/Controllers/Profile/ProfileController.php`, election/secretariat/project scope consumers.

**Interfaces:**
- Consumes: current production-compatible runtime only.
- Produces: executable contracts that define what mature user-visible behavior must remain available through the migration.

- [ ] **Step 1: Write failing/characterization tests for representative urban and rural legacy paths**

```php
public function test_current_urban_member_can_resolve_groups_without_changing_visible_capabilities(): void
{
    $user = LegacySpatialFixture::urbanSariUser();

    $groups = app(\App\Services\GroupService::class)->getGroupsForUser($user);

    $this->assertNotEmpty($groups);
    $this->assertTrue(collect($groups)->contains(fn ($group) => (string) $group->group_type === '0'));
}

public function test_current_rural_branch_does_not_require_a_city_id_when_village_data_exists(): void
{
    $user = LegacySpatialFixture::ruralChahardangehUserWithoutCity();

    $levels = app(\App\Services\GroupService::class)->getLocationLevels($user);

    $this->assertNotEmpty($levels);
    $this->assertTrue(collect($levels)->contains(fn ($level) => $level['level'] === 'village'));
}
```

If a small reusable fixture helper is required, create `tests/Support/LegacySpatialFixture.php` in the same task and keep it test-only.

- [ ] **Step 2: Run the focused contracts and document any genuine baseline failures rather than changing behavior**

Run:

```bash
php artisan test tests/Feature/LocationGovernance/LegacySpatialBaselineContractTest.php \
  tests/Feature/LocationGovernance/LegacyRegistrationLocationContractTest.php \
  tests/Feature/LocationGovernance/LegacyGroupMembershipContractTest.php \
  tests/Feature/LocationGovernance/LegacyElectionScopeContractTest.php
```

Expected: PASS for intentionally preserved behavior. If an existing defect is exposed, record it in `LEGACY_SPATIAL_INVENTORY.md` as pre-existing and decide separately whether it belongs in this program.

- [ ] **Step 3: Record every known legacy geography consumer**

`LEGACY_SPATIAL_INVENTORY.md` must classify each consumer as `registration`, `profile/address`, `grouping`, `group display/admin`, `elections`, `secretariat`, `projects`, `polls`, `Najm Hoda`, `API`, `seed/import`, or `other`, and record the exact legacy dependency (`Address FK`, geography model, `location_level`, `address_id`, or hard-coded tier name).

- [ ] **Step 4: Run existing mature regression gates before schema work**

```bash
php artisan test tests/Feature/Elections
php artisan test tests/Feature/GroupChat
php artisan test tests/Feature/NajmHoda
php artisan test
```

Expected: all baseline suites green on the planning-derived implementation branch.

- [ ] **Step 5: Commit checkpoint C0**

```bash
git add tests/Feature/LocationGovernance tests/Support/LegacySpatialFixture.php docs/location-governance/LEGACY_SPATIAL_INVENTORY.md
git commit -m "test: freeze legacy spatial behavior"
```

---

### Task 2: Add the generic Location Core schema and models (C1-A)

**Files:**
- Create: `database/migrations/2026_09_10_000001_create_location_core_tables.php`
- Create: `app/Models/Location.php`
- Create: `app/Models/LocationType.php`
- Create: `app/Models/LocationSchema.php`
- Create: `app/Models/LocationSchemaType.php`
- Create: `app/Models/LocationTypeRelation.php`
- Create: `app/Models/LocationExternalId.php`
- Create: `app/Models/LocationRelation.php`
- Create: `tests/Unit/LocationGovernance/LocationSchemaContractTest.php`
- Create: `tests/Feature/LocationGovernance/LocationPersistenceTest.php`

**Interfaces:**
- Produces: `Location(parent_id, location_type_id, schema_id, country_code, canonical_name, localized_names, status, centroid_latitude, centroid_longitude, valid_from, valid_until, provenance)` and relation tables for allowed structure, external IDs, and historical successor/predecessor links.

- [ ] **Step 1: Write schema tests first**

```php
public function test_location_tree_supports_branching_and_optional_micro_locations(): void
{
    $schema = LocationSchema::factory()->create(['country_code' => 'IR']);
    $section = LocationType::factory()->create(['key' => 'section']);
    $city = LocationType::factory()->create(['key' => 'city']);
    $rural = LocationType::factory()->create(['key' => 'rural_district']);

    LocationTypeRelation::create(['location_schema_id' => $schema->id, 'parent_type_id' => $section->id, 'child_type_id' => $city->id]);
    LocationTypeRelation::create(['location_schema_id' => $schema->id, 'parent_type_id' => $section->id, 'child_type_id' => $rural->id]);

    $this->assertDatabaseHas('location_type_relations', ['parent_type_id' => $section->id, 'child_type_id' => $city->id]);
    $this->assertDatabaseHas('location_type_relations', ['parent_type_id' => $section->id, 'child_type_id' => $rural->id]);
}
```

- [ ] **Step 2: Run and verify RED**

```bash
php artisan test tests/Unit/LocationGovernance/LocationSchemaContractTest.php tests/Feature/LocationGovernance/LocationPersistenceTest.php
```

Expected: FAIL because the new tables/models do not exist.

- [ ] **Step 3: Implement the additive migration**

Migration must create indexes/constraints for parent traversal, country/schema filtering, type lookup, external-id uniqueness by source, and historical relation lookup. It must not alter or drop legacy geography tables.

- [ ] **Step 4: Implement focused Eloquent models and casts**

`Location` exposes `parent()`, `children()`, `type()`, `schema()`, `externalIds()`, `predecessors()`, and `successors()`; JSON/localized/provenance fields use explicit casts. No model method may know `province_id`, `city_id`, or any other legacy fixed tier FK.

- [ ] **Step 5: Run tests and migration rollback locally/test DB**

```bash
php artisan test tests/Unit/LocationGovernance/LocationSchemaContractTest.php tests/Feature/LocationGovernance/LocationPersistenceTest.php
php artisan migrate --pretend
```

Expected: PASS; pretend SQL contains only additive new tables.

- [ ] **Step 6: Commit C1-A**

```bash
git add database/migrations/2026_09_10_000001_create_location_core_tables.php app/Models/Location*.php tests/Unit/LocationGovernance tests/Feature/LocationGovernance/LocationPersistenceTest.php
git commit -m "feat: add generic location core"
```

---

### Task 3: Implement schema validation, ancestry, endpoints, and lifecycle resolution (C1-B)

**Files:**
- Create: `app/Services/LocationGovernance/LocationSchemaResolver.php`
- Create: `app/Services/LocationGovernance/LocationTreeResolver.php`
- Create: `app/Services/LocationGovernance/LocationLifecycleService.php`
- Create: `app/Exceptions/InvalidLocationHierarchy.php`
- Create: `tests/Unit/LocationGovernance/LocationSchemaResolverTest.php`
- Create: `tests/Unit/LocationGovernance/LocationTreeResolverTest.php`
- Create: `tests/Feature/LocationGovernance/LocationLifecycleHistoryTest.php`

**Interfaces:**
- Produces:
  - `LocationSchemaResolver::allowedChildTypes(Location $parent): Collection`
  - `LocationSchemaResolver::assertValidParentChild(Location $parent, LocationType $childType): void`
  - `LocationTreeResolver::ancestors(Location $location): Collection`
  - `LocationTreeResolver::residenceEndpointAllowed(Location $location): bool`
  - `LocationLifecycleService::rename(Location $location, array $localizedNames, User $actor): Location`
  - `LocationLifecycleService::supersede(Location $old, Collection $successors, string $relationType, User $actor): void`

- [ ] **Step 1: Write tests for urban branch, rural branch, village endpoint, flexible micro-local nesting, and merge/split history**

```php
public function test_small_village_can_be_a_residence_endpoint_without_neighborhood(): void
{
    $village = LocationFixture::smallVillageWithoutNeighborhood();

    $this->assertTrue(app(LocationTreeResolver::class)->residenceEndpointAllowed($village));
}

public function test_complex_may_be_below_street_or_alley_when_schema_allows_both(): void
{
    $fixture = LocationFixture::iranMicroLocationSchema();

    $this->assertContains('residential_complex', $fixture->childKeysOf('street'));
    $this->assertContains('residential_complex', $fixture->childKeysOf('alley'));
}
```

- [ ] **Step 2: Verify RED, then implement minimal resolvers**

```bash
php artisan test tests/Unit/LocationGovernance/LocationSchemaResolverTest.php tests/Unit/LocationGovernance/LocationTreeResolverTest.php tests/Feature/LocationGovernance/LocationLifecycleHistoryTest.php
```

- [ ] **Step 3: Prove lifecycle operations are non-destructive**

Tests must assert old IDs remain queryable after rename/merge/split and that successors are linked rather than replacing historical FKs.

- [ ] **Step 4: Commit C1-B**

```bash
git add app/Services/LocationGovernance app/Exceptions/InvalidLocationHierarchy.php tests/Unit/LocationGovernance tests/Feature/LocationGovernance/LocationLifecycleHistoryTest.php
git commit -m "feat: resolve location topology and lifecycle"
```

---

### Task 4: Add independent Governance Areas, topology, mappings, and capability policy (C2)

**Files:**
- Create: `database/migrations/2026_09_10_000002_create_governance_core_tables.php`
- Create: `app/Models/GovernanceArea.php`
- Create: `app/Models/GovernanceAreaLocation.php`
- Create: `app/Models/GovernanceCapabilityPolicy.php`
- Create: `app/Models/GovernanceAreaOverride.php`
- Create: `app/Services/LocationGovernance/GovernanceResolver.php`
- Create: `app/Services/LocationGovernance/GovernanceCapabilityResolver.php`
- Create: `tests/Unit/LocationGovernance/GovernanceResolverTest.php`
- Create: `tests/Unit/LocationGovernance/GovernanceCapabilityResolverTest.php`
- Create: `tests/Feature/LocationGovernance/GovernanceMappingTest.php`

**Interfaces:**
- Produces:
  - `GovernanceResolver::officialAreasForResidence(Location $location): Collection`
  - `GovernanceResolver::baseOfficialAreaForResidence(Location $location): GovernanceArea`
  - `GovernanceResolver::officialAncestors(GovernanceArea $area): Collection`
  - `GovernanceCapabilityResolver::capabilities(GovernanceArea $area): GovernanceCapabilities`

- [ ] **Step 1: Write tests proving Location != Governance Area and that one Governance Area may map multiple Locations**

```php
public function test_governance_area_can_map_multiple_real_world_locations(): void
{
    $area = GovernanceArea::factory()->official()->create();
    $locations = Location::factory()->count(2)->create();

    $area->locations()->attach($locations->pluck('id'));

    $this->assertCount(2, $area->fresh()->locations);
}
```

- [ ] **Step 2: Write base-scope tests for urban neighborhood, village neighborhood, and village-without-neighborhood**

All three must resolve through the same `baseOfficialAreaForResidence()` contract.

- [ ] **Step 3: Implement policy inheritance**

Resolution order is `EarthCoop default → country/governance-type policy → area override`; an override may only change fields explicitly allowed by the policy schema.

- [ ] **Step 4: Run focused tests**

```bash
php artisan test tests/Unit/LocationGovernance/GovernanceResolverTest.php tests/Unit/LocationGovernance/GovernanceCapabilityResolverTest.php tests/Feature/LocationGovernance/GovernanceMappingTest.php
```

- [ ] **Step 5: Commit C2**

```bash
git add database/migrations/2026_09_10_000002_create_governance_core_tables.php app/Models/Governance*.php app/Services/LocationGovernance/Governance*.php tests/Unit/LocationGovernance/Governance* tests/Feature/LocationGovernance/GovernanceMappingTest.php
git commit -m "feat: add governance topology and capability policy"
```

---

### Task 5: Add historical residency and user-place relationships (C3)

**Files:**
- Create: `database/migrations/2026_09_10_000003_create_user_location_relationships.php`
- Create: `app/Models/UserLocationRelationship.php`
- Create: `app/Services/LocationGovernance/ResidenceService.php`
- Create: `app/Services/LocationGovernance/ResidenceTransferPolicy.php`
- Modify: `app/Models/User.php`
- Create: `tests/Unit/LocationGovernance/ResidenceTransferPolicyTest.php`
- Create: `tests/Feature/LocationGovernance/PrimaryResidenceHistoryTest.php`
- Create: `tests/Feature/LocationGovernance/NonResidenceRelationshipVotingTest.php`

**Interfaces:**
- Produces:
  - `User::primaryResidence(): HasOne`
  - `User::locationRelationships(): HasMany`
  - `ResidenceService::setInitialPrimaryResidence(User $user, Location $location, array $evidence): UserLocationRelationship`
  - `ResidenceService::transferPrimaryResidence(User $user, Location $to, User $actor, string $reason, bool $override = false): UserLocationRelationship`
  - `ResidenceTransferPolicy::remainingExplicitTransfers(User $user, CarbonInterface $at): int`

- [ ] **Step 1: Write tests for historical intervals and default 2-transfers-per-rolling-12-month quota**

```php
public function test_third_explicit_transfer_in_rolling_year_is_rejected_without_override(): void
{
    $user = User::factory()->create();
    $service = app(ResidenceService::class);

    $service->setInitialPrimaryResidence($user, Location::factory()->create(), []);
    $service->transferPrimaryResidence($user, Location::factory()->create(), $user, 'move 1');
    $service->transferPrimaryResidence($user, Location::factory()->create(), $user, 'move 2');

    $this->expectException(ResidenceTransferLimitExceeded::class);
    $service->transferPrimaryResidence($user, Location::factory()->create(), $user, 'move 3');
}
```

- [ ] **Step 2: Write test proving work/study never grants official voting scope**

The same user may have `work` and `study` relationships, but `GovernanceResolver` membership input must use only active `primary_residence`.

- [ ] **Step 3: Implement migration/models/services without deleting `addresses`**

- [ ] **Step 4: Run focused tests and legacy contracts**

```bash
php artisan test tests/Unit/LocationGovernance/ResidenceTransferPolicyTest.php tests/Feature/LocationGovernance/PrimaryResidenceHistoryTest.php tests/Feature/LocationGovernance/NonResidenceRelationshipVotingTest.php tests/Feature/LocationGovernance/LegacySpatialBaselineContractTest.php
```

- [ ] **Step 5: Commit C3**

```bash
git add database/migrations/2026_09_10_000003_create_user_location_relationships.php app/Models/User.php app/Models/UserLocationRelationship.php app/Services/LocationGovernance/Residence* tests/Unit/LocationGovernance/ResidenceTransferPolicyTest.php tests/Feature/LocationGovernance/PrimaryResidenceHistoryTest.php tests/Feature/LocationGovernance/NonResidenceRelationshipVotingTest.php
git commit -m "feat: add historical residence relationships"
```

---

### Task 6: Build the versioned Iran reference geography importer (C4)

**Files:**
- Create: `app/Services/LocationGovernance/Import/ReferenceDataset.php`
- Create: `app/Services/LocationGovernance/Import/ReferenceGeographyNormalizer.php`
- Create: `app/Services/LocationGovernance/Import/ReferenceGeographyValidator.php`
- Create: `app/Services/LocationGovernance/Import/ReferenceGeographyImporter.php`
- Create: `app/Console/Commands/LocationReferenceImportCommand.php`
- Create: `database/migrations/2026_09_10_000004_create_location_import_audit_tables.php`
- Create: `database/reference/ir/v1/schema.json`
- Create: `database/reference/ir/v1/locations.jsonl`
- Create: `tests/Unit/LocationGovernance/ReferenceGeographyNormalizerTest.php`
- Create: `tests/Feature/LocationGovernance/ReferenceGeographyImportTest.php`
- Create: `tests/Feature/LocationGovernance/ReferenceGeographyImportIdempotencyTest.php`

**Interfaces:**
- Produces command:

```bash
php artisan location:reference-import IR --version=v1 --dry-run
php artisan location:reference-import IR --version=v1 --apply
```

- [ ] **Step 1: Write importer tests for dry-run, validation failure, idempotency, and source/version audit**

```php
public function test_same_dataset_version_is_idempotent(): void
{
    $importer = app(ReferenceGeographyImporter::class);
    $dataset = ReferenceDataset::fromPath(database_path('reference/ir/v1'));

    $first = $importer->apply($dataset);
    $second = $importer->apply($dataset);

    $this->assertSame($first->locationCount(), $second->locationCount());
    $this->assertSame(0, $second->createdCount());
}
```

- [ ] **Step 2: Normalize representative Iran urban and rural branches**

Permanent dataset fixtures must include at minimum:

```text
Iran → Mazandaran → Sari County → Central Section → Sari City → Urban Region/Neighborhood
Iran → Mazandaran → Sari County → Chahardangeh Section → Rural District → Village → optional Neighborhood
Iran → Mazandaran → ... → Small Village (no neighborhood)
```

Legacy numeric IDs are source metadata only; they are not canonical ID requirements.

- [ ] **Step 3: Implement dry-run diff output before apply**

Dry-run must report create/update/deactivate/conflict counts and must perform no writes.

- [ ] **Step 4: Run importer tests and a local dry-run**

```bash
php artisan test tests/Unit/LocationGovernance/ReferenceGeographyNormalizerTest.php tests/Feature/LocationGovernance/ReferenceGeographyImportTest.php tests/Feature/LocationGovernance/ReferenceGeographyImportIdempotencyTest.php
php artisan location:reference-import IR --version=v1 --dry-run
```

- [ ] **Step 5: Commit C4**

```bash
git add app/Services/LocationGovernance/Import app/Console/Commands/LocationReferenceImportCommand.php database/migrations/2026_09_10_000004_create_location_import_audit_tables.php database/reference/ir/v1 tests/Unit/LocationGovernance/ReferenceGeographyNormalizerTest.php tests/Feature/LocationGovernance/ReferenceGeographyImport*.php
git commit -m "feat: add versioned Iran geography importer"
```

---

### Task 7: Expose schema-driven location selection API and feature flags (C5-A)

**Files:**
- Create: `config/location-governance.php`
- Create: `app/Http/Controllers/Location/LocationSelectionController.php`
- Create: `app/Http/Resources/LocationOptionResource.php`
- Create: `app/Http/Requests/LocationChildrenRequest.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/LocationGovernance/LocationSelectionApiTest.php`

**Interfaces:**
- Produces:
  - `GET /location/options/root?country=IR`
  - `GET /location/options/{location}/children`
  - JSON fields: `id`, `type_key`, localized `label`, `is_residence_endpoint`, `has_children`, `status`.
- Feature flags:
  - `LOCATION_GOVERNANCE_RUNTIME_ENABLED=false`
  - `LOCATION_GOVERNANCE_REGISTRATION_ENABLED=false`
  - `LOCATION_GOVERNANCE_GROUPS_ENABLED=false`
  - `LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false`

- [ ] **Step 1: Write API tests for branching, endpoint selection, locale labels, and pending visibility rules**
- [ ] **Step 2: Verify RED**

```bash
php artisan test tests/Feature/LocationGovernance/LocationSelectionApiTest.php
```

- [ ] **Step 3: Implement API through `LocationSchemaResolver`, never direct type-name conditionals**
- [ ] **Step 4: Confirm flags default to legacy-safe/off**
- [ ] **Step 5: Commit C5-A**

```bash
git add config/location-governance.php app/Http/Controllers/Location app/Http/Resources/LocationOptionResource.php app/Http/Requests/LocationChildrenRequest.php routes/web.php tests/Feature/LocationGovernance/LocationSelectionApiTest.php
git commit -m "feat: expose schema driven location selection"
```

---

### Task 8: Cut registration/profile location UX to Location + Primary Residence behind a flag (C5-B)

**Files:**
- Modify: `app/Http/Controllers/Auth/Register/StartController.php`
- Modify: `app/Http/Controllers/Profile/ProfileController.php`
- Modify: `app/Services/ProfileCompletionService.php`
- Modify: `resources/views/auth/register.blade.php` or the exact current registration Blade resolved from `StartController`
- Modify: the current profile address Blade resolved from `ProfileController`
- Create: `resources/js/location-selector.js`
- Modify: `resources/js/app.js`
- Create: `tests/Feature/LocationGovernance/RegistrationPrimaryResidenceTest.php`
- Create: `tests/Feature/LocationGovernance/ProfileResidenceTransferTest.php`
- Extend: `tests/Feature/RegistrationTermsGateTest.php`

**Interfaces:**
- Consumes: Location Selection API + `ResidenceService`.
- Produces: registration/profile can save one canonical Location endpoint and create/transfer Primary Residence.

- [ ] **Step 1: Write registration test with urban Sari, rural village branch, and village-without-neighborhood**

```php
public function test_registration_accepts_a_valid_rural_residence_endpoint_without_city(): void
{
    config()->set('location-governance.registration_enabled', true);
    $village = LocationFixture::smallVillageWithoutNeighborhood();

    $response = $this->withSession(['registration_terms_accepted' => true])
        ->post(route('register.process'), RegistrationFixture::validPayload([
            'primary_residence_location_id' => $village->id,
        ]));

    $response->assertSessionHasNoErrors('primary_residence_location_id');
}
```

- [ ] **Step 2: Write feature-flag rollback test**

When registration flag is false, legacy registration remains reachable and no canonical residence write is required.

- [ ] **Step 3: Implement selector and controller validation**

Server validation must verify active/selectable endpoint through `LocationTreeResolver`; never trust only client-provided IDs.

- [ ] **Step 4: Run focused + invitation/terms regression tests**

```bash
php artisan test tests/Feature/LocationGovernance/RegistrationPrimaryResidenceTest.php tests/Feature/LocationGovernance/ProfileResidenceTransferTest.php tests/Feature/RegistrationTermsGateTest.php tests/Feature/Invitation/InvitationLaunchContractTest.php
npm test --if-present
```

- [ ] **Step 5: Commit C5-B**

```bash
git add app/Http/Controllers/Auth/Register/StartController.php app/Http/Controllers/Profile/ProfileController.php app/Services/ProfileCompletionService.php resources/views resources/js/location-selector.js resources/js/app.js tests/Feature/LocationGovernance tests/Feature/RegistrationTermsGateTest.php
git commit -m "feat: add canonical residence registration flow"
```

---

### Task 9: Add explicit membership dimensions and group creation policy (C6-A)

**Files:**
- Create: `database/migrations/2026_09_10_000005_create_membership_dimension_tables.php`
- Create: `app/Models/MembershipDimension.php`
- Create: `app/Models/GroupCreationPolicy.php`
- Create: `app/Contracts/Membership/DimensionResolver.php`
- Create: `app/Services/Membership/PublicDimensionResolver.php`
- Create: `app/Services/Membership/ProfessionDimensionResolver.php`
- Create: `app/Services/Membership/SpecialtyDimensionResolver.php`
- Create: `app/Services/Membership/AgeDimensionResolver.php`
- Create: `app/Services/Membership/GenderDimensionResolver.php`
- Create: `tests/Unit/Membership/DimensionResolverTest.php`
- Create: `tests/Unit/Membership/GroupCreationPolicyTest.php`

**Interfaces:**
- `DimensionResolver::dimensionKey(): string`
- `DimensionResolver::valuesFor(User $user): Collection`
- `GroupCreationPolicy::modeFor(GovernanceArea $area, string $dimensionKey, mixed $dimensionValue): GroupCreationMode`

- [ ] **Step 1: Write tests that initial dimensions are explicit/resolved, not arbitrary key/value data**
- [ ] **Step 2: Write policy tests for `automatic`, `threshold`, `on_demand`, and `disabled`**
- [ ] **Step 3: Implement resolvers by adapting current specialty/experience/age/gender domain data without putting geography inside resolvers**
- [ ] **Step 4: Run tests and commit C6-A**

```bash
php artisan test tests/Unit/Membership
git add database/migrations/2026_09_10_000005_create_membership_dimension_tables.php app/Models/MembershipDimension.php app/Models/GroupCreationPolicy.php app/Contracts/Membership app/Services/Membership tests/Unit/Membership
git commit -m "feat: add membership dimensions and creation policy"
```

---

### Task 10: Implement deterministic Membership Engine (C6-B)

**Files:**
- Create: `app/Services/Membership/MembershipEngine.php`
- Create: `app/Data/Membership/MembershipIntent.php`
- Create: `app/Data/Membership/MembershipResolution.php`
- Create: `app/Services/Membership/MembershipAuditService.php`
- Create: `database/migrations/2026_09_10_000006_create_membership_resolution_audits.php`
- Create: `tests/Unit/Membership/MembershipEngineTest.php`
- Create: `tests/Feature/LocationGovernance/MultidimensionalMembershipTest.php`
- Create: `tests/Feature/LocationGovernance/GroupExplosionPreventionTest.php`

**Interfaces:**
- Produces `MembershipEngine::resolve(User $user, bool $materialize = false): MembershipResolution`.
- `MembershipResolution` contains official governance areas, community areas, dimension intents, suppressed intents with policy reasons, and an audit fingerprint.

- [ ] **Step 1: Write deterministic resolution tests**

Same user attributes + same residence + same policy version must produce the same canonical membership intent ordering/fingerprint.

- [ ] **Step 2: Write all five dimension integration cases**

```php
public function test_primary_residence_resolves_public_profession_specialty_age_and_gender_intents(): void
{
    $user = MembershipFixture::fullyClassifiedUser();

    $resolution = app(MembershipEngine::class)->resolve($user);

    $this->assertEqualsCanonicalizing(
        ['public', 'profession', 'specialty', 'age', 'gender'],
        $resolution->dimensionKeys()
    );
}
```

- [ ] **Step 3: Write anti-explosion test**

Disabled/threshold-not-met micro-local dimensions must be represented as suppressed intents without physical Group creation.

- [ ] **Step 4: Implement engine and audit record**
- [ ] **Step 5: Run and commit C6-B**

```bash
php artisan test tests/Unit/Membership/MembershipEngineTest.php tests/Feature/LocationGovernance/MultidimensionalMembershipTest.php tests/Feature/LocationGovernance/GroupExplosionPreventionTest.php
git add app/Services/Membership app/Data/Membership database/migrations/2026_09_10_000006_create_membership_resolution_audits.php tests/Unit/Membership/MembershipEngineTest.php tests/Feature/LocationGovernance/MultidimensionalMembershipTest.php tests/Feature/LocationGovernance/GroupExplosionPreventionTest.php
git commit -m "feat: add deterministic membership engine"
```

---

### Task 11: Add canonical Group scope and cut GroupService over behind a flag (C7)

**Files:**
- Create: `database/migrations/2026_09_10_000007_add_canonical_scope_to_groups.php`
- Modify: `app/Models/Group.php`
- Create: `app/Services/Groups/GovernanceScopedGroupService.php`
- Modify: `app/Services/GroupService.php`
- Create: `tests/Feature/LocationGovernance/GovernanceScopedGroupTest.php`
- Create: `tests/Feature/LocationGovernance/GroupCutoverFlagTest.php`
- Extend: existing Group Chat/Admin regression tests that construct Groups with location metadata.

**Interfaces:**
- Target Group identity is `governance_area_id + dimension_key + dimension_value identity`, not `address_id + location_level`.
- `GovernanceScopedGroupService::materialize(MembershipIntent $intent): ?Group` returns null when policy says do not create.

- [ ] **Step 1: Write failing tests proving two groups with the same dimension in different Governance Areas stay distinct and duplicate materialization is idempotent**
- [ ] **Step 2: Add nullable canonical columns/indexes only; do not drop legacy columns**
- [ ] **Step 3: Implement new service and make `GroupService` delegate when `LOCATION_GOVERNANCE_GROUPS_ENABLED=true`**
- [ ] **Step 4: Remove new-path assumptions that `alley/street/neighborhood` automatically imply role semantics; read capability/policy instead**
- [ ] **Step 5: Run focused and full group suites**

```bash
php artisan test tests/Feature/LocationGovernance/GovernanceScopedGroupTest.php tests/Feature/LocationGovernance/GroupCutoverFlagTest.php tests/Feature/GroupChat tests/Feature/Admin
```

- [ ] **Step 6: Commit C7**

```bash
git add database/migrations/2026_09_10_000007_add_canonical_scope_to_groups.php app/Models/Group.php app/Services/Groups app/Services/GroupService.php tests/Feature/LocationGovernance tests/Feature/GroupChat tests/Feature/Admin
git commit -m "feat: scope groups by governance area"
```

---

### Task 12: Cut formal Elections to Governance topology (C8)

**Files:**
- Create: `app/Contracts/Governance/OfficialGovernanceTopology.php`
- Create: `app/Services/Elections/GovernanceElectionTopology.php`
- Modify: exact current election topology/policy resolver services discovered in C0 inventory.
- Modify: election models only where a canonical `governance_area_id` is required.
- Create: `database/migrations/2026_09_10_000008_add_governance_scope_to_elections.php`
- Create: `tests/Feature/LocationGovernance/OfficialElectionGovernanceTopologyTest.php`
- Create: `tests/Feature/LocationGovernance/CommunityElectionBoundaryTest.php`
- Extend: `tests/Unit/Elections/ElectionPolicyResolverTest.php` and existing systemic election lifecycle suites.

**Interfaces:**
- `OfficialGovernanceTopology::parentOf(GovernanceArea $area): ?GovernanceArea`
- `OfficialGovernanceTopology::childrenOf(GovernanceArea $area): Collection`
- Formal election creation/resolution accepts canonical Governance Area scope.

- [ ] **Step 1: Write test proving formal election parent/child traversal uses Governance Areas and does not query legacy geography models**
- [ ] **Step 2: Write Community election boundary test**

An internal election in a Community Area may elect community managers but cannot create formal EarthCoop systemic officeholders unless policy explicitly opts it in.

- [ ] **Step 3: Add canonical election scope additively and flag the resolver**
- [ ] **Step 4: Run the entire election regression suite**

```bash
php artisan test tests/Feature/LocationGovernance/OfficialElectionGovernanceTopologyTest.php tests/Feature/LocationGovernance/CommunityElectionBoundaryTest.php tests/Unit/Elections tests/Feature/Elections
```

- [ ] **Step 5: Commit C8**

```bash
git add app/Contracts/Governance app/Services/Elections database/migrations/2026_09_10_000008_add_governance_scope_to_elections.php tests/Feature/LocationGovernance tests/Unit/Elections tests/Feature/Elections
git commit -m "feat: resolve systemic elections through governance topology"
```

---

### Task 13: Move Secretariat, Projects, Polls, and shared scope consumers to Governance Areas (C9)

**Files:**
- Modify: scope fields/resolvers in the existing Secretariat module discovered by C0.
- Modify: current Project scope model/service/controller paths discovered by C0.
- Modify: current Poll scope model/service/controller paths discovered by C0.
- Create: `app/Contracts/Governance/GovernanceScopedResource.php` only if multiple subsystems need the same minimal contract.
- Create: `tests/Feature/LocationGovernance/SecretariatGovernanceScopeTest.php`
- Create: `tests/Feature/LocationGovernance/ProjectGovernanceScopeTest.php`
- Create: `tests/Feature/LocationGovernance/PollGovernanceScopeTest.php`
- Extend: existing Secretariat/Project/Poll regression suites.

**Interfaces:**
- Each spatially scoped subsystem stores/resolves `governance_area_id` or resolves through its owning canonical Group; none interprets raw legacy geography tables.

- [ ] **Step 1: Write one failing canonical-scope contract per subsystem**
- [ ] **Step 2: Add nullable canonical scope columns/indexes where required**
- [ ] **Step 3: Update services/controllers to resolve capabilities through `GovernanceCapabilityResolver`**
- [ ] **Step 4: Run subsystem and mature validation tests**

```bash
php artisan test tests/Feature/LocationGovernance/SecretariatGovernanceScopeTest.php tests/Feature/LocationGovernance/ProjectGovernanceScopeTest.php tests/Feature/LocationGovernance/PollGovernanceScopeTest.php
php artisan test tests/Feature/Secretariat --testsuite=Feature || true
php artisan test
```

If the repository has no `tests/Feature/Secretariat` directory, use the exact Secretariat test paths recorded in C0 rather than treating the shell fallback as proof.

- [ ] **Step 5: Commit C9**

```bash
git add app tests database/migrations
git commit -m "feat: unify governance scope consumers"
```

---

### Task 14: Add Community Areas and micro-location policy (C10-A)

**Files:**
- Extend: `governance_areas` model/schema using a non-destructive migration if community classification fields are not already present.
- Create: `app/Services/LocationGovernance/CommunityAreaService.php`
- Create: `app/Services/LocationGovernance/CommunityCreationPolicy.php`
- Create: `tests/Feature/LocationGovernance/CommunityAreaCreationTest.php`
- Create: `tests/Feature/LocationGovernance/MicroLocationWithoutCommunityTest.php`

**Interfaces:**
- `CommunityCreationPolicy::mayCreateFor(Location $location, User $actor): bool`
- `CommunityAreaService::createFor(Location $location, User $actor): GovernanceArea`

- [ ] **Step 1: Write test proving a verified complex/building can exist with no Community Area**
- [ ] **Step 2: Write on-demand Community creation test and capability inheritance test**
- [ ] **Step 3: Implement without adding Community Areas to formal systemic topology by default**
- [ ] **Step 4: Commit C10-A**

```bash
php artisan test tests/Feature/LocationGovernance/CommunityAreaCreationTest.php tests/Feature/LocationGovernance/MicroLocationWithoutCommunityTest.php
git add app/Services/LocationGovernance database/migrations tests/Feature/LocationGovernance
git commit -m "feat: add policy driven community areas"
```

---

### Task 15: Add crowdsourced locations, distinct-user evidence, deduplication, and review workflow (C10-B)

**Files:**
- Create: `database/migrations/2026_09_10_000009_create_location_proposal_tables.php`
- Create: `app/Models/LocationProposal.php`
- Create: `app/Models/LocationProposalEvidence.php`
- Create: `app/Services/LocationGovernance/LocationProposalService.php`
- Create: `app/Services/LocationGovernance/LocationDuplicateDetector.php`
- Create: `app/Http/Controllers/Location/LocationProposalController.php`
- Create: `tests/Feature/LocationGovernance/LocationProposalWorkflowTest.php`
- Create: `tests/Feature/LocationGovernance/DistinctVerifierThresholdTest.php`
- Create: `tests/Unit/LocationGovernance/LocationDuplicateDetectorTest.php`

**Interfaces:**
- Proposal statuses: `pending`, `ready_for_review`, `approved`, `rejected`, `merged`, `needs_evidence`.
- `LocationProposalService::support(LocationProposal $proposal, User $user, array $evidence): void` enforces one effective verification per distinct user.
- Threshold crossing changes status to `ready_for_review`; it never auto-approves.

- [ ] **Step 1: Write duplicate-first behavior test**

When a likely pending/approved match exists under the same plausible parent, API returns/selects that candidate instead of blindly creating a duplicate.

- [ ] **Step 2: Write 10-distinct-user default threshold test and same-user replay test**
- [ ] **Step 3: Implement audited approve/reject/merge/request-more-evidence transitions**
- [ ] **Step 4: Run and commit C10-B**

```bash
php artisan test tests/Feature/LocationGovernance/LocationProposalWorkflowTest.php tests/Feature/LocationGovernance/DistinctVerifierThresholdTest.php tests/Unit/LocationGovernance/LocationDuplicateDetectorTest.php
git add database/migrations/2026_09_10_000009_create_location_proposal_tables.php app/Models/LocationProposal*.php app/Services/LocationGovernance/LocationProposalService.php app/Services/LocationGovernance/LocationDuplicateDetector.php app/Http/Controllers/Location/LocationProposalController.php tests/Feature/LocationGovernance tests/Unit/LocationGovernance/LocationDuplicateDetectorTest.php
git commit -m "feat: add crowdsourced location review workflow"
```

---

### Task 16: Add consent-based geolocation and reverse-geocoder abstraction (C11-A)

**Files:**
- Create: `app/Contracts/Geocoding/ReverseGeocoder.php`
- Create: `app/Data/Geocoding/ReverseGeocodeResult.php`
- Create: `app/Services/LocationGovernance/GeolocationMatchService.php`
- Create: `app/Http/Controllers/Location/GeolocationController.php`
- Create: `resources/js/location-geolocation.js`
- Modify: `resources/js/location-selector.js`
- Create: `tests/Unit/LocationGovernance/GeolocationMatchServiceTest.php`
- Create: `tests/Feature/LocationGovernance/GeolocationAssistTest.php`

**Interfaces:**
- `ReverseGeocoder::reverse(float $latitude, float $longitude, string $locale): ReverseGeocodeResult`
- `GeolocationMatchService::match(ReverseGeocodeResult $result): GeolocationMatch`
- No provider-specific DTO escapes the adapter boundary.

- [ ] **Step 1: Write GPS match/conflict tests with a fake provider**

Manual residence must remain selectable even when GPS points elsewhere; conflict yields evidence status `inconsistent`, not registration failure.

- [ ] **Step 2: Write privacy test ensuring raw precise coordinates are not persisted by default**
- [ ] **Step 3: Implement provider abstraction and browser opt-in only**
- [ ] **Step 4: Run and commit C11-A**

```bash
php artisan test tests/Unit/LocationGovernance/GeolocationMatchServiceTest.php tests/Feature/LocationGovernance/GeolocationAssistTest.php
git add app/Contracts/Geocoding app/Data/Geocoding app/Services/LocationGovernance/GeolocationMatchService.php app/Http/Controllers/Location/GeolocationController.php resources/js/location-geolocation.js resources/js/location-selector.js tests/Unit/LocationGovernance/GeolocationMatchServiceTest.php tests/Feature/LocationGovernance/GeolocationAssistTest.php
git commit -m "feat: add consent based location detection"
```

---

### Task 17: Build Location/Governance admin control center and Najm Hoda review surface (C11-B)

**Files:**
- Create: `app/Http/Controllers/Admin/LocationGovernanceController.php`
- Create: `resources/views/admin/location-governance/index.blade.php`
- Modify: `resources/views/admin/dashboard.blade.php`
- Create: `app/Services/NajmHoda/LocationGovernanceReviewService.php`
- Extend: existing Najm Hoda capability/approval registry using the exact pattern discovered during implementation.
- Create: `tests/Feature/Admin/LocationGovernanceControlCenterTest.php`
- Create: `tests/Feature/NajmHoda/LocationGovernanceReviewServiceTest.php`

**Interfaces:**
- Admin workflows expose reference/import history, pending proposals, duplicates, verification evidence, lifecycle operations, governance mappings, and policies as coherent reviewed actions.
- Najm Hoda may summarize/recommend but cannot approve/reject/merge sensitive location/governance changes autonomously.

- [ ] **Step 1: Write authorization tests for admin-only consequential actions**
- [ ] **Step 2: Write Najm Hoda test proving recommendations are approval-gated**
- [ ] **Step 3: Implement read/review dashboard and audited POST actions**
- [ ] **Step 4: Run Admin + Najm Hoda regression gates**

```bash
php artisan test tests/Feature/Admin/LocationGovernanceControlCenterTest.php tests/Feature/NajmHoda/LocationGovernanceReviewServiceTest.php tests/Feature/NajmHoda
```

- [ ] **Step 5: Commit C11-B**

```bash
git add app/Http/Controllers/Admin/LocationGovernanceController.php resources/views/admin/location-governance resources/views/admin/dashboard.blade.php app/Services/NajmHoda/LocationGovernanceReviewService.php tests/Feature/Admin/LocationGovernanceControlCenterTest.php tests/Feature/NajmHoda/LocationGovernanceReviewServiceTest.php
git commit -m "feat: add location governance review center"
```

---

### Task 18: Remove canonical-runtime Address dependencies from authentication/home/profile consumers (C11-C)

**Files:**
- Modify: `app/Http/Middleware/Authenticate.php`
- Modify: `app/Http/Controllers/HomeController.php`
- Modify: `app/Services/ProfileCompletionService.php`
- Modify: `app/Http/Controllers/Profile/ProfileController.php`
- Create: `tests/Feature/LocationGovernance/CanonicalRuntimeAddressIndependenceTest.php`
- Create: `tests/Feature/LocationGovernance/HomeCanonicalResidenceTest.php`

**Interfaces:**
- New runtime derives display ancestry from `primaryResidence.location` and `LocationTreeResolver`.
- Legacy `User::address()` may remain rollback-only until C14 but must not be required when canonical runtime flags are enabled.

- [ ] **Step 1: Write source/runtime contract that canonical mode succeeds for a user with Primary Residence and no legacy Address row**
- [ ] **Step 2: Replace Address reads only on canonical path**
- [ ] **Step 3: Run profile/home/auth and legacy rollback tests**

```bash
php artisan test tests/Feature/LocationGovernance/CanonicalRuntimeAddressIndependenceTest.php tests/Feature/LocationGovernance/HomeCanonicalResidenceTest.php tests/Feature/LocationGovernance/LegacySpatialBaselineContractTest.php
```

- [ ] **Step 4: Commit C11-C**

```bash
git add app/Http/Middleware/Authenticate.php app/Http/Controllers/HomeController.php app/Services/ProfileCompletionService.php app/Http/Controllers/Profile/ProfileController.php tests/Feature/LocationGovernance
git commit -m "refactor: remove canonical runtime address dependency"
```

---

### Task 19: Fresh-database bootstrap rehearsal and permanent scenario suite (C12-A)

**Files:**
- Create: `tests/Feature/LocationGovernance/FreshBootstrapLocationGovernanceTest.php`
- Create: `tests/Feature/LocationGovernance/GlobalArchitectureScenarioTest.php`
- Create: `database/seeders/LocationGovernanceBootstrapSeeder.php` only for small policy/bootstrap metadata; do not place full geography dataset here.
- Create: `docs/location-governance/UAT_SCENARIOS.md`
- Create: `docs/location-governance/BOOTSTRAP_RUNBOOK.md`

**Interfaces:**
- Clean DB bootstraps schema + small metadata seeder + reference importer and can complete representative UAT.

- [ ] **Step 1: Encode permanent architecture scenarios from the Spec**

The scenario suite must cover:

```text
urban Sari resident
rural Chahardangeh branch
village without neighborhood
urban neighborhood + street
alley/complex/building Community
location proposal + duplicate reuse
10-distinct-user ready-for-review threshold
GPS match and GPS/manual conflict
Primary Residence transfer/quota
work/study without voting rights
public/profession/specialty/age/gender membership
threshold/on-demand/disabled group creation
official election topology
Community internal election
rename/merge/split historical integrity
```

- [ ] **Step 2: Run fresh bootstrap on disposable local/test DB only**

```bash
php artisan migrate:fresh --env=testing
php artisan db:seed --class=LocationGovernanceBootstrapSeeder --env=testing
php artisan location:reference-import IR --version=v1 --apply --env=testing
php artisan test tests/Feature/LocationGovernance/FreshBootstrapLocationGovernanceTest.php tests/Feature/LocationGovernance/GlobalArchitectureScenarioTest.php
```

**Safety:** this command sequence is authorized only for a disposable testing database at this checkpoint. It is not authorization to run `migrate:fresh` on production.

- [ ] **Step 3: Commit C12-A**

```bash
git add tests/Feature/LocationGovernance database/seeders/LocationGovernanceBootstrapSeeder.php docs/location-governance
git commit -m "test: rehearse global location governance bootstrap"
```

---

### Task 20: Full validation, CI, UAT branch checkpoint, and cutover evidence (C12-B)

**Files:**
- Modify/Create only if needed: `.github/workflows/integration-full-validation.yml` focused location/governance regression step.
- Create: `docs/superpowers/progress/2026-09-10-global-location-governance-progress.md`
- Create: `docs/location-governance/CUTOVER_READINESS.md`

**Interfaces:**
- Produces a single immutable candidate SHA with green focused + full validation evidence.

- [ ] **Step 1: Add focused CI command without removing any mature gate**

```bash
php artisan test tests/Unit/LocationGovernance tests/Unit/Membership tests/Feature/LocationGovernance
```

- [ ] **Step 2: Run local complete validation**

```bash
php artisan test
npm test --if-present
npm run build
```

Then run the repository's existing focused Governance, Elections, Group Chat/Admin, Secretariat, Najm Hoda, Najm Bahar, Stock, JavaScript, Responsive, and Full Project PHPUnit gates exactly as defined in current workflows.

- [ ] **Step 3: Push the candidate branch and require green GitHub Actions**

Record workflow run IDs, candidate SHA, schema/import version, feature-flag states, and UAT dataset version in the progress document.

- [ ] **Step 4: Execute UAT from `UAT_SCENARIOS.md` with test users only**

Do not use production destructive operations. Any defect returns to the relevant earlier checkpoint and gets a regression test before fix.

- [ ] **Step 5: Commit evidence updates**

```bash
git add .github/workflows/integration-full-validation.yml docs/superpowers/progress/2026-09-10-global-location-governance-progress.md docs/location-governance/CUTOVER_READINESS.md
git commit -m "docs: record location governance validation readiness"
```

---

### Task 21: Prepare the production cutover package — STOP FOR EXPLICIT APPROVAL (C13)

**Files:**
- Create: `docs/location-governance/PRODUCTION_CUTOVER_RUNBOOK.md`
- Create: `docs/location-governance/PRODUCTION_ROLLBACK_RUNBOOK.md`
- Create: `app/Console/Commands/LocationGovernanceReadinessCommand.php`
- Create: `tests/Feature/LocationGovernance/ProductionReadinessCommandTest.php`

**Interfaces:**
- `php artisan location-governance:readiness` performs read-only checks and exits non-zero when preconditions fail.

- [ ] **Step 1: Write read-only readiness tests**

Checks include migrations present, importer version applied, canonical governance mappings complete for required reference paths, no unresolved fatal proposal/import conflicts, feature flags known, and validation candidate SHA recorded.

- [ ] **Step 2: Write backup/cutover/rollback runbooks**

Runbook must specify:

1. maintenance window;
2. database backup + restore verification;
3. current application SHA capture;
4. reference dataset/version capture;
5. read-only readiness command;
6. reversible flag activation order: runtime → registration/profile → groups → elections → subsystem consumers;
7. smoke tests after each activation;
8. rollback by flag/application SHA before any destructive cleanup;
9. explicit prohibition on legacy table drop during initial cutover.

- [ ] **Step 3: Run readiness on staging/UAT only**

```bash
php artisan test tests/Feature/LocationGovernance/ProductionReadinessCommandTest.php
php artisan location-governance:readiness
```

- [ ] **Step 4: Commit C13 preparation**

```bash
git add docs/location-governance/PRODUCTION_* app/Console/Commands/LocationGovernanceReadinessCommand.php tests/Feature/LocationGovernance/ProductionReadinessCommandTest.php
git commit -m "ops: prepare location governance cutover"
```

- [ ] **Step 5: HARD STOP**

Do **not** run a production bootstrap, destructive migration, flag cutover, or merge to `main` merely because this task is green. Present the immutable candidate SHA, backup plan, UAT evidence, CI evidence, and runbooks to the user and obtain explicit approval for the exact production action.

---

### Task 22: Post-cutover legacy retirement — SECOND EXPLICIT APPROVAL GATE (C14)

**Files:**
- Create: `tests/Architecture/NoLegacyGeographyRuntimeDependencyTest.php`
- Create: `docs/location-governance/LEGACY_RETIREMENT_AUDIT.md`
- Only after approval, create a separately dated destructive cleanup migration; its filename must be chosen at execution time and reviewed before run.

**Interfaces:**
- Architecture test scans canonical runtime namespaces/routes for forbidden dependencies on legacy fixed geography models/columns.

- [ ] **Step 1: Prove legacy runtime is unused before deleting anything**

The audit must include repository search, route/controller/service checks, query logs or representative runtime evidence, and all full validation gates.

- [ ] **Step 2: Write architecture test**

Forbidden dependencies in canonical runtime include direct references to legacy `Continent`, `Country`, `Province`, `County`, `Section`, `City`, `Rural`, `Village`, `Region`, `Neighborhood`, `Street`, `Alley`, fixed `Address` geography FKs, and Group `location_level/address_id` as authoritative scope.

- [ ] **Step 3: HARD STOP and request explicit approval for cleanup**

No destructive cleanup migration is authored or run until the user approves the retirement audit checkpoint.

- [ ] **Step 4: After approval only, create/test cleanup migration against a restored production backup clone first**

A passing rollback strategy must be backup restore/application rollback, not an unsafe attempt to reconstruct deleted historical data from `down()`.

- [ ] **Step 5: Run complete validation again and commit retirement separately**

```bash
php artisan test
npm test --if-present
npm run build
```

Legacy retirement must be its own PR/reviewable commit series, never hidden inside the architecture implementation PR.

---

## Cross-task test matrix

| Requirement | Permanent test owner |
|---|---|
| Branching country schema | `LocationSchemaResolverTest` |
| Rural path without city | `LocationTreeResolverTest`, `RegistrationPrimaryResidenceTest` |
| Village base official scope | `GovernanceResolverTest` |
| Location != Governance Area | `GovernanceMappingTest` |
| Historical residence + quota | `PrimaryResidenceHistoryTest`, `ResidenceTransferPolicyTest` |
| Work/study no official vote | `NonResidenceRelationshipVotingTest` |
| Five membership dimensions | `MultidimensionalMembershipTest` |
| No empty-group explosion | `GroupExplosionPreventionTest` |
| Groups canonical scope | `GovernanceScopedGroupTest` |
| Formal election topology | `OfficialElectionGovernanceTopologyTest` |
| Community election separation | `CommunityElectionBoundaryTest` |
| Location rename/merge/split history | `LocationLifecycleHistoryTest` |
| Import idempotency/audit | `ReferenceGeographyImportIdempotencyTest` |
| Distinct-user verification | `DistinctVerifierThresholdTest` |
| Duplicate location reuse | `LocationDuplicateDetectorTest` |
| GPS assistive only | `GeolocationAssistTest` |
| Canonical runtime independent of Address | `CanonicalRuntimeAddressIndependenceTest` |
| Fresh bootstrap | `FreshBootstrapLocationGovernanceTest` |
| Complete acceptance journey | `GlobalArchitectureScenarioTest` |
| No legacy dependencies after retirement | `NoLegacyGeographyRuntimeDependencyTest` |

---

## Execution rules

1. Create an isolated implementation worktree/branch from the approved planning checkpoint using the repository's normal Superpowers worktree workflow.
2. One checkpoint at a time; tests RED before implementation and GREEN before commit.
3. Never mix cleanup/refactoring unrelated to the active checkpoint.
4. Prefer nullable/additive schema changes until C14.
5. Keep legacy fallback only as temporary rollback scaffolding; do not create a permanent dual-domain architecture.
6. Every change to geography/governance policy or consequential review action must be auditable.
7. When a mature regression fails, stop and determine whether the failure is an intended contract change explicitly authorized by the Spec. If not, fix the regression before proceeding.
8. Production remains untouched throughout C0–C12.
9. C13 and C14 are separate human approval gates; approval of this implementation plan is not approval of destructive production actions.

## Definition of Done for the architecture program

The program is complete only when a clean database can bootstrap the new model; urban and rural residents resolve through the same Location → Governance → Membership contract; Primary Residence alone drives official geographic membership; all five group dimensions use Governance Areas; formal elections use Governance topology; Community elections remain separate; reference imports and crowdsourcing are auditable; GPS is assistive; mature EarthCoop validation remains green; UAT passes; production cutover is explicitly approved and safely executed; and only then a separately approved retirement audit proves fixed legacy geography is no longer authoritative at runtime.
