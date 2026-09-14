# Location / Governance Cutover Readiness

This document records **readiness evidence only**. It is not authorization to change Production.

## Current hardened post-UI candidate

- Current Production/main baseline before this hardening PR: `c26f012e9b683b69342050f435931486a3bb6874`
- Hardened branch: `agent/location-governance-production-readiness-membership-fix-20260914`
- Draft PR: `#113`
- Current validated checkpoint before this documentation update: `3b8bca8905839d5a23d170984093e99a99371131`
- Full Validation: `#2620` / run ID `34833958912`
- Validation job: `103943434913`
- Result: `success`

Full Validation #2620 completed successfully on the exact hardened checkpoint. Evidence from the uploaded regression artifact:

- Location / Governance: **201 passed / 1018 assertions**;
- Location / Governance JavaScript: passing;
- Deployment Console: **25 passed / 230 assertions**;
- Najm Bahar: **174 tests / 899 assertions**;
- Governance: **28 tests / 309 assertions**;
- Najm Hoda + n8n: **503 tests / 3025 assertions**;
- Group Chat: **56 tests / 305 assertions**;
- Group Admin / Identity: **24 tests / 95 assertions**;
- Stock: **14 tests / 18 assertions**;
- Full Project PHPUnit: **1690 tests / 9073 assertions**, 2 skipped, 47 existing PHPUnit deprecations;
- frontend build, integrated migrations, route/command boot, Group Chat JavaScript, diagnostics upload and final regression enforcement all succeeded.

The documentation update that records this evidence changes the branch SHA and therefore requires its own final Full Validation before PR #113 can be considered merge-ready.

## What this hardening adds

The readiness contract now fails closed unless all of the following are true:

1. all canonical additive migrations are applied, including `2026_09_13_000001_create_pending_residence_intents_table` required by registration/profile pending exact-residence flows;
2. the reviewed versioned Governance topology is fully applied and idempotent for the target dataset (`create=0`, `update=0`, `conflict=0`, with reviewed areas unchanged), rather than accepting any arbitrary single Location-to-Governance mapping;
3. the five Stage C systemic group policies (`public`, `profession`, `specialty`, `age`, `gender`) are activated through the reviewed canonical policy seeder and the default Governance capability reports automatic group creation;
4. the existing schema/import/flag/proposal/release-evidence checks continue to pass.

The cPanel and Production runbooks now also require an explicit Laravel configuration-cache check before relying on `.env` changes and include the bounded Stage C policy transition (`APPLY-GROUP-POLICY`) before readiness.

## Production code deployment state

PR #112 was merged previously into `main` at `c26f012e9b683b69342050f435931486a3bb6874`. Because `.github/workflows/deploy.yml` deploys pushes to `main`, FTP Deploy #39 / run `34830668090` successfully synchronized that code to cPanel Production.

That FTP workflow does **not** execute Production migrations, bootstrap, geography import, Governance topology import, Stage C policy activation, configuration-cache clearing, or Location/Governance feature-flag changes. `.env` is excluded from the FTP sync.

Therefore the code deployment must not be confused with canonical database preparation or activation.

## Production My Groups observation

A Production screenshot taken while `LOCATION_GOVERNANCE_GROUPS_ENABLED=false` showed historical Tehran/Sohanak groups alongside canonical Sari reference groups previously materialized during controlled testing.

Repository tracing established that this is the dark-launch legacy-path behavior rather than evidence of a failed canonical residence transfer:

- while the groups flag is false, `/groups` delegates to the mature legacy controller;
- the legacy list does not separate canonical Governance-scoped rows from legacy spatial memberships;
- legacy memberships are deliberately retained through C13 for rollback/history;
- existing canonical cutover regression coverage proves that, with Stage C groups enabled, a residence transfer deactivates stale canonical memberships and activates the new canonical branch, and the canonical index excludes legacy spatial groups.

Accordingly, no legacy membership rows should be deleted merely to make the pre-activation list look clean. The Production Stage C smoke test must explicitly verify that the account moved from Tehran/Sohanak to the Sari reference neighborhood shows the current Sari canonical ancestry and no former Tehran/Sohanak branch as current canonical memberships after `LOCATION_GOVERNANCE_GROUPS_ENABLED=true` is separately approved and enabled.

## Historical validated C13 preparation

The original C13 preparation candidate was `c34f7ef406cac4bf512ee789c31688e65c764189` on `agent/global-location-governance-implementation-20260910`, validated by Full Validation #2424 / run `34610982735`, job `103301574562`. A later documentation checkpoint `943804fbcac2c702790466ae266cce8a2557a0d5` was validated by #2426 / run `34616614013`, job `103320370800`.

Those runs remain historical architecture evidence; they are not substitutes for the current post-UI hardened candidate evidence above.

## Fresh Canonical Start launch policy

The owner has explicitly chosen **Fresh Canonical Start** for the current early Production population. Existing users' legacy spatial/profile geography does not need to be bulk-converted into canonical Location/Governance before launch.

This means:

- an empty or partially populated `user_location_relationships` table is not by itself a readiness failure;
- existing users may establish/correct canonical Primary Residence through the canonical profile flow after activation;
- no one-off destructive user-geography conversion is required for initial cutover;
- new users use the canonical schema-driven flow once the registration/profile stage is separately approved and enabled.

This policy does **not** authorize deleting the Production database or mature subsystem state. Users, authentication, groups, elections, Najm Bahar, Stock, messages, projects and other existing state remain protected. No `migrate:fresh`, reset, truncate, table drop or destructive legacy retirement is permitted.

## Production backup boundary

No Production backup or restore rehearsal has been performed from this workspace because this workspace has GitHub/CI access but no cPanel/SSH/MySQL Production connection.

Before any additive Production write, a current recoverable cPanel/database backup or provider snapshot must exist. If hosting limitations make an isolated restore rehearsal impractical, that limitation must be recorded honestly and explicitly accepted; it must never be represented as verified restore evidence.

## Human-approval boundary — activation still stopped

`DEPLOYMENT_CONSOLE_ENABLED` must remain `false` until the backup/preflight checkpoint is accepted. When the controlled preparation window is explicitly approved, the console may be enabled temporarily with a high-entropy secret for its fixed allowlisted operations, then must be disabled again before activation.

Until separate activation approval, do not:

- enable any `LOCATION_GOVERNANCE_*_ENABLED` Production flag;
- delete or retire legacy geography or membership history;
- perform destructive database operations;
- perform C14 legacy retirement.

C14 remains a post-cutover audit/observation phase requiring a new, separate explicit approval.

## Readiness verdict

**C13 architecture and Stage C/UI package: implemented.**

**Post-UI Production-readiness hardening: GREEN on checkpoint `3b8bca8905839d5a23d170984093e99a99371131` via Full Validation #2620.**

**Current documentation checkpoint: awaiting its own fresh Full Validation before PR #113 is merge-ready.**

**Production canonical activation: NOT AUTHORIZED.** The next operational phase after a final merge-approved candidate is safe Production backup/preflight and additive preparation with all rollout flags still OFF.