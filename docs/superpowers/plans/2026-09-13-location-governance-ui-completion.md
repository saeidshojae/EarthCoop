# Location/Governance UI Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete EarthCoop's canonical Location/Governance UI so registration, profile, admin user management, proposals, pending residence intent, governance visibility, and Community Areas work coherently across arbitrary country schemas without enabling unsafe legacy assumptions.

**Architecture:** Keep authoritative Primary Residence anchored only to approved canonical `Location` rows. Add an explicit pending-residence-intent domain object that points from a user/current residence relationship to an open `LocationProposal`; this allows registration/profile completion to proceed while governance and membership continue to derive only from the approved anchor. Build one schema-driven Location Picker contract used by registration, profile, and admin, then add user/admin governance views on top of existing canonical services.

**Tech Stack:** Laravel 9/PHP, Blade, Vite JavaScript, Bootstrap/unified EarthCoop layouts, PHPUnit Feature tests, existing Location/Governance services and feature flags.

**Spec:** `docs/superpowers/specs/2026-09-13-location-governance-ui-completion-design.md`

## Global Constraints

- Work only on `agent/location-governance-ui-completion-20260913`; never edit `main` directly.
- Preserve dark-launch behavior: do not enable `LOCATION_GOVERNANCE_*` Production flags in this plan.
- Canonical UI must be schema-driven; never hard-code a universal Iran-only location chain.
- Open proposals (`pending`, `ready_for_review`, `needs_evidence`) are selectable but are never themselves official Governance Areas.
- Official governance and canonical group membership resolve only from approved canonical Primary Residence anchors.
- Proposal approval/merge refinement must not consume ordinary Primary Residence transfer quota.
- A stale pending intent must never move a user after that user has changed residence.
- Community Areas remain optional, on-demand, policy-controlled, and separate from official governance/elections.
- Sensitive proposal review remains explicit, CSRF-protected, authorized, audited, and human-approved.
- TDD is mandatory: each production change starts with a targeted RED, then minimal GREEN, then relevant regression tests.
- Preserve existing Najm Bahar, elections, Najm Hoda, Group Chat, invitations, profile-completion, and canonical membership behavior.
- Every checkpoint commit must be independently reviewable and must not contain known failing targeted tests.

---

## File Structure Map

### Pending residence intent domain
- Create: `database/migrations/2026_09_13_000001_create_pending_residence_intents_table.php` — explicit current pending exact-residence selection without polluting `user_location_relationships.location_id`.
- Create: `app/Models/PendingResidenceIntent.php` — typed relationships to user, current anchor relationship, proposal, resolver metadata.
- Modify: `app/Models/User.php` — `pendingResidenceIntents()` / `currentPendingResidenceIntent()` relationships or focused query helper.
- Modify: `app/Models/LocationProposal.php` — `pendingResidenceIntents()` relationship.
- Modify: `app/Services/LocationGovernance/ResidenceService.php` — set/replace/clear pending intent, resolve approved/merged proposal, and staleness guard.
- Modify: `app/Services/LocationGovernance/LocationProposalService.php` — invoke convergence after approve/merge without changing review authority.

### Shared Location Picker read/write contract
- Modify: `app/Http/Controllers/LocationGovernance/LocationOptionsController.php` — serialize approved children, open proposals, allowed next types, endpoint/proposal-policy metadata.
- Modify: `app/Http/Controllers/Location/LocationProposalController.php` — return reusable candidate state needed by picker; retain server-authoritative duplicate detection.
- Modify: `resources/js/location-selector.js` — one reusable component supporting arbitrary depth, proposal creation/reuse, pending selection, stale resolution, and explicit `location:<id>` / `proposal:<id>` identity.
- Modify: `resources/js/app.js` only if initialization/export contract needs adjustment.

