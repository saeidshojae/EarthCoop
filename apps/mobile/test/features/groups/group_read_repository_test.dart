import 'dart:collection';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/api/retry_policy.dart';
import 'package:earthcoop_mobile/features/groups/group_cache.dart';
import 'package:earthcoop_mobile/features/groups/group_repository.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
      'markRead posts the visible sequence with a deterministic idempotency key',
      () async {
    final adapter = _RecordingAdapter([
      _jsonResponse(
        200,
        {
          'status': 'success',
          'data': {
            'cursor': 8,
            'unread': {'total': 0},
          },
          'error': null,
          'meta': {'api_version': 'v1'},
          'request_id': 'req-group-read',
        },
      ),
    ]);
    final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'));
    dio.httpClientAdapter = adapter;
    final repository = GroupRepository(
      apiClient: ApiClient(
        dio: dio,
        bearerTokenProvider: () async => 'token',
        requestIdFactory: () => 'req-group-read-client',
        retryDelay: (_) async {},
        retryPolicy: const RetryPolicy(maxAttempts: 1),
      ),
      cache: _NoopGroupCache(),
    );

    await repository.markRead(42, throughSequence: 8);

    final request = adapter.requests.single;
    expect(request.method, 'POST');
    expect(request.path, '/groups/42/read');
    expect(request.data, {'through_sequence': 8});
    expect(request.headers['Idempotency-Key'], 'group-read-42-8');
  });
}

ResponseBody _jsonResponse(int status, Map<String, Object?> body) =>
    ResponseBody.fromString(
      jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );

class _RecordingAdapter implements HttpClientAdapter {
  _RecordingAdapter(Iterable<ResponseBody> responses)
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

class _NoopGroupCache implements GroupProjectionCache {
  @override
  Future<List<Map<String, Object?>>> readAll() async =>
      const <Map<String, Object?>>[];

  @override
  Future<Map<String, Object?>?> readOne(int id) async => null;

  @override
  Future<void> writeAll(List<Map<String, Object?>> values) async {}
}
