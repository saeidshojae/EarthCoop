import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/app/bootstrap/bootstrap_state.dart';
import 'package:earthcoop_mobile/app/router/app_router.dart';
import 'package:earthcoop_mobile/core/auth/session_controller.dart';
import 'package:earthcoop_mobile/features/home/home_screen.dart';
import '../../app/router/router_test.dart' show sampleSession;

void main() {
  testWidgets('Home opens wallet through protected route', (tester) async {
    final route = AppRouter(
        bootstrap: const BootstrapState.compatible(),
        session: SessionState.authenticated(sampleSession()),
        najmBaharBuilder: (_) => const Scaffold(body: Text('wallet-data')));
    addTearDown(route.router.dispose);
    await tester.pumpWidget(MaterialApp.router(routerConfig: route.router));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('home-najm-bahar-action')));
    await tester.pumpAndSettle();
    expect(find.text('wallet-data'), findsOneWidget);
  });
  testWidgets('direct wallet path does not invoke builder without login',
      (tester) async {
    var called = false;
    final route = AppRouter(
        bootstrap: const BootstrapState.compatible(),
        session: const SessionState.unauthenticated(),
        najmBaharBuilder: (_) {
          called = true;
          return const Text('secret-wallet');
        });
    addTearDown(route.router.dispose);
    await tester.pumpWidget(MaterialApp.router(routerConfig: route.router));
    route.router.go('/najm-bahar');
    await tester.pumpAndSettle();
    expect(called, false);
    expect(find.text('secret-wallet'), findsNothing);
  });
  testWidgets('direct wallet path obeys live session after logout',
      (tester) async {
    var state = SessionState.authenticated(sampleSession());
    var called = false;
    final route = AppRouter(
        bootstrap: const BootstrapState.compatible(),
        session: state,
        currentSession: () => state,
        najmBaharBuilder: (_) {
          called = true;
          return const Text('secret-wallet');
        });
    addTearDown(route.router.dispose);
    await tester.pumpWidget(MaterialApp.router(routerConfig: route.router));
    await tester.pumpAndSettle();
    state = const SessionState.unauthenticated();
    route.router.go('/najm-bahar');
    await tester.pumpAndSettle();
    expect(called, false);
  });
  testWidgets('blocked bootstrap does not start wallet builder',
      (tester) async {
    var called = false;
    final route = AppRouter(
        bootstrap: const BootstrapState.degradedOffline(),
        session: SessionState.authenticated(sampleSession()),
        najmBaharBuilder: (_) {
          called = true;
          return const Text('secret-wallet');
        });
    addTearDown(route.router.dispose);
    await tester.pumpWidget(MaterialApp.router(routerConfig: route.router));
    route.router.go('/najm-bahar');
    await tester.pumpAndSettle();
    expect(called, false);
  });
  testWidgets('Home wallet action disables immediately during logout',
      (tester) async {
    final pending = Completer<void>();
    var opened = false;
    await tester.pumpWidget(MaterialApp(
        home: HomeScreen(
            onOpenNajmBahar: () => opened = true,
            onLogout: () => pending.future)));
    await tester.tap(find.byKey(const Key('home-logout-action')));
    await tester.pump();
    await tester.tap(find.byKey(const Key('home-najm-bahar-action')));
    expect(opened, false);
    pending.complete();
    await tester.pumpAndSettle();
  });
}