### Registration / profile / admin residence flows
- Modify: `resources/views/auth/register_step3_canonical.blade.php`.
- Modify: `app/Http/Controllers/Auth/Register/Step3Controller.php`.
- Modify: `resources/views/profile/partials/location_canonical.blade.php`.
- Modify: `app/Http/Controllers/LocationGovernance/ProfileResidenceController.php`.
- Modify: `app/Http/Controllers/LocationGovernance/ProfileEditController.php`.
- Modify: `resources/views/admin/user/edit.blade.php` and, if residence is exposed on creation, `resources/views/admin/user/create.blade.php`.
- Create: `app/Http/Controllers/Admin/UserResidenceController.php` — canonical admin residence mutation with actor/reason and domain-service use.
- Modify: admin routes where `SafeUserController` routes are currently declared.

### User Location/Governance experience
- Create: `app/Http/Controllers/LocationGovernance/MyLocationGovernanceController.php`.
- Create: `resources/views/location-governance/my-location-governance.blade.php`.
- Modify: `routes/location-governance.php`.
- Modify: `resources/views/partials/sidebar-unified.blade.php` and matching mobile/navigation partial if the project has a separate current mobile entry.

### Community / admin operations
- Create or modify focused Community controller/routes only after policy contracts are proven.
- Modify: `resources/views/admin/location-governance/index.blade.php`.
- Modify: `app/Http/Controllers/Admin/LocationGovernanceController.php`.
- Create focused partials under `resources/views/admin/location-governance/` if the current page would otherwise become monolithic.

### Core test suite for this phase
- Create: `tests/Feature/LocationGovernance/PendingResidenceIntentTest.php`.
- Create: `tests/Feature/LocationGovernance/LocationPickerProposalContractTest.php`.
- Extend: `tests/Feature/LocationGovernance/CanonicalResidenceUiContractTest.php`.
- Create: `tests/Feature/LocationGovernance/RegistrationPendingResidenceTest.php`.
- Create: `tests/Feature/LocationGovernance/ProfilePendingResidenceTest.php`.
- Create: `tests/Feature/Admin/CanonicalUserResidenceEditTest.php`.
- Create: `tests/Feature/LocationGovernance/MyLocationGovernancePageTest.php`.
- Create: `tests/Feature/LocationGovernance/CommunityAreaUiTest.php`.
- Extend/create: `tests/Feature/Admin/LocationGovernanceControlCenterTest.php`.
- Add JS contract/regression coverage following the repository's existing JavaScript-test convention for `location-selector.js`.

---

### Task 1: Persist Pending Residence Intent Without Polluting Canonical Residence

**Files:**
- Create: `database/migrations/2026_09_13_000001_create_pending_residence_intents_table.php`
- Create: `app/Models/PendingResidenceIntent.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/LocationProposal.php`
- Test: `tests/Feature/LocationGovernance/PendingResidenceIntentTest.php`

**Interfaces:**
- Consumes: existing `UserLocationRelationship` current Primary Residence and `LocationProposal` open states.
- Produces: `PendingResidenceIntent` with `user_id`, `anchor_relationship_id`, `location_proposal_id`, `status`, `selected_at`, `resolved_at`, `cancelled_at`, `resolved_location_id`, `metadata`; one current open intent per user enforced by service/database semantics.

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

- [ ] **Step 2: Run targeted test and verify RED**

Run: `php artisan test tests/Feature/LocationGovernance/PendingResidenceIntentTest.php`

Expected: FAIL because the table/model does not yet exist.

- [ ] **Step 3: Add migration with explicit foreign keys and lifecycle fields**

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

- [ ] **Step 4: Add model relationships/casts and user/proposal relations**

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

- [ ] **Step 5: Run migration/model tests GREEN**

Run: `php artisan test tests/Feature/LocationGovernance/PendingResidenceIntentTest.php`

Expected: PASS.

- [ ] **Step 6: Commit checkpoint**

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

- [ ] **Step 1: Add RED tests for currentness, quota preservation, approval, merge, rejection-safe behavior**

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

- [ ] **Step 2: Run targeted test and verify RED**

Run: `php artisan test tests/Feature/LocationGovernance/PendingResidenceIntentTest.php`

Expected: FAIL for missing service methods/convergence.

