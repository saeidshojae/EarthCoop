import 'package:earthcoop_mobile/core/push/fcm_client_configuration.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('absent configuration explicitly leaves FCM unavailable', () {
    expect(
        fcmOptionsFromValues(
            apiKey: '', appId: '', senderId: '', projectId: ''),
        isNull);
  });
  test('partial or mismatched project configuration is rejected', () {
    expect(
        () => fcmOptionsFromValues(
            apiKey: 'key', appId: '', senderId: '123', projectId: 'project'),
        throwsFormatException);
    expect(
        () => fcmOptionsFromValues(
            apiKey: 'key',
            appId: '1:456:android:a',
            senderId: '123',
            projectId: 'project'),
        throwsFormatException);
  });
  test('complete Android client values produce Firebase options', () {
    final options = fcmOptionsFromValues(
        apiKey: 'synthetic-key',
        appId: '1:123:android:a',
        senderId: '123',
        projectId: 'earthcoop-test')!;
    expect(options.apiKey, 'synthetic-key');
    expect(options.projectId, 'earthcoop-test');
    expect(options.messagingSenderId, '123');
    expect(options.appId, '1:123:android:a');
  });
}
