import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

import 'push_provider_selector.dart';
import 'push_token_source.dart';

class FcmPushTokenSource implements PushTokenSource {
  const FcmPushTokenSource();

  @override
  PushProvider get provider => PushProvider.fcm;

  @override
  Future<String?> initialize() async {
    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp();
    }
    return FirebaseMessaging.instance.getToken();
  }

  @override
  Stream<String> get tokenChanges => FirebaseMessaging.instance.onTokenRefresh;

  @override
  Future<void> dispose() async {}
}
