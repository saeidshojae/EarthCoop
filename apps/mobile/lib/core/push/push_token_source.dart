import 'push_provider_selector.dart';

abstract interface class PushTokenSource {
  PushProvider get provider;

  Future<String?> initialize();

  Stream<String> get tokenChanges;

  Future<void> dispose();
}
