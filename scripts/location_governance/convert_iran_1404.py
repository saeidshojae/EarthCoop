#!/usr/bin/env python3
"""Validate the pinned Iranian divisions CSV and stage a v2 geography candidate.

No database connection, SQL, Artisan invocation, or repository v1 write occurs here.
Settlements are quarantined pending a trustworthy residential classification;
source urban-zone rows are staged, but still require municipal reconciliation.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import io
import json
from collections import Counter
from pathlib import Path

UPSTREAM_COMMIT = '68687cf96cc1852d5d38c7283353c80829331758'
UPSTREAM_GIT_BLOB = 'ca9f4a0d69c7c9d77e6434447c7fe123a322271e'
EXPECTED_COUNTS = {0: 1, 1: 31, 2: 484, 3: 1193, 4: 2777, 5: 1481, 6: 99317, 7: 191}
TYPE_KEYS = {0: 'country', 1: 'province', 2: 'county', 3: 'section', 4: 'rural_district', 5: 'city', 6: 'village', 7: 'urban_region'}
PARENT_TYPES = {0: set(), 1: {0}, 2: {1}, 3: {2}, 4: {3}, 5: {3}, 6: {4}, 7: {5}}
QUARANTINED_TYPES = {6}
HEADER = ['Id', 'ParentCountryDivisionId', 'Name', 'Code', 'DivisionType']


def source_payload(source_dir: Path) -> bytes:
    # Explicitly reject missing/extra chunks; never silently ingest a partial dataset.
    paths = sorted(source_dir.glob('iran.part-*.csv'))
    if [p.name for p in paths] != [f'iran.part-{n:02}.csv' for n in range(1, 10)]:
        raise ValueError('Iran 1404 source must contain exactly the nine pinned CSV chunks')
    return b''.join(p.read_bytes() for p in paths)


def git_blob_sha1(data: bytes) -> str:
    return hashlib.sha1(b'blob ' + str(len(data)).encode('ascii') + b'\0' + data).hexdigest()


def read_rows(payload: bytes, allow_fixture: bool = False) -> tuple[list[dict], dict]:
    blob = git_blob_sha1(payload)
    if not allow_fixture and blob != UPSTREAM_GIT_BLOB:
        raise ValueError(f'Source Git blob mismatch: expected {UPSTREAM_GIT_BLOB}, got {blob}')
    text = payload.decode('utf-8-sig')
    reader = csv.DictReader(io.StringIO(text, newline=''))
    if reader.fieldnames != HEADER:
        raise ValueError(f'Unexpected CSV header: {reader.fieldnames!r}')

    rows = []
    seen: dict[int, int] = {}
    counts: Counter[int] = Counter()
    roots = 0
    code_keys: set[tuple[int, str]] = set()
    for record in reader:
        number = reader.line_num
        if None in record or any(value is None for value in record.values()):
            raise ValueError(f'Malformed CSV at physical line {number}')
        try:
            id_ = int(record['Id'])
            parent = int(record['ParentCountryDivisionId']) if record['ParentCountryDivisionId'] else None
            type_ = int(record['DivisionType'])
        except (TypeError, ValueError) as exc:
            raise ValueError(f'Invalid row identity on physical line {number}') from exc
        name = record['Name'].strip()
        code = record['Code'].strip()
        if id_ < 1 or not name or not code or type_ not in TYPE_KEYS:
            raise ValueError(f'Invalid identity/name/type on physical line {number}')
        if id_ in seen:
            raise ValueError(f'Duplicate source ID {id_} on physical line {number}')
        if parent is None:
            roots += 1
            if type_ != 0:
                raise ValueError(f'Non-country root at source ID {id_}')
        else:
            if parent not in seen:
                raise ValueError(f'Missing or out-of-order parent {parent} for {id_}')
            if seen[parent] not in PARENT_TYPES[type_]:
                raise ValueError(f'Invalid parent type for {id_}: {seen[parent]} -> {type_}')
        if type_ in (5, 6, 7):
            code_key = (type_, code)
            if code_key in code_keys:
                raise ValueError(f'Duplicate source code for type {type_}: {code}')
            code_keys.add(code_key)
        seen[id_] = type_
        counts[type_] += 1
        rows.append({'id': id_, 'parent': parent, 'name': name, 'code': code, 'type': type_})
    if roots != 1:
        raise ValueError(f'Expected one country root; got {roots}')
    if not allow_fixture and dict(counts) != EXPECTED_COUNTS:
        raise ValueError(f'Source count mismatch: {dict(counts)}')
    summary = {
        'source_git_blob_sha1': blob,
        'source_sha256': hashlib.sha256(payload).hexdigest(),
        'source_bytes': len(payload),
        'total_rows': len(rows),
        'counts_by_division_type': {TYPE_KEYS[t]: counts[t] for t in sorted(TYPE_KEYS)},
    }
    return rows, summary


def reference_row(row: dict) -> dict:
    t = row['type']
    return {
        'external_id': f'IR-1404-{row["id"]}',
        'parent_external_id': f'IR-1404-{row["parent"]}' if row['parent'] is not None else None,
        'type': TYPE_KEYS[t],
        # Do not fabricate an English translation. Canonical name is the
        # source's verified Persian name until a sourced translation exists.
        'canonical_name': row['name'],
        'localized_names': {'fa': row['name']},
        'status': 'active',
        'provenance': {
            'source': 'IranCountryDivisions/geo_1404',
            'source_commit': UPSTREAM_COMMIT,
            'source_row_id': row['id'],
            'source_code': row['code'],
            'source_division_type': t,
            'dataset_year': 1404,
        },
        'metadata': {'governance_authorized': t != 6, 'source_authoritative': True},
    }


def convert(payload: bytes, output_dir: Path, schema_template: Path, allow_fixture: bool = False) -> dict:
    if output_dir.exists():
        raise ValueError(f'Output path already exists; no overwrite permitted: {output_dir}')
    if output_dir.name.lower() == 'v1' or '/database/reference/ir/v1' in output_dir.as_posix().lower():
        raise ValueError('Refusing to overwrite any v1 reference data')
    rows, manifest = read_rows(payload, allow_fixture=allow_fixture)
    schema = json.loads(schema_template.read_text(encoding='utf-8'))
    if schema.get('country_code') != 'IR' or schema.get('version') != 'v1':
        raise ValueError('Unexpected input schema template')
    schema.update({'key': 'ir-reference-v2', 'version': 'v2', 'name': 'Iran real reference candidate 1404 (staging)'})
    ids = {row['id'] for row in rows if row['type'] not in QUARANTINED_TYPES}
    main = [reference_row(row) for row in rows if row['type'] not in QUARANTINED_TYPES]
    quarantine = [
        {
            # DivisionType=6 means an administrative آبادی, which can be a
            # village, farm, place or mine. Keep the verified geography but
            # never present an unverified settlement as an official village.
            **reference_row(row),
            'type': 'settlement',
            'classification': 'unverified_settlement',
            'review_reason': 'Administrative settlement type does not establish residential village eligibility',
            'metadata': {
                **reference_row(row)['metadata'],
                'settlement_kind': 'unknown',
                'residential_eligibility': 'unverified',
                'is_residence_endpoint': False,
                'importable': False,
                'governance_authorized': False,
            },
        }
        for row in rows if row['type'] in QUARANTINED_TYPES
    ]
    if len(main) + len(quarantine) != len(rows):
        raise AssertionError('No source row may disappear')
    if any(int(row['parent_external_id'].split('-')[-1]) not in ids for row in main if row['parent_external_id']):
        raise AssertionError('Active staging row has missing/quarantined parent')
    manifest.update({
        'source_repository': 'Hameds/IranCountryDivisions',
        'source_commit': UPSTREAM_COMMIT,
        'license': 'MIT; see source/ir/1404/LICENSE.MIT.txt',
        'dataset_version': 'v2',
        'staged_active_rows': len(main),
        'quarantined_settlements': len(quarantine),
        'settlement_classification': {
            'geographic_identity_verified': len(quarantine),
            'residential_eligibility_unverified': len(quarantine),
            'residential_villages_verified': 0,
            'nonresidential_settlements_verified': 0,
            'governance_authorized': 0,
        },
        'warning': 'STAGING ONLY. Administrative levels are authoritative source data; Production apply still requires a separate rollout approval.',
    })
    output_dir.mkdir(parents=True, exist_ok=False)
    (output_dir / 'schema.json').write_text(json.dumps(schema, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    for name, data in [('locations.jsonl', main), ('settlements.review.jsonl', quarantine)]:
        with (output_dir / name).open('w', encoding='utf-8', newline='\n') as handle:
            for row in data:
                handle.write(json.dumps(row, ensure_ascii=False, separators=(',', ':')) + '\n')
    manifest['settlements_review_sha256'] = hashlib.sha256((output_dir / 'settlements.review.jsonl').read_bytes()).hexdigest()
    (output_dir / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source-dir', type=Path, required=True, help='Directory holding nine pinned source chunks')
    parser.add_argument('--schema-template', type=Path, required=True, help='Reviewed IR v1 schema template')
    parser.add_argument('--output-dir', type=Path, required=True, help='NEW empty-free output path; must not exist')
    args = parser.parse_args()
    manifest = convert(source_payload(args.source_dir), args.output_dir, args.schema_template)
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
