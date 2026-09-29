import 'dart:async';
import 'dart:collection';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/api/retry_policy.dart';
import 'package:earthcoop_mobile/core/local/app_database.dart';
import 'package:earthcoop_mobile/features/groups/group_cache.dart';
import 'package:earthcoop_mobile/features/groups/group_repository.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('GroupRepository', () {
    test(
        'list decodes canonical projection, ignores additive fields and caches it',
        () async {
      final adapter = RecordingAdapter([
        jsonResponse(200, successEnvelope([groupJson(extra: true)])),
      ]);
      final cache = MemoryGroupCache();
      final repository = GroupRepository(
        apiClient: buildClient(adapter),
        cache: cache,
      );

      final result = await repository.list();

      expect(result.isStale, isFalse);
      expect(result.value, hasLength(1));
      expect(result.value.single.id, 42);
      expect(result.value.single.name, 'مجمع عمومی محله نمونه');
      expect(result.value.single.membership.role, 1);
      expect(result.value.single.membership.roleLabel, 'فعال');
      expect(result.value.single.identity.dimensionKey, 'public');
      expect(result.value.single.membersCount, 12);
      expect(cache.items.single.id, 42);
      expect(adapter.requests.single.path, '/groups');
    });

    test('detail always performs authoritative API read before using cache',
        () async {
      final adapter = RecordingAdapter([
        jsonResponse(200, successEnvelope(groupJson(extra: true))),
      ]);
      final cache = MemoryGroupCache(items: [cachedGroupJson()]);
      final repository = GroupRepository(
        apiClient: buildClient(adapter),
        cache: cache,
      );

      final result = await repository.find(42);

      expect(result.isStale, isFalse);
      expect(result.value.name, 'مجمع عمومی محله نمونه');
      expect(adapter.requests.single.path, '/groups/42');
    });

    test('transient list failure may return stale cached projection', () async {
      final adapter = RecordingAdapter([
        jsonResponse(
            503,
            errorEnvelope('temporarily_unavailable',
                retryable: true, status: 503)),
        jsonResponse(
            503,
            errorEnvelope('temporarily_unavailable',
                retryable: true, status: 503)),
      ]);
      final cache = MemoryGroupCache(items: [cachedGroupJson()]);
      final repository = GroupRepository(
        apiClient: buildClient(adapter),
        cache: cache,
      );

      final result = await repository.list();

      expect(result.isStale, isTrue);
      expect(result.value.single.name, 'نسخه ذخیره‌شده');
      expect(adapter.requests, hasLength(2));
    });

    test('forbidden detail never falls back to cached projection', () async {
      final adapter = RecordingAdapter([
        jsonResponse(
            403, errorEnvelope('forbidden', retryable: false, status: 403)),
      ]);
      final cache = MemoryGroupCache(items: [cachedGroupJson()]);
      final repository = GroupRepository(
        apiClient: buildClient(adapter),
        cache: cache,
      );

      await expectLater(repository.find(42), throwsA(isA<Exception>()));
      expect(adapter.requests.single.path, '/groups/42');
    });
  });

  test('Drift group projection cache survives close/reopen and remains bounded',
      () async {
    final directory =
        await Directory.systemTemp.createTemp('earthcoop-groups-');
    final file = File('${directory.path}/app.sqlite');
    addTearDown(() async {
      if (await directory.exists()) await directory.delete(recursive: true);
    });

    final firstDb = AppDatabase.file(file);
    final firstCache = DriftGroupProjectionCache(firstDb, maxEntries: 2);
    await firstCache.writeAll([
      groupJson(id: 41, name: 'گروه ۴۱'),
      groupJson(id: 42, name: 'گروه ۴۲'),
      groupJson(id: 43, name: 'گروه ۴۳'),
    ]);
    await firstDb.close();

    final reopenedDb = AppDatabase.file(file);
    addTearDown(reopenedDb.close);
    final reopenedCache = DriftGroupProjectionCache(reopenedDb, maxEntries: 2);
    final restored = await reopenedCache.readAll();

    expect(restored.map((item) => item['id']), [42, 43]);
    expect((await reopenedCache.readOne(41)), isNull);
    expect((await reopenedCache.readOne(43))?['name'], 'گروه ۴۳');
  });
}

ApiClient buildClient(RecordingAdapter adapter) {
  final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'));
  dio.httpClientAdapter = adapter;
  return ApiClient(
    dio: dio,
    bearerTokenProvider: () async => 'token',
    requestIdFactory: () => 'req-groups',
    retryDelay: (_) async {},
    retryPolicy: const RetryPolicy(maxAttempts: 2),
  );
}

Map<String, Object?> groupJson({
  int id = 42,
  String name = 'مجمع عمومی محله نمونه',
  bool extra = false,
}) =>
    {
      'id': id,
      'name': name,
      'identity': {
        'governance_area_id': 7,
        'dimension_key': 'public',
        'dimension_value_key': 'assembly',
      },
      'membership': {
        'role': 1,
        'role_label': 'فعال',
        'status': 1,
      },
      'members_count': 12,
      'last_activity_at': '2026-09-29T00:00:00Z',
      if (extra) 'future_additive_field': {'ignored': true},
    };

Map<String, Object?> cachedGroupJson() => groupJson(name: 'نسخه ذخیره‌شده');

Map<String, Object?> successEnvelope(Object? data) => {
      'status': 'success',
      'data': data,
      'error': null,
      'meta': {'api_version': 'v1'},
      'request_id': 'req-groups-server',
    };

Map<String, Object?> errorEnvelope(
  String code, {
  required bool retryable,
  required int status,
}) =>
    {
      'status': 'error',
      'data': null,
      'error': {
        'code': code,
        'message': code,
        'details': <String, Object?>{},
        'retryable': retryable,
      },
      'meta': {'api_version': 'v1', 'http_status': status},
      'request_id': 'req-groups-error',
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

class MemoryGroupCache implements GroupProjectionCache {
  MemoryGroupCache({List<Map<String, Object?>>? items})
      : items = items ?? <Map<String, Object?>>[];

  List<Map<String, Object?>> items;

  @override
  Future<List<Map<String, Object?>>> readAll() async => items;

  @override
  Future<Map<String, Object?>?> readOne(int id) async {
    for (final item in items) {
      if (item['id'] == id) return item;
    }
    return null;
  }

  @override
  Future<void> writeAll(List<Map<String, Object?>> values) async {
    items = values;
  }
}
