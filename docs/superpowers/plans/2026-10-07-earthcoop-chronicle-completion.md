# EarthCoop Chronicle Completion — Implementation Record

**Date:** 2026-10-07  
**Bounded context:** `app/Chronicle`  
**Dependency direction:** Chronicle → Temporal only

## Baseline discovered during reconciliation

Chronicle was not actually “planned only”. Before this completion work, the repository already contained:

- public `/chronicle` registered by `ChronicleServiceProvider`;
- `EarthCoopEpoch` locking the constitutional epoch at `2022-03-21` / 1 Farvardin 1401;
- `EarthCoopYearCalculator` deriving EarthCoop Year as Jalali year minus 1400 after the epoch;
- a public Chronicle page showing today in Jalali/Gregorian form, EarthCoop Year and the epoch;
- FA/EN/AR Chronicle localization files;
- unit/feature tests protecting epoch/year semantics and the Temporal/Chronicle boundary.

The prior documentation statement that there was no `/chronicle` page or runtime Chronicle domain was therefore stale and has been corrected.

## Completion scope

The remaining approved Chronicle product contract is implemented by adding:

1. `chronicle_milestones` persistence.
2. Canonical `occurred_on` date storage.
3. Multilingual FA/EN/AR title and description payloads.
4. Publish/draft state and deterministic same-date ordering.
5. Soft deletion plus creator/updater attribution.
6. Admin create/edit/update/delete management under the canonical admin boundary.
7. Temporal-aware date input/parsing for milestone dates.
8. Public rendering of **published only** milestones on `/chronicle`.
9. Derived EarthCoop Year per milestone, never a stored database field.
10. Public header/footer discovery plus sitemap inclusion.
11. Public-page protection during the short deploy-before-migrate window: if the milestone table does not exist yet, `/chronicle` still renders the epoch/today shell with an empty timeline.

## Data contract

`chronicle_milestones` stores historical content only:

- `occurred_on`: canonical Gregorian `DATE` used as storage/interchange form;
- `title_translations`: JSON locale map;
- `description_translations`: nullable JSON locale map;
- `is_published`: public visibility;
- `sort_order`: deterministic ordering for milestones sharing a date;
- creator/updater IDs, timestamps and soft-delete timestamp.

There is intentionally **no** `earthcoop_year` column. EarthCoop Year is derived from `occurred_on` by `EarthCoopYearCalculator`.

## Boundary guarantees

- Chronicle may call Temporal for parsing/formatting.
- Temporal core must never import Chronicle/EarthCoop-era semantics.
- Elections, Najm Bahar, groups, communications and other operational modules continue using ordinary canonical Temporal contracts and do not read Chronicle.
- The epoch remains a version-controlled constitutional constant, not an admin setting.
- Draft milestones never appear on the public timeline or public sitemap last-modified derivation.

## Validation contract

Feature coverage verifies:

- an admin can submit a Persian/Jalali date and the milestone stores the correct canonical date;
- EarthCoop Year is not persisted;
- public Chronicle exposes published milestones and hides drafts;
- active locale selects milestone translations with deterministic fallback;
- non-admin users cannot access Chronicle milestone management;
- the public sitemap contains `/chronicle`;
- the existing epoch/year boundary tests remain green.

## Deployment

This completion introduces one additive migration:

`2026_10_07_000001_create_chronicle_milestones_table.php`

After deployment, run the normal production migration command. The public Chronicle page remains safe before the migration runs, but admin milestone management requires the table.

## Completion criterion

Chronicle is considered complete when the milestone/admin/public/navigation implementation is merged, targeted Temporal/Chronicle validation is green, Full Validation is green, and documentation reflects the actual repository state.
