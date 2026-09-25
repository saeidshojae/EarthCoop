# Location/Governance UAT — Checkpoint 1: naming and current-flow regression

**Scope:** close the first UAT checkpoint without broad redesign or Production data writes.  
**Source baseline:** `aeb12e4d1fdc2b7217fb1e9d1d344a727ff564d7` (Full Validation #3193 and Responsive #677 passed).  
**Candidate:** use the final HEAD of PR #133 after its own green CI; do not substitute the baseline SHA as evidence for later changes.

## Evidence already observed in local UAT

- User completed pending urban-region-without-neighborhood registration; no forced Street selection; the saved primary residence and group counts matched the chosen governance path.
- User completed reference-village-without-neighborhood registration; structural choices were visible after explicit reference schema metadata reconciliation.
- A later UAT found that the same pending village was represented by both an official observer membership and a pending shell. The presentation-only deduplication fix retained the official observer row and reconciled Home, My Groups, desktop/mobile navigation and My Location/Governance.
- At the green baseline the user inspected the pages again: public groups **8** (one active, seven observer), profession/specialty **48**, age/gender **16**, total **72**. Pending village appeared once; its name displayed in Persian in My Groups and My Location/Governance. This is user-provided visual evidence, not an independently captured Production test.
- Existing local governance reference data may still carry the older Persian name even when the Location row and pending shell display the newer Persian label. A green source/test run does not update an existing local database by itself.

## Confirmed naming defect and decision

The reference external ID `IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-NONEIGHBORHOOD` links **one** Location to **one** official GovernanceArea. Their earlier source labels disagreed:

| Record | Earlier canonical | Earlier Persian |
| --- | --- | --- |
| Location | Reference Village Without Neighborhood | روستای مرجع بدون محله |
| Official GovernanceArea | Chahardangeh Village Without Neighborhood | روستای چهاردانگه بدون محله |

This row is a synthetic reference/UAT fixture. For this **existing fixture only**, align the official GovernanceArea canonical/English/Persian names with its linked Location: `Reference Village Without Neighborhood` / `روستای مرجع بدون محله`. **Do not create, merge, re-ID or delete either record.** This decision does not rename any real user-created village or change the broader country schema.

The existing versioned topology importer now compares localized names in its dry-run, in addition to canonical name, type, parent and mappings. Its guarded ownership check remains in force. An authorized apply updates the same reference GovernanceArea identity; it is not an automatic migration or a general rename tool.

## Source fixes covered by this checkpoint

1. Pending structural and proposal group names read current localized Location/Proposal data rather than an old canonical-name snapshot; the same correction is used by all user-facing group-count surfaces.
2. `LocationDisplayName::typed()` recognizes `روستای` as an existing village prefix, avoiding duplicate `روستا روستای ...`.
3. `GovernanceAreaDisplayName` provides locale, base-language, and canonical fallbacks; registration residence continent/project-scope labels and administrative topology/Community names use locale-aware names.
4. My Location/Governance uses the active locale for area and location labels and retains the official GovernanceArea name when it is explicitly localized. A mapped Location is only a fallback when that area lacks a locale label.
5. The admin proposal queue and reference explorer show localized names; the canonical-name **edit input intentionally remains canonical** for explicit human editing. No automatic rewriting of canonical proposal data occurs.
6. The project-scope bridge falls back from regional locale (e.g. `fa-IR`) to `fa` before canonical strings.

Localized *display names* are distinct from machine-readable type/status/review codes that appear in technical administrator diagnostics. Translating those status and recommendation codes is a separate UX/i18n work item, not authorization to change API keys or persisted state during this checkpoint.

## Regression checks added / updated

- Unit: localized official GovernanceArea locale fallback and typed Persian village prefix.
- Feature: imported reference Location/Governance names agree, and a localized-name-only drift is visible in dry-run and corrected idempotently without changing IDs or the Location-to-Governance mapping.
- Feature: admin queue/reference/topology names display in Persian without changing editable canonical values.
- Feature: project scope returns Persian labels for `fa-IR`; My Location/Governance respects the selected language.
- Existing PHP, JS, elections, group-count and responsive gates remain mandatory.

## Explicitly outstanding before checkpoint sign-off

1. Run targeted tests and **Full Validation / Responsive Contract** on the exact final HEAD; investigate any regression rather than changing old assertions to hide a contract break.
2. On the user's **local UAT database only**, after the code update and green CI, run:
   `php artisan location-governance:reference-topology IR --dataset-version=v1 --dry-run`
   Review **all** reported create/update/conflict counts. The expected limited change in the already imported reference fixture is one official GovernanceArea rename, but actual local drift may produce other counts. If counts differ, STOP and inspect; do not assume the desired one-row operation.
3. Only after the owner reviews the dry-run and approves the precise local apply, run the existing reviewed reference-topology apply procedure. Never run it automatically or against Production under this UAT checkpoint.
4. Re-check the **same existing village test account** on Home, My Groups, My Location/Governance and the admin reference/topology pages. The reference village should have the same Persian label at the Location, official GovernanceArea, and pending group surfaces, without changing total group counts (72 for that test profile).
5. Record the final SHA, CI run IDs, local dry-run/apply results and UAT pass/fail. Only then mark checkpoint 1 closed and start checkpoint 2.

**Outside this checkpoint:** full claim approval/rejection lifecycle UAT, one-neighborhood variants, election and other consumer UAT, PR-wide merge audit, Production rollout and post-rollout responsive checks. No destructive Production/legacy operation is permitted here.
