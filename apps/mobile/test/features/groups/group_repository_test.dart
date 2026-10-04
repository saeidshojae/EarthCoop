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
import 'package:earthcoop_mobile/features/groups/group_feed_dto.dart';
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
      expect(cache.items.single['id'], 42);
      expect(adapter.requests.single.path, '/groups');
    });

    test(
      'detail always performs authoritative API read before using cache',
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
      },
    );

    test(
      'activity decodes canonical feed snapshots and sends bounded delta query',
      () async {
        final adapter = RecordingAdapter([
          jsonResponse(
            200,
            successEnvelope({
              'events': [
                {
                  'version': 1,
                  'event_id': 'feed:8:v1',
                  'group_id': 42,
                  'sequence': 8,
                  'type': 'feed.message.snapshot',
                  'actor_id': 7,
                  'occurred_at': '2026-10-03T10:00:00Z',
                  'payload': {
                    'content_type': 'message',
                    'content_id': 80,
                    'message': 'سلام به اعضای گروه',
                    'user_id': 7,
                    'sender': 'سعید شجاعی',
                    'created_at': '13:30',
                    'parent_id': null,
                    'state': 'sent',
                  },
                },
                {
                  'version': 1,
                  'event_id': 'feed:9:v1',
                  'group_id': 42,
                  'sequence': 9,
                  'type': 'feed.post.snapshot',
                  'actor_id': 7,
                  'occurred_at': '2026-10-03T10:01:00Z',
                  'payload': {
                    'content_type': 'post',
                    'content_id': 90,
                    'title': 'گزارش فعالیت',
                    'content': 'خلاصه گزارش گروه',
                  },
                },
                {
                  'version': 1,
                  'event_id': 'feed:10:v1',
                  'group_id': 42,
                  'sequence': 10,
                  'type': 'feed.poll.snapshot',
                  'actor_id': 7,
                  'occurred_at': '2026-10-03T10:02:00Z',
                  'payload': {
                    'content_type': 'poll',
                    'content_id': 100,
                    'question': 'جلسه بعدی چه روزی باشد؟',
                    'options': [
                      {'id': 1, 'poll_id': 100, 'text': 'شنبه'},
                      {'id': 2, 'poll_id': 100, 'text': 'یکشنبه'},
                    ],
                  },
                },
              ],
              'after_sequence': 0,
              'latest_sequence': 10,
              'has_more': false,
            }),
          ),
        ]);
        final repository = GroupRepository(
          apiClient: buildClient(adapter),
          cache: MemoryGroupCache(),
        );

        final events = await repository.activity(42, limit: 20);

        expect(events, hasLength(3));
        expect(events[0].kind, GroupFeedKind.message);
        expect(events[0].sender, 'سعید شجاعی');
        expect(events[0].message, 'سلام به اعضای گروه');
        expect(events[1].kind, GroupFeedKind.post);
        expect(events[1].title, 'گزارش فعالیت');
        expect(events[2].kind, GroupFeedKind.poll);
        expect(events[2].question, 'جلسه بعدی چه روزی باشد؟');
        expect(events[2].options, ['شنبه', 'یکشنبه']);
        expect(adapter.requests.single.path, '/groups/42/feed/delta');
        expect(adapter.requests.single.queryParameters['after_sequence'], 0);
        expect(adapter.requests.single.queryParameters['limit'], 20);
        expect(adapter.requests.single.queryParameters['window'], 'latest');
      },
    );

    test('unread count uses authoritative group unread endpoint', () async {
      final adapter = RecordingAdapter([
        jsonResponse(
          200,
          successEnvelope({
            'total': 3,
            'cursor': 7,
            'first_unread_sequence': 8,
          }),
        ),
      ]);
      final repository = GroupRepository(
        apiClient: buildClient(adapter),
        cache: MemoryGroupCache(),
      );

      final unread = await repository.unreadCount(42);

      expect(unread, 3);
      expect(adapter.requests.single.path, '/groups/42/unread');
    });

    test('transient list failure may return stale cached projection', () async {
      final adapter = RecordingAdapter([
        jsonResponse(
          503,
          errorEnvelope(
            'temporarily_unavailable',
            retryable: true,
            status: 503,
          ),
        ),
        jsonResponse(
          503,
          errorEnvelope(
            'temporarily_unavailable',
            retryable: true,
            status: 503,
          ),
        ),
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
          403,
          errorEnvelope('forbidden', retryable: false, status: 403),
        ),
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

  test(
    'Drift group projection cache survives close/reopen and remains bounded',
    () async {
      final directory = await Directory.systemTemp.createTemp(
        'earthcoop-groups-',
      );
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
      final reopenedCache = DriftGroupProjectionCache(
        reopenedDb,
        maxEntries: 2,
      );
      final restored = await reopenedCache.readAll();

      expect(restored.map((item) => item['id']), [42, 43]);
      expect((await reopenedCache.readOne(41)), isNull);
      expect((await reopenedCache.readOne(43))?['name'], 'گروه ۴۳');
    },
  );
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
      'membership': {'role': 1, 'role_label': 'فعال', 'status': 1},
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
