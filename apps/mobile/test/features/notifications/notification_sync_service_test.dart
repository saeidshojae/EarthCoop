import 'dart:async';
import 'dart:io';

import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/core/deep_links/semantic_link.dart';
import 'package:earthcoop_mobile/core/local/app_database.dart';
import 'package:earthcoop_mobile/features/notifications/notification_dto.dart';
import 'package:earthcoop_mobile/features/notifications/notification_repository.dart';
import 'package:earthcoop_mobile/features/notifications/notification_sync_service.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('notification DTO keeps typed semantic link and ignores additive fields', () {
    final notification = NotificationDto.fromJson(notificationJson('n-1', extra: true));

    expect(notification.id, 'n-1');
    expect(notification.title, 'عنوان اعلان');
    expect(notification.read, isFalse);
    expect(notification.link?.route, 'group.detail');
    expect(notification.link?.params['group_id'], 42);
  });

  test('first authoritative sync replaces local projection', () async {
    final store = MemoryNotificationProjectionStore(
      items: [NotificationDto.fromJson(notificationJson('stale'))],
    );
    final source = FakeNotificationPageSource([
      page(['n-2', 'n-1']),
    ]);
    final service = NotificationSyncService(source: source, store: store);

    final result = await service.syncOnResume();

    expect(result.map((item) => item.id), ['n-2', 'n-1']);
    expect(store.items.map((item) => item.id), ['n-2', 'n-1']);
    expect(store.nextCursor, isNull);
    expect(source.requestedCursors, [null]);
  });

  test('multi-page sweep persists continuation cursor then clears on completion', () async {
    final gate = Completer<void>();
    final store = MemoryNotificationProjectionStore();
    final source = BlockingNotificationPageSource(gate);
    final service = NotificationSyncService(source: source, store: store);

    final sync = service.syncOnResume();
    await source.secondPageRequested.future;

    expect(store.nextCursor, 'cursor-2');
    gate.complete();
    final result = await sync;

    expect(result.map((item) => item.id), ['n-3', 'n-2', 'n-1']);
    expect(store.nextCursor, isNull);
    expect(source.requestedCursors, [null, 'cursor-2']);
  });

  test('duplicate and out-of-order resume hints coalesce into one authoritative sweep', () async {
    final gate = Completer<void>();
    final source = SingleBlockingSource(gate);
    final store = MemoryNotificationProjectionStore();
    final service = NotificationSyncService(source: source, store: store);

    final first = service.syncOnResume();
    final second = service.syncOnResume();
    final third = service.syncOnResume();
    gate.complete();

    final results = await Future.wait([first, second, third]);

    expect(source.calls, 1);
    expect(results.every((items) => items.single.id == 'n-1'), isTrue);
  });

  test('invalid continuation cursor restarts once from authoritative first page', () async {
    final store = MemoryNotificationProjectionStore();
    final source = FakeNotificationPageSource([
      page(['n-old'], nextCursor: 'invalid-cursor', hasMore: true),
      const ApiFailure(
        code: 'validation_error',
        message: 'Invalid notification cursor.',
        retryable: false,
        httpStatus: 422,
      ),
      page(['n-new']),
    ]);
    final service = NotificationSyncService(source: source, store: store);

    final result = await service.syncOnResume();

    expect(result.map((item) => item.id), ['n-new']);
    expect(source.requestedCursors, [null, 'invalid-cursor', null]);
    expect(store.nextCursor, isNull);
  });

  test('fresh sweep applies authoritative changes and deletions', () async {
    final store = MemoryNotificationProjectionStore(
      items: [
        NotificationDto.fromJson(notificationJson('n-1', read: false)),
        NotificationDto.fromJson(notificationJson('deleted')),
      ],
    );
    final source = FakeNotificationPageSource([
      page(['n-1'], read: true),
    ]);
    final service = NotificationSyncService(source: source, store: store);

    await service.syncOnResume();

    expect(store.items, hasLength(1));
    expect(store.items.single.id, 'n-1');
    expect(store.items.single.read, isTrue);
  });

  test('Drift notification projection and cursor survive close/reopen', () async {
    final directory = await Directory.systemTemp.createTemp('earthcoop-notifications-');
    final file = File('${directory.path}/app.sqlite');
    addTearDown(() async {
      if (await directory.exists()) await directory.delete(recursive: true);
    });

    final firstDb = AppDatabase.file(file);
    final firstStore = DriftNotificationProjectionStore(firstDb);
    await firstStore.replaceAll([
      NotificationDto.fromJson(notificationJson('n-2')),
      NotificationDto.fromJson(notificationJson('n-1')),
    ]);
    await firstStore.writeNextCursor('cursor-persisted');
    await firstDb.close();

    final reopenedDb = AppDatabase.file(file);
    addTearDown(reopenedDb.close);
    final reopened = DriftNotificationProjectionStore(reopenedDb);

    expect((await reopened.readAll()).map((item) => item.id), ['n-2', 'n-1']);
    expect(await reopened.readNextCursor(), 'cursor-persisted');
  });
}

