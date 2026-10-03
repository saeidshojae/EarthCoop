import 'package:earthcoop_mobile/features/notifications/notification_dto.dart';
import 'package:earthcoop_mobile/features/notifications/notification_repository.dart';
import 'package:earthcoop_mobile/features/notifications/notification_sync_service.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('markRead uses deterministic key and persists authoritative read state',
      () async {
    final source = _ReadSource();
    final store = _MemoryStore([_unreadNotification()]);
    final service = NotificationSyncService(source: source, store: store);

    await service.markRead('notice-1');

    expect(source.notificationId, 'notice-1');
    expect(source.idempotencyKey, 'notification-read-notice-1');
    expect(source.networkAllowed, isTrue);
    expect(store.values.single.read, isTrue);
    expect(store.values.single.readAt, DateTime.utc(2026, 10, 3, 12));
  });
}

NotificationDto _unreadNotification() => NotificationDto(
      id: 'notice-1',
      type: 'group.activity',
      title: 'فعالیت جدید',
      message: 'یک فعالیت جدید در گروه دارید.',
      context: const <String, Object?>{},
      read: false,
      createdAt: DateTime.utc(2026, 10, 3),
    );

class _ReadSource implements NotificationPageSource, NotificationReadSource {
  String? notificationId;
  String? idempotencyKey;
  bool? networkAllowed;

  @override
  Future<NotificationPage> fetchPage({String? cursor, int limit = 20}) async =>
      const NotificationPage(
        items: <NotificationDto>[],
        nextCursor: null,
        hasMore: false,
      );

  @override
  Future<NotificationDto?> markRead(
    String notificationId, {
    required String idempotencyKey,
    required bool networkAllowed,
  }) async {
    this.notificationId = notificationId;
    this.idempotencyKey = idempotencyKey;
    this.networkAllowed = networkAllowed;
    return NotificationDto(
      id: 'notice-1',
      type: 'group.activity',
      title: 'فعالیت جدید',
      message: 'یک فعالیت جدید در گروه دارید.',
      context: const <String, Object?>{},
      read: true,
      readAt: DateTime.utc(2026, 10, 3, 12),
      createdAt: DateTime.utc(2026, 10, 3),
    );
  }
}

class _MemoryStore implements NotificationProjectionStore {
  _MemoryStore(List<NotificationDto> initial) : values = List.of(initial);

  List<NotificationDto> values;
  String? cursor;

  @override
  Future<List<NotificationDto>> readAll() async => List.of(values);

  @override
  Future<String?> readNextCursor() async => cursor;

  @override
  Future<void> replaceAll(List<NotificationDto> values) async {
    this.values = List.of(values);
  }

  @override
  Future<void> writeNextCursor(String? cursor) async {
    this.cursor = cursor;
  }
}
