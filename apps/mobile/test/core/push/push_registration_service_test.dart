import 'dart:async';
import 'dart:collection';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/api/retry_policy.dart';
import 'package:earthcoop_mobile/core/logging/diagnostics.dart';
import 'package:earthcoop_mobile/core/push/push_provider_selector.dart';
import 'package:earthcoop_mobile/core/push/push_registration_service.dart';
import 'package:earthcoop_mobile/core/push/push_token_source.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('foreground retry obtains a token when initial provider result is empty',
      () async {
    var attempts = 0;
    final source = FakePushTokenSource(
        provider: PushProvider.fcm,
        initialToken: null,
        initializeToken: () async => attempts++ == 0 ? null : 'recovered');
    final adapter = SequenceHttpAdapter(
        [jsonResponse(200, successData(pushData(provider: 'fcm')))]);
    final service = PushRegistrationService(
        apiClient: buildClient(adapter),
        deviceId: 'device-1',
        tokenSource: source);
    expect(await service.initialize(), PushRegistrationStatus.awaitingToken);
    expect(await service.initialize(), PushRegistrationStatus.registered);
    expect(adapter.requests, hasLength(1));
    expect(source.initializeCalls, 2);
    await service.dispose();
  });

  test(
      'latest rotation during provider initialization wins over stale initial token',
      () async {
    final pending = Completer<String?>();
    final entered = Completer<void>();
    final source = FakePushTokenSource(
        provider: PushProvider.fcm,
        initialToken: null,
        initializeToken: () {
          entered.complete();
          return pending.future;
        });
    final adapter = SequenceHttpAdapter(
        [jsonResponse(200, successData(pushData(provider: 'fcm')))]);
    final service = PushRegistrationService(
        apiClient: buildClient(adapter),
        deviceId: 'device-1',
        tokenSource: source);
    final initialization = service.initialize();
    await entered.future;
    source.emit('newest');
    await adapter.waitForRequestCount(1).timeout(const Duration(seconds: 5));
    await source.flush();
    pending.complete('stale-initial');
    expect(await initialization, PushRegistrationStatus.registered);
    expect(adapter.requests, hasLength(1));
    expect(
        adapter.requests.single.data, {'provider': 'fcm', 'token': 'newest'});
    await service.dispose();
  });

  test('initial registration can recover after a failed HTTP attempt',
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
        'request_id': 'req-push-failure'
      }),
      jsonResponse(200, successData(pushData(provider: 'fcm'))),
    ]);
    final source = FakePushTokenSource(
        provider: PushProvider.fcm, initialToken: 'retry-token');
    final service = PushRegistrationService(
        apiClient: buildClient(adapter),
        deviceId: 'device-1',
        tokenSource: source);
    await expectLater(service.initialize(), throwsA(anything));
    expect(await service.initialize(), PushRegistrationStatus.registered);
    expect(adapter.requests, hasLength(2));
    expect(source.initializeCalls, 1);
  });

  test('failed token rotation is contained and next rotation registers',
      () async {
    final adapter = SequenceHttpAdapter([
      jsonResponse(200, successData(pushData(provider: 'fcm'))),
      jsonResponse(503, {
        'status': 'error',
        'data': null,
        'error': {
          'code': 'unavailable',
          'message': 'Try later',
          'retryable': true,
        },
        'meta': {'api_version': 'v1'},
        'request_id': 'req-push-failure'
      }),
      jsonResponse(200, successData(pushData(provider: 'fcm'))),
    ]);
    final source = FakePushTokenSource(
        provider: PushProvider.fcm, initialToken: 'token-a');
    final service = PushRegistrationService(
        apiClient: buildClient(adapter),
        deviceId: 'device-1',
        tokenSource: source);
    await service.initialize();
    source.emit('token-b');
    await adapter.waitForRequestCount(2).timeout(const Duration(seconds: 5));
    await source.flush();
    source.emit('token-c');
    await adapter.waitForRequestCount(3).timeout(const Duration(seconds: 5));
    expect(adapter.requests.last.data, {'provider': 'fcm', 'token': 'token-c'});
  });

  test('initial token registers once and repeated initialization is idempotent',
      () async {
    final adapter = SequenceHttpAdapter([
      jsonResponse(200, successData(pushData(provider: 'fcm'))),
    ]);
    final source = FakePushTokenSource(
      provider: PushProvider.fcm,
      initialToken: 'push-secret-a',
    );
    final service = PushRegistrationService(
      apiClient: buildClient(adapter),
      deviceId: 'device-1',
      tokenSource: source,
    );

    expect(await service.initialize(), PushRegistrationStatus.registered);
    expect(await service.initialize(), PushRegistrationStatus.registered);

    expect(source.initializeCalls, 1);
    expect(adapter.requests, hasLength(1));
    expect(adapter.requests.single.method, 'PUT');
    expect(adapter.requests.single.path, '/devices/device-1/push');
    expect(adapter.requests.single.data, {
      'provider': 'fcm',
      'token': 'push-secret-a',
    });
  });

  test('token rotation registers the new token without duplicate old delivery',
      () async {
    final adapter = SequenceHttpAdapter([
      jsonResponse(200, successData(pushData(provider: 'fcm'))),
      jsonResponse(200, successData(pushData(provider: 'fcm'))),
    ]);
    final source = FakePushTokenSource(
      provider: PushProvider.fcm,
      initialToken: 'push-secret-a',
    );
    final service = PushRegistrationService(
      apiClient: buildClient(adapter),
      deviceId: 'device-1',
      tokenSource: source,
    );

    await service.initialize();
    source.emit('push-secret-a');
    source.emit('push-secret-b');
    await adapter.waitForRequestCount(2);

    expect(adapter.requests, hasLength(2));
    expect(adapter.requests.last.data, {
      'provider': 'fcm',
      'token': 'push-secret-b',
    });
  });

  test('disable removes server registration and stops token observation',
      () async {
    final adapter = SequenceHttpAdapter([
      jsonResponse(200, successData(pushData(provider: 'hms'))),
      jsonResponse(200, successData(pushData(provider: null, enabled: false))),
    ]);
    final source = FakePushTokenSource(
      provider: PushProvider.hms,
      initialToken: 'hms-secret-a',
    );
    final service = PushRegistrationService(
      apiClient: buildClient(adapter),
      deviceId: 'device-2',
      tokenSource: source,
    );

    await service.initialize();
    await service.disable();
    source.emit('hms-secret-b');
    await source.flush();

    expect(adapter.requests, hasLength(2));
    expect(adapter.requests.last.method, 'DELETE');
    expect(adapter.requests.last.path, '/devices/device-2/push');
    expect(source.disposeCalls, 1);
  });

  test('unavailable source performs no registration', () async {
    final adapter = SequenceHttpAdapter(const []);
    final service = PushRegistrationService(
      apiClient: buildClient(adapter),
      deviceId: 'device-3',
      tokenSource: null,
    );

    expect(await service.initialize(), PushRegistrationStatus.unavailable);
    expect(adapter.requests, isEmpty);
  });

  test('push tokens are never emitted into diagnostics', () async {
    final events = <DiagnosticEvent>[];
    final diagnostics = RedactingDiagnosticsSink(onEvent: events.add);
    final adapter = SequenceHttpAdapter([
      jsonResponse(200, successData(pushData(provider: 'fcm'))),
    ]);
    final source = FakePushTokenSource(
      provider: PushProvider.fcm,
      initialToken: 'push-secret-never-log',
    );
    final service = PushRegistrationService(
      apiClient: buildClient(adapter, diagnostics: diagnostics),
      deviceId: 'device-4',
      tokenSource: source,
      diagnostics: diagnostics,
    );

    await service.initialize();

    final rendered =
        events.map((event) => '${event.name}:${event.data}').join('|');
    expect(rendered, isNot(contains('push-secret-never-log')));
  });
}

