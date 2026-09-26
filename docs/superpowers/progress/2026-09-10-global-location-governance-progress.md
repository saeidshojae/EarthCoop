# Global Location & Governance — Implementation Progress

**Original plan:** `docs/superpowers/plans/2026-09-10-global-location-governance-implementation-final.md`  
**Spec:** `docs/superpowers/specs/2026-09-10-global-location-governance-architecture-design.md`  
**Current status baseline:** `main@9b936dd9c9b194098f6736eeac8917ccb2d622f6`  
**Last reconciled:** 2026-09-26

> This progress file is the current status ledger for the Location/Governance workstream. Historical plan/checkpoint SHAs below are architecture evidence, not the current next-task pointer. For the program after Location/Governance, use `docs/PRE_NATIVE_MOBILE_READINESS_STATUS.fa.md`.

## Current program verdict

The global Location/Governance architecture, Production administrative v2 cutover, structural-state matrix, proposal/review lifecycle, and canonical runtime consumer alignment have all progressed beyond the old C13-preparation state.

The workstream is **closed enough to return to the main Pre-Native Mobile Readiness program**. Remaining Location/Governance items are either optional/feature-gated operational UAT, UX polish, or the separately approval-gated C14 legacy-retirement phase.

## Current checkpoint status

| Checkpoint / closure | Scope | Current status |
|---|---|---|
| C0 | Legacy spatial behavior freeze and consumer inventory | Complete |
| C1 | Generic Location core, topology, lifecycle | Complete |
| C2 | Independent Governance tree and capability policy | Complete |
| C3 | Historical primary residence and transfer policy | Complete |
| C4 | Versioned reference geography import | Complete |
| C5 | Schema-driven registration/profile cutover behind reversible flags | Complete |
| C6 | Multidimensional membership engine | Complete |
| C7 | Canonical Group scope | Complete |
| C8 | Canonical Election topology | Complete |
| C9 | Secretariat / Projects / Polls scope routing | Complete |
| C10 | Community policy and crowdsourced proposals | Complete |
| C11 | Optional geolocation, admin control center, Najm Hoda human-gated review | Complete as architecture/runtime foundation; external reverse-geocoder provider remains independent/deferred |
| C12-A | Fresh bootstrap and permanent global/UAT scenarios | Complete |
| C12-B | Focused CI gate, full validation and UAT candidate | Complete |
| C13 | Production cutover package | **Completed beyond preparation:** guarded Iran 1404 v2 administrative cutover subsequently executed through PRs #143–#145; later structural/review/consumer hardening also merged |
| Post-C13 Checkpoint 2 | Complete City/Region/Village structural-state matrix | Complete — PR #146 |
| Post-C13 Checkpoint 3 | Proposal support/review lifecycle | Complete — PR #147 |
| Post-C13 Checkpoint 4 | Canonical active-consumer audit | Complete — PR #148; fixed-SHA Responsive #707 + Integration #3266 green before merge |
| C14 | Legacy retirement | **Not started intentionally**; post-cutover observation/audit + second explicit approval required |

## Manual UAT reconciliation

The following structural flows are **not open backlog**:

- City without Urban Region (including Kiasar UAT line);
- Urban Region without Neighborhood;
- Village without Neighborhood;
- registration stopping at the deepest available governance base rather than Street/micro-address;
- pending exact-location/group presentation and group-count/role behavior hardened through the September UAT line.

These scenarios remain in `docs/location-governance/UAT_SCENARIOS.md` because that file is a permanent regression matrix, not because they still require first-time UAT.

## Iran 1404 administrative v2 status

The old status in this file ended at C13 preparation. Subsequent work changed that materially:

1. PR #141 established the Iran 1404 national administrative hierarchy/runtime foundation.
2. PR #143 added Production-safe read-only v1→v2 runtime/preflight operations.
3. PR #144 added guarded additive v2 staging.
4. PR #145 completed the fail-closed Production cutover path and activation boundary.
5. Project operator evidence on 2026-09-25 records successful shared/Production execution with runtime v2 active, blockers=0, and bounded migration of verified live dependencies.

