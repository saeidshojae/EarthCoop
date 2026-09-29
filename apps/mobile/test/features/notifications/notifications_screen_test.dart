import 'package:earthcoop_mobile/core/deep_links/semantic_link.dart';
import 'package:earthcoop_mobile/features/notifications/notification_dto.dart';
import 'package:earthcoop_mobile/features/notifications/notifications_controller.dart';
import 'package:earthcoop_mobile/features/notifications/notifications_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('notifications screen exposes loading and empty states', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: NotificationsScreen(state: NotificationsState.loading())),
    );
    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    await tester.pumpWidget(
      const MaterialApp(home: NotificationsScreen(state: NotificationsState.empty())),
    );
    expect(find.text('اعلانی برای نمایش وجود ندارد.'), findsOneWidget);
  });

  testWidgets('ready notifications render unread state and typed-link action', (tester) async {
    SemanticLink? opened;
    await tester.pumpWidget(
      MaterialApp(
        home: NotificationsScreen(
          state: NotificationsState.ready([sampleNotification()]),
          onOpenLink: (link) => opened = link,
        ),
      ),
    );

    expect(find.text('عنوان اعلان'), findsOneWidget);
    expect(find.text('متن اعلان'), findsOneWidget);
    expect(find.byKey(const Key('notification-unread-n-1')), findsOneWidget);

    await tester.tap(find.byKey(const Key('notification-card-n-1')));
    expect(opened?.route, 'group.detail');
    expect(opened?.params['group_id'], 42);
  });

  testWidgets('notification without typed link is not executable', (tester) async {
    var opened = false;
    await tester.pumpWidget(
      MaterialApp(
        home: NotificationsScreen(
          state: NotificationsState.ready([
            NotificationDto(
              id: 'n-no-link',
              type: 'plain',
              title: 'بدون پیوند',
              message: 'فقط متن',
              context: const {},
              read: true,
              createdAt: DateTime.utc(2026, 9, 29),
            ),
          ]),
          onOpenLink: (_) => opened = true,
        ),
      ),
    );

    await tester.tap(find.byKey(const Key('notification-card-n-no-link')));
    expect(opened, isFalse);
  });

  testWidgets('retryable failure offers retry but forbidden does not', (tester) async {
    var retried = false;
    await tester.pumpWidget(
      MaterialApp(
        home: NotificationsScreen(
          state: const NotificationsState.failure(
            NotificationViewFailure.retryable('ارتباط برقرار نشد.'),
          ),
          onRetry: () => retried = true,
        ),
      ),
    );
    await tester.tap(find.byKey(const Key('notifications-retry')));
    expect(retried, isTrue);

    await tester.pumpWidget(
      const MaterialApp(
        home: NotificationsScreen(
          state: NotificationsState.failure(
            NotificationViewFailure.forbidden('دسترسی مجاز نیست.'),
          ),
        ),
      ),
    );
    expect(find.byKey(const Key('notifications-retry')), findsNothing);
  });
}

NotificationDto sampleNotification() => NotificationDto(
      id: 'n-1',
      type: 'group.notice',
      title: 'عنوان اعلان',
      message: 'متن اعلان',
      context: const {'group_id': 42},
      read: false,
      createdAt: DateTime.utc(2026, 9, 29),
      link: const SemanticLink(
        version: 1,
        route: 'group.detail',
        params: {'group_id': 42},
      ),
    );
