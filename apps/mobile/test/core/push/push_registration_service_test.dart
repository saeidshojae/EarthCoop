import 'dart:async';
import 'dart:collection';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/logging/diagnostics.dart';
import 'package:earthcoop_mobile/core/push/push_provider_selector.dart';
import 'package:earthcoop_mobile/core/push/push_registration_service.dart';
import 'package:earthcoop_mobile/core/push/push_token_source.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
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
    await source.flush();

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

    final rendered = events.map((event) => '${event.name}:${event.data}').join('|');
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

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
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
  });

  @override
  final PushProvider provider;
  final String? initialToken;
  final StreamController<String> _controller =
      StreamController<String>.broadcast();

  int initializeCalls = 0;
  int disposeCalls = 0;

  @override
  Future<String?> initialize() async {
    initializeCalls += 1;
    return initialToken;
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
