import 'package:earthcoop_mobile/app/bootstrap/bootstrap_state.dart';
import 'package:earthcoop_mobile/app/router/app_router.dart';
import 'package:earthcoop_mobile/core/auth/session_controller.dart';
import 'package:earthcoop_mobile/core/auth/session_models.dart';
import 'package:earthcoop_mobile/core/deep_links/semantic_link.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('compatible unauthenticated startup opens injected login route',
      (tester) async {
    final appRouter = AppRouter(
      bootstrap: const BootstrapState.compatible(),
      session: const SessionState.unauthenticated(),
      loginBuilder: (context) => const Scaffold(
        key: Key('production-login-route-screen'),
        body: Text('ورود واقعی'),
      ),
    );

    await tester.pumpWidget(MaterialApp.router(routerConfig: appRouter.router));
    await tester.pumpAndSettle();

    expect(
      find.byKey(const Key('production-login-route-screen')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('home-route-screen')), findsNothing);
  });

  testWidgets('protected semantic destination redirects unauthenticated user',
      (tester) async {
    final appRouter = AppRouter(
      bootstrap: const BootstrapState.compatible(),
      session: const SessionState.unauthenticated(),
      initialLink: const SemanticLink(
        version: 1,
        route: 'group.detail',
        params: {'group_id': 42},
      ),
    );

    await tester.pumpWidget(MaterialApp.router(routerConfig: appRouter.router));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('login-route-screen')), findsOneWidget);
    expect(find.byKey(const Key('group-detail-route-screen')), findsNothing);
  });

  testWidgets('authenticated compatible user may resolve group destination',
      (tester) async {
    final appRouter = AppRouter(
      bootstrap: const BootstrapState.compatible(),
      session: SessionState.authenticated(sampleSession()),
      initialLink: const SemanticLink(
        version: 1,
        route: 'group.detail',
        params: {'group_id': 42},
      ),
    );

    await tester.pumpWidget(MaterialApp.router(routerConfig: appRouter.router));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('group-detail-route-screen')), findsOneWidget);
    expect(find.text('42'), findsOneWidget);
  });

  testWidgets('required update blocks protected deep-link navigation',
      (tester) async {
    final appRouter = AppRouter(
      bootstrap: const BootstrapState.requiredUpdate(),
      session: SessionState.authenticated(sampleSession()),
      initialLink: const SemanticLink(
        version: 1,
        route: 'group.detail',
        params: {'group_id': 42},
      ),
    );

    await tester.pumpWidget(MaterialApp.router(routerConfig: appRouter.router));
    await tester.pumpAndSettle();

    expect(
        find.byKey(const Key('required-update-route-screen')), findsOneWidget);
    expect(find.byKey(const Key('group-detail-route-screen')), findsNothing);
  });

  testWidgets('degraded offline cannot open a fresh protected deep link',
      (tester) async {
    final appRouter = AppRouter(
      bootstrap: const BootstrapState.degradedOffline(),
      session: SessionState.authenticated(sampleSession()),
      initialLink: const SemanticLink(
        version: 1,
        route: 'group.detail',
        params: {'group_id': 42},
      ),
    );

    await tester.pumpWidget(MaterialApp.router(routerConfig: appRouter.router));
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('home-route-screen')), findsOneWidget);
    expect(find.byKey(const Key('group-detail-route-screen')), findsNothing);
  });

  testWidgets('drill-down routes preserve Android back history', (tester) async {
    final appRouter = AppRouter(
      bootstrap: const BootstrapState.compatible(),
      session: SessionState.authenticated(sampleSession()),
      groupsBuilder: (context, openGroup) => Scaffold(
        key: const Key('test-groups-screen'),
        body: FilledButton(
          key: const Key('test-open-group'),
          onPressed: () => openGroup(42),
          child: const Text('باز کردن گروه'),
        ),
      ),
      groupDetailBuilder: (context, groupId) => Scaffold(
        key: const Key('test-group-detail-screen'),
        body: Text('group:$groupId'),
      ),
      notificationsBuilder: (context, openLink) => const Scaffold(
        key: Key('test-notifications-screen'),
        body: Text('اعلان‌ها'),
      ),
    );

    await tester.pumpWidget(MaterialApp.router(routerConfig: appRouter.router));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('home-groups-action')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('test-groups-screen')), findsOneWidget);

    await tester.tap(find.byKey(const Key('test-open-group')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('test-group-detail-screen')), findsOneWidget);

    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('test-groups-screen')), findsOneWidget);

    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('home-route-screen')), findsOneWidget);

    await tester.tap(find.byKey(const Key('home-notifications-action')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('test-notifications-screen')), findsOneWidget);

    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('home-route-screen')), findsOneWidget);
  });
}

NativeSession sampleSession() => NativeSession(
      token: 'session-token',
      expiresAt: DateTime.utc(2026, 10, 1),
      user: const SessionUser(id: 42, firstName: 'Test', lastName: 'Member'),
      device: const SessionDevice(
        id: 'device-1',
        platform: 'android',
        appVersion: '1.0.0',
        locale: 'fa',
        timezone: 'Asia/Tehran',
        pushCapable: true,
      ),
    );
