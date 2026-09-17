# Production Residence Selector Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore the Production registration/profile residence picker so imported Iran geography traverses correctly, existing canonical residence paths hydrate from root to endpoint, and Persian UI consistently renders localized location names.

**Architecture:** Keep the existing schema-driven Location/Governance API and shared selector core. Add real-reference-data regression coverage, make canonical residence hydration a first-class server path (not only a pending-proposal path), replay any persisted residence path in the browser, and centralize location display-name precedence without changing minimum-depth/support/pending-group rules in this release.

**Tech Stack:** Laravel 9 / PHP, Eloquent, Blade, Vite ES modules, Node built-in test runner.

**Spec:** `docs/superpowers/specs/2026-09-17-residence-proposal-support-and-pending-groups-design.md` — implements Release 1 / Track A only.

## Global Constraints

- Work only on `agent/location-production-uat-hardening-20260917`; never edit `main` directly.
- Follow RED → verify RED → minimal GREEN → full regression for every production-code change.
- Do not change Production flags or mutate Production data in this release.
- Do not implement structural claims, committed-support automation, minimum-depth policy changes, or pending-group schema yet.
- Preserve the existing project-scope contract: project market scope may stop at any geographic level.
- Preserve open proposal statuses (`pending`, `ready_for_review`, `needs_evidence`) and deep proposal hydration.
- Persian display precedence is `localized_names.fa` (or current locale) first, then Persian fallback where appropriate, then canonical/name fallback.
- Final candidate must pass Location/Governance PHP, Location/Governance JavaScript, Najm Hoda, Najm Bahar, Stock, Full Project PHPUnit, and the regression gate.

---

### Task 1: Lock the real imported Iran traversal contract

**Files:**
- Create: `tests/Feature/LocationGovernance/ReferenceLocationSelectionProductionRegressionTest.php`
- Read/verify: `app/Services/LocationGovernance/Import/ReferenceGeographyImporter.php`
- Read/verify: `app/Http/Controllers/LocationGovernance/LocationOptionsController.php`

**Interfaces:**
- Consumes: `ReferenceGeographyImporter::import('IR', 'v1', true)` and public `GET /location/options/{location}/children`.
- Produces: a regression proving the actual `IR-COUNTRY → IR-MAZ-001 → IR-MAZ-SARI-COUNTY` imported branch is traversable and localized.

- [ ] **Step 1: Write the real-reference regression test**

```php
public function test_real_iran_reference_root_traverses_to_mazandaran_and_sari_county_in_persian(): void
{
    config(['location-governance.runtime_enabled' => true]);
    app()->setLocale('fa');

    app(ReferenceGeographyImporter::class)->import('IR', 'v1', true);

    $iran = LocationExternalId::query()
        ->where('source', ReferenceGeographyImporter::SOURCE)
        ->where('dataset_version', 'v1')
        ->where('external_id', 'IR-COUNTRY')
        ->firstOrFail()
        ->location;

    $provinceResponse = $this->getJson('/location/options/'.$iran->id.'/children');
    $provinceResponse->assertOk();
    $mazandaran = collect($provinceResponse->json('data'))->firstWhere('label', 'مازندران');
    $this->assertNotNull($mazandaran);

    $countyResponse = $this->getJson('/location/options/'.$mazandaran['id'].'/children');
    $countyResponse->assertOk();
    $this->assertTrue(collect($countyResponse->json('data'))->contains(
        fn (array $row): bool => $row['label'] === 'شهرستان ساری'
    ));
}
```

- [ ] **Step 2: Run the focused test**

Run:
```bash
php artisan test tests/Feature/LocationGovernance/ReferenceLocationSelectionProductionRegressionTest.php
```

Expected: if it fails, the failure must identify the imported API/data-contract defect before any patch. If it passes, retain it as missing Production-regression coverage and do not change the API merely to manufacture a RED.

- [ ] **Step 3: If and only if the focused test fails, apply the smallest API/import fix**

