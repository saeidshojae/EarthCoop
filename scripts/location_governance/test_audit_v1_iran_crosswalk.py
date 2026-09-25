import importlib.util
import unittest
from pathlib import Path

SOURCE = Path(__file__).with_name("audit_v1_iran_crosswalk.py")
spec = importlib.util.spec_from_file_location("iran_crosswalk_audit", SOURCE)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

OLD = [
    {"external_id": "country", "parent_external_id": None, "type": "country",
     "canonical_name": "Iran", "localized_names": {"fa": "ایران"}},
    {"external_id": "province", "parent_external_id": "country", "type": "province",
     "canonical_name": "Mazandaran", "localized_names": {"fa": "مازندران"}},
]
SOURCE_ROWS = [
    {"id": 1, "parent": None, "type": 0, "name": "ایران", "code": "ir"},
    {"id": 4, "parent": 1, "type": 1, "name": "مازندران", "code": "02"},
]
EXPECTED = {
    "country": (1, "country", None, "ایران"),
    "province": (4, "province", "country", "مازندران"),
}


class ReadOnlyCrosswalkTests(unittest.TestCase):
    def test_exact_parent_type_and_source_identity_are_verified(self):
        report = module.audit(SOURCE_ROWS, OLD, EXPECTED)
        self.assertEqual(2, report["counts"]["verified_identity"])
        self.assertEqual(0, report["counts"]["synthetic_hold"])
        self.assertEqual("IR-1404-4", report["matches"][1]["candidate_v2_external_id"])
        self.assertEqual("review_only_no_database_changes", report["matches"][1]["action"])

    def test_unmatched_legacy_rows_are_held_not_fuzzy_matched(self):
        extra = {"external_id": "reference-village", "parent_external_id": "province",
                 "type": "village", "canonical_name": "Reference Village",
                 "localized_names": {"fa": "روستای مرجع بدون محله"}}
        report = module.audit(SOURCE_ROWS, OLD + [extra], EXPECTED)
        self.assertEqual(1, report["counts"]["synthetic_hold"])
        self.assertEqual("reference-village", report["held"][0]["v1_external_id"])
        self.assertIn("no_auto_match", report["held"][0]["action"])

    def test_changed_parent_name_and_type_fail_closed(self):
        bad_cases = [
            ([SOURCE_ROWS[0], {**SOURCE_ROWS[1], "parent": None}], "parent lineage"),
            ([SOURCE_ROWS[0], {**SOURCE_ROWS[1], "name": "نام دیگر"}], "identity/type/name"),
            ([SOURCE_ROWS[0], {**SOURCE_ROWS[1], "type": 2}], "identity/type/name"),
        ]
        for changed, error in bad_cases:
            with self.subTest(error=error), self.assertRaisesRegex(ValueError, error):
                module.audit(changed, OLD, EXPECTED)

    def test_changed_legacy_parent_and_many_to_one_mapping_fail_closed(self):
        changed = [OLD[0], {**OLD[1], "parent_external_id": None}]
        with self.assertRaisesRegex(ValueError, "v1 identity/type/parent"):
            module.audit(SOURCE_ROWS, changed, EXPECTED)

        duplicate_mapping = {
            **EXPECTED, "duplicate": (4, "province", "country", "مازندران")
        }
        with self.assertRaisesRegex(ValueError, "Missing expected v1"):
            module.audit(SOURCE_ROWS, OLD, duplicate_mapping)
        with self.assertRaisesRegex(ValueError, "Multiple v1 identities"):
            module.audit(SOURCE_ROWS, OLD + [{"external_id": "duplicate", "parent_external_id": "country",
                                             "type": "province", "canonical_name": "Duplicate"}],
                         duplicate_mapping)

    def test_missing_expected_source_or_legacy_identity_fails_closed(self):
        with self.assertRaisesRegex(ValueError, "Missing expected v1"):
            module.audit(SOURCE_ROWS, OLD[:1], EXPECTED)
        with self.assertRaisesRegex(ValueError, "Source identity"):
            module.audit(SOURCE_ROWS[:1], OLD, EXPECTED)


if __name__ == "__main__":
    unittest.main()
