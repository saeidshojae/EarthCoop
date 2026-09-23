# Iran 1404 v2 — isolated database pilot runbook

Status: staging only. This document is not permission to delete any existing database, to run any Production command, or to merge PR #134.

## Exact candidate

Upstream source: Hameds/IranCountryDivisions, commit 68687cf96cc1852d5d38c7283353c80829331758. Git blob ca9f4a0d69c7c9d77e6434447c7fe123a322271e. Retain the supplied MIT license and its copyright notice.

The generated candidate holds 6,158 administrative places: 1 country, 31 provinces, 484 counties, 1,193 sections, 2,777 rural districts, 1,481 cities and 191 urban zones. A separate review file holds 99,317 **geographically verified but residentially unverified** settlements, each with `type=settlement`, `residential_eligibility=unverified`, `is_residence_endpoint=false`, `importable=false` and `governance_authorized=false`. These are not imported or deleted. Settlement geography, residential eligibility and governance authorization are independent decisions; see `docs/location-governance/IR_1404_SETTLEMENT_CLASSIFICATION_DECISION.md`. Urban zones have municipal_reconciliation_required=true; Sari shows only three source zones whereas our local UAT includes region five.

## Generate on an isolated Windows checkout

From a clean checkout of the data-audit branch, run the following one line commands using Python 3. The output directory must NOT already exist.

    python -m unittest -v scripts/location_governance/test_convert_iran_1404.py
    python scripts/location_governance/convert_iran_1404.py --source-dir database/reference/source/ir/1404 --schema-template database/reference/ir/v1/schema.json --output-dir storage/app/iran-1404-v2-candidate

Read the generated manifest.json and check total_rows=105475, staged_active_rows=6158, quarantined_settlements=99317 and the pinned upstream blob. The converter also emits source_sha256. The existing database and IR v1 file remain untouched.

## Disposable database only

Use a newly created, empty LOCAL MySQL database with geo_uat in its name, for example earthcoop_geo_uat. Do not reuse the current EarthCoop database, a PHPUnit database, an existing user database or a Production database. Configure a separate local environment pointing to that database, then confirm the actual database identity before migrating its schema.

Only in that isolated checkout, copy generated schema.json and locations.jsonl to database/reference/ir/v2. Never copy settlements.review.jsonl into the importable directory or overwrite database/reference/ir/v1.

The expected dry-run against the EMPTY disposable database is:

    php artisan location:reference-import IR --dataset-version=v2 --dry-run
    create: 6158; update: 0; deactivate: 0; conflict: 0

After explicit review of that dry-run and confirmation of the connection identity, the isolated-only command is:

    php artisan location:reference-import IR --dataset-version=v2 --apply --confirm=APPLY-IR-1404-V2-ISOLATED

The command refuses Production, a DB without geo_uat in its name, a missing exact confirmation, any non-v2 IR location, any existing residence history or any Iranian governance area.

Repeat dry-run: unchanged=6158 and create/update/deactivate/conflict all zero. Record import time, memory, schema/identity row counts and successful paths such as Iran → Mazandaran → Sari → Chahardangeh → Kiasar. Do not infer that all village paths are ready; settlements await classification.

## V1 → v2 identity review (non-mutating)

Before considering any shared-database transition, run the reviewed crosswalk auditor against the pinned 1404 source and the 18-row v1 fixture:

    python3 -m unittest -v scripts/location_governance/test_audit_v1_iran_crosswalk.py
    python3 scripts/location_governance/audit_v1_iran_crosswalk.py --source-dir database/reference/source/ir/1404 --v1-locations database/reference/ir/v1/locations.jsonl --output storage/app/iran-v1-crosswalk-review.json

This creates a NEW JSON report only. It does not access a database or write into importable reference directories. Expect eight verified real source identities, one municipality-dependent Sari urban zone and nine synthetic UAT places held without automatic mapping. In particular, the real Kiasar city is not discarded just because the v1 fixture included it to exercise a no-urban-region path.

The pinned mapping is deliberately narrow: Iran, Mazandaran, Sari county, Sari central section, Sari city, Chahardangeh section, Kiasar city, Chahardangeh rural district; Sari urban region 1 still requires a municipality check. Never convert this JSON report into SQL or a mass update without an explicit, separately reviewed database identity/reconciliation plan. Existing user-generated proposals and other non-v1 Location rows must be inventoried before a shared-database change.

## Release blockers

1. Obtain valid residential eligibility for settlements; a source type called settlement does not automatically authorize village governance, voting or group creation.
2. Reconcile Sari municipal zone five and municipality neighborhoods/streets with upstream year-1404 administrative source zones.
3. Create an approved crosswalk from synthetic v1 and existing proposals/groups/users to real v2 identifiers before any shared-database import; otherwise the IR root and locations duplicate.
4. Benchmark final-volume importer and agree on batch/rollback limits. The current per-row importer is not assumed to support 105k records within shared hosting limits.
5. Complete checkpoint 2 onward and Production cutover only after these gates and separate owner authorization.

No destructive command is needed merely because the current users are test accounts. Preserve the existing UAT DB and use a disposable DB to make the real-data transition faster.
