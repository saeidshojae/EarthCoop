# Iran 1404 settlement catalog — dependency-ordered implementation plan

> **STATUS UPDATE — 2026-09-26**  
> This file is now a **historical implementation plan**, not the current backlog. Its original C0–C6 text is preserved below for design provenance. Current status must be read together with `docs/PRE_NATIVE_MOBILE_READINESS_STATUS.fa.md` and current `main`.
>
> **Current baseline:** `main@9b936dd9c9b194098f6736eeac8917ccb2d622f6`.
>
> The original header `PLAN ONLY / Draft` is obsolete as a statement of present implementation state. Major parts of the plan were subsequently implemented and merged through PRs #133, #141 and later Location/Governance checkpoints.
>
> Current reconciliation:
>
> | Original checkpoint | 2026-09-26 status | Evidence / note |
> |---|---|---|
> | C0 — evidence/branch reconciliation | **DONE / SUPERSEDED** | PR chain was reconciled; no-region/no-neighborhood UAT line ultimately merged via #133 and closed structurally via #146. |
> | C1 — neutral settlement catalog | **IMPLEMENTED** | `ReferenceSettlement` runtime/model exists on main; 99,317 neutral settlement identities were imported/validated in isolated UAT evidence. |
> | C2 — evidence classification | **IMPLEMENTED CORE / PARTIAL OPERATIONAL UAT** | review/evidence models and `ReferenceSettlementReviewService` exist; nationwide human classification remains intentionally incomplete. |
> | C3 — user residence claim | **IMPLEMENTED** | settlement residence claim, registration bridge, pending exact residence and pending-group behavior exist on main. |
> | C4 — performance/full-volume isolated validation | **DONE for catalog foundation** | isolated full-volume import/replay evidence was recorded for 99,317 rows; this does not mean nationwide residential classification is complete. |
> | C5 — local E2E UAT/crosswalk | **PARTIAL MANUAL UAT + CUTOVER COMPLETED FOR ADMIN v2** | manual «وری» flow covered search/registration/shared picker/hydration/pending settlement-neighborhood/group presentation; broader support-threshold/admin-evidence/nonresidential/flag/mobile-RTL manual matrix is not fully documented as complete. v1→v2 administrative cutover was later completed via #143–#145. |
> | C6 — release gates | **SPLIT** | automated exact-SHA gates and the Iran v2 administrative cutover were completed; settlement public rollout/classification remains separately feature-gated and is not implied by the v2 admin cutover. |
>
> Important: the original `Immediate next code slice` at the end of this document is **historical and already executed**. Do not start C1 again.

---

## Historical plan text (preserved)

Status at authoring time (2026-09-24): **PLAN ONLY / Draft**. This statement describes the original planning moment only. It does not describe current main.

## Verified baseline (2026-09-24)

