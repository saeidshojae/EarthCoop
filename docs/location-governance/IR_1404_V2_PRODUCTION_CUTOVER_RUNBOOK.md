# Iran 1404 v2 Production Cutover Runbook

This runbook is the approved sequence for loading the pinned Iran 1404 v2 geography into an already-running EarthCoop Production database.

It is additive and fail-closed. It does not delete v1 geography, legacy residences, groups, proposals, or governance history.

## Preconditions

Before any write:

- Current Production database backup exists and is recorded.
- All migrations are Ran.
- The deployed code is the exact approved SHA.
- Deployment Console is temporarily enabled with its existing secret.
- IR_1404_V2_RUNTIME_ENABLED=false.
- IR_SETTLEMENT_CATALOG_ENABLED=false.
- IR_SETTLEMENT_CLAIMS_ENABLED=false.

Importing v2 data while IR_1404_V2_RUNTIME_ENABLED=false must not change live residence or project-scope menus.

Never use migrate:fresh, migrate:reset, migrate:rollback, manual SQL rewrites, or direct flag mutation through the Deployment Console.

## 1. Read-only v1 -> v2 audit

Run Deployment Console operation: iran_v1_v2_audit.

A present unreviewed v1 identity or a present non-verified_identity mapping is a STOP condition for live cutover. Existing dependencies on reviewed v1 identities are preserved inventory; do not delete them to make the report smaller.

## 2. Administrative geography dry-run

Run: reference_v2_dry_run.

Expected first-load baseline: create=6158, update=0, deactivate=0, conflict=0, unchanged=0.

Any conflict is a STOP condition.

## 3. Apply administrative geography while still dark

Run: reference_v2_apply.
Deployment Console confirmation: APPLY-IR-1404-V2.

Then re-run reference_v2_dry_run and require create=0, update=0, deactivate=0, conflict=0, unchanged=6158.

The live selector must still use v1 because IR_1404_V2_RUNTIME_ENABLED=false.

## 4. Neutral settlement catalog dry-run

Run: settlement_v2_dry_run.

The command validates the nine pinned source chunks, exact Git blob, hierarchy, 99,317 settlement identities, and their v2 rural-district parents.

Expected first-load baseline: validated=99317, existing=0, would_insert=99317, applied=0, mode=dry-run.

Every settlement remains unverified_settlement, residential_eligibility=unverified, governance_authorized=false, operational_promotion_allowed=false.

No settlement becomes a Location, GovernanceArea, official group, or voting scope merely by import.

## 5. Apply neutral settlement catalog while still dark

Run: settlement_v2_apply.
Deployment Console confirmation: APPLY-IR-1404-SETTLEMENTS.

Re-run settlement_v2_dry_run and require validated=99317, existing=99317, would_insert=0, applied=0, mode=dry-run.

Any protected identity or classification drift is a STOP condition.

## 6. Governance topology dry-run

Run: topology_v2_dry_run. Require conflict=0 and review all counts before apply.

## 7. Apply v2 Governance topology while still dark

Run: topology_v2_apply.
Deployment Console confirmation: APPLY-GOV-IR-1404-V2.

Re-run topology_v2_dry_run and require create=0, update=0, conflict=0 with imported v2 areas unchanged.

## 8. HARD STOP before live cutover

Record backup identifier, deployed SHA, audit output, reference counts, settlement counts, topology counts, and read-only flag status.

Do not continue if any operation failed or produced unexplained counts.

## 9. Explicit live selector cutover

Only after the evidence above is accepted, set IR_1404_V2_RUNTIME_ENABLED=true in Production and refresh Laravel configuration using the approved cPanel procedure.

Do not change the five existing Location/Governance rollout flags merely for this cutover.

Smoke-test Step 3, Profile residence edit, Admin residence edit, project geographic scope when enabled, and existing v1 user hydration/fallback.

If smoke fails, set only IR_1404_V2_RUNTIME_ENABLED=false, refresh config, and investigate. Do not delete imported v2 data.

## 10. Settlement search and claim activation

After v2 runtime smoke passes, enable separately:

IR_SETTLEMENT_CATALOG_ENABLED=true
IR_SETTLEMENT_CLAIMS_ENABLED=true

Refresh configuration safely and smoke-test one rural path: دهستان -> روستا/آبادی -> محله.

The selected settlement remains pending and non-authorizing until human review.

## 11. Success criteria

- v2 geography is idempotent at 6,158 rows.
- settlement catalog is idempotent at 99,317 neutral rows.
- v2 Governance topology is idempotent with zero conflicts.
- v1 data and dependencies remain preserved.
- live v2 selector cutover is controlled by IR_1404_V2_RUNTIME_ENABLED.
- settlement catalog and claim flags are separately controlled.
- registration/profile/admin share the same reviewed picker behavior.
- no Production write requires free-form shell, SQL, or Artisan input.
