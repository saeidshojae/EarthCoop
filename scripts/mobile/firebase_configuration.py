"""Convert Android client configuration to build defines; never accept server keys."""
import argparse
import base64
import json
import os
from pathlib import Path
import re
import sys
import xml.etree.ElementTree as ET

PACKAGE = 'coop.earthcoop.earthcoop_mobile'
SECRET_KEYS = {'private_key', 'private_key_id', 'client_email', 'client_secret'}


def _contains_server_credentials(value):
    if isinstance(value, dict):
        if SECRET_KEYS.intersection(value) or value.get('type') == 'service_account':
            return True
        return any(_contains_server_credentials(child) for child in value.values())
    if isinstance(value, list):
        return any(_contains_server_credentials(child) for child in value)
    return False


def _value(value):
    if not isinstance(value, str) or not value or value.strip() != value or re.search(r'\s', value):
        raise ValueError('Firebase client configuration contains invalid required fields')
    return value


def firebase_defines(document):
    if not isinstance(document, dict) or _contains_server_credentials(document):
        raise ValueError('Expected Android client configuration; server credentials are prohibited')
    project = document.get('project_info') or {}
    project_id = _value(project.get('project_id'))
    sender = _value(project.get('project_number'))
    if not sender.isascii() or not sender.isdigit():
        raise ValueError('Firebase project number is invalid')
    clients = document.get('client')
    if not isinstance(clients, list):
        raise ValueError('Firebase Android client list is missing')
    matches = [client for client in clients if isinstance(client, dict)
               and (client.get('client_info') or {}).get('android_client_info', {}).get('package_name') == PACKAGE]
    if len(matches) != 1:
        raise ValueError('Expected exactly one matching EarthCoop Android client')
    client = matches[0]
    app_id = _value(client['client_info'].get('mobilesdk_app_id'))
    if not app_id.startswith(f'1:{sender}:android:') or not app_id.split(':')[-1]:
        raise ValueError('Firebase app ID does not match the project number')
    keys = client.get('api_key')
    if not isinstance(keys, list) or len(keys) != 1 or not isinstance(keys[0], dict):
        raise ValueError('Expected one unambiguous Firebase Android API key')
    return {
        'EARTHCOOP_FCM_PROJECT_ID': project_id,
        'EARTHCOOP_FCM_SENDER_ID': sender,
        'EARTHCOOP_FCM_APP_ID': app_id,
        'EARTHCOOP_FCM_API_KEY': _value(keys[0].get('current_key')),
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--input', type=Path)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--allow-missing', action='store_true')
    parser.add_argument('--android-resources', type=Path)
    args = parser.parse_args()
    encoded = os.environ.get('EARTHCOOP_FIREBASE_ANDROID_JSON_BASE64', '')
    try:
        if args.input and encoded:
            raise ValueError('Select exactly one client configuration source')
        raw = args.input.read_bytes() if args.input else base64.b64decode(encoded, validate=True) if encoded else None
        if raw is None:
            if not args.allow_missing:
                raise ValueError('Firebase Android client configuration is required')
            defines = {}
        else:
            defines = firebase_defines(json.loads(raw))
        args.output.parent.mkdir(parents=True, exist_ok=True)
        descriptor = os.open(args.output, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
        with os.fdopen(descriptor, 'w') as stream:
            json.dump(defines, stream)
        args.output.chmod(0o600)
        if args.android_resources:
            if defines:
                resources = ET.Element('resources')
                names = {'EARTHCOOP_FCM_APP_ID': 'google_app_id',
                         'EARTHCOOP_FCM_API_KEY': 'google_api_key',
                         'EARTHCOOP_FCM_SENDER_ID': 'gcm_defaultSenderId',
                         'EARTHCOOP_FCM_PROJECT_ID': 'project_id'}
                for key, name in names.items():
                    ET.SubElement(resources, 'string', {'name': name, 'translatable': 'false'}).text = defines[key]
                args.android_resources.parent.mkdir(parents=True, exist_ok=True)
                ET.ElementTree(resources).write(args.android_resources, encoding='utf-8', xml_declaration=True)
                args.android_resources.chmod(0o600)
            else:
                args.android_resources.unlink(missing_ok=True)
        print('FCM client configuration: ready' if defines else 'FCM client configuration: unavailable')
        return 0
    except (ValueError, OSError, TypeError, KeyError, AttributeError):
        print('Invalid or missing Firebase Android client configuration; values withheld.', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
