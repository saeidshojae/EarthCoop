# Location/Governance UI Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete EarthCoop's canonical Location/Governance UI so registration, profile, admin user editing, proposals, pending residence intent, governance visibility, and Community Areas work coherently across arbitrary country schemas without unsafe legacy assumptions.

**Architecture:** Keep authoritative Primary Residence anchored only to approved canonical `Location` rows. Add an explicit pending-residence-intent domain object that points from a user/current residence relationship to an open `LocationProposal`; registration/profile completion can proceed while governance and membership continue to derive only from the approved anchor. Build one schema-driven Location Picker used by registration, profile, and admin, then add user/admin governance views on top of existing canonical services.

**Tech Stack:** Laravel 9/PHP, Blade, Vite JavaScript, Bootstrap/unified EarthCoop layouts, Node 18 built-in test runner, PHPUnit Feature tests, existing Location/Governance services and feature flags.

**Spec:** `docs/superpowers/specs/2026-09-13-location-governance-ui-completion-design.md`

## Global Constraints

- Work only on `agent/location-governance-ui-completion-20260913`; never edit `main` directly.
- Preserve dark-launch behavior: do not enable `LOCATION_GOVERNANCE_*` Production flags in this plan.
- Canonical UI must be schema-driven; never hard-code a universal Iran-only location chain.
- Open proposals (`pending`, `ready_for_review`, `needs_evidence`) are selectable but never official Governance Areas.
- Official governance and canonical group membership resolve only from approved canonical Primary Residence anchors.
- Proposal approval/merge refinement must not consume ordinary Primary Residence transfer quota.
- A stale pending intent must never move a user after that user has changed residence.
- Community Areas remain optional, on-demand, policy-controlled, and separate from official governance/elections.
- Sensitive proposal review remains explicit, CSRF-protected, authorized, audited, and human-approved.
- TDD is mandatory: each production change starts with a targeted RED, then minimal GREEN, then relevant regression tests.
- Preserve existing Najm Bahar, elections, Najm Hoda, Group Chat, invitations, profile-completion, and canonical membership behavior.
- Every checkpoint commit must be independently reviewable and contain no known failing targeted tests.

---

## File Structure Map

### Pending residence intent domain
- Create: `database/migrations/2026_09_13_000001_create_pending_residence_intents_table.php` — explicit current pending exact-residence selection without polluting `user_location_relationships.location_id`.
- Create: `app/Models/PendingResidenceIntent.php` — typed relations to user, anchor relationship, proposal, and resolved Location.
- Modify: `app/Models/User.php` — pending intent relation/query helper.
- Modify: `app/Models/LocationProposal.php` — pending intent relation.
- Modify: `app/Services/LocationGovernance/ResidenceService.php` — set/replace/clear intent, safe convergence, staleness guard.
- Modify: `app/Services/LocationGovernance/LocationProposalService.php` — converge intents after approve/merge without changing review authority.

### Shared Location Picker
- Modify: `app/Http/Controllers/LocationGovernance/LocationOptionsController.php` — approved children, open proposals, allowed next types, endpoint metadata.
- Modify: `app/Http/Controllers/Location/LocationProposalController.php` — picker-friendly create/reuse response.
- Modify: `resources/js/location-selector.js` — arbitrary-depth traversal, proposal create/reuse, pending selection, resolved/stale state.
- Modify: `package.json` — add `test:location-governance` using Node's built-in test runner.
- Create: `tests/js/location-governance/location-selector.test.mjs`.

### Registration / profile / admin residence
- Modify: `resources/views/auth/register_step3_canonical.blade.php`.
- Modify: `app/Http/Controllers/Auth/Register/Step3Controller.php`.
- Modify: `resources/views/profile/partials/location_canonical.blade.php`.
- Modify: `app/Http/Controllers/LocationGovernance/ProfileResidenceController.php`.
- Modify: `app/Http/Controllers/LocationGovernance/ProfileEditController.php`.
- Create: `app/Http/Controllers/Admin/UserResidenceController.php`.
- Modify: `resources/views/admin/user/edit.blade.php`.
- Modify: `routes/web.php` — canonical admin residence route inside existing admin user permission boundary.
- Modify: `app/Http/Controllers/Admin/SafeUserController.php` only for explicit reconciliation coordination proved necessary by tests.

