# EarthCoop Temporal System Implementation Plan

> **Execution status — 2026-10-07:** This plan is no longer a purely prospective checklist. The central Temporal architecture and Communication Center integration are merged into `main` through PR #219 / merge commit `8b12a8e0554436a3c4c1b973748bd1c8168b36ea`. Final integration gates passed: Temporal System Targeted Gate #569, Responsive Contract Validation #1024, and Integration Full Validation #3948.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a single EarthCoop temporal layer that keeps UTC canonical, renders Jalali for Persian and Gregorian for other locales, separates LocalDate from Instant, and progressively migrates direct date/calendar usage behind stable internal interfaces.

**Architecture:** `app/Temporal` now exists as the canonical bounded context with value objects, context resolution, calendar adapters and a TemporalService. Product surfaces have been migrated vertically with targeted tests; Chronicle remains explicitly deferred and consumes Temporal one-way later.

**Tech Stack:** Laravel 12, PHP 8.2+, Carbon/CarbonImmutable, morilog/jalali, Blade, Vite/JavaScript, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-10-01-earthcoop-temporal-system-chronicle-design.md`

## Global Constraints
- Canonical application/storage timezone remains UTC.
- `fa` defaults to Jalali; `en`, `ar`, and unknown locales default to Gregorian.
- EarthCoop Year is not part of Temporal System.
- `birth_date` is LocalDate, not Instant.
- Business logic never depends on localized display strings.
- No destructive production data migration without a separate audit/checkpoint.
- No mandatory external CDN for core date input.
- Prefer CarbonImmutable in new temporal code.
- Targeted tests are the normal gate; Full Validation only at merge-candidate milestones.

## Review Focus
1. Midnight/timezone boundary can change the civil day for the same Instant.
2. Jalali leap boundaries (Esfand 29/30 and Farvardin 1) round-trip correctly.
3. Latin, Persian and Arabic-Indic digits normalize identically.
4. Invalid/missing timezone falls back deterministically without inventing sensitive deadline semantics.
5. Known legacy localized inputs are temporarily accepted and observable during migration.

---

### Task 1: Temporal Core Contract
**Files:**
- Create `config/temporal.php`
- Create `app/Temporal/Context/TemporalContext.php`
- Create `app/Temporal/ValueObjects/LocalDate.php`
- Create `app/Temporal/Contracts/CalendarAdapter.php`
- Create `app/Temporal/Contracts/TemporalService.php`
- Test `tests/Unit/Temporal/TemporalContextTest.php`
- Test `tests/Unit/Temporal/LocalDateTest.php`

**Produces:** `TemporalContext::create(...)`, `LocalDate::fromCanonical()`, `LocalDate::toCanonical()`, core contracts and locale/calendar policy.

- [ ] Write RED tests for locale defaults, timezone validation and LocalDate round-trip.
- [ ] Run `php artisan test tests/Unit/Temporal/TemporalContextTest.php tests/Unit/Temporal/LocalDateTest.php` and observe expected failure.
- [ ] Implement minimal immutable value objects/contracts/config.
- [ ] Run the targeted tests GREEN.
- [ ] Commit `feat: establish temporal core contracts`.

### Task 2: Calendar Adapters and Digit Normalization
Create Gregorian/Jalali adapters, DigitNormalizer and unit tests. Prove `2026-10-01 ↔ 1405/07/09`, Persian and Arabic-Indic digit normalization, invalid day/month rejection and Jalali leap round-trip. Only Jalali adapter may know Morilog.

### Task 3: Context Resolution and Temporal Manager
Create TemporalContextResolver, TemporalManager, provider bindings and tests. Prove `fa`→Jalali, `en/ar`→Gregorian, timezone fallback, midnight differences and recipient context independent of process locale.

### Task 4: Blade Presentation Components
Create `<x-temporal.date>`, `<x-temporal.date-time>`, `<x-temporal.relative>` and tests for localized output, semantic `<time datetime>` and RTL-safe machine values.

### Task 5: Registration and Birth Date Migration
Migrate Step1Controller/register view and age policy. Prove Persian Jalali input, English Gregorian input, same canonical birth date, exact minimum-age boundary, invalid leap/day rejection and Google OAuth regression safety.

### Task 6: Elections Temporal Migration
Migrate election notifications/reminders/views. Preserve lifecycle semantics. Prove locale-specific rendering, recipient context and canonical deadline comparison.

### Task 7: Groups, Posts, Messages and Social Surfaces
Replace direct Verta usage on migrated social surfaces; prove relative/absolute localized rendering and no Jalali leakage into English UI.

### Task 8: Najm Bahar, Stock and Auction Migration
Migrate transaction exports, auction parsing/rendering, stock reports and Najm Bahar reports. Prove human localization, machine canonical output, canonical auction instants, domain validation and localized report boundaries.

### Task 9: Communication Center Recipient-Aware Dates
Migrate communication context builders. Prove same event renders Jalali for Persian recipient and Gregorian for English recipient regardless of queue process locale; retry/idempotency unchanged.

### Task 10: Admin, Reports, Analytics and Exports
Migrate hard-coded `fa-IR`, direct controller Jalali parsing and date filters. Prove Jalali month queries use real Jalali boundaries and rolling aggregation stays calendar-neutral.

### Task 11: Frontend Date Input Consolidation
Create official local/bundled date input path. Remove mandatory CDN dependency from core forms. Prove locale picker configuration and server-side canonical revalidation.

### Task 12: Architecture Guard and Legacy Removal
Add a first-party architecture test forbidding unapproved direct `Morilog\\Jalali`, `verta(` and hard-coded `toLocaleDateString('fa-IR')`. Shrink temporary allowlist as surfaces migrate; remove stale duplicate picker paths only when proven unused.

### Task 13: Final Temporal Verification
Run Temporal unit/feature suites, auth/registration, elections, communication, Najm Bahar/Stock targeted gates, repository debt scan, then one Full Validation on the merge candidate. Verify no unintended destructive migration or external runtime calendar CDN dependency.

## Delivery Checkpoints
- A: Tasks 1–4 — Temporal Foundation
- B: Tasks 5–6 — Identity/Elections
- C: Tasks 7–10 — Product Surfaces
- D: Tasks 11–12 — Boundary Closure
- E: Task 13 — Merge Candidate

## Deferred to Chronicle Plan
`/chronicle`, EarthCoop epoch/year calculation, milestones, historical timeline and Chronicle admin are intentionally outside this plan.


---

## Implementation reconciliation — 2026-10-07

### Completed and merged

- Temporal core contracts, context resolver, Gregorian/Jalali adapters, digit normalization and `TemporalManager`.
- Shared Blade presentation components and bundled/localized date input path.
- Canonical LocalDate handling for birth date and age policy.
- Temporal migrations across registration, elections, major group/social surfaces, Najm Bahar, Stock/Auction, admin/report surfaces and Communication Center.
- Repository architecture ratchet preventing direct first-party legacy Jalali APIs outside the canonical Jalali adapter; current known direct legacy-calendar debt baseline is empty.
- Communication scheduling semantics hardened to canonical UTC clocks, explicit schedule timezone, planned occurrence persistence, drift-resistant recurrence and timezone-aware weekly-report periods.
- Final merged validation gates: Temporal #569, Responsive #1024, Full Validation #3948.

### Repository-wide UI closure

The residual human-facing Temporal debt identified during the reconciliation has now been closed.

PR #223 migrated remaining raw `datetime-local` inputs and direct human-facing Blade date rendering through the shared Temporal input/presentation boundary, added canonical/ISO compatibility at the parser boundary, and extended the architecture ratchet.

PR #225 then closed the date-only layer by migrating raw `type="date"` surfaces and local-date filters/persistence paths through Temporal parsing while preserving canonical `Y-m-d` machine compatibility.

The first-party Blade architecture ratchet now forbids raw `datetime-local`, raw `type="date"`, direct `diffForHumans()` and the known direct human-facing date-format patterns. The direct legacy-calendar debt baseline remains empty outside the canonical Jalali adapter.

Final validation for PR #225:
- Temporal System Targeted Gate #577 — success
- Iran 1404 Reference Staging Validation #462 — success
- Integration Full Validation #3963 — success

Accordingly, the repository-wide Temporal presentation/input migration is considered closed as of 2026-10-07.

### Chronicle status

The earlier “not implemented” reconciliation was incorrect. Chronicle / «گاه‌شمار EarthCoop» already had a public `/chronicle` route/page, the constitutional epoch `1401/01/01 SH = 2022-03-21 = EarthCoop Year 1`, an EarthCoop-year calculator, localized copy and Chronicle tests outside Temporal core.

The Chronicle completion work adds the missing product layer: canonical milestone persistence, multilingual milestone content, admin management, public milestone rendering, navigation and sitemap discovery. EarthCoop Year is derived and never stored. Chronicle remains a one-way consumer of Temporal and does not participate in operational business-date logic.

Detailed completion contract: `docs/superpowers/plans/2026-10-07-earthcoop-chronicle-completion.md`.
