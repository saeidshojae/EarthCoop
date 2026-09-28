import 'dart:async';
import 'dart:collection';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/auth/secure_session_store.dart';
import 'package:earthcoop_mobile/core/auth/session_repository.dart';
import 'package:earthcoop_mobile/core/device/device_context.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('ApiSessionRepository', () {
    test('login binds device metadata and stores token only in secure store',
        () async {
      final adapter = RecordingAdapter([
        jsonResponse(201, successEnvelope(sessionJson(token: 'secret-token'))),
      ]);
      final secureStore = MemorySecureSessionStore();
      final client = buildClient(adapter, secureStore);
      final repository =
          ApiSessionRepository(apiClient: client, secureStore: secureStore);

      final session = await repository.login(
        email: 'member@example.test',
        password: 'secret-password',
        device: const DeviceContext(
          platform: 'android',
          appVersion: '1.0.0',
          locale: 'fa',
          timezone: 'Asia/Tehran',
          pushCapable: true,
        ),
      );

      expect(session.token, 'secret-token');
      expect(await secureStore.readToken(), 'secret-token');
      expect(await secureStore.readDeviceId(), 'device-1');
      expect(adapter.requests.single.path, '/auth/session');
      expect(adapter.requests.single.method, 'POST');
      expect(adapter.requests.single.data, containsPair('platform', 'android'));
      expect(adapter.requests.single.data, containsPair('push_capable', true));
      expect(
          adapter.requests.single.data.toString(), contains('secret-password'));
    });

    test('restore validates via GET with bearer and X-Device-ID', () async {
      final adapter = RecordingAdapter([
        jsonResponse(200, successEnvelope(sessionJson(includeToken: false))),
      ]);
      final secureStore = MemorySecureSessionStore(
        token: 'stored-token',
        deviceId: 'device-1',
      );
      final repository = ApiSessionRepository(
        apiClient: buildClient(adapter, secureStore),
        secureStore: secureStore,
      );

      final restored = await repository.restoreAndValidate();

      expect(restored, isNotNull);
      expect(restored!.token, 'stored-token');
      final request = adapter.requests.single;
      expect(request.method, 'GET');
      expect(request.path, '/auth/session');
      expect(request.headers['Authorization'], 'Bearer stored-token');
      expect(request.headers['X-Device-ID'], 'device-1');
    });

    test('revoked restore fails closed and clears stored credentials',
        () async {
      final adapter = RecordingAdapter([
        jsonResponse(401, errorEnvelope('unauthenticated')),
      ]);
      final secureStore = MemorySecureSessionStore(
        token: 'revoked-token',
        deviceId: 'device-1',
      );
      final repository = ApiSessionRepository(
        apiClient: buildClient(adapter, secureStore),
        secureStore: secureStore,
      );

      final restored = await repository.restoreAndValidate();

      expect(restored, isNull);
      expect(await secureStore.readToken(), isNull);
      expect(await secureStore.readDeviceId(), isNull);
    });

    test('rotate replaces token while retaining the same bound device',
        () async {
      final adapter = RecordingAdapter([
        jsonResponse(200, successEnvelope(sessionJson(token: 'new-token'))),
      ]);
      final secureStore = MemorySecureSessionStore(
        token: 'old-token',
        deviceId: 'device-1',
      );
      final repository = ApiSessionRepository(
        apiClient: buildClient(adapter, secureStore),
        secureStore: secureStore,
      );

      final rotated = await repository.rotateCurrent();

      expect(rotated.token, 'new-token');
      expect(await secureStore.readToken(), 'new-token');
      expect(await secureStore.readDeviceId(), 'device-1');
      final request = adapter.requests.single;
      expect(request.method, 'POST');
      expect(request.path, '/auth/session/rotate');
      expect(request.headers['X-Device-ID'], 'device-1');
    });

    test('revoke uses DELETE and never clears credentials by itself', () async {
      final adapter = RecordingAdapter([
        ResponseBody.fromString('', 204),
      ]);
      final secureStore = MemorySecureSessionStore(
        token: 'current-token',
        deviceId: 'device-1',
      );
      final repository = ApiSessionRepository(
        apiClient: buildClient(adapter, secureStore),
        secureStore: secureStore,
      );

      await repository.revokeCurrent();

      final request = adapter.requests.single;
      expect(request.method, 'DELETE');
      expect(request.path, '/auth/session');
      expect(request.headers['X-Device-ID'], 'device-1');
      expect(await secureStore.readToken(), 'current-token');
    });
  });
}

ApiClient buildClient(RecordingAdapter adapter, SecureSessionStore store) {
  final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'));
  dio.httpClientAdapter = adapter;
  return ApiClient(
    dio: dio,
    bearerTokenProvider: store.readToken,
    requestIdFactory: () => 'req-auth',
    retryDelay: (_) async {},
  );
}

Map<String, Object?> successEnvelope(Object? data) => {
      'status': 'success',
      'data': data,
      'error': null,
      'meta': {'api_version': 'v1'},
      'request_id': 'req-auth-server',
    };

Map<String, Object?> errorEnvelope(String code) => {
      'status': 'error',
      'data': null,
      'error': {
        'code': code,
        'message': code,
        'details': <String, Object?>{},
        'retryable': false,
      },
      'meta': {'api_version': 'v1', 'http_status': 401},
      'request_id': 'req-auth-error',
    };

Map<String, Object?> sessionJson({
  String token = 'token',
  bool includeToken = true,
}) =>
    {
      if (includeToken) 'token': token,
      'token_type': 'Bearer',
      'expires_at': '2026-10-01T00:00:00Z',
      'user': {
        'id': 42,
        'first_name': 'Test',
        'last_name': 'Member',
      },
      'device': {
        'id': 'device-1',
        'platform': 'android',
        'app_version': '1.0.0',
        'locale': 'fa',
        'timezone': 'Asia/Tehran',
        'push_capable': true,
        'last_seen_at': '2026-09-29T00:00:00Z',
      },
    };

ResponseBody jsonResponse(int status, Map<String, Object?> body) =>
    ResponseBody.fromString(
      jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );

class RecordingAdapter implements HttpClientAdapter {
  RecordingAdapter(Iterable<ResponseBody> responses)
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
    return _responses.removeFirst();
  }

  @override
  void close({bool force = false}) {}
}

class MemorySecureSessionStore implements SecureSessionStore {
  MemorySecureSessionStore({this.token, this.deviceId});

  String? token;
  String? deviceId;

  @override
  Future<void> clear() async {
    token = null;
    deviceId = null;
  }

  @override
  Future<String?> readDeviceId() async => deviceId;

  @override
  Future<String?> readToken() async => token;

  @override
  Future<void> write({required String token, required String deviceId}) async {
    this.token = token;
    this.deviceId = deviceId;
  }
}
