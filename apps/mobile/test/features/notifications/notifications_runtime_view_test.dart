import 'dart:async';

import 'package:earthcoop_mobile/features/notifications/notifications_runtime_view.dart';
import 'package:earthcoop_mobile/features/notifications/notifications_controller.dart';
import 'package:earthcoop_mobile/features/notifications/notification_sync_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'notification_sync_service_test.dart'
    show FakeNotificationPageSource, MemoryNotificationProjectionStore, page;

void main() {
  testWidgets('foreground signal refreshes mounted inbox without navigation',
      (tester) async {
    final events = StreamController<void>.broadcast();
    final controller = NotificationsController(NotificationSyncService(
      source: FakeNotificationPageSource([
        page([]),
        page(['new-notice'])
      ]),
      store: MemoryNotificationProjectionStore(),
    ));
    await tester.pumpWidget(MaterialApp(
        home: NotificationsRuntimeView(
      controller: controller,
      onOpenLink: (_) => fail('foreground event must not navigate'),
      refreshEvents: events.stream,
    )));
    await tester.pumpAndSettle();
    expect(controller.state.items, isEmpty);
    events.add(null);
    await tester.pumpAndSettle();
    expect(controller.state.items.single.id, 'new-notice');
    expect(find.text(controller.state.items.single.title!), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    expect(events.hasListener, isFalse);
    await events.close();
  });
}
