# Location/Governance Bootstrap Runbook

## Purpose

`LocationGovernanceBootstrapSeeder` installs only the small canonical contracts required before reference geography is imported:

- the versioned Iran reference schema (`ir-reference-v1`);
- the 13 canonical location types and their allowed parent/child relations;
- the five membership dimensions (`public`, `profession`, `specialty`, `age`, `gender`);
- conservative default group-creation policies;
- the default governance capability policy.

It intentionally does **not** create countries, provinces, cities, villages, streets, buildings, or any other real `locations` rows. Real geography belongs to the audited reference importer.

## Hard safety rule

`migrate:fresh` is permitted only against a disposable local/testing database whose loss is expected.

**Never run `php artisan migrate:fresh` against production, staging with valuable data, or any shared database.** Production cutover has a separate approval-gated runbook and is outside this bootstrap procedure.

Before any destructive local/test rehearsal, confirm the active environment and database name explicitly.

## Fresh disposable rehearsal

Example for the isolated testing environment:

```bash
php artisan migrate:fresh --env=testing
php artisan db:seed --class=LocationGovernanceBootstrapSeeder --env=testing
php artisan test tests/Feature/LocationGovernance/FreshBootstrapLocationGovernanceTest.php tests/Feature/LocationGovernance/GlobalArchitectureScenarioTest.php
```

Expected bootstrap invariants:

1. `ir-reference-v1` exists and is active.
2. Exactly the 13 canonical location types are attached to that schema.
3. The canonical type graph includes both urban and rural variable-depth paths.
4. Five membership dimensions exist and point to their registered resolver classes.
5. Default policy records exist without materializing groups.
6. Running the seeder again does not duplicate any bootstrap record.
7. `locations` remains empty immediately after bootstrap alone.

## Reference geography import

After bootstrap, real geography must be loaded through the versioned reference import path, not by extending the seeder. Use the audited importer/command documented in `REFERENCE_IMPORTER_IMPLEMENTATION_NOTES.md` and retain its import-run audit result.

Bootstrap and geography import are deliberately separate so schema/policy changes stay small, repeatable, and reviewable while large geographic datasets remain versioned and auditable.

## Rollback and recovery

The bootstrap seeder is idempotent and uses stable keys. Re-running it is the supported repair mechanism for missing small reference contracts in disposable/test environments.

Do not delete or recreate production geography as a bootstrap repair. Any production migration, cutover, destructive repair, or legacy retirement requires its own explicit checkpoint and approval.