### User Location/Governance experience
- Create: `app/Http/Controllers/LocationGovernance/MyLocationGovernanceController.php`.
- Create: `app/Http/Controllers/LocationGovernance/CommunityAreaController.php`.
- Create: `resources/views/location-governance/my-location-governance.blade.php`.
- Modify: `routes/location-governance.php`.
- Modify: `resources/views/partials/sidebar-unified.blade.php` — this existing responsive sidebar serves desktop and mobile states.

### Admin Location/Governance operations
- Modify: `app/Http/Controllers/Admin/LocationGovernanceController.php`.
- Modify: `resources/views/admin/location-governance/index.blade.php` to compose focused sections.
- Create: `resources/views/admin/location-governance/partials/proposal-queue.blade.php`.
- Create: `resources/views/admin/location-governance/partials/reference-explorer.blade.php`.
- Create: `resources/views/admin/location-governance/partials/governance-topology.blade.php`.
- Create: `resources/views/admin/location-governance/partials/community-overview.blade.php`.
- Create: `resources/views/admin/location-governance/partials/import-diagnostics.blade.php`.
- Create: `resources/views/admin/location-governance/partials/health-diagnostics.blade.php`.

### Core tests
- Create: `tests/Feature/LocationGovernance/PendingResidenceIntentTest.php`.
- Create: `tests/Feature/LocationGovernance/LocationPickerProposalContractTest.php`.
- Extend: `tests/Feature/LocationGovernance/CanonicalResidenceUiContractTest.php`.
- Create: `tests/Feature/LocationGovernance/RegistrationPendingResidenceTest.php`.
- Create: `tests/Feature/LocationGovernance/ProfilePendingResidenceTest.php`.
- Create: `tests/Feature/Admin/CanonicalUserResidenceEditTest.php`.
- Create: `tests/Feature/LocationGovernance/MyLocationGovernancePageTest.php`.
- Create: `tests/Feature/LocationGovernance/CommunityAreaUiTest.php`.
- Extend: `tests/Feature/Admin/LocationGovernanceControlCenterTest.php`.

---

### Task 1: Persist Pending Residence Intent Without Polluting Canonical Residence

**Files:**
- Create: `database/migrations/2026_09_13_000001_create_pending_residence_intents_table.php`
- Create: `app/Models/PendingResidenceIntent.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/LocationProposal.php`
- Test: `tests/Feature/LocationGovernance/PendingResidenceIntentTest.php`

**Interfaces:**
- Consumes: existing current `UserLocationRelationship` and open `LocationProposal`.
- Produces: `PendingResidenceIntent` with `user_id`, `anchor_relationship_id`, `location_proposal_id`, `resolved_location_id`, `status`, `selected_at`, `resolved_at`, `cancelled_at`, `metadata`.

- [ ] **Step 1: Write the failing persistence test**

```php
#[Test]
public function a_user_can_hold_pending_exact_residence_without_replacing_the_approved_anchor(): void
{
    [$user, $anchor, $proposal] = $this->makeUserAnchorAndOpenProposal();

    $intent = PendingResidenceIntent::create([
        'user_id' => $user->id,
        'anchor_relationship_id' => $anchor->id,
        'location_proposal_id' => $proposal->id,
        'status' => 'pending',
        'selected_at' => now(),
    ]);

    $this->assertSame($anchor->location_id, $user->locationRelationships()->whereNull('ended_at')->sole()->location_id);
    $this->assertSame($proposal->id, $intent->locationProposal->id);
}
```

- [ ] **Step 2: Run RED**

Run: `php artisan test tests/Feature/LocationGovernance/PendingResidenceIntentTest.php`

Expected: FAIL because table/model do not exist.

- [ ] **Step 3: Add additive migration**

```php
Schema::create('pending_residence_intents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('anchor_relationship_id')->constrained('user_location_relationships')->cascadeOnDelete();
    $table->foreignId('location_proposal_id')->constrained('location_proposals')->cascadeOnDelete();
    $table->foreignId('resolved_location_id')->nullable()->constrained('locations')->nullOnDelete();
    $table->string('status')->default('pending')->index();
    $table->dateTime('selected_at')->index();
    $table->dateTime('resolved_at')->nullable()->index();
    $table->dateTime('cancelled_at')->nullable()->index();
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'status'], 'pending_residence_intents_user_status_index');
});
```

- [ ] **Step 4: Add model relations/casts**

```php
public function locationProposal(): BelongsTo
{
    return $this->belongsTo(LocationProposal::class);
}

public function anchorRelationship(): BelongsTo
{
    return $this->belongsTo(UserLocationRelationship::class, 'anchor_relationship_id');
}
```