- [ ] **Step 3: Implement transactional intent replacement and currentness guard**

Service rules:

```php
$current = UserLocationRelationship::query()
    ->where('user_id', $user->id)
    ->where('relationship_type', 'primary_residence')
    ->whereNull('ended_at')
    ->lockForUpdate()
    ->firstOrFail();

// Replace only the user's currently-open intent; preserve history by cancelling it.
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

For resolution, update only if `anchor_relationship_id` is still the current unended Primary Residence relationship. End anchor and create refined relationship with `explicit_transfer=false`, provenance metadata, and `change_reason='location_proposal_resolution'`.

- [ ] **Step 4: Call convergence only after approve/merge resolution is durable**

In `LocationProposalService::approve()` and `merge()`, after the proposal is tied to a canonical `resolved_location_id`, call `ResidenceService::resolvePendingResidenceIntents(...)` inside safe transaction ordering. Do not invoke convergence on reject.

- [ ] **Step 5: Run targeted and history regression tests**

Run:
```bash
php artisan test tests/Feature/LocationGovernance/PendingResidenceIntentTest.php tests/Feature/LocationGovernance/PrimaryResidenceHistoryTest.php
```

Expected: PASS; explicit transfer quota/history contracts remain unchanged.

- [ ] **Step 6: Commit checkpoint**

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
- Consumes: `LocationSchemaResolver::allowedChildTypes(Location $location)` and open `LocationProposal` statuses.
- Produces JSON under each root/children response with stable `data`, `proposals`, and `allowed_types` collections.

- [ ] **Step 1: Write RED API contract test**

```php
$response->assertJsonStructure([
    'data' => [['id', 'type_key', 'label', 'is_residence_endpoint', 'has_children', 'status']],
    'proposals' => [['id', 'type_key', 'label', 'status', 'selectable']],
    'allowed_types' => [['id', 'key', 'label', 'proposal_allowed']],
]);
```

Also assert rejected/resolved proposals are not emitted as selectable open candidates.

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/LocationGovernance/LocationPickerProposalContractTest.php`

Expected: FAIL because current payload contains only `data`.

- [ ] **Step 3: Implement focused serializers**

Use methods such as:

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

`allowed_types` must come from server-side schema resolution; JavaScript must not infer geography rules.

- [ ] **Step 4: Run API contract GREEN**

Run: `php artisan test tests/Feature/LocationGovernance/LocationPickerProposalContractTest.php`

Expected: PASS.

- [ ] **Step 5: Commit checkpoint**

```bash
git add app/Http/Controllers/LocationGovernance/LocationOptionsController.php tests/Feature/LocationGovernance/LocationPickerProposalContractTest.php
git commit -m "feat: expose canonical location picker contract"
```

---

### Task 4: Upgrade Shared JavaScript Location Picker With Proposal UX

**Files:**
- Modify: `resources/js/location-selector.js`
- Modify: `resources/js/app.js` only if required by current initialization pattern
- Test: repository-standard JS contract/regression file for Location Picker
- Extend: `tests/Feature/LocationGovernance/CanonicalResidenceUiContractTest.php`

**Interfaces:**
- Consumes: Task 3 JSON contract and existing `POST /locations/proposals`.
- Produces hidden fields `location_id` and `location_proposal_id`, with exactly one active selection identity.

- [ ] **Step 1: Add RED contract assertions for proposal controls**

Feature/JS contract must prove the canonical registration/profile markup initializes the same picker and exposes both hidden targets:

```php
$this->assertStringContainsString('name="location_id"', $html);
$this->assertStringContainsString('name="location_proposal_id"', $html);
$this->assertStringContainsString('data-location-selector', $html);
```

JS behavior test must cover direct `street -> complex`, `street -> alley -> complex`, and proposal selection without hard-coded depth.

- [ ] **Step 2: Verify RED**

Run targeted PHP + JS tests using the repository's existing JS test command.

- [ ] **Step 3: Implement stable selection identity**

Core client state:

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

