import csv
import importlib.util
import io
import json
import tempfile
import unittest
from pathlib import Path

MODULE_PATH = Path(__file__).with_name('convert_iran_1404.py')
spec = importlib.util.spec_from_file_location('convert_iran_1404', MODULE_PATH)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


def payload(records):
    stream = io.StringIO(newline='')
    writer = csv.writer(stream, lineterminator='\n')
    writer.writerow(module.HEADER)
    writer.writerows(records)
    return stream.getvalue().encode('utf-8')


ROWS = [
    [1, '', 'ایران', 'ir', 0],
    [2, 1, 'مازندران', '02', 1],
    [3, 2, 'ساری', '07', 2],
    [4, 3, 'چهاردانگه', '01', 3],
    [5, 4, 'دهستان چهاردانگه', '0002', 4],
    [6, 4, 'کیاسر', '2170', 5],
    [7, 5, 'گاوداری علیزاده, نجمی و صمدی', '341126', 6],
    [8, 6, 'کیاسر ۱', '2999', 7],
]


class IranCandidateConversionTests(unittest.TestCase):
    def test_csv_quotes_and_ancestors_and_counts(self):
        parsed, info = module.read_rows(payload(ROWS), allow_fixture=True)
        self.assertEqual(8, len(parsed))
        self.assertEqual('گاوداری علیزاده, نجمی و صمدی', parsed[6]['name'])
        self.assertEqual(1, info['counts_by_division_type']['village'])
        self.assertEqual(1, info['counts_by_division_type']['urban_region'])

    def test_source_git_blob_pinning_is_mandatory(self):
        with self.assertRaisesRegex(ValueError, 'Git blob mismatch'):
            module.read_rows(payload(ROWS))

    def test_bad_parent_or_duplicate_row_fails_closed(self):
        for extra, expected in [
            ([10, 99, 'بی‌والد', '33', 1], 'parent'),
            ([6, 4, 'تکراری', '2171', 5], 'Duplicate source ID'),
            ([10, 3, 'روستای بدون دهستان', '500100', 6], 'Invalid parent type'),
            ([10, 4, 'کد شهر تکراری', '2170', 5], 'Duplicate source code'),
        ]:
            with self.subTest(extra=extra), self.assertRaisesRegex(ValueError, expected):
                module.read_rows(payload(ROWS + [extra]), allow_fixture=True)

    def test_conversion_separates_settlements_and_preserves_provenance(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            schema = root / 'schema.json'
            schema.write_text(json.dumps({'key': 'ir-reference-v1', 'country_code': 'IR', 'version': 'v1',
                                          'name': 'Iran v1', 'types': [], 'relations': []}), encoding='utf-8')
            out = root / 'candidate'
            result = module.convert(payload(ROWS), out, schema, allow_fixture=True)
            main = [json.loads(line) for line in (out / 'locations.jsonl').read_text(encoding='utf-8').splitlines()]
            review = [json.loads(line) for line in (out / 'settlements.review.jsonl').read_text(encoding='utf-8').splitlines()]
            self.assertEqual(7, result['staged_active_rows'])
            self.assertEqual(1, result['quarantined_settlements'])
            self.assertEqual(result['settlements_review_sha256'], module.hashlib.sha256((out / 'settlements.review.jsonl').read_bytes()).hexdigest())
            self.assertEqual('IR-1404-4', main[4]['parent_external_id'])
            self.assertEqual('IR-1404-5', review[0]['parent_external_id'])
            self.assertFalse(review[0]['metadata']['governance_authorized'])
            self.assertEqual('settlement', review[0]['type'])
            self.assertEqual('unverified', review[0]['metadata']['residential_eligibility'])
            self.assertEqual('unknown', review[0]['metadata']['settlement_kind'])
            self.assertFalse(review[0]['metadata']['is_residence_endpoint'])
            self.assertFalse(review[0]['metadata']['importable'])
            self.assertEqual(0, result['settlement_classification']['residential_villages_verified'])
            self.assertEqual(1, result['settlement_classification']['residential_eligibility_unverified'])
            self.assertNotIn('settlement', [type_['key'] for type_ in
                                           json.loads((out / 'schema.json').read_text(encoding='utf-8'))['types']])
            self.assertTrue(main[-1]['metadata']['governance_authorized'])
            self.assertTrue(main[-1]['metadata']['source_authoritative'])
            self.assertEqual('v2', json.loads((out / 'schema.json').read_text(encoding='utf-8'))['version'])
            self.assertEqual('IR-1404-1', main[0]['external_id'])
            self.assertEqual(1404, main[0]['provenance']['dataset_year'])
            with self.assertRaisesRegex(ValueError, 'already exists'):
                module.convert(payload(ROWS), out, schema, allow_fixture=True)
            self.assertTrue((out / 'locations.jsonl').exists())

    def test_village_sounding_name_cannot_self_certify_residence_or_governance(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            schema = root / 'schema.json'
            schema.write_text(json.dumps({'key': 'ir-reference-v1', 'country_code': 'IR', 'version': 'v1',
                                          'name': 'Iran v1', 'types': [], 'relations': []}), encoding='utf-8')
            village_sounding = [list(row) for row in ROWS]
            village_sounding[6][2] = 'روستای نمونه مسکونی'
            out = root / 'candidate'
            module.convert(payload(village_sounding), out, schema, allow_fixture=True)
            review = json.loads((out / 'settlements.review.jsonl').read_text(encoding='utf-8').strip())
            self.assertEqual('روستای نمونه مسکونی', review['canonical_name'])
            self.assertEqual('settlement', review['type'])
            self.assertEqual('unverified_settlement', review['classification'])
            self.assertEqual('unverified', review['metadata']['residential_eligibility'])
            self.assertFalse(review['metadata']['is_residence_endpoint'])
            self.assertFalse(review['metadata']['importable'])
            self.assertFalse(review['metadata']['governance_authorized'])

    def test_v1_target_is_always_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            schema = root / 'template.json'
            schema.write_text(json.dumps({'country_code': 'IR', 'version': 'v1'}), encoding='utf-8')
            with self.assertRaisesRegex(ValueError, 'v1'):
                module.convert(payload(ROWS), root / 'database' / 'reference' / 'ir' / 'v1', schema,
                               allow_fixture=True)

    def test_missing_source_chunk_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            with self.assertRaisesRegex(ValueError, 'nine pinned'):
                module.source_payload(Path(temp))


if __name__ == '__main__':
    unittest.main()
