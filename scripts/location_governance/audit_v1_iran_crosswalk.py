#!/usr/bin/env python3
"""Read-only, fail-closed audit of the 18 legacy IR v1 UAT rows against pinned 1404 source.

This script neither connects to a database nor emits SQL or mutates any reference
file. A source mapping is a REVIEW CANDIDATE, never an authorization to rewrite
existing Location IDs, governance assignments, addresses or group memberships.
"""
from __future__ import annotations

import argparse
import importlib.util
import json
from pathlib import Path

MODULE = Path(__file__).with_name("convert_iran_1404.py")
spec = importlib.util.spec_from_file_location("iran_1404_converter_for_crosswalk", MODULE)
converter = importlib.util.module_from_spec(spec)
spec.loader.exec_module(converter)

# These nine v1 IDs are actual named administrative places. The v1 urban region
# requires a separate municipal review, even where its source label matches.
# All other v1 rows are synthetic UAT fixtures; no fuzzy name match is allowed.
PINNED_MATCHES = {
    "IR-COUNTRY": (1, "country", None, "ایران"),
    "IR-MAZ-001": (4, "province", "IR-COUNTRY", "مازندران"),
    "IR-MAZ-SARI-COUNTY": (67, "county", "IR-MAZ-001", "ساری"),
    "IR-MAZ-SARI-CENTRAL": (611, "section", "IR-MAZ-SARI-COUNTY", "مرکزی"),
    "IR-SARI-001": (4602, "city", "IR-MAZ-SARI-CENTRAL", "ساری"),
    "IR-SARI-URBAN-01": (5984, "urban_region", "IR-SARI-001", "ساری 1"),
    "IR-MAZ-SARI-CHAHARDANGEH-SECTION": (609, "section", "IR-MAZ-SARI-COUNTY", "چهاردانگه"),
    "IR-MAZ-SARI-CHAHARDANGEH-KIASAR": (4600, "city", "IR-MAZ-SARI-CHAHARDANGEH-SECTION", "کیاسر"),
    "IR-MAZ-SARI-CHAHARDANGEH-RD": (1938, "rural_district", "IR-MAZ-SARI-CHAHARDANGEH-SECTION", "چهاردانگه"),
}
MUNICIPAL_REVIEW = {"IR-SARI-URBAN-01"}
EXPECTED_V1_ROWS = 18
EXPECTED_COUNTS = {"verified_identity": 8, "municipal_review": 1, "synthetic_hold": 9}


def audit(source_rows: list[dict], v1_rows: list[dict], expected: dict | None = None) -> dict:
    expected = PINNED_MATCHES if expected is None else expected
    by_source = {row["id"]: row for row in source_rows}
    by_v1 = {row["external_id"]: row for row in v1_rows}
    if len(by_source) != len(source_rows) or len(by_v1) != len(v1_rows):
        raise ValueError("Duplicate source ID or v1 external ID")
    if set(expected) - set(by_v1):
        raise ValueError("Missing expected v1 reference row(s)")
    if set(expected.values()) and len({item[0] for item in expected.values()}) != len(expected):
        raise ValueError("Multiple v1 identities map to one 1404 source row; explicit review required")

    matches = []
    for old_id, (new_id, type_, old_parent, source_name) in expected.items():
        previous = by_v1[old_id]
        source = by_source.get(new_id)
        if source is None or source["name"] != source_name or converter.TYPE_KEYS[source["type"]] != type_:
            raise ValueError(f"Source identity/type/name changed for {old_id}; stop crosswalk")
        if previous.get("type") != type_ or previous.get("parent_external_id") != old_parent:
            raise ValueError(f"v1 identity/type/parent changed for {old_id}; stop crosswalk")
        expected_source_parent = expected[old_parent][0] if old_parent is not None else None
        if source["parent"] != expected_source_parent:
            raise ValueError(f"1404 parent lineage changed for {old_id}; stop crosswalk")
        matches.append({
            "v1_external_id": old_id,
            "candidate_v2_external_id": f"IR-1404-{new_id}",
            "candidate_1404_source_id": new_id,
            "v1_fa_name": previous.get("localized_names", {}).get("fa"),
            "source_1404_fa_name": source["name"],
            "status": "municipal_review" if old_id in MUNICIPAL_REVIEW else "verified_identity",
            "action": "review_only_no_database_changes",
        })

    held = [{
        "v1_external_id": row["external_id"],
        "type": row["type"],
        "name": row.get("localized_names", {}).get("fa", row["canonical_name"]),
        "status": "synthetic_hold",
        "action": "preserve_existing_uat_history_no_auto_match",
    } for row in v1_rows if row["external_id"] not in expected]
    counts = {
        "verified_identity": sum(m["status"] == "verified_identity" for m in matches),
        "municipal_review": sum(m["status"] == "municipal_review" for m in matches),
        "synthetic_hold": len(held),
    }
    return {
        "source_commit": converter.UPSTREAM_COMMIT,
        "source_git_blob_sha1": converter.UPSTREAM_GIT_BLOB,
        "v1_rows": len(v1_rows),
        "counts": counts,
        "matches": matches,
        "held": held,
        "caution": "Read-only candidate crosswalk. Never use it as an UPDATE/DELETE plan without a separately approved migration.",
    }


def main() -> int:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument("--source-dir", required=True, type=Path)
    p.add_argument("--v1-locations", required=True, type=Path)
    p.add_argument("--output", required=True, type=Path, help="New report path; existing files are never overwritten")
    args = p.parse_args()
    if args.output.exists():
        raise ValueError(f"Output exists; refusing overwrite: {args.output}")
    rows, summary = converter.read_rows(converter.source_payload(args.source_dir))
    legacy = [json.loads(line) for line in args.v1_locations.read_text(encoding="utf-8").splitlines() if line.strip()]
    if len(legacy) != EXPECTED_V1_ROWS:
        raise ValueError(f"Expected exactly {EXPECTED_V1_ROWS} reviewed legacy rows; got {len(legacy)}")
    report = audit(rows, legacy)
    if report["counts"] != EXPECTED_COUNTS or summary["total_rows"] != 105475:
        raise ValueError(f"Unexpected crosswalk counts; stop: {report['counts']}")
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open("x", encoding="utf-8") as stream:
        json.dump(report, stream, ensure_ascii=False, indent=2)
        stream.write("\n")
    print(json.dumps({"v1_rows": report["v1_rows"], "counts": report["counts"],
                      "output": str(args.output)}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