Render pending badge textually (`در انتظار تأیید`) and contextual `مکان من در فهرست نیست` action only for `proposal_allowed` child types.

- [ ] **Step 4: Submit proposal through existing authenticated endpoint and reuse server result**

If response `kind === 'location'`, select approved Location. If response `kind === 'proposal'`, render/select proposal immediately. Never manufacture IDs client-side.

- [ ] **Step 5: Run targeted JS/PHP GREEN**

Expected: PASS and existing arbitrary-depth traversal tests remain green.

- [ ] **Step 6: Commit checkpoint**

```bash
git add resources/js/location-selector.js resources/js/app.js tests/Feature/LocationGovernance/CanonicalResidenceUiContractTest.php
git commit -m "feat: add proposal flow to location picker"
```

---

### Task 5: Complete Registration With Approved or Pending Exact Residence

**Files:**
- Modify: `resources/views/auth/register_step3_canonical.blade.php`
- Modify: `app/Http/Controllers/Auth/Register/Step3Controller.php`
- Test: `tests/Feature/LocationGovernance/RegistrationPendingResidenceTest.php`
- Regression: canonical registration tests already present in `tests/Feature/LocationGovernance/`

**Interfaces:**
- Consumes: Location Picker selection and `ResidenceService::setPendingResidenceIntent()`.
- Produces completed registration with either exact approved residence or approved anchor + pending exact intent.

- [ ] **Step 1: Write RED registration scenarios**

Cover:
1. approved endpoint works unchanged;
2. open proposal under an approved residence endpoint can complete registration;
3. pending proposal cannot become Primary Residence FK;
4. official anchor remains approved ancestor;
5. rejected/resolved-invalid proposal selection is rejected server-side.

Example:

```php
$response = $this->actingAs($user)->post(route('register.step3.process'), [
    'location_proposal_id' => $proposal->id,
]);
$response->assertRedirect(route('home'));
$this->assertSame($parent->id, $user->fresh()->locationRelationships()->whereNull('ended_at')->sole()->location_id);
$this->assertSame($proposal->id, $user->pendingResidenceIntents()->where('status', 'pending')->sole()->location_proposal_id);
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/LocationGovernance/RegistrationPendingResidenceTest.php`

- [ ] **Step 3: Validate exactly one target server-side**

Accept nullable `location_id` and `location_proposal_id`, then reject zero/both selections. For proposal selection, validate open status, schema relationship, selectable policy, and derive the approved anchor from the proposal parent/path; do not trust client anchor IDs.

- [ ] **Step 4: Store approved anchor + pending intent and emit precise success copy**

Approved: current success copy.
Pending: explain registration is complete, exact location is awaiting review, and official governance temporarily uses the approved parent/ancestor.

- [ ] **Step 5: Run registration + profile-completion regressions**

Run targeted LocationGovernance registration tests and invitation/profile-completion tests that depend on residence completeness.

- [ ] **Step 6: Commit checkpoint**

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
- Consumes: same picker and ResidenceService intent lifecycle.
- Produces profile view model for approved path, open intent/status, resolved/rejected outcome, and true-transfer quota behavior.

- [ ] **Step 1: Write RED tests**

Assert profile can select an open proposal without consuming explicit transfer count when it refines the same approved anchor, while changing to a different approved residence still calls transfer policy.

- [ ] **Step 2: Verify RED**

Run both pending-profile and existing primary-residence tests.

- [ ] **Step 3: Implement intent-aware update branching**

Rules:
- approved different residence => `transferPrimaryResidence()`;
- proposal under current approved anchor => set/replace pending intent only;
- proposal requiring a different approved anchor => perform one real transfer to that anchor, then attach pending intent;
- same approved location => no transfer;
- rejected/non-open proposal => validation error.

- [ ] **Step 4: Render current approved path + pending status + correction action**

Never label a proposal as approved Location. Show transfer quota only for actual moves.

- [ ] **Step 5: Run targeted GREEN + regressions**

- [ ] **Step 6: Commit checkpoint**