The administrative v2 cutover **does not** imply nationwide residential/governance promotion of the 99,317 source settlements. Reference-settlement classification/rollout remains independently evidence-gated.

## Reference-settlement status

The 2026-09-24 settlement catalog plan is no longer PLAN ONLY. Current main includes the neutral catalog, residence claims, review/evidence model, registration bridge, pending exact settlement display/group shells, and settlement-backed pending-neighborhood flow.

Manual UAT evidence exists for the «وری» line including search, registration, shared Step3/Profile/Admin picker behavior, hydration/path display and pending group behavior.

Still not falsely claimed as fully manually closed:

- ten independent real UAT users reaching one settlement threshold;
- complete human evidence-review → residential/nonresidential operator matrix;
- manual nonresidential cleanup end-to-end;
- full settlement feature-flag replay;
- complete settlement admin/review mobile+RTL matrix;
- nationwide residential classification/promotion.

These are operational/feature-scope items, not reasons to reopen the core global Location/Governance architecture before Mobile Readiness.

## Proposal support/review status

The old backlog item “default 10 supporters” must be split correctly:

- **Backend lifecycle:** complete. Distinct-user support, committed-selection provenance, ancestry propagation, ready-for-review semantics, rejection cleanup and admin dependency visibility are covered by Checkpoint 3 / PR #147.
- **Potential future UX polish:** progress indicator, clearer pending/ready/approved explanation, nearby-user invite CTA, and higher-volume admin queue/filter ergonomics.

Do not reimplement the support lifecycle when addressing those UX items.

## Canonical consumer closure

PR #148 / Checkpoint 4 reconciled active runtime consumers with canonical Residence/GovernanceArea/Membership truth, including Profile/Admin/My Groups, group chat/role presentation, election policy/conflict/appointments, admin filters/temporary roles, Najm Bahar salary targeting, Najm Hoda context and the live group-search endpoint.

Final branch SHA `1138435326f1a0bf1f3cb6f1e217438fee5157b8` passed Responsive #707 and Integration Full Validation #3266, then merged into main as `9b936dd9c9b194098f6736eeac8917ccb2d622f6`.

## Safety state now

- Legacy geography/history remains intentionally available for rollback/compatibility.
- C14 is not authorized by completion of Checkpoint 4.
- No `migrate:fresh`, truncate/drop or broad destructive cleanup is implied by this progress update.
- Reference-settlement source identity does not automatically grant residential eligibility, GovernanceArea, active group or vote rights.
- Najm Hoda remains human/authority-gated for sensitive Location/Governance decisions.

## Historical C13 evidence

The original C13 implementation candidate `c34f7ef406cac4bf512ee789c31688e65c764189` was validated by Full Validation #2424 / run `34610982735`; the documentation checkpoint `943804fbcac2c702790466ae266cce8a2557a0d5` was validated by #2426. Those remain useful historical architecture evidence, but they no longer describe the current project state.

## Next program — not another Location/Governance checkpoint

The next main program is **Pre-Native Mobile Readiness Foundations**, beginning with:

**M0 — API Constitution + mobile capability/domain inventory**.

Why M0 is next:

- Sanctum and scattered APIs exist, but there is no clean, frozen `/api/v1` mobile contract boundary;
- `routes/api.php` still contains substantial closure-based and legacy geography surfaces;
- Native should consume stable domain/API contracts rather than reproduce web-specific coupling.

The planned sequence and blocker/non-blocker classification are maintained in `docs/PRE_NATIVE_MOBILE_READINESS_STATUS.fa.md`.

## Explicitly not the next step

Do **not** restart any of the following merely because old plans still contain them:

- first-time UAT of city/region/village absence states;
- C1 settlement catalog implementation;
- generic Production backup/preflight for a v2 administrative cutover that already occurred;
- C14 legacy retirement;
- full Marketplace/Company build before API/mobile foundations.
