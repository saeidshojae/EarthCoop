import 'dart:async';

import 'package:huawei_push/huawei_push.dart';

import 'push_provider_selector.dart';
import 'push_token_source.dart';

class HmsPushTokenSource implements PushTokenSource {
  const HmsPushTokenSource({
    this.initialTokenTimeout = const Duration(seconds: 15),
  });

  final Duration initialTokenTimeout;

  @override
  PushProvider get provider => PushProvider.hms;

  @override
  Future<String?> initialize() async {
    await Push.setAutoInitEnabled(true);
    final tokenFuture = Push.getTokenStream.first.timeout(
      initialTokenTimeout,
      onTimeout: () => '',
    );
    await Push.getToken('');
    final token = await tokenFuture;
    return token.isEmpty ? null : token;
  }

  @override
  Stream<String> get tokenChanges => Push.getTokenStream;

  @override
  Future<void> dispose() async {}
}