ApiClient buildClient(
  SequenceHttpAdapter adapter, {
  DiagnosticsSink diagnostics = const NoopDiagnosticsSink(),
}) {
  final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'));
  dio.httpClientAdapter = adapter;
  return ApiClient(
    dio: dio,
    bearerTokenProvider: () async => 'bearer-secret',
    deviceIdProvider: () async => 'device-bound',
    requestIdFactory: () => 'req-push',
    retryDelay: (_) async {},
    retryPolicy: const RetryPolicy(maxAttempts: 1),
    diagnostics: diagnostics,
  );
}

Map<String, Object?> pushData({
  required String? provider,
  bool enabled = true,
}) =>
    {
      'device_id': 'device-1',
      'provider': provider,
      'push_enabled': enabled,
      'push_token_updated_at': '2026-09-29T00:00:00.000Z',
    };

Map<String, Object?> successData(Object? data) => {
      'status': 'success',
      'data': data,
      'error': null,
      'meta': {'api_version': 'v1'},
      'request_id': 'req-push-server',
    };

ResponseBody jsonResponse(int status, Map<String, Object?> body) =>
    ResponseBody.fromString(
      jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );

class SequenceHttpAdapter implements HttpClientAdapter {
  SequenceHttpAdapter(Iterable<ResponseBody> responses)
      : _responses = Queue<ResponseBody>.of(responses);

  final Queue<ResponseBody> _responses;
  final List<RequestOptions> requests = [];
  final Map<int, Completer<void>> _requestCountWaiters = {};

  Future<void> waitForRequestCount(int count) {
    if (requests.length >= count) return Future<void>.value();
    return (_requestCountWaiters[count] ??= Completer<void>()).future;
  }

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    final reachedCounts = _requestCountWaiters.keys
        .where((count) => requests.length >= count)
        .toList(growable: false);
    for (final count in reachedCounts) {
      _requestCountWaiters.remove(count)?.complete();
    }
    if (_responses.isEmpty) throw StateError('No response queued');
    return _responses.removeFirst();
  }

  @override
  void close({bool force = false}) {}
}

class FakePushTokenSource implements PushTokenSource {
  FakePushTokenSource({
    required this.provider,
    required this.initialToken,
    this.initializeToken,
  });

  @override
  final PushProvider provider;
  final String? initialToken;
  final Future<String?> Function()? initializeToken;
  final StreamController<String> _controller =
      StreamController<String>.broadcast();

  int initializeCalls = 0;
  int disposeCalls = 0;

  @override
  Future<String?> initialize() async {
    initializeCalls += 1;
    return initializeToken == null ? initialToken : await initializeToken!();
  }

  @override
  Stream<String> get tokenChanges => _controller.stream;

  void emit(String token) => _controller.add(token);

  Future<void> flush() => Future<void>.delayed(Duration.zero);

  @override
  Future<void> dispose() async {
    disposeCalls += 1;
  }
}