Allowed production files are limited to the exact failing contract (`LocationOptionsController`, `ReferenceGeographyImporter`, or schema relation/import code). Do not hard-code Iran IDs in runtime code; the production fix must remain schema-driven.

- [ ] **Step 4: Re-run the focused test until GREEN**

```bash
php artisan test tests/Feature/LocationGovernance/ReferenceLocationSelectionProductionRegressionTest.php
```

- [ ] **Step 5: Commit the regression/API fix**

```bash
git add tests/Feature/LocationGovernance/ReferenceLocationSelectionProductionRegressionTest.php app/Http/Controllers/LocationGovernance/LocationOptionsController.php app/Services/LocationGovernance/Import/ReferenceGeographyImporter.php
git commit -m "test: cover production Iran location traversal"
```

Only add production files that actually changed.

---

### Task 2: Hydrate a canonical residence path even when no proposal is pending

**Files:**
- Create: `tests/Feature/LocationGovernance/ProfileCanonicalResidenceHydrationTest.php`
- Modify: `app/Http/Controllers/LocationGovernance/ProfileEditController.php`
- Modify: `resources/views/profile/partials/location_canonical.blade.php`

**Interfaces:**
- Consumes: current `primary_residence` relationship and optional `PendingResidenceIntent`.
- Produces: `residenceHydrationPath` containing root→canonical endpoint for ordinary residence, with open proposal identities appended when a pending intent exists.

- [ ] **Step 1: Write the failing canonical hydration test**

Create a canonical path `country → province → county → section → city → urban_region → neighborhood`, set it as the user's current primary residence, render `profile.edit`, and assert:

```php
$response->assertSee('data-location-current-path', false);
$response->assertSeeInOrder([
    'location:'.$country->id,
    'location:'.$province->id,
    'location:'.$county->id,
    'location:'.$section->id,
    'location:'.$city->id,
    'location:'.$region->id,
    'location:'.$neighborhood->id,
], false);
```

Also assert the hidden canonical `location_id` remains the neighborhood ID and `location_proposal_id` is empty.

- [ ] **Step 2: Verify RED**

```bash
php artisan test tests/Feature/LocationGovernance/ProfileCanonicalResidenceHydrationTest.php
```

Expected current failure: `ProfileEditController::residenceHydrationPath()` returns `[]` whenever no pending intent exists.

- [ ] **Step 3: Generalize server hydration**

Refactor the controller so hydration is built from the canonical current residence first, then any pending proposal chain is appended. The canonical helper must walk `Location::parent()` with cycle protection and return identities in root→leaf order. The proposal helper must retain the existing pending-chain cycle protection.

Required resulting behavior:

```php
$residenceHydrationPath = $this->residenceHydrationPath(
    $primaryResidence?->location,
    $pendingResidenceIntent,
);
```

- [ ] **Step 4: Re-run the focused test and existing deep-proposal test**

```bash
php artisan test tests/Feature/LocationGovernance/ProfileCanonicalResidenceHydrationTest.php tests/Feature/LocationGovernance/DeepProposalReviewWorkflowTest.php
```

Expected: both GREEN; deep pending hydration remains root→anchor→proposal parent→deepest proposal.

- [ ] **Step 5: Commit canonical hydration**

```bash
git add app/Http/Controllers/LocationGovernance/ProfileEditController.php resources/views/profile/partials/location_canonical.blade.php tests/Feature/LocationGovernance/ProfileCanonicalResidenceHydrationTest.php
git commit -m "fix: hydrate canonical residence path"
```

---

### Task 3: Replay persisted canonical paths in the browser

**Files:**
- Modify: `resources/js/registration-location-ux.js`
- Create or modify: `tests/js/location-governance/residence-hydration.test.mjs`
- Preserve: `resources/js/location-selector-core.js`

