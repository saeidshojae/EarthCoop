import 'dart:async';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/auth/session_controller.dart';
import 'package:earthcoop_mobile/core/auth/session_models.dart';
import 'package:earthcoop_mobile/core/push/session_push_binding.dart';
import 'package:earthcoop_mobile/core/push/push_provider_selector.dart';
import 'package:earthcoop_mobile/core/push/push_token_source.dart';
import 'package:flutter_test/flutter_test.dart';

import 'push_registration_service_test.dart'
    show
        SequenceHttpAdapter,
        FakePushTokenSource,
        jsonResponse,
        successData,
        pushData;

NativeSession session(int user) => NativeSession(
    token: 'account-$user',
    expiresAt: null,
    user: SessionUser(id: user, firstName: 'Member', lastName: ''),
    device: SessionDevice(
        id: 'device-$user',
        platform: 'android',
        appVersion: '1',
        locale: 'fa',
        timezone: 'Asia/Tehran',
        pushCapable: true));

void main() {
  test(
      'production binding captures matching bearer and device for each account',
      () async {
    final adapter = SequenceHttpAdapter([
      jsonResponse(200, successData(pushData(provider: 'fcm'))),
      jsonResponse(200, successData(pushData(provider: 'fcm'))),
    ]);
    final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'))
      ..httpClientAdapter = adapter;
    var current = SessionState.authenticated(session(1));
    var calls = 0;
    final first = FakePushTokenSource(
        provider: PushProvider.fcm, initialToken: 'provider-one');
    final second = FakePushTokenSource(
        provider: PushProvider.fcm, initialToken: 'provider-two');
    final coordinator = createSessionPushCoordinator(
        dio: dio,
        sessionState: () => current,
        canRegister: () => true,
        tokenSourceFactory: () async => calls++ == 0 ? first : second,
        requestIdFactory: () => 'binding',
        retryDelay: (_) async {});
    await coordinator.activate(current.session!);
    current = SessionState.authenticated(session(2));
    await coordinator.activate(current.session!);
    first.emit('obsolete');
    await first.flush();
    expect(adapter.requests, hasLength(2));
    expect(adapter.requests[0].headers['Authorization'], 'Bearer account-1');
    expect(adapter.requests[0].headers['X-Device-ID'], 'device-1');
    expect(adapter.requests[1].headers['Authorization'], 'Bearer account-2');
    expect(adapter.requests[1].headers['X-Device-ID'], 'device-2');
    expect(adapter.requests[1].path, '/devices/device-2/push');
    await coordinator.dispose();
  });

  test('blocked bootstrap prevents provider startup and later permits recovery',
      () async {
    final adapter = SequenceHttpAdapter(
        [jsonResponse(200, successData(pushData(provider: 'fcm')))]);
    final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'))
      ..httpClientAdapter = adapter;
    final current = SessionState.authenticated(session(1));
    var allowed = false;
    final source = FakePushTokenSource(
        provider: PushProvider.fcm, initialToken: 'provider');
    final coordinator = createSessionPushCoordinator(
        dio: dio,
        sessionState: () => current,
        canRegister: () => allowed,
        tokenSourceFactory: () async => source,
        requestIdFactory: () => 'binding',
        retryDelay: (_) async {});
    await coordinator.activate(current.session!);
    expect(source.initializeCalls, 0);
    expect(adapter.requests, isEmpty);
    allowed = true;
    await coordinator.retry();
    expect(adapter.requests, hasLength(1));
    allowed = false;
    source.emit('blocked');
    await source.flush();
    expect(adapter.requests, hasLength(1));
    await coordinator.dispose();
  });

  test(
      'account changes during provider initialization cannot send old credentials',
      () async {
    final adapter = SequenceHttpAdapter([]);
    final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'))
      ..httpClientAdapter = adapter;
    var current = SessionState.authenticated(session(1));
    final selected = Completer<PushTokenSource?>();
    final entered = Completer<void>();
    final source =
        FakePushTokenSource(provider: PushProvider.fcm, initialToken: 'late');
    final coordinator = createSessionPushCoordinator(
        dio: dio,
        sessionState: () => current,
        canRegister: () => true,
        tokenSourceFactory: () {
          entered.complete();
          return selected.future;
        },
        requestIdFactory: () => 'binding',
        retryDelay: (_) async {});
    final activation = coordinator.activate(current.session!);
    await entered.future;
    current = SessionState.authenticated(session(2));
    selected.complete(source);
    await activation;
    expect(source.initializeCalls, 0);
    expect(adapter.requests, isEmpty);
    await coordinator.dispose();
    expect(source.disposeCalls, 1);
  });
}
