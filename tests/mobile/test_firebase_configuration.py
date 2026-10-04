import importlib.util
import pathlib
import unittest

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

if __name__ == '__main__':
    unittest.main()
