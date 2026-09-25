# Iran 1404 v2 — isolated database pilot runbook

Status: staging only. This document is not permission to delete any existing database, to run any Production command, or to merge PR #134.

## Exact candidate

Upstream source: Hameds/IranCountryDivisions, commit 68687cf96cc1852d5d38c7283353c80829331758. Git blob ca9f4a0d69c7c9d77e6434447c7fe123a322271e. Retain the supplied MIT license and its copyright notice.

The generated candidate holds 6,158 administrative places: 1 country, 31 provinces, 484 counties, 1,193 sections, 2,777 rural districts, 1,481 cities and 191 urban zones. A separate review file holds 99,317 **geographically verified but residentially unverified** settlements, each with `type=settlement`, `residential_eligibility=unverified`, `is_residence_endpoint=false`, `importable=false` and `governance_authorized=false`. They are now importable only through the separate guarded neutral-catalog command into a disposable `geo_uat` database; they are still not imported into the shared/Production database and never become operational `locations` merely by catalog import. Settlement geography, residential eligibility and governance authorization are independent decisions; see `docs/location-governance/IR_1404_SETTLEMENT_CLASSIFICATION_DECISION.md`. The 191 urban zones in the pinned 1404 source are accepted as the canonical administrative baseline. Legacy EarthCoop zone rows do not override them; any real missing zone is added later through the normal user-proposal and human-review flow.

## Generate on an isolated Windows checkout

From a clean checkout of the data-audit branch, run the following one line commands using Python 3. The output directory must NOT already exist.

    python -m unittest -v scripts/location_governance/test_convert_iran_1404.py
    python scripts/location_governance/convert_iran_1404.py --source-dir database/reference/source/ir/1404 --schema-template database/reference/ir/v1/schema.json --output-dir storage/app/iran-1404-v2-candidate

Read the generated manifest.json and check total_rows=105475, staged_active_rows=6158, quarantined_settlements=99317 and the pinned upstream blob. The converter also emits source_sha256. The existing database and IR v1 file remain untouched.

## Isolated pilot and current local UAT

The empty `geo_uat` path remains available for destructive-free benchmarking. In addition, the current local UAT database may now receive v2 alongside v1 because identities are version-scoped; v1 rows and existing UAT history are preserved. Production remains blocked by the command guard.

No generated `database/reference/ir/v2` checkout files are required for local UAT: `ReferenceDataset` deterministically rebuilds the 6,158 administrative rows from the nine pinned source chunks when v2 files are absent. Never overwrite `database/reference/ir/v1`.

The expected dry-run against the EMPTY disposable database is:

    php artisan location:reference-import IR --dataset-version=v2 --dry-run
    create: 6158; update: 0; deactivate: 0; conflict: 0

After explicit review of that dry-run and confirmation of the connection identity, the isolated-only command is:

    php artisan location:reference-import IR --dataset-version=v2 --apply --confirm=APPLY-IR-1404-V2-ISOLATED

The isolated token retains the empty-DB restrictions. For the existing local UAT database, use the separate explicit token below; it preserves v1 and refuses Production:

    php artisan location:reference-import IR --dataset-version=v2 --dry-run
    php artisan location:reference-import IR --dataset-version=v2 --apply --confirm=APPLY-IR-1404-V2-UAT
    php artisan location-governance:reference-topology IR --dataset-version=v2 --dry-run
    php artisan location-governance:reference-topology IR --dataset-version=v2 --apply --confirm=APPLY-GOV-IR-1404-V2-UAT

Expected first import: `create=6158, conflict=0`. Expected first topology sync after v1 topology already exists: `create=6158, conflict=0`; the shared Global/Asia areas remain unchanged. Once v2 exists, canonical Iran menus prefer the v2 root.

Repeat dry-run: unchanged=6158 and create/update/deactivate/conflict all zero. Record import time, memory, schema/identity row counts and successful paths such as Iran → Mazandaran → Sari → Chahardangeh → Kiasar. Do not infer that all village paths are ready; settlements await classification.

The neutral settlement catalog has a separate isolated command:

    DB_DATABASE=earthcoop_geo_uat php artisan location:iran-settlement-catalog storage/app/iran-1404-v2-candidate/settlements.review.jsonl storage/app/iran-1404-v2-candidate/manifest.json
    DB_DATABASE=earthcoop_geo_uat php artisan location:iran-settlement-catalog storage/app/iran-1404-v2-candidate/settlements.review.jsonl storage/app/iran-1404-v2-candidate/manifest.json --apply --confirm=APPLY-IR-SETTLEMENT-CATALOG-ISOLATED

CI has proven all 99,317 rows can be inserted into disposable MySQL and an immediate replay inserts zero. This proof is not authorization for a shared-database import.

## V1 → v2 identity review (non-mutating)

Before considering any shared-database transition, run the reviewed crosswalk auditor against the pinned 1404 source and the 18-row v1 fixture:

    python3 -m unittest -v scripts/location_governance/test_audit_v1_iran_crosswalk.py
    python3 scripts/location_governance/audit_v1_iran_crosswalk.py --source-dir database/reference/source/ir/1404 --v1-locations database/reference/ir/v1/locations.jsonl --output storage/app/iran-v1-crosswalk-review.json

This creates a NEW JSON report only. It does not access a database or write into importable reference directories. Expect the source-backed v1 identities, including Sari urban region 1, to map to their pinned 1404 identities; synthetic UAT-only places remain historical compatibility data and are not allowed to override the 1404 menu. In particular, the real Kiasar city is not discarded just because the v1 fixture included it to exercise a no-urban-region path.

The pinned mapping is deliberately narrow: Iran, Mazandaran, Sari county, Sari central section, Sari city, Chahardangeh section, Kiasar city, Chahardangeh rural district; Sari urban region 1 is source-backed and treated as a verified identity for compatibility mapping. Never convert this JSON report into SQL or a mass update without an explicit, separately reviewed database identity/reconciliation plan. Existing user-generated proposals and other non-v1 Location rows must be inventoried before a shared-database change.

For the *current database* dependency inventory, run the read-only command:

    php artisan location:iran-v1-v2-runtime-audit
    php artisan location:iran-v1-v2-runtime-audit --json

It reports reviewed v1→v2 candidates plus counts of residence relationships, proposals, governance mappings and groups reachable through governance. It performs no INSERT/UPDATE/DELETE. Any present v1 identity without an approved crosswalk, and any present mapping marked `municipal_review`, keeps `shared_cutover_blocked=true`.

## Release blockers

1. Obtain valid residential eligibility for settlements; a source type called settlement does not automatically authorize village governance, voting or group creation.
2. Do not synthesize missing zones from legacy data. If a real zone/neighborhood/street is absent from the 1404 source, let users propose it through the existing pending/review workflow.
3. Create an approved crosswalk from synthetic v1 and existing proposals/groups/users to real v2 identifiers before any shared-database import; otherwise the IR root and locations duplicate.
4. The 99,317-row neutral catalog importer is benchmarked in disposable CI MySQL; the 6,158-row operational geography importer is also green in isolation. Shared-hosting/Production cutover still requires the runtime dependency audit, rollback design and explicit authorization.
5. Complete checkpoint 2 onward and Production cutover only after these gates and separate owner authorization.

No destructive command is needed merely because the current users are test accounts. Preserve the existing UAT DB and use a disposable DB to make the real-data transition faster.