NotificationPage page(
  List<String> ids, {
  String? nextCursor,
  bool hasMore = false,
  bool read = false,
}) =>
    NotificationPage(
      items: ids
          .map((id) => NotificationDto.fromJson(notificationJson(id, read: read)))
          .toList(growable: false),
      nextCursor: nextCursor,
      hasMore: hasMore,
    );

Map<String, Object?> notificationJson(
  String id, {
  bool read = false,
  bool extra = false,
}) =>
    {
      'id': id,
      'type': 'group.notice',
      'title': 'عنوان اعلان',
      'message': 'متن اعلان',
      'url': '/legacy/$id',
      'link': {
        'version': 1,
        'route': 'group.detail',
        'params': {'group_id': 42},
        'fallback_url': 'https://earthcoop.ir/groups/42',
      },
      'context': {'group_id': 42},
      'read': read,
      'read_at': read ? '2026-09-29T01:00:00.000Z' : null,
      'created_at': '2026-09-29T00:00:00.000Z',
      if (extra) 'future_field': true,
    };

class FakeNotificationPageSource implements NotificationPageSource {
  FakeNotificationPageSource(List<Object> responses) : _responses = responses;

  final List<Object> _responses;
  final List<String?> requestedCursors = [];

  @override
  Future<NotificationPage> fetchPage({String? cursor, int limit = 20}) async {
    requestedCursors.add(cursor);
    final response = _responses.removeAt(0);
    if (response is ApiFailure) throw response;
    return response as NotificationPage;
  }
}

class BlockingNotificationPageSource implements NotificationPageSource {
  BlockingNotificationPageSource(this.gate);

  final Completer<void> gate;
  final Completer<void> secondPageRequested = Completer<void>();
  final List<String?> requestedCursors = [];

  @override
  Future<NotificationPage> fetchPage({String? cursor, int limit = 20}) async {
    requestedCursors.add(cursor);
    if (cursor == null) {
      return page(['n-3', 'n-2'], nextCursor: 'cursor-2', hasMore: true);
    }
    secondPageRequested.complete();
    await gate.future;
    return page(['n-1']);
  }
}

class SingleBlockingSource implements NotificationPageSource {
  SingleBlockingSource(this.gate);

  final Completer<void> gate;
  int calls = 0;

  @override
  Future<NotificationPage> fetchPage({String? cursor, int limit = 20}) async {
    calls += 1;
    await gate.future;
    return page(['n-1']);
  }
}

class MemoryNotificationProjectionStore implements NotificationProjectionStore {
  MemoryNotificationProjectionStore({List<NotificationDto>? items})
      : items = items ?? <NotificationDto>[];

  List<NotificationDto> items;
  String? nextCursor;

  @override
  Future<List<NotificationDto>> readAll() async => List.unmodifiable(items);

  @override
  Future<void> replaceAll(List<NotificationDto> values) async {
    items = List.of(values);
  }

  @override
  Future<String?> readNextCursor() async => nextCursor;

  @override
  Future<void> writeNextCursor(String? cursor) async {
    nextCursor = cursor;
  }
}
