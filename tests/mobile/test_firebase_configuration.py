import importlib.util
import pathlib
import unittest
import tempfile
import subprocess
import json
import os
import sys
import xml.etree.ElementTree as ET

PATH = pathlib.Path(__file__).resolve().parents[2] / 'scripts/mobile/firebase_configuration.py'

class FirebaseConfigurationTest(unittest.TestCase):
    def test_selects_only_matching_android_application(self):
        spec = importlib.util.spec_from_file_location('firebase_configuration', PATH)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        config = {
            'project_info': {'project_id': 'earthcoop-test', 'project_number': '123456789'},
            'client': [
                {'client_info': {'mobilesdk_app_id': '1:123456789:android:bbbb',
                    'android_client_info': {'package_name': 'other.app'}},
                 'api_key': [{'current_key': 'other-key'}]},
                {'client_info': {'mobilesdk_app_id': '1:123456789:android:aaaa',
                    'android_client_info': {'package_name': 'coop.earthcoop.earthcoop_mobile'}},
                 'api_key': [{'current_key': 'synthetic-client-key'}]},
            ],
        }
        self.assertEqual(module.firebase_defines(config), {
            'EARTHCOOP_FCM_PROJECT_ID': 'earthcoop-test',
            'EARTHCOOP_FCM_SENDER_ID': '123456789',
            'EARTHCOOP_FCM_APP_ID': '1:123456789:android:aaaa',
            'EARTHCOOP_FCM_API_KEY': 'synthetic-client-key',
        })

    def test_server_credentials_are_never_accepted_as_apk_configuration(self):
        spec = importlib.util.spec_from_file_location('firebase_configuration', PATH)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        with self.assertRaises(ValueError):
            module.firebase_defines({'type': 'service_account', 'private_key': 'not-a-real-key'})

    def run_cli(self, directory, document=None, allow_missing=False):
        directory = pathlib.Path(directory)
        output = directory / 'defines.json'
        resources = directory / 'firebase.xml'
        args = [sys.executable, str(PATH), '--output', str(output), '--android-resources', str(resources)]
        if document is not None:
            source = directory / 'input.json'
            source.write_text(json.dumps(document))
            args += ['--input', str(source)]
        if allow_missing:
            args += ['--allow-missing']
        env = dict(os.environ)
        env.pop('EARTHCOOP_FIREBASE_ANDROID_JSON_BASE64', None)
        return subprocess.run(args, env=env, text=True, capture_output=True), output, resources

    def test_missing_optional_configuration_clears_stale_android_resources(self):
        with tempfile.TemporaryDirectory() as directory:
            (pathlib.Path(directory) / 'firebase.xml').write_text('stale')
            result, output, resources = self.run_cli(directory, allow_missing=True)
            self.assertEqual(result.returncode, 0)
            self.assertEqual(json.loads(output.read_text()), {})
            self.assertFalse(resources.exists())
            self.assertIn('unavailable', result.stdout)

    def test_invalid_configuration_fails_without_echoing_values_or_writing_outputs(self):
        with tempfile.TemporaryDirectory() as directory:
            result, output, resources = self.run_cli(directory, {'private_key': 'SYNTHETIC_DO_NOT_ECHO'}, True)
            self.assertEqual(result.returncode, 1)
            self.assertNotIn('SYNTHETIC_DO_NOT_ECHO', result.stdout + result.stderr)
            self.assertFalse(output.exists())
            self.assertFalse(resources.exists())

    def test_android_resources_and_dart_defines_use_the_same_client(self):
        document = {
            'project_info': {'project_id': 'earthcoop-test', 'project_number': '123456789'},
            'client': [{'client_info': {'mobilesdk_app_id': '1:123456789:android:aaaa',
                'android_client_info': {'package_name': 'coop.earthcoop.earthcoop_mobile'}},
                'api_key': [{'current_key': 'synthetic-client-key'}]}],
        }
        with tempfile.TemporaryDirectory() as directory:
            result, output, resources = self.run_cli(directory, document)
            self.assertEqual(result.returncode, 0, result.stderr)
            defines = json.loads(output.read_text())
            strings = {item.attrib['name']: item.text for item in ET.parse(resources).getroot()}
            self.assertEqual(strings['google_app_id'], defines['EARTHCOOP_FCM_APP_ID'])
            self.assertEqual(strings['project_id'], defines['EARTHCOOP_FCM_PROJECT_ID'])
            self.assertEqual(strings['gcm_defaultSenderId'], defines['EARTHCOOP_FCM_SENDER_ID'])
            self.assertEqual(strings['google_api_key'], defines['EARTHCOOP_FCM_API_KEY'])
            self.assertEqual(output.stat().st_mode & 0o777, 0o600)
            self.assertNotIn('synthetic-client-key', result.stdout + result.stderr)

if __name__ == '__main__':
    unittest.main()