- PR #133 head `61dc07af4118b288f5ee396bb492bb0c1435fc6a`: Responsive #691 and Full Validation #3207 succeeded; still Draft. Its name-consistency local UAT and merge approval are separate release gates.
- PR #134 head `395d22ea8892f6d7c97ff6d67c27024552d8df0f`: Iran staging #32 succeeded; still Draft. Parent is PR #133's branch, not main.
- Pinned Iran 1404 source has 105,475 rows: 6,158 non-settlement administrative records and 99,317 `DivisionType=6` settlements. The former have been tested in an isolated MySQL v2 pilot (#22); the latter are retained in NON-IMPORTABLE `settlements.review.jsonl`. Neither means Production data have been changed.
- `DivisionType=6` proves geographic source identity, **not** residential eligibility, village status, a governance area, or voting rights. No lexical rule may promote records. The other 6,158 administrative rows, including the 191 source urban zones, are the canonical 1404 baseline; missing finer/extra levels are handled through the existing proposal workflow rather than legacy-data overrides.
- Current importer is per-row: lookups for identity and parent, location writes and per-item audit inserts inside a single transaction. Do **not** extrapolate the 6,158-row pilot to 105,475-row hosting suitability.

## Invariants (apply at every checkpoint)

1. Preserve all source IDs, source year, source parent and provenance. One source settlement identity must not be recreated as a crowdsourced Location merely because residential evidence is pending.
2. Separate `geographic_known`, `residential_eligibility`, `residence_claim_status`, `governance_authorization`, and `active_group_membership`. None implies the next.
3. A user may finish registration on a pending residence claim under the existing registration rules; pending governance is never silently activated. A claim is not an approval.
4. Existing v1 users, location proposals, group counts, governance areas, election eligibility, and pending structural claims remain unchanged until an approved identity crosswalk and explicit cutover.
5. Location name, type, and address hierarchy must never be used as a substitute for a verified source ID+version+parent match. Conflict -> review, not auto-merge.
6. Every write route must have RBAC, idempotency, authorization, audit, privacy limits, rate limiting where relevant, and transaction-safe retries.
7. Production/real DB remains untouched throughout this plan unless a separate owner-approved migration runbook explicitly names the exact database, dry-run counts, backup, rollback and authorized apply.

## C0 — freeze evidence and reconcile branch dependencies (read-only)

- Record exact PR #133 / #134 heads, required checks, branch compare and actual diff (not only their PR descriptions). Verify PR #134's current changes preserve #133's no-neighborhood registration contract.
- Run a **read-only** inventory of existing users, location relationships, user-suggested locations, governance areas, reference external IDs and election/group memberships in the dedicated local UAT DB; export only counts and anonymized relationship evidence.
- Review PR #133's naming checkpoint and local reference-topology dry-run; do not apply it automatically. Establish the exact UAT fixtures for city without zone, zone without neighborhood, village without neighborhood, and pending village.
- Exit: evidence manifests checked in; no DB write; explicit known blockers remain documented.

## C1 — neutral, non-governance settlement catalog (test-first)

Prefer a distinct indexed source-identity catalog rather than immediately writing all 99,317 unverified settlements into operational `locations`: operational Location schema, route policies and auto-grouping currently attach behaviors to type/level, while catalog entries must not imply residence or governance. The alternative of operational `settlement` requires an equally complete route/permission audit and is not the default.

Proposed dedicated model `reference_settlements` (final naming after actual migration inventory):
- immutable normalized identity: `source`, `dataset_version`, `external_id`; unique composite index.
- `source_code`, `source_row_id`, `parent_external_id`, `name_fa`, `normalized_search_name`; parent/source indexes and stable cursor pagination.
- `classification` initially `unverified_settlement`; `residential_eligibility` initially `unverified`; `governance_authorized=false`; classification evidence linked separately; source/provenance/version and archival status.
- Do not copy incoming `importable=false` into a permanent promise that catalogs cannot import. Instead distinguish `catalog_import_allowed` from `operational_location_promotion_allowed=false`; the latter remains evidence-gated.
- Referential identity to an operational Location, if later approved, is optional and unique, never guessed by name.

RED tests: source ID idempotency, wrong parent, swapped type, duplicate code within source, malformed/modified manifest, no accidental operational Location/Group/GovernanceArea writes, stable paginated search and unicode Persian aliases, non-residential/pending hidden from official residence-only results. Build immutable reader and a **separate** isolated-only, batch-idempotent catalog importer guarded by explicit environment and exact DB identity.

## C2 — evidence classification (test-first)

Evidence table/append-only audit: source authority, source publication date, applicability date/period, source code and parent, stable document checksum/reference, captured reviewer, evidence type, decision, policy version and supersession link. No unsupported claim that 1404 dataset contains population or residential status.

- Match second official dated source only when code + division type + parent chain + date resolve to a single identity. A historic census population count alone is not proof of current residential/legal status.
- Conflicting, absent, moved or duplicated matches -> `needs_review`, retain both identities and history. Verified non-residential remains searchable as geographic place but not residence endpoint.
- Governed promotion to operational village or link to a previously existing village is a separate reviewed action, with crosswalk and downstream impact preview; **residential evidence does not itself create a GovernanceArea**.
- Export classification counts and evidence coverage with a denominator of 99,317; never present unverified records as confirmed villages.

## C3 — user residence claim on an existing settlement (test-first)

Expose indexed catalog search as a distinct, clearly labeled geographic search. In registration, an unverified catalog hit opens a `residence claim` tied to source+version+external_id, *not* `location proposal`; allow the user to finish registration under the previously agreed pending-location contract. The registration backend must explicitly support this path; front-end-only acceptance is insufficient.

Recommended lifecycle: `submitted -> supported -> under_review -> approved/rejected/needs_information` with immutable decision events; allow idempotent same-user submission, prevent duplicate claims for the same person+source identity, avoid exposing claimant identity to other users. Existing support threshold 10 prioritizes human review **only**; never confers residential eligibility, governance, vote or active-group rights by itself. Rejection must preserve residence history and allow correction/appeal through audited workflow. Review queue must show source parent chain, conflict evidence and provenance.

Only after verified residential eligibility AND a separately approved operational mapping/governance policy should existing group-activation and election rules take effect. Add explicit negative tests ensuring that user-proposed pending villages and source-backed pending settlements do not collide or double-count groups.

## C4 — performance, load and failure tests in disposable database

Measure complete 99,317 catalog rows plus 6,158 administrative rows in a dedicated empty `geo_uat` DB; report wall-clock and peak RSS, batch-size impact, index/search latencies, query count, repeated import, cancellation/resume, same-source retry and source-change conflicts. Avoid long single transaction and per-row repeated queries for 100k records; use bounded chunks and checkpointed import run IDs, with rollback/repair strategy for partial runs. Verify source SHA256 and row-level uniqueness before first write. Test duplicate retry and simultaneous claimant/support operations. No full-site CI run until targeted RED/GREEN are stable.

## C5 — local E2E UAT and crosswalk

Walk: imported 1404 province/county/section/rural-district/city/urban-zone paths across multiple provinces; existing but unverified settlement and pending residence claim; verified non-residential mine/farm; city with no urban zones; urban zone with no neighborhoods; village with no neighborhoods; shared pending location supported by 10 users; a user-proposed missing zone; direct street-to-building. For every path compare registration completion, accurate group counts, pending status, audit, election eligibility, search speed and admin review.

Perform read-only crosswalk against *all* actual current DB identities (not merely 18-row v1 fixture). Produce `matched / ambiguous / synthetic / pending / unmapped` counts, dependency preview and explicit reviewer sign-off. No name-based mass remap, deletion, or duplicate Iran root.

## C6 — release gates (not automatic)

Before any merge: exact-SHA targeted and full validations, diff/security audit, local UAT evidence, performance envelope and PR #133 dependency resolved. Before a shared/Production DB apply: separately reviewed and owner-approved operational plan specifying backup/restore test, DB identity, zero/expected write counts, traffic-safe batches, reversible identity crosswalk, rollback, partial failure behavior, feature flags and audit. Production remains blocked unless explicitly authorized.

## Immediate next code slice (historical)

At authoring time the next step was C1 with a read-only catalog representation and tests on a child branch off PR #134. That work was later implemented and is no longer the current next task. See `docs/PRE_NATIVE_MOBILE_READINESS_STATUS.fa.md` for the current sequence.
