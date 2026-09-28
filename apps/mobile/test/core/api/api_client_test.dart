import 'dart:async';
import 'dart:collection';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/core/api/request_context.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('ApiClient', () {
    test('decodes the v1 success envelope and preserves request id', () async {
      final adapter = SequenceHttpAdapter([
        jsonResponse(200, {
          'status': 'success',
          'data': {'name': 'EarthCoop'},
          'error': null,
          'meta': {'api_version': 'v1'},
          'request_id': 'req-server-1',
        }),
      ]);
      final client = buildClient(adapter);

      final result = await client.get<Map<String, dynamic>>(
        '/me',
        decodeData: (json) => Map<String, dynamic>.from(json! as Map),
      );

      expect(result.data['name'], 'EarthCoop');
      expect(result.requestId, 'req-server-1');
    });

    test('maps stable v1 errors and Retry-After metadata', () async {
      final adapter = SequenceHttpAdapter([
        jsonResponse(
          409,
          {
            'status': 'error',
            'data': null,
            'error': {
              'code': 'request_in_progress',
              'message': 'Request is still processing.',
              'details': {'operation': 'notification.read'},
              'retryable': true,
            },
            'meta': {'api_version': 'v1', 'http_status': 409},
            'request_id': 'req-error-1',
          },
          headers: {
            'retry-after': ['3'],
          },
        ),
      ]);
      final client = buildClient(adapter);

      try {
        await client.get<Object?>('/notifications', decodeData: (json) => json);
        fail('Expected ApiFailure');
      } on ApiFailure catch (failure) {
        expect(failure.code, 'request_in_progress');
        expect(failure.retryable, isTrue);
        expect(failure.requestId, 'req-error-1');
        expect(failure.retryAfter, const Duration(seconds: 3));
      }
    });

    test('attaches bearer and request id but keeps idempotency mutation-scoped', () async {
      final adapter = SequenceHttpAdapter([
        jsonResponse(200, successData({'ok': true})),
        jsonResponse(200, successData({'ok': true})),
      ]);
      final client = buildClient(adapter, bearer: 'secret-bearer');

      await client.get<Object?>('/me', decodeData: (json) => json);
      await client.post<Object?>(
        '/notifications/n-1/read',
        data: const {},
        context: const RequestContext(
          requestId: 'req-client-2',
          idempotencyKey: 'idem-1',
        ),
        decodeData: (json) => json,
      );

      expect(adapter.requests[0].headers['Authorization'], 'Bearer secret-bearer');
      expect(adapter.requests[0].headers.containsKey('Idempotency-Key'), isFalse);
      expect(adapter.requests[1].headers['X-Request-ID'], 'req-client-2');
      expect(adapter.requests[1].headers['Idempotency-Key'], 'idem-1');
    });

    test('mutation retry reuses the original idempotency key', () async {
      final adapter = SequenceHttpAdapter([
        jsonResponse(503, errorData('temporarily_unavailable', retryable: true)),
        jsonResponse(200, successData({'ok': true})),
      ]);
      final client = buildClient(adapter);

      final result = await client.post<Map<String, dynamic>>(
        '/notifications/n-1/read',
        data: const {},
        context: const RequestContext(
          requestId: 'req-retry-1',
          idempotencyKey: 'idem-stable',
        ),
        decodeData: (json) => Map<String, dynamic>.from(json! as Map),
      );

      expect(result.data['ok'], isTrue);
      expect(adapter.requests, hasLength(2));
      expect(
        adapter.requests.map((r) => r.headers['Idempotency-Key']).toSet(),
        {'idem-stable'},
      );
    });

    test('retryable false is never automatically retried', () async {
      final adapter = SequenceHttpAdapter([
        jsonResponse(503, errorData('blocked', retryable: false)),
        jsonResponse(200, successData({'should_not_run': true})),
      ]);
      final client = buildClient(adapter);

      await expectLater(
        client.get<Object?>('/me', decodeData: (json) => json),
        throwsA(isA<ApiFailure>().having((e) => e.code, 'code', 'blocked')),
      );
      expect(adapter.requests, hasLength(1));
    });

    test('malformed envelopes fail closed', () async {
      final adapter = SequenceHttpAdapter([
        jsonResponse(200, {'data': {'ok': true}}),
      ]);
      final client = buildClient(adapter);

      await expectLater(
        client.get<Object?>('/me', decodeData: (json) => json),
        throwsA(
          isA<ApiFailure>().having(
            (e) => e.code,
            'code',
            'malformed_response',
          ),
        ),
      );
    });
  });
}

ApiClient buildClient(SequenceHttpAdapter adapter, {String? bearer}) {
  final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'));
  dio.httpClientAdapter = adapter;
  return ApiClient(
    dio: dio,
    bearerTokenProvider: () async => bearer,
    requestIdFactory: () => 'req-generated',
    retryDelay: (_) async {},
  );
}

Map<String, Object?> successData(Object? data) => {
      'status': 'success',
      'data': data,
      'error': null,
      'meta': {'api_version': 'v1'},
      'request_id': 'req-success',
    };

Map<String, Object?> errorData(String code, {required bool retryable}) => {
      'status': 'error',
      'data': null,
      'error': {
        'code': code,
        'message': code,
        'details': <String, Object?>{},
        'retryable': retryable,
      },
      'meta': {'api_version': 'v1', 'http_status': 503},
      'request_id': 'req-failure',
    };

ResponseBody jsonResponse(
  int status,
  Map<String, Object?> body, {
  Map<String, List<String>>? headers,
}) =>
    ResponseBody.fromString(
      jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
        ...?headers,
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
    if (_responses.isEmpty) {
      throw StateError('No response queued');
    }
    return _responses.removeFirst();
  }

  @override
  void close({bool force = false}) {}
}