**Interfaces:**
- Consumes: `data-location-current-path`, `data-location-current-id`, `data-location-current-proposal-id`.
- Produces: sequential change-event replay for canonical-only and canonical+proposal paths, with final hidden IDs restored consistently.

- [ ] **Step 1: Write a failing JavaScript contract**

The test must load `registration-location-ux.js` source and prove the canonical path is not gated by proposal presence. At minimum assert the old guard is absent and replay is path-driven:

```js
assert.match(source, /if \(!levels \|\| !currentPath\.length\) return;/);
assert.doesNotMatch(source, /!currentProposalId\) return/);
assert.match(source, /dispatchEvent\(new Event\('change'/);
```

Also assert final state distinguishes a canonical endpoint from a proposal endpoint instead of unconditionally clearing `location_id`.

- [ ] **Step 2: Verify RED**

```bash
npm run test:location-governance
```

Expected current failure: `replayPersistedPath()` contains `|| !currentProposalId` and therefore skips canonical-only paths.

- [ ] **Step 3: Implement minimal canonical/proposal replay**

Change `replayPersistedPath()` to run whenever `currentPath.length > 0`. Replay each identity sequentially, waiting for the corresponding select. After replay:

- canonical terminal identity → keep/restore `location_id = currentLocationId`, clear proposal ID;
- proposal terminal identity → clear canonical hidden ID, keep/restore `location_proposal_id = currentProposalId`;
- do not enable submit merely because replay happened; allow selector state/endpoint policy to determine validity, except preserving a previously valid stored endpoint.

- [ ] **Step 4: Re-run JS tests**

```bash
npm run test:location-governance
```

Expected: GREEN.

- [ ] **Step 5: Commit browser hydration**

```bash
git add resources/js/registration-location-ux.js tests/js/location-governance/residence-hydration.test.mjs
git commit -m "fix: replay canonical residence selection"
```

---

### Task 4: Use one localized display-name rule for profile residence state

**Files:**
- Create: `app/Support/LocationDisplayName.php`
- Modify: `resources/views/profile/partials/location_canonical.blade.php`
- Modify: `app/Http/Controllers/LocationGovernance/LocationOptionsController.php`
- Modify: `tests/Feature/LocationGovernance/ProfileCanonicalResidenceHydrationTest.php`
- Modify: `tests/Feature/LocationGovernance/LocationSelectionApiTest.php`

**Interfaces:**
- Produces: `LocationDisplayName::for(Location|LocationProposal $model, ?string $locale = null): string`.
- Precedence: requested/current locale → Persian fallback for Persian UI → canonical name → legacy name.

- [ ] **Step 1: Extend tests to fail on English profile summary under Persian locale**

Create a neighborhood with:

```php
'canonical_name' => 'Sari Reference Neighborhood',
'localized_names' => ['fa' => 'محله مرجع ساری'],
```

Then assert the Persian profile page contains `محله مرجع ساری` and does not render `Sari Reference Neighborhood` as the current-residence label.

- [ ] **Step 2: Verify RED**

```bash
php artisan test tests/Feature/LocationGovernance/ProfileCanonicalResidenceHydrationTest.php
```

Expected current failure: the Blade uses `canonical_name ?: name` directly.

- [ ] **Step 3: Add the display-name helper and reuse it**

Implement the helper so both canonical locations and proposals resolve labels consistently. Replace direct canonical-name reads in the current-residence and pending-residence blocks. Update `LocationOptionsController::serialize()` / `serializeProposal()` to use the same helper without changing response shape.

- [ ] **Step 4: Re-run localization/API tests**

```bash
php artisan test tests/Feature/LocationGovernance/ProfileCanonicalResidenceHydrationTest.php tests/Feature/LocationGovernance/LocationSelectionApiTest.php
```

Expected: GREEN.

- [ ] **Step 5: Commit localization consistency**