- [ ] **Step 5: Run GREEN**

Run: `php artisan test tests/Feature/LocationGovernance/PendingResidenceIntentTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_13_000001_create_pending_residence_intents_table.php app/Models/PendingResidenceIntent.php app/Models/User.php app/Models/LocationProposal.php tests/Feature/LocationGovernance/PendingResidenceIntentTest.php
git commit -m "feat: persist pending residence intent"
```

---

### Task 2: Add ResidenceService Intent Lifecycle and Safe Proposal Convergence

**Files:**
- Modify: `app/Services/LocationGovernance/ResidenceService.php`
- Modify: `app/Services/LocationGovernance/LocationProposalService.php`
- Test: `tests/Feature/LocationGovernance/PendingResidenceIntentTest.php`
- Regression: `tests/Feature/LocationGovernance/PrimaryResidenceHistoryTest.php`

**Interfaces:**
- Consumes: Task 1 `PendingResidenceIntent`.
- Produces:
  - `ResidenceService::setPendingResidenceIntent(User $user, LocationProposal $proposal, array $metadata = []): PendingResidenceIntent`
  - `ResidenceService::clearPendingResidenceIntent(User $user, string $reason): void`
  - `ResidenceService::resolvePendingResidenceIntents(LocationProposal $proposal, Location $resolvedLocation): int`

- [ ] **Step 1: Add RED lifecycle tests**

```php
#[Test]
public function resolving_same_pending_intent_refines_current_residence_without_explicit_transfer(): void
{
    [$user, $anchor, $proposal, $resolved] = $this->makeResolvableIntent();

    $count = app(ResidenceService::class)->resolvePendingResidenceIntents($proposal, $resolved);

    $current = $user->fresh()->locationRelationships()->whereNull('ended_at')->latest('id')->firstOrFail();
    $this->assertSame(1, $count);
    $this->assertSame($resolved->id, $current->location_id);
    $this->assertFalse((bool) $current->explicit_transfer);
}

#[Test]
public function stale_intent_cannot_move_user_after_real_residence_transfer(): void
{
    [$user, $anchor, $proposal, $resolved, $differentHome] = $this->makeResolvableIntentWithAlternativeHome();
    app(ResidenceService::class)->transferPrimaryResidence($user, $differentHome, $user, 'real_move');

    $count = app(ResidenceService::class)->resolvePendingResidenceIntents($proposal, $resolved);

    $this->assertSame(0, $count);
    $this->assertSame($differentHome->id, $user->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
}
```

- [ ] **Step 2: Run RED**

Run: `php artisan test tests/Feature/LocationGovernance/PendingResidenceIntentTest.php`

Expected: FAIL for missing service methods.

- [ ] **Step 3: Implement transactional replacement/currentness guard**

```php
$current = UserLocationRelationship::query()
    ->where('user_id', $user->id)
    ->where('relationship_type', 'primary_residence')
    ->whereNull('ended_at')
    ->lockForUpdate()
    ->firstOrFail();

PendingResidenceIntent::query()
    ->where('user_id', $user->id)
    ->where('status', 'pending')
    ->lockForUpdate()
    ->get()
    ->each(fn ($intent) => $intent->forceFill([
        'status' => 'cancelled',
        'cancelled_at' => now(),
    ])->save());
```

Resolution updates only intents whose `anchor_relationship_id` is still the user's current unended Primary Residence. End the anchor and create a refined relationship with `explicit_transfer=false`, `change_reason='location_proposal_resolution'`, and provenance metadata.

- [ ] **Step 4: Trigger convergence after approve/merge only**

In `LocationProposalService::approve()` and `merge()`, after durable `resolved_location_id`, call `ResidenceService::resolvePendingResidenceIntents(...)`. Reject does not move residence.

- [ ] **Step 5: Run lifecycle/history GREEN**

```bash
php artisan test tests/Feature/LocationGovernance/PendingResidenceIntentTest.php tests/Feature/LocationGovernance/PrimaryResidenceHistoryTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/LocationGovernance/ResidenceService.php app/Services/LocationGovernance/LocationProposalService.php tests/Feature/LocationGovernance/PendingResidenceIntentTest.php
git commit -m "feat: resolve pending residence intent safely"
```

---

### Task 3: Expose Picker Read Model for Approved Children, Open Proposals, and Allowed Types

