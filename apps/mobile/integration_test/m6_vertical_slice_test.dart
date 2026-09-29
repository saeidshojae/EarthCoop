import 'package:earthcoop_mobile/app/bootstrap/bootstrap_state.dart';
import 'package:earthcoop_mobile/app/router/app_router.dart';
import 'package:earthcoop_mobile/core/auth/session_controller.dart';
import 'package:earthcoop_mobile/core/auth/session_models.dart';
import 'package:earthcoop_mobile/features/groups/group_detail_screen.dart';
import 'package:earthcoop_mobile/features/groups/group_dto.dart';
import 'package:earthcoop_mobile/features/groups/groups_controller.dart';
import 'package:earthcoop_mobile/features/groups/groups_screen.dart';
import 'package:earthcoop_mobile/features/notifications/notification_dto.dart';
import 'package:earthcoop_mobile/features/notifications/notifications_controller.dart';
import 'package:earthcoop_mobile/features/notifications/notifications_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets(
    'authenticated vertical slice navigates home groups detail and notification deep link',
    (tester) async {
      final group = sampleGroup();
      final notification = NotificationDto.fromJson({
        'id': 'notification-1',
        'type': 'group.notice',
        'title': 'اعلان گروه',
        'message': 'یک رویداد تازه در گروه شما ثبت شد.',
        'url': '/groups/42',
        'link': {
          'version': 1,
          'route': 'group.detail',
          'params': {'group_id': 42},
          'fallback_url': '/home',
        },
        'context': {'group_id': 42},
        'read': false,
        'read_at': null,
        'created_at': '2026-09-29T10:00:00.000Z',
      });

      final appRouter = AppRouter(
        bootstrap: const BootstrapState.compatible(),
        session: SessionState.authenticated(sampleSession()),
        groupsBuilder: (context, openGroup) => GroupsScreen(
          state: GroupsState.ready([group]),
          onOpenGroup: openGroup,
        ),
        groupDetailBuilder: (context, groupId) => GroupDetailScreen(
          state: GroupDetailState.ready(group),
        ),
        notificationsBuilder: (context, openLink) => NotificationsScreen(
          state: NotificationsState.ready([notification]),
          onOpenLink: openLink,
        ),
      );

      await tester.pumpWidget(
        MaterialApp.router(routerConfig: appRouter.router),
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('home-route-screen')), findsOneWidget);

      await tester.tap(find.byKey(const Key('home-groups-action')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('groups-route-screen')), findsOneWidget);

      await tester.tap(find.byKey(const Key('group-card-42')));
      await tester.pumpAndSettle();
      expect(
          find.byKey(const Key('group-detail-route-screen')), findsOneWidget);
      expect(find.text('مجمع عمومی محله آزمایشی'), findsOneWidget);

      appRouter.router.go('/home');
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('home-notifications-action')));
      await tester.pumpAndSettle();
      expect(
          find.byKey(const Key('notifications-route-screen')), findsOneWidget);

      await tester
          .tap(find.byKey(const Key('notification-card-notification-1')));
      await tester.pumpAndSettle();
      expect(
          find.byKey(const Key('group-detail-route-screen')), findsOneWidget);
      expect(find.text('مجمع عمومی محله آزمایشی'), findsOneWidget);
    },
  );
}

GroupDto sampleGroup() => const GroupDto(
      id: 42,
      name: 'مجمع عمومی محله آزمایشی',
      identity: GroupIdentity(
        governanceAreaId: 7,
        dimensionKey: 'public',
        dimensionValueKey: 'neighborhood',
      ),
      membership: GroupMembership(
        role: 1,
        roleLabel: 'فعال',
        status: 1,
      ),
      membersCount: 24,
    );

NativeSession sampleSession() => NativeSession(
      token: 'session-token',
      expiresAt: DateTime.utc(2026, 10, 1),
      user: const SessionUser(
        id: 42,
        firstName: 'Test',
        lastName: 'Member',
      ),
      device: const SessionDevice(
        id: 'device-1',
        platform: 'android',
        appVersion: '1.0.0',
        locale: 'fa',
        timezone: 'Asia/Tehran',
        pushCapable: true,
      ),
    );
