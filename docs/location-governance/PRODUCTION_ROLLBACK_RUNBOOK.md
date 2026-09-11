# Location / Governance Production Rollback Runbook

> This runbook is designed for C13 rollback while the legacy geography remains intact. It deliberately prefers reversible feature-flag/application rollback over destructive database reversal.

## 1. Rollback principles

During the initial C13 cutover:

1. stop forward rollout immediately when a blocking trigger appears;
2. revert canonical feature flags before considering data/schema actions;
3. revert the application to the captured previous SHA if flag rollback does not restore service;
4. preserve additive canonical tables/import audit/history unless a separately approved disaster-recovery restore is required;
5. never run `migrate:fresh`, destructive cleanup, or legacy-table deletion as rollback;
6. do not roll back additive migrations casually after Production may have written canonical data;
7. keep all rollback evidence.

The existence of this document is not authorization to restore over Production. A database restore is a high-impact recovery action and requires the approved incident procedure/authority for the exact environment.

## 2. Rollback triggers

Any of the following is sufficient to stop the next rollout stage and begin rollback assessment:

- `location-governance:readiness` returns non-zero unexpectedly;
- reference import reports unresolved conflicts or unexpected large-scale update/deactivation;
- authentication or Home/dashboard develops a blocking regression;
- registration/profile cannot complete for representative canonical users;
- Primary Residence produces incorrect official voting scope;
- work/study/other relationships gain official geographic voting rights;
- groups explode, disappear, duplicate incorrectly, or mature Group Chat/Admin breaks;
- formal election topology resolves incorrectly or election lifecycle breaks;
- Projects/Secretariat/Polls/shared spatial consumers regress after their stage;
- material 5xx/error/queue anomaly begins after a Location/Governance stage;
- critical Najm Bahar, Najm Hoda, Governance, Stock, Elections, Group Chat or identity regression appears;
- deployed SHA/dataset/version differs from the approved target;
- operator loses confidence in backup/restore evidence or observability.

When in doubt, prefer stopping forward activation. Do not enable the next flag to “see if it fixes” the current stage.

## 3. Capture incident state before changing it

Record immediately:

```text
INCIDENT_STARTED_AT=
CURRENT_APPLICATION_SHA=
PREVIOUS_APPLICATION_SHA=
CURRENT_FLAGS=
CURRENT_IMPORT_RUN_ID=
CURRENT_READINESS_RESULT=
FAILED_STAGE=
SYMPTOMS=
ERROR_REFERENCES=
OPERATOR=
```

Do not delay an urgent flag rollback merely to create perfect notes, but preserve enough evidence to reconstruct the sequence.

## 4. Flag-first rollback

Reverse only the stages that were activated, newest first.

### Stage E rollback — remaining consumers

```text
LOCATION_GOVERNANCE_PROJECTS_ENABLED=false
```

Reload application configuration using the deployment platform's standard safe procedure, then verify the consumer has returned to its legacy/fallback behavior.

### Stage D rollback — elections

```text
LOCATION_GOVERNANCE_ELECTIONS_ENABLED=false
```

Verify formal election scope/lifecycle returns to the pre-cutover path.

### Stage C rollback — groups

```text
LOCATION_GOVERNANCE_GROUPS_ENABLED=false
```

Verify group membership/group resolution uses the pre-cutover path and mature Group Chat/Admin is healthy.

### Stage B rollback — registration/profile

```text
LOCATION_GOVERNANCE_REGISTRATION_ENABLED=false
```

Verify new registration/profile traffic uses the intended legacy path. Existing canonical records are retained; do not delete them as part of flag rollback.

### Stage A rollback — canonical runtime

```text
LOCATION_GOVERNANCE_RUNTIME_ENABLED=false
```

Verify authentication, Home/dashboard and core user navigation return to the legacy-compatible runtime.

After each reversal, run the smallest Production-safe smoke checks needed to determine whether service is restored. If service is restored, stop making additional changes unless the incident plan requires completing a full canonical rollback.

## 5. Application SHA rollback

If flag rollback is insufficient, deploy the captured exact previous release:

```text
PREVIOUS_APPLICATION_SHA=<captured before cutover>
```

Use the deployment platform's normal immutable-release rollback mechanism. Do not rebuild a different commit and call it the previous release.

After application rollback:

- confirm deployed SHA;
- confirm Location/Governance flags are in the intended rolled-back state;
- smoke authentication/Home;
- smoke representative Group/Election/critical mature subsystem paths;
- check error/queue health.

The additive canonical database structures/imported records should normally remain in place but inert behind disabled flags. This avoids destructive emergency schema reversal and preserves audit/history for diagnosis.

## 6. Reference-import rollback policy

Do **not** attempt to undo a completed reference import with ad-hoc SQL.

The importer is versioned/audited. A C13 service rollback should normally disable canonical consumption via flags/application SHA while leaving imported reference rows and audit records intact.

If the import itself is proven to have corrupted Production data beyond safe isolation by flags, escalate to the approved database-disaster-recovery procedure using the verified pre-cutover backup. Do not invent compensating deletes/updates during the incident.

## 7. Migration rollback policy

Do not use these as routine Production rollback commands:

```bash
php artisan migrate:fresh
php artisan migrate:reset
php artisan migrate:rollback
```

C13 migrations are intentionally additive. Once Production has interacted with canonical structures, schema rollback can destroy valid data or break code/audit relationships.

If an additive migration itself makes the database unusable and application/flag rollback cannot isolate it, treat that as a database recovery incident. Use only a reviewed recovery plan or verified backup restore with explicit authority.

## 8. Database restore — last-resort recovery

A full/point-in-time restore may be considered only when:

- flag rollback and application rollback cannot restore safe service; and
- the database state itself is materially corrupted/unsafe; and
- the pre-cutover backup/restore rehearsal is verified; and
- the exact restore action has the required explicit Production authority.

Before restore, capture any recoverable incident evidence and determine what post-backup legitimate writes could be lost.

After restore, verify:

- database identity/timestamp matches the intended recovery point;
- expected user/account counts are plausible;
- Najm Bahar critical tables/ledger are consistent/queryable;
- Groups/Elections/Governance/Location tables are queryable;
- application SHA and flags match the restored/pre-cutover runtime expectation;
- authentication/Home and representative mature flows pass smoke checks.

Never use a restore rehearsal target as the Production restore source unless it is itself the approved immutable backup artifact.

## 9. Post-rollback evidence

Record:

```text
ROLLBACK_STARTED_AT=
ROLLBACK_COMPLETED_AT=
ROLLBACK_TRIGGER=
FLAGS_REVERTED=
APPLICATION_SHA_BEFORE=
APPLICATION_SHA_AFTER=
DATABASE_RESTORE_USED=yes|no
BACKUP_ID_IF_USED=
SMOKE_RESULT=
KNOWN_RESIDUAL_CANONICAL_DATA=
FOLLOW_UP_REQUIRED=
```

Attach logs/screenshots/monitoring references according to the project's operational evidence policy.

## 10. Reattempt criteria

Do not re-enable canonical flags in the same incident merely because symptoms disappeared.

A new attempt requires:

- root cause identified;
- regression test added before the fix when the defect is reproducible in code;
- new candidate SHA;
- focused Location/Governance validation green;
- Full Validation and all mature subsystem gates green;
- relevant UAT repeated;
- readiness evidence updated for the new SHA;
- explicit approval for the new exact Production action.

## 11. Legacy retirement remains prohibited

Rollback success does not authorize cleanup of the canonical or legacy architecture. Legacy retirement belongs to C14, after an observation/audit period and a second explicit approval.