**Files:**
- Modify: `app/Http/Controllers/LocationGovernance/LocationOptionsController.php`
- Test: `tests/Feature/LocationGovernance/LocationPickerProposalContractTest.php`

**Interfaces:**
- Consumes: `LocationSchemaResolver::allowedChildTypes(Location $location)` and open proposal statuses.
- Produces: JSON keys `data`, `proposals`, `allowed_types`.

- [ ] **Step 1: Write RED API contract**

```php
$response->assertJsonStructure([
    'data' => [['id', 'type_key', 'label', 'is_residence_endpoint', 'has_children', 'status']],
    'proposals' => [['id', 'type_key', 'label', 'status', 'selectable']],
    'allowed_types' => [['id', 'key', 'label', 'proposal_allowed']],
]);
```

Assert rejected/resolved proposals are not emitted as selectable open candidates.

- [ ] **Step 2: Run RED**

Run: `php artisan test tests/Feature/LocationGovernance/LocationPickerProposalContractTest.php`

Expected: FAIL because current payload only contains approved `data`.

- [ ] **Step 3: Implement focused serializers**

```php
private function serializeProposal(LocationProposal $proposal): array
{
    return [
        'id' => $proposal->id,
        'type_key' => $proposal->type?->key,
        'label' => $proposal->canonical_name,
        'status' => $proposal->status->value,
        'selectable' => true,
    ];
}
```

`allowed_types` comes from server-side schema resolution; JavaScript never infers geography rules.

- [ ] **Step 4: Run GREEN**

Run: `php artisan test tests/Feature/LocationGovernance/LocationPickerProposalContractTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/LocationGovernance/LocationOptionsController.php tests/Feature/LocationGovernance/LocationPickerProposalContractTest.php
git commit -m "feat: expose canonical location picker contract"
```

---

### Task 4: Upgrade Shared JavaScript Location Picker With Proposal UX

**Files:**
- Modify: `resources/js/location-selector.js`
- Modify: `package.json`
- Create: `tests/js/location-governance/location-selector.test.mjs`
- Extend: `tests/Feature/LocationGovernance/CanonicalResidenceUiContractTest.php`

**Interfaces:**
- Consumes: Task 3 JSON contract and existing authenticated `POST /locations/proposals`.
- Produces: exactly one active selection through hidden `location_id` or `location_proposal_id`.

- [ ] **Step 1: Add RED PHP/JS contracts**

```php
$this->assertStringContainsString('name="location_id"', $html);
$this->assertStringContainsString('name="location_proposal_id"', $html);
$this->assertStringContainsString('data-location-selector', $html);
```

Node test must cover direct `street -> complex`, `street -> alley -> complex`, and proposal selection without hard-coded depth.

- [ ] **Step 2: Add test command and verify RED**

In `package.json`:

```json
"test:location-governance": "node --test tests/js/location-governance"
```

Run:
```bash
npm run test:location-governance
php artisan test tests/Feature/LocationGovernance/CanonicalResidenceUiContractTest.php
```

Expected: RED because proposal selection controls do not exist yet.

- [ ] **Step 3: Implement stable client selection identity**

```js
const selection = { kind: null, id: null };

function selectLocation(id) {
  selection.kind = 'location';
  selection.id = Number(id);
  locationInput.value = String(id);
  proposalInput.value = '';
}

function selectProposal(id) {
  selection.kind = 'proposal';
  selection.id = Number(id);
  locationInput.value = '';
  proposalInput.value = String(id);
}
```

Render pending text badge `در انتظار تأیید` and `مکان من در فهرست نیست` only for server-returned `proposal_allowed` types.

- [ ] **Step 4: Reuse server create response**

If response `kind === 'location'`, select the approved Location. If `kind === 'proposal'`, render/select that proposal. Never manufacture IDs client-side.

- [ ] **Step 5: Run GREEN**

