import 'package:earthcoop_mobile/core/push/fcm_push_token_source.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('FCM token changes can be observed before Firebase initialization', () {
    final source = FcmPushTokenSource();
    expect(() => source.tokenChanges, returnsNormally);
  });
}
