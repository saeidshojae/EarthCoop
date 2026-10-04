import 'dart:async';
import 'dart:collection';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/core/api/retry_policy.dart';
import 'package:earthcoop_mobile/core/offline/offline_queue_repository.dart';
import 'package:earthcoop_mobile/features/notifications/notification_repository.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('offline read queues once and applies optimistic local state', () async {
    final adapter = RecordingAdapter([]);
    final queue = MemoryOfflineQueueRepository();
    final optimistic = <String>[];
    final repository = NotificationRepository(
      apiClient: buildClient(adapter),
      offlineQueue: queue,
      optimisticMarkRead: (id) async => optimistic.add(id),
    );

    await repository.markRead(
      'n-1',
      idempotencyKey: 'idem-read-1',
      networkAllowed: false,
    );
    await repository.markRead(
      'n-1',
      idempotencyKey: 'idem-read-duplicate',
      networkAllowed: false,
    );

    expect(adapter.requests, isEmpty);
    expect(optimistic, ['n-1', 'n-1']);
    final pending = await queue.pending();
    expect(pending, hasLength(1));
    expect(pending.single.idempotencyKey, 'idem-read-1');
  });

  test(
    'failed online read retains intent and original key for reconnect',
    () async {
      final adapter = RecordingAdapter([
        jsonResponse(503, {
          'status': 'error',
          'data': null,
          'error': {
            'code': 'temporarily_unavailable',
            'message': 'retry',
            'retryable': true,
          },
          'meta': {'api_version': 'v1'},
          'request_id': 'req-server',
        }),
      ]);
      final queue = MemoryOfflineQueueRepository();
      final repository = NotificationRepository(
        apiClient: buildClient(adapter),
        offlineQueue: queue,
      );

      final result = await repository.markRead(
        'n-1',
        idempotencyKey: 'original-key',
        networkAllowed: true,
      );

      expect(result, isNull);
      final pending = await queue.pending();
      expect(pending, hasLength(1));
      expect(pending.single.idempotencyKey, 'original-key');
      expect(pending.single.payload['notification_id'], 'n-1');
    },
  );

  test('forbidden online read is never queued for retry', () async {
    final adapter = RecordingAdapter([
      jsonResponse(403, {
        'status': 'error',
        'data': null,
        'error': {
          'code': 'forbidden',
          'message': 'forbidden',
          'retryable': false,
        },
        'meta': {'api_version': 'v1'},
        'request_id': 'req-server',
      }),
    ]);
    final queue = MemoryOfflineQueueRepository();
    final repository = NotificationRepository(
      apiClient: buildClient(adapter),
      offlineQueue: queue,
    );
    await expectLater(
      repository.markRead('n-1', idempotencyKey: 'key', networkAllowed: true),
      throwsA(isA<ApiFailure>()),
    );
    expect(await queue.all(), isEmpty);
  });

  test('replay read posts with the original idempotency key', () async {
    final adapter = RecordingAdapter([
      jsonResponse(200, successEnvelope(notificationJson('n-1', read: true))),
    ]);
    final queue = MemoryOfflineQueueRepository();
    final repository = NotificationRepository(
      apiClient: buildClient(adapter),
      offlineQueue: queue,
    );
    await repository.markRead(
      'n-1',
      idempotencyKey: 'idem-preserved',
      networkAllowed: false,
    );
    final operation = (await queue.pending()).single;

    await repository.replayMarkRead(operation);

    expect(adapter.requests, hasLength(1));
    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/notifications/n-1/read');
    expect(
      adapter.requests.single.headers['Idempotency-Key'],
      'idem-preserved',
    );
  });
}

ApiClient buildClient(RecordingAdapter adapter) {
  final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'));
  dio.httpClientAdapter = adapter;
  return ApiClient(
    dio: dio,
    bearerTokenProvider: () async => 'token',
    requestIdFactory: () => 'req-offline-read',
    retryDelay: (_) async {},
    retryPolicy: const RetryPolicy(maxAttempts: 1),
  );
}

Map<String, Object?> notificationJson(String id, {required bool read}) => {
      'id': id,
      'type': 'group.notice',
      'title': 'عنوان',
      'message': 'متن',
      'link': null,
      'context': <String, Object?>{},
      'read': read,
      'read_at': read ? '2026-09-29T01:00:00.000Z' : null,
      'created_at': '2026-09-29T00:00:00.000Z',
    };

Map<String, Object?> successEnvelope(Object? data) => {
      'status': 'success',
      'data': data,
      'error': null,
      'meta': {'api_version': 'v1'},
      'request_id': 'req-server',
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
