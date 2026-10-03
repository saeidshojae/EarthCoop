import 'package:earthcoop_mobile/core/deep_links/semantic_link.dart';
import 'package:earthcoop_mobile/features/notifications/notification_dto.dart';
import 'package:earthcoop_mobile/features/notifications/notification_repository.dart';
import 'package:earthcoop_mobile/features/notifications/notification_sync_service.dart';
import 'package:earthcoop_mobile/features/notifications/notifications_controller.dart';
import 'package:earthcoop_mobile/features/notifications/notifications_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('controller marks notification read optimistically and persists it', () async {
    final service = _FakeNotificationSyncService();
    final controller = NotificationsController(service);

    await controller.load();
    expect(controller.state.items.single.read, isFalse);

    await controller.markRead('notice-1');

    expect(service.markReadCalls, 1);
    expect(controller.state.items.single.read, isTrue);
  });

  testWidgets('tapping executable notification marks it read and navigates',
      (tester) async {
    final notification = _notification();
    var markedId = '';
    SemanticLink? opened;

    await tester.pumpWidget(
      MaterialApp(
        home: NotificationsScreen(
          state: NotificationsState.ready([notification]),
          onMarkRead: (value) => markedId = value.id,
          onOpenLink: (link) => opened = link,
        ),
      ),
    );

    await tester.tap(find.byKey(const Key('notification-card-notice-1')));
    await tester.pump();

    expect(markedId, 'notice-1');
    expect(opened?.route, 'group.detail');
    expect(opened?.params['group_id'], 42);
  });
}

NotificationDto _notification() => NotificationDto(
      id: 'notice-1',
      type: 'group.activity',
      title: 'فعالیت جدید',
      message: 'یک فعالیت جدید در گروه دارید.',
      context: const <String, Object?>{},
      read: false,
      createdAt: DateTime.utc(2026, 10, 3),
      link: const SemanticLink(
        version: 1,
        route: 'group.detail',
        params: {'group_id': 42},
      ),
    );

class _FakeNotificationSyncService extends NotificationSyncService {
  _FakeNotificationSyncService()
      : super(
          source: _EmptyPageSource(),
          store: _NoopProjectionStore(),
        );

  int markReadCalls = 0;

  @override
  Future<List<NotificationDto>> syncOnResume() async => [_notification()];

  Future<void> markRead(String notificationId) async {
    markReadCalls += 1;
  }
}

class _EmptyPageSource implements NotificationPageSource {
  @override
  Future<NotificationPage> fetchPage({String? cursor, int limit = 20}) async =>
      const NotificationPage(
        items: <NotificationDto>[],
        nextCursor: null,
        hasMore: false,
      );
}

class _NoopProjectionStore implements NotificationProjectionStore {
  @override
  Future<List<NotificationDto>> readAll() async => const <NotificationDto>[];

  @override
  Future<String?> readNextCursor() async => null;

  @override
  Future<void> replaceAll(List<NotificationDto> values) async {}

  @override
  Future<void> writeNextCursor(String? cursor) async {}
}
