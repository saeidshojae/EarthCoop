import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('UAT APK signing uses a stable secret-backed keystore contract', () {
    final gradle = File('android/app/build.gradle.kts').readAsStringSync();
    final workflow = File(
      '../../.github/workflows/mobile-uat-publish.yml',
    ).readAsStringSync();

    expect(gradle, contains('EARTHCOOP_UAT_KEYSTORE_PATH'));
    expect(gradle, contains('EARTHCOOP_UAT_KEY_ALIAS'));
    expect(gradle, contains('EARTHCOOP_UAT_STORE_PASSWORD'));
    expect(gradle, contains('EARTHCOOP_UAT_KEY_PASSWORD'));

    expect(workflow, contains('ANDROID_UAT_KEYSTORE_BASE64'));
    expect(workflow, contains('ANDROID_UAT_KEY_ALIAS'));
    expect(workflow, contains('ANDROID_UAT_STORE_PASSWORD'));
    expect(workflow, contains('ANDROID_UAT_KEY_PASSWORD'));
    expect(workflow, contains('EARTHCOOP_UAT_KEYSTORE_PATH'));

    expect(
      workflow,
      isNot(contains('apps/mobile/android/app/uat.keystore')),
    );
    expect(workflow, isNot(contains('apps/mobile/android/app/uat.jks')));
  });

  test('UAT host publication requires explicit manual opt-in', () {
    final workflow = File(
      '../../.github/workflows/mobile-uat-publish.yml',
    ).readAsStringSync();

    expect(workflow, contains('publish_to_host:'));
    expect(workflow, contains('default: false'));
    expect(
      RegExp(r'if:\s*\$\{\{\s*inputs\.publish_to_host\s*==\s*true\s*\}\}').allMatches(workflow).length,
      greaterThanOrEqualTo(2),
      reason: 'Both FTP validation and host publication must be opt-in guarded.',
    );
  });
}
