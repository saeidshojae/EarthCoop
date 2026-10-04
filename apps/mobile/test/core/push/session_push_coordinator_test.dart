import 'dart:async';

import 'package:earthcoop_mobile/core/push/session_push_coordinator.dart';
import 'package:earthcoop_mobile/core/push/push_registration_service.dart';
import 'package:earthcoop_mobile/core/push/push_provider_selector.dart';
import 'package:flutter_test/flutter_test.dart';

import '../auth/session_controller_test.dart' show sampleSession;
import 'push_registration_service_test.dart'
    show
        SequenceHttpAdapter,
        FakePushTokenSource,
        buildClient,
        jsonResponse,
        successData,
        pushData;

void main() {
  test(
      'credential replacement stops prior token observer before new registration',
      () async {
    final first =
        FakePushTokenSource(provider: PushProvider.fcm, initialToken: 'first');
    final second =
        FakePushTokenSource(provider: PushProvider.fcm, initialToken: 'second');
    final adapters = [
      SequenceHttpAdapter(
          [jsonResponse(200, successData(pushData(provider: 'fcm')))]),
      SequenceHttpAdapter(
          [jsonResponse(200, successData(pushData(provider: 'fcm')))]),
    ];
    var created = 0;
    final coordinator =
        SessionPushCoordinator(createRegistration: (session) async {
      final index = created++;
      return PushRegistrationService(
          apiClient: buildClient(adapters[index]),
          deviceId: session.device.id,
          tokenSource: index == 0 ? first : second);
    });
    await coordinator.activate(sampleSession(token: 'account-one'));
    await coordinator.activate(sampleSession(token: 'account-two'));
    first.emit('obsolete');
    await first.flush();
    expect(first.disposeCalls, 1);
    expect(adapters.first.requests, hasLength(1));
    expect(adapters.last.requests.single.data,
        {'provider': 'fcm', 'token': 'second'});
    await coordinator.dispose();
    expect(second.disposeCalls, 1);
  });

  test('logout while provider selection is pending prevents registration',
      () async {
    final selected = Completer<PushRegistrationService>();
    final entered = Completer<void>();
    final adapter = SequenceHttpAdapter([]);
    final source =
        FakePushTokenSource(provider: PushProvider.fcm, initialToken: 'late');
    final coordinator = SessionPushCoordinator(createRegistration: (_) {
      entered.complete();
      return selected.future;
    });
    final activation = coordinator.activate(sampleSession());
    await entered.future;
    await coordinator.disable();
    selected.complete(PushRegistrationService(
        apiClient: buildClient(adapter),
        deviceId: 'device-1',
        tokenSource: source));
    await activation;
    expect(adapter.requests, isEmpty);
    expect(source.disposeCalls, 1);
  });

  test('foreground retry registers retained token after a transient failure',
      () async {
    final adapter = SequenceHttpAdapter([
      jsonResponse(503, {
        'status': 'error',
        'data': null,
        'error': {
          'code': 'unavailable',
          'message': 'Try later',
          'retryable': true,
        },
        'meta': {'api_version': 'v1'},
        'request_id': 'retry'
      }),
      jsonResponse(200, successData(pushData(provider: 'fcm'))),
    ]);
    final source = FakePushTokenSource(
        provider: PushProvider.fcm, initialToken: 'retained');
    final coordinator = SessionPushCoordinator(
        createRegistration: (session) async => PushRegistrationService(
            apiClient: buildClient(adapter),
            deviceId: session.device.id,
            tokenSource: source));
    await coordinator.activate(sampleSession());
    await coordinator.retry();
    expect(adapter.requests, hasLength(2));
    expect(source.initializeCalls, 1);
    await coordinator.dispose();
  });
}