```bash
git add resources/views/profile/partials/location_canonical.blade.php app/Http/Controllers/LocationGovernance/ProfileResidenceController.php app/Http/Controllers/LocationGovernance/ProfileEditController.php tests/Feature/LocationGovernance/ProfilePendingResidenceTest.php
git commit -m "feat: complete canonical profile residence flow"
```

---

### Task 7: Migrate Admin User Residence Editing to the Canonical Picker

**Files:**
- Create: `app/Http/Controllers/Admin/UserResidenceController.php`
- Modify: `resources/views/admin/user/edit.blade.php`
- Modify: current admin user route file
- Modify: `app/Http/Controllers/Admin/SafeUserController.php` only if demographic/residence reconciliation boundary needs coordination
- Test: `tests/Feature/Admin/CanonicalUserResidenceEditTest.php`

**Interfaces:**
- Consumes: shared picker, ResidenceService, current authenticated admin actor.
- Produces explicit admin residence mutation endpoint requiring `reason`; optional override only through already-authorized admin policy, never by direct row update.

- [ ] **Step 1: Write RED admin tests**

Cover approved move, pending proposal, required reason, schema validation, protected audit actor, and group reconciliation when flag enabled.

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Admin/CanonicalUserResidenceEditTest.php`

- [ ] **Step 3: Implement focused controller**

Example signature:

```php
public function update(Request $request, User $user, ResidenceService $residences): RedirectResponse
```

Validate selection and `reason`; use authenticated admin as `changed_by_user_id`. Do not add raw geography columns to `SafeUserController::update()`.

- [ ] **Step 4: Embed shared picker in admin edit UI**

Keep the existing identity form intact except for a clearly separated canonical Residence card. Do not refactor unrelated inline CSS/layout in this task.

- [ ] **Step 5: Run admin + canonical membership regressions**

- [ ] **Step 6: Commit checkpoint**

```bash
git add app/Http/Controllers/Admin/UserResidenceController.php resources/views/admin/user/edit.blade.php app/Http/Controllers/Admin/SafeUserController.php tests/Feature/Admin/CanonicalUserResidenceEditTest.php routes
git commit -m "feat: add canonical admin residence editing"
```

---

### Task 8: Add “My Location & Governance” Page and Navigation

**Files:**
- Create: `app/Http/Controllers/LocationGovernance/MyLocationGovernanceController.php`
- Create: `resources/views/location-governance/my-location-governance.blade.php`
- Modify: `routes/location-governance.php`
- Modify: `resources/views/partials/sidebar-unified.blade.php`
- Modify matching active mobile navigation partial if current layout has one
- Test: `tests/Feature/LocationGovernance/MyLocationGovernancePageTest.php`

**Interfaces:**
- Consumes: `ResidenceService::officialGovernanceAreasFor()`, current pending intent, canonical group memberships.
- Produces authenticated read-only page grouped into Residence, Official Governance Chain, Canonical Memberships, Communities.

- [ ] **Step 1: Write RED page contract**

```php
$response->assertSee('مکان و حکمرانی من');
$response->assertSee('محل سکونت من');
$response->assertSee('زنجیره حکمرانی رسمی');
$response->assertSee('عضویت‌های من');
$response->assertSee('ناظر');
```

Also assert official chain uses `GovernanceArea` names, not arbitrary Location ancestors.

- [ ] **Step 2: Verify RED**

- [ ] **Step 3: Build focused controller view model**

Group memberships by `public`, `profession`, `specialty`, `age`, `gender`, then by active/base versus observer/upstream. Do not assume reference count 81.

- [ ] **Step 4: Add responsive Blade page and navigation entry**

Use text labels in addition to badges/colors. Link residence edit from the page.

- [ ] **Step 5: Run page/navigation tests GREEN**

- [ ] **Step 6: Commit checkpoint**

```bash
git add app/Http/Controllers/LocationGovernance/MyLocationGovernanceController.php resources/views/location-governance/my-location-governance.blade.php routes/location-governance.php resources/views/partials/sidebar-unified.blade.php tests/Feature/LocationGovernance/MyLocationGovernancePageTest.php
git commit -m "feat: add my location and governance page"
```

---

### Task 9: Add Policy-Safe Community Area UX

**Files:**
- Create or modify focused user Community controller under `app/Http/Controllers/LocationGovernance/`
- Modify: `routes/location-governance.php`
- Modify: `resources/views/location-governance/my-location-governance.blade.php`
- Test: `tests/Feature/LocationGovernance/CommunityAreaUiTest.php`
- Regression: existing `CommunityAreaCreationTest` / `CommunityElectionBoundaryTest`

**Interfaces:**
- Consumes: `CommunityCreationPolicy` and `CommunityAreaService::createFor(Location $location, User $actor)`.
- Produces idempotent create/view action only for approved eligible micro-locations.

- [ ] **Step 1: Write RED UI/policy tests**

Prove:
- eligible approved complex/building gets create action;
- existing Community gets view/open state;
- pending proposal gets no create action;
- ineligible Location gets none;
- repeated create returns same Community;
- Community remains outside formal election topology.

- [ ] **Step 2: Verify RED**

- [ ] **Step 3: Implement controller that delegates entirely to policy/service**

No duplicated eligibility logic in Blade.

- [ ] **Step 4: Render Community section separately from official chain**

Copy must explicitly say Community is not automatically an official systemic-election tier.

- [ ] **Step 5: Run Community regressions GREEN**

- [ ] **Step 6: Commit checkpoint**

```bash
git add app/Http/Controllers/LocationGovernance routes/location-governance.php resources/views/location-governance/my-location-governance.blade.php tests/Feature/LocationGovernance/CommunityAreaUiTest.php
git commit -m "feat: add policy safe community area UX"
```

---

### Task 10: Complete Admin Location/Governance Control Center

**Files:**
- Modify: `app/Http/Controllers/Admin/LocationGovernanceController.php`
- Modify: `resources/views/admin/location-governance/index.blade.php`
- Create focused partials under `resources/views/admin/location-governance/` if needed for proposal/reference/topology/community/diagnostics sections
- Test: `tests/Feature/Admin/LocationGovernanceControlCenterTest.php`

**Interfaces:**
- Consumes: canonical Location, LocationProposal, GovernanceArea, Community, import-run data and existing Hoda review summaries.
- Produces operational read models and existing human-gated mutation forms; no raw topology mutation introduced by this phase.

- [ ] **Step 1: Add RED control-center contracts**

Assert sections for:
- proposal filters/status/evidence/audit;
- reference Location explorer summary;
- official Governance topology summary;
- Community overview;
- import diagnostics;
- health diagnostics.

- [ ] **Step 2: Verify RED**

- [ ] **Step 3: Add bounded read queries/view models**

Health indicators include at minimum open proposals, proposals above review threshold, invalid/unresolved residence intents, Locations missing schema/type where canonical runtime expects them, and governance mappings with missing/inactive Location references where applicable.

- [ ] **Step 4: Split Blade into focused partials if size materially increases**

Keep approve/reject/merge/request-evidence POST actions explicit and CSRF-protected; Hoda remains recommendation-only.

- [ ] **Step 5: Run admin control-center tests GREEN**

- [ ] **Step 6: Commit checkpoint**

```bash
git add app/Http/Controllers/Admin/LocationGovernanceController.php resources/views/admin/location-governance tests/Feature/Admin/LocationGovernanceControlCenterTest.php
git commit -m "feat: complete location governance control center"
```

---

### Task 11: UX Hardening for Alternate Schemas, Errors, Accessibility, and Responsive States

**Files:**
- Modify: `resources/js/location-selector.js`
- Modify canonical registration/profile/admin/location-governance Blade files touched above
- Extend relevant Feature and JS contract tests

**Interfaces:**
- Consumes all earlier UI contracts.
- Produces explicit loading/empty/error/duplicate/stale/resolved states across desktop/mobile, keyboard-friendly controls, and localization-ready copy.

- [ ] **Step 1: Add RED edge-case tests**

Required cases:
- street directly to complex;
- street to alley to complex;
- building as endpoint;
- village endpoint without neighborhood;
- country schema with different branching;
- duplicate proposal returned as existing Location;
- reusable proposal returned instead of duplicate;
- proposal changes state while picker is open;
- network failure preserves form state;
- inactive parent causes server validation rather than silent corruption.

- [ ] **Step 2: Verify RED**

- [ ] **Step 3: Implement explicit client states and accessible labels**

Buttons/selects must have labels; badges use text; no correctness depends only on color. Keep dynamic levels progressive and arbitrary-depth.

- [ ] **Step 4: Run responsive/JS/location-governance targeted suites GREEN**

- [ ] **Step 5: Build production assets**

Run: `npm run build`

Expected: successful Vite build with no new compile errors.

- [ ] **Step 6: Commit checkpoint**

```bash
git add resources/js/location-selector.js resources/views tests
git commit -m "fix: harden location governance UX states"
```

---

### Task 12: Stage-C UI Completion Integration Gate and Release Readiness

**Files:**
- Test-only adjustments if a test reveals a real missing regression guard; no feature expansion in this task.
- Update docs/checklist only if repository convention requires a release note/checkpoint record.

**Interfaces:**
- Consumes all prior tasks.
- Produces a release candidate suitable for PR review while feature flags remain dark until separate Production cutover approval.

- [ ] **Step 1: Run focused Location/Governance suite**

Run the repository's Location/Governance feature test group/path including all new tests.

Expected: PASS with no failures/errors.

- [ ] **Step 2: Run affected admin/profile/registration/invitation/group regression suites**

At minimum include:
- canonical registration/profile residence;
- admin users;
- Location/Governance control center;
- canonical group membership/reconciliation;
- Community boundaries;
- profile completion/invitations.

Expected: PASS.

- [ ] **Step 3: Run project-required responsive/frontend validation**

Run current repository Responsive workflow/test command and `npm run build`.

Expected: PASS.

- [ ] **Step 4: Run Full Validation on exact candidate commit**

Use the repository's existing Full Validation workflow on the exact branch head. Do not merge if any required gate fails.

Expected: all required jobs PASS, including Najm Bahar, governance, group chat, Najm Hoda, JavaScript, full PHPUnit, and regression gate.

- [ ] **Step 5: Verify dark-launch safety before PR handoff**

Confirm code defaults remain false and no commit changes Production `.env` or enables Location/Governance flags. Do not change Production flags as part of this plan.

- [ ] **Step 6: Final branch review**

Compare branch to `main`; verify changes are limited to this spec, migrations are additive/non-destructive, no legacy data is dropped, and no unrelated UI refactor slipped in.

- [ ] **Step 7: Commit any test-only release guard, then stop for merge approval**

```bash
git status
git log --oneline --decorate main..HEAD
```

Do not merge to `main` without the user's explicit approval after exact-candidate validation.

---

## Dependency Order and Checkpoints

1. Task 1 establishes the persistence boundary.
2. Task 2 establishes safe domain lifecycle/convergence.
3. Tasks 3–4 establish the one shared picker contract.
4. Tasks 5–7 migrate registration/profile/admin consumers onto that contract.
5. Tasks 8–9 expose user governance/community understanding without changing authority boundaries.
6. Task 10 completes admin operations/visibility.
7. Task 11 hardens cross-schema UI/UX.
8. Task 12 is the exact-candidate integration gate.

A reviewer may reject any checkpoint independently. Later tasks must not compensate for a broken earlier contract.

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
- Active vs Observer membership visualization: Task 8.
- Community Area separation/policy: Task 9.
- Admin topology/proposals/import/diagnostics: Task 10.
- Accessibility/responsive/error/concurrency states: Task 11.
- Dark launch and full regression/release safety: Task 12.

No `TBD`, `TODO`, automatic Governance creation from proposals, or Production flag activation is part of this plan.