Run the two commands from Step 2; expect PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/location-selector.js package.json tests/js/location-governance/location-selector.test.mjs tests/Feature/LocationGovernance/CanonicalResidenceUiContractTest.php
git commit -m "feat: add proposal flow to location picker"
```

---

### Task 5: Complete Registration With Approved or Pending Exact Residence

**Files:**
- Modify: `resources/views/auth/register_step3_canonical.blade.php`
- Modify: `app/Http/Controllers/Auth/Register/Step3Controller.php`
- Test: `tests/Feature/LocationGovernance/RegistrationPendingResidenceTest.php`
- Regression: existing canonical registration tests under `tests/Feature/LocationGovernance/`

**Interfaces:**
- Consumes: picker selection and `ResidenceService::setPendingResidenceIntent()`.
- Produces: completed registration with exact approved residence or approved anchor + pending exact intent.

- [ ] **Step 1: Write RED scenarios**

Cover approved endpoint, open proposal completion, no proposal ID stored as Primary Residence FK, approved anchor governance, and invalid/rejected proposal rejection.

```php
$response = $this->actingAs($user)->post(route('register.step3.process'), [
    'location_proposal_id' => $proposal->id,
]);
$response->assertRedirect(route('home'));
$this->assertSame($parent->id, $user->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
$this->assertSame($proposal->id, $user->pendingResidenceIntents()->where('status', 'pending')->sole()->location_proposal_id);
```

- [ ] **Step 2: Run RED**

Run: `php artisan test tests/Feature/LocationGovernance/RegistrationPendingResidenceTest.php`

- [ ] **Step 3: Validate exactly one selection server-side**

Accept nullable `location_id` and `location_proposal_id`; reject zero or both. For proposal selection, validate open state, schema relationship, and selectable policy; derive approved anchor from proposal parent/path instead of trusting client anchor IDs.

- [ ] **Step 4: Persist anchor + intent and distinguish success copy**

Pending success explains registration is complete, exact location awaits review, and official governance temporarily uses the approved anchor.

- [ ] **Step 5: Run registration/profile-completion regressions GREEN**

Run new test plus existing canonical registration and invitation/profile-completion tests that depend on residence completeness.

- [ ] **Step 6: Commit**

```bash
git add resources/views/auth/register_step3_canonical.blade.php app/Http/Controllers/Auth/Register/Step3Controller.php tests/Feature/LocationGovernance/RegistrationPendingResidenceTest.php
git commit -m "feat: allow pending exact residence at registration"
```

---

### Task 6: Complete Profile Residence Editing and Proposal Status

**Files:**
- Modify: `resources/views/profile/partials/location_canonical.blade.php`
- Modify: `app/Http/Controllers/LocationGovernance/ProfileResidenceController.php`
- Modify: `app/Http/Controllers/LocationGovernance/ProfileEditController.php`
- Test: `tests/Feature/LocationGovernance/ProfilePendingResidenceTest.php`
- Regression: `tests/Feature/LocationGovernance/ProfilePrimaryResidenceTest.php`

**Interfaces:**
- Consumes: shared picker and ResidenceService intent lifecycle.
- Produces: approved path, pending status, resolution outcome, and true-transfer quota behavior.

- [ ] **Step 1: Write RED profile tests**

Assert proposal refinement can be selected without counting approval/merge as an explicit move; changing to a different approved residence still obeys transfer policy.

- [ ] **Step 2: Run RED**

```bash
php artisan test tests/Feature/LocationGovernance/ProfilePendingResidenceTest.php tests/Feature/LocationGovernance/ProfilePrimaryResidenceTest.php
```

- [ ] **Step 3: Implement intent-aware update branching**

Rules:
- approved different residence => `transferPrimaryResidence()`;
- proposal under current approved anchor => set/replace intent only;
- proposal requiring a different approved anchor => one real transfer to that anchor, then attach intent;
- same approved location => no transfer;
- rejected/non-open proposal => validation error.

- [ ] **Step 4: Render approved path + pending status + correction action**

Never label a proposal as approved Location. Show transfer quota only for actual moves.

- [ ] **Step 5: Run GREEN**

Run the command from Step 2; expect PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/profile/partials/location_canonical.blade.php app/Http/Controllers/LocationGovernance/ProfileResidenceController.php app/Http/Controllers/LocationGovernance/ProfileEditController.php tests/Feature/LocationGovernance/ProfilePendingResidenceTest.php
git commit -m "feat: complete canonical profile residence flow"
```

---

### Task 7: Migrate Admin User Residence Editing to the Canonical Picker

**Files:**
- Create: `app/Http/Controllers/Admin/UserResidenceController.php`
- Modify: `resources/views/admin/user/edit.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/SafeUserController.php` only if a failing reconciliation test proves coordination is needed
- Test: `tests/Feature/Admin/CanonicalUserResidenceEditTest.php`

**Interfaces:**
- Consumes: shared picker, ResidenceService, authenticated admin actor.
- Produces: `PUT /admin/users/{user}/residence` inside existing admin + `permission:users.edit` boundary, requiring a reason.

- [ ] **Step 1: Write RED admin tests**

Cover approved move, pending proposal, required reason, schema validation, audit actor, and membership reconciliation when groups flag is enabled.

- [ ] **Step 2: Run RED**

Run: `php artisan test tests/Feature/Admin/CanonicalUserResidenceEditTest.php`

- [ ] **Step 3: Add focused controller and route**

```php
Route::put('/{user}/residence', [UserResidenceController::class, 'update'])
    ->middleware('permission:users.edit')
    ->name('residence.update');
```

Controller signature:

```php
public function update(Request $request, User $user, ResidenceService $residences): RedirectResponse
```

Validate exactly one selection plus required `reason`; use authenticated admin as actor. Never mutate canonical residence with raw DB updates.

- [ ] **Step 4: Embed a separate canonical Residence card in admin edit**

Keep existing identity form behavior intact; use the same `data-location-selector` contract and show current approved/pending status.

- [ ] **Step 5: Run GREEN + SafeUser regressions**

Run new test and existing SafeUser lifecycle/canonical membership reconciliation tests.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/UserResidenceController.php resources/views/admin/user/edit.blade.php routes/web.php app/Http/Controllers/Admin/SafeUserController.php tests/Feature/Admin/CanonicalUserResidenceEditTest.php
git commit -m "feat: add canonical admin residence editing"
```

---

### Task 8: Add “My Location & Governance” Page and Responsive Navigation Entry

**Files:**
- Create: `app/Http/Controllers/LocationGovernance/MyLocationGovernanceController.php`
- Create: `resources/views/location-governance/my-location-governance.blade.php`
- Modify: `routes/location-governance.php`
- Modify: `resources/views/partials/sidebar-unified.blade.php`
- Test: `tests/Feature/LocationGovernance/MyLocationGovernancePageTest.php`

**Interfaces:**
- Consumes: `ResidenceService::officialGovernanceAreasFor()`, current pending intent, canonical memberships.
- Produces: authenticated `location-governance.me` page with Residence, Official Governance Chain, Memberships, Communities sections.

- [ ] **Step 1: Write RED page/navigation contract**

```php
$response->assertSee('مکان و حکمرانی من');
$response->assertSee('محل سکونت من');
$response->assertSee('زنجیره حکمرانی رسمی');
$response->assertSee('عضویت‌های من');
$response->assertSee('ناظر');
```

Assert official chain displays `GovernanceArea` identities rather than arbitrary Location ancestors, and the unified responsive sidebar contains the route.

- [ ] **Step 2: Run RED**

Run: `php artisan test tests/Feature/LocationGovernance/MyLocationGovernancePageTest.php`

- [ ] **Step 3: Build focused controller view model**

Group memberships by `public`, `profession`, `specialty`, `age`, `gender`, then active/base versus observer/upstream. Never assume count 81.

- [ ] **Step 4: Build responsive RTL Blade and sidebar entry**

Use textual labels as well as badges/colors; link residence edit from the page. `sidebar-unified.blade.php` already controls collapsed mobile and desktop sidebar behavior, so no duplicate mobile menu implementation is introduced.

- [ ] **Step 5: Run GREEN**

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/LocationGovernance/MyLocationGovernanceController.php resources/views/location-governance/my-location-governance.blade.php routes/location-governance.php resources/views/partials/sidebar-unified.blade.php tests/Feature/LocationGovernance/MyLocationGovernancePageTest.php
git commit -m "feat: add my location and governance page"
```

---

### Task 9: Add Policy-Safe Community Area UX

**Files:**
- Create: `app/Http/Controllers/LocationGovernance/CommunityAreaController.php`
- Modify: `routes/location-governance.php`
- Modify: `resources/views/location-governance/my-location-governance.blade.php`
- Test: `tests/Feature/LocationGovernance/CommunityAreaUiTest.php`
- Regression: `tests/Feature/LocationGovernance/CommunityAreaCreationTest.php`
- Regression: `tests/Feature/LocationGovernance/CommunityElectionBoundaryTest.php`

**Interfaces:**
- Consumes: `CommunityCreationPolicy` and `CommunityAreaService::createFor(Location $location, User $actor)`.
- Produces: `POST /location-governance/community/{location}` as an idempotent create action only for approved eligible Locations.

- [ ] **Step 1: Write RED policy/UI tests**

Prove eligible approved complex/building gets create action, existing Community gets view state, pending proposal gets no create action, ineligible Location gets no action, repeat creation is idempotent, and Community remains outside formal election topology.

- [ ] **Step 2: Run RED**

```bash
php artisan test tests/Feature/LocationGovernance/CommunityAreaUiTest.php tests/Feature/LocationGovernance/CommunityAreaCreationTest.php tests/Feature/LocationGovernance/CommunityElectionBoundaryTest.php
```

- [ ] **Step 3: Implement controller that delegates to canonical policy/service**

```php
public function store(Location $location, Request $request, CommunityAreaService $communities): RedirectResponse
{
    $area = $communities->createFor($location, $request->user());

    return back()->with('success', 'جامعه محلی ایجاد یا بازیابی شد.');
}
```

No duplicate eligibility logic in Blade.

- [ ] **Step 4: Render Community separately from official chain**

Copy explicitly states Community is not automatically an official systemic-election tier.

- [ ] **Step 5: Run GREEN**

Run Step 2 command; expect PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/LocationGovernance/CommunityAreaController.php routes/location-governance.php resources/views/location-governance/my-location-governance.blade.php tests/Feature/LocationGovernance/CommunityAreaUiTest.php
git commit -m "feat: add policy safe community area UX"
```

---

### Task 10: Complete Admin Location/Governance Control Center

**Files:**
- Modify: `app/Http/Controllers/Admin/LocationGovernanceController.php`
- Modify: `resources/views/admin/location-governance/index.blade.php`
- Create: `resources/views/admin/location-governance/partials/proposal-queue.blade.php`
- Create: `resources/views/admin/location-governance/partials/reference-explorer.blade.php`
- Create: `resources/views/admin/location-governance/partials/governance-topology.blade.php`
- Create: `resources/views/admin/location-governance/partials/community-overview.blade.php`
- Create: `resources/views/admin/location-governance/partials/import-diagnostics.blade.php`
- Create: `resources/views/admin/location-governance/partials/health-diagnostics.blade.php`
- Test: `tests/Feature/Admin/LocationGovernanceControlCenterTest.php`

**Interfaces:**
- Consumes: canonical Location, LocationProposal, GovernanceArea, Community, import runs, existing Hoda review summaries.
- Produces: operational read models plus existing human-gated proposal mutation forms; no raw official-topology mutation is added in this phase.

- [ ] **Step 1: Add RED control-center contracts**

Assert proposal filters/status/evidence/audit, reference explorer, official topology, Community overview, import diagnostics, and health diagnostics sections.

- [ ] **Step 2: Run RED**

Run: `php artisan test tests/Feature/Admin/LocationGovernanceControlCenterTest.php`

- [ ] **Step 3: Add bounded read queries/view models**

Health indicators include open proposals, above-threshold proposals, invalid/unresolved pending residence intents, canonical Locations missing schema/type, and invalid governance mappings where applicable.

- [ ] **Step 4: Compose exact focused Blade partials**

Move the current proposal section into `proposal-queue.blade.php`; render the five additional named partials. Keep approve/reject/merge/request-evidence POST actions explicit and CSRF-protected; Hoda remains recommendation-only.

- [ ] **Step 5: Run GREEN**

Run Step 2 command; expect PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/LocationGovernanceController.php resources/views/admin/location-governance/index.blade.php resources/views/admin/location-governance/partials tests/Feature/Admin/LocationGovernanceControlCenterTest.php
git commit -m "feat: complete location governance control center"
```

---

### Task 11: UX Hardening for Alternate Schemas, Errors, Accessibility, and Responsive States

**Files:**
- Modify: `resources/js/location-selector.js`
- Modify: `tests/js/location-governance/location-selector.test.mjs`
- Modify: `resources/views/auth/register_step3_canonical.blade.php`
- Modify: `resources/views/profile/partials/location_canonical.blade.php`
- Modify: `resources/views/admin/user/edit.blade.php`
- Modify: `resources/views/location-governance/my-location-governance.blade.php`
- Extend relevant PHP tests from Tasks 5–10.

**Interfaces:**
- Consumes all earlier UI contracts.
- Produces explicit loading/empty/error/duplicate/stale/resolved states, keyboard-friendly controls, localization-ready copy, and cross-schema behavior.

- [ ] **Step 1: Add RED edge-case tests**

Required cases: street→complex, street→alley→complex, building endpoint, village endpoint without neighborhood, alternate-country branch, duplicate returned as existing Location, reusable proposal, proposal state changes while picker open, network failure preserving form state, inactive parent rejected server-side.

- [ ] **Step 2: Run RED**

```bash
npm run test:location-governance
php artisan test tests/Feature/LocationGovernance tests/Feature/Admin/CanonicalUserResidenceEditTest.php tests/Feature/Admin/LocationGovernanceControlCenterTest.php
```

- [ ] **Step 3: Implement explicit client/accessibility states**

Controls have text labels; correctness never depends only on color; dynamic levels remain arbitrary-depth and progressively disclosed; failed proposal submit leaves user-entered name/path intact.

- [ ] **Step 4: Run targeted GREEN**

Run Step 2 command; expect PASS.

- [ ] **Step 5: Build production assets**

Run: `npm run build`

Expected: successful Vite build with no new compile errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/location-selector.js tests/js/location-governance/location-selector.test.mjs resources/views/auth/register_step3_canonical.blade.php resources/views/profile/partials/location_canonical.blade.php resources/views/admin/user/edit.blade.php resources/views/location-governance/my-location-governance.blade.php tests/Feature
git commit -m "fix: harden location governance UX states"
```

---

### Task 12: Stage-C UI Completion Integration Gate and Release Readiness

**Files:**
- No planned production-code expansion. Add only regression guards if validation exposes a real uncovered defect, with a fresh RED/GREEN cycle.

**Interfaces:**
- Consumes all previous tasks.
- Produces an exact release candidate suitable for PR review while feature flags remain dark until separate Production cutover approval.

- [ ] **Step 1: Run focused Location/Governance suite**

Run: `php artisan test tests/Feature/LocationGovernance`

Expected: PASS with no failures/errors.

- [ ] **Step 2: Run affected admin/profile/invitation/group regressions**

Run relevant existing suites for admin users, canonical membership/reconciliation, invitation/profile completion, Community boundaries, and Location/Governance control center.

Expected: PASS.

- [ ] **Step 3: Run frontend validations**

```bash
npm run test:location-governance
npm run test:group-chat
npm run build
```

Expected: PASS.

- [ ] **Step 4: Run repository Full Validation on exact candidate commit**

Use the existing Full Validation workflow for the exact branch HEAD. Do not merge if any required gate fails.

Expected: all required jobs PASS, including Najm Bahar, Governance, Group Chat, Najm Hoda, JavaScript, Full Project PHPUnit, and regression gate.

- [ ] **Step 5: Verify dark-launch safety**

Confirm code defaults remain false and no commit changes Production `.env` or enables Location/Governance flags. Do not change Production flags in this plan.

- [ ] **Step 6: Review branch diff against `main`**

Verify changes are limited to this spec, migration is additive/non-destructive, no legacy data is dropped, and no unrelated UI refactor slipped in.

- [ ] **Step 7: Stop for explicit merge approval**

```bash
git status
git log --oneline --decorate main..HEAD
```

Do not merge to `main` without explicit user approval after exact-candidate validation.

---

## Dependency Order and Checkpoints

1. Task 1 establishes the persistence boundary.
2. Task 2 establishes safe domain lifecycle/convergence.
3. Tasks 3–4 establish the shared picker contract.
4. Tasks 5–7 migrate registration/profile/admin consumers.
5. Tasks 8–9 expose user governance/community understanding without changing authority boundaries.
6. Task 10 completes admin operational visibility.
7. Task 11 hardens cross-schema UI/UX.
8. Task 12 is the exact-candidate integration gate.

Each task ends in a reviewable checkpoint. A later task must not compensate for a broken earlier contract.

## Spec Coverage Self-Review

- Shared schema-driven picker: Tasks 3–4.
- Arbitrary sub-neighborhood depth and branching: Tasks 3–4, 11.
- Missing-location proposal/reuse/duplicate handling: Tasks 3–6, 11.
- Pending selectable but not official governance: Tasks 1–6.
- Approval/merge convergence without transfer-quota consumption: Task 2.
- Stale-intent guard: Task 2.
- Registration non-blocking on pending micro-location: Task 5.
- Profile editing/status: Task 6.
- Admin canonical residence editing: Task 7.
- My Location & Governance: Task 8.
- Active vs Observer visualization: Task 8.
- Community Area separation/policy: Task 9.
- Admin topology/proposals/import/diagnostics: Task 10.
- Accessibility/responsive/error/concurrency states: Task 11.
- Dark launch/full regression/release safety: Task 12.

The plan contains no deferred placeholder requirement, no automatic Governance creation from proposals, and no Production flag activation.