```bash
git add app/Support/LocationDisplayName.php app/Http/Controllers/LocationGovernance/LocationOptionsController.php resources/views/profile/partials/location_canonical.blade.php tests/Feature/LocationGovernance/ProfileCanonicalResidenceHydrationTest.php tests/Feature/LocationGovernance/LocationSelectionApiTest.php
git commit -m "fix: localize canonical residence labels"
```

---

### Task 5: Add Production data-health evidence for the target reference root

**Files:**
- Modify: `tests/Feature/LocationGovernance/ProductionReadinessCommandTest.php`
- Modify: `app/Console/Commands/LocationGovernanceReadinessCommand.php`

**Interfaces:**
- Consumes: configured target country/schema/source/version.
- Produces: read-only readiness evidence that the target imported root identity exists, is active, and has at least one active schema-valid child.

- [ ] **Step 1: Write a failing readiness test**

Build reference evidence, then deliberately detach/deactivate the target root's valid first-level children and assert the readiness command fails with a stable diagnostic such as:

```text
reference_traversal: fail
```

The command must remain read-only.

- [ ] **Step 2: Verify RED**

```bash
php artisan test tests/Feature/LocationGovernance/ProductionReadinessCommandTest.php
```

- [ ] **Step 3: Implement the read-only traversal check**

Resolve the target dataset root through `LocationExternalId` for the configured source/version, require an active canonical root, resolve schema-valid child types, and require at least one active child. Do not import, repair, or mutate anything.

- [ ] **Step 4: Re-run readiness tests**

```bash
php artisan test tests/Feature/LocationGovernance/ProductionReadinessCommandTest.php
```

Expected: GREEN.

- [ ] **Step 5: Commit readiness evidence**

```bash
git add app/Console/Commands/LocationGovernanceReadinessCommand.php tests/Feature/LocationGovernance/ProductionReadinessCommandTest.php
git commit -m "test: verify reference location traversal readiness"
```

---

### Task 6: Release-1 regression and candidate verification

**Files:**
- No new product scope.
- Update the plan/progress document only if verification discovers a documented result worth preserving.

**Interfaces:**
- Consumes all Release-1 tasks.
- Produces a PR candidate; no merge/deploy without explicit user approval.

- [ ] **Step 1: Run focused PHP suite**

```bash
php artisan test tests/Feature/LocationGovernance/ReferenceLocationSelectionProductionRegressionTest.php tests/Feature/LocationGovernance/ProfileCanonicalResidenceHydrationTest.php tests/Feature/LocationGovernance/DeepProposalReviewWorkflowTest.php tests/Feature/LocationGovernance/LocationSelectionApiTest.php tests/Feature/LocationGovernance/ProductionReadinessCommandTest.php
```

- [ ] **Step 2: Run Location/Governance JavaScript suite**

```bash
npm run test:location-governance
```

- [ ] **Step 3: Run the repository Full Validation workflow on the exact final HEAD**

Require all gates to succeed, including Location/Governance PHP, Location/Governance JavaScript, Najm Hoda, Najm Bahar, Stock, Full Project PHPUnit, and regression enforcement.

- [ ] **Step 4: Audit branch diff against current `main`**

Confirm no structural-claim, support-automation, pending-group, project-scope, election, or unrelated changes entered Release 1.

- [ ] **Step 5: Open/update PR for review**

Document the exact candidate SHA, Full Validation run, Production symptom covered, and remaining Release 2–4 work. Keep unmerged until explicit approval.

## Self-review

- Spec coverage: this plan covers Track A / Release 1 only: real imported Iran traversal, canonical profile hydration, browser replay, localization, explicit errors/data readiness, and full regression. Releases 2–4 are intentionally excluded.
- Placeholder scan: no TBD/TODO placeholders are present.
- Type consistency: `residenceHydrationPath` remains an ordered list of `location:<id>` / `proposal:<id>` identities; browser replay consumes the same identity format already used by the selector.
- Boundary check: no fake locations, structural claims, committed-support automation, or pending Group rows are introduced here.
