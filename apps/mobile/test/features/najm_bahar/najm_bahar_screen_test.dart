import 'dart:async';
import 'package:earthcoop_mobile/core/auth/session_controller.dart';
import '../../core/auth/session_controller_test.dart' as sessions;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_controller.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_screen.dart';
import 'najm_bahar_repository_test.dart' as fixtures;

void main() {
  for (final logout in [false, true]) {
    testWidgets(
        'idle ${logout ? 'logout' : 'session change'} immediately removes financial data',
        (tester) async {
      final auth = sessions.PendingRevokeRepository();
      final scope = SessionController(repository: auth)
        ..state =
            SessionState.authenticated(sessions.sampleSession(token: 'a'));
      final adapter = fixtures.BoundaryAdapter((r) => fixtures.envelope(
          r.path.endsWith('/account')
              ? fixtures.accountJson()
              : [fixtures.transactionJson(9)]));
      final controller = NajmBaharController(
          fixtures.repository(adapter,
              current: () =>
                  scope.state.phase == SessionPhase.authenticated &&
                  scope.state.session?.token == 'a'),
          sessionChanges: scope);
      addTearDown(() {
        controller.dispose();
        scope.dispose();
      });
      await tester.runAsync(() => controller.load());
      await tester.pumpWidget(
          MaterialApp(home: NajmBaharScreen(controller: controller)));
      expect(find.text('NB-7'), findsOneWidget);
      final requests = adapter.requests.length;
      Future<void>? ending;
      if (logout) {
        ending = scope.logout();
      } else {
        scope.state =
            SessionState.authenticated(sessions.sampleSession(token: 'b'));
      }
      await tester.pump();
      expect(find.text('NB-7'), findsNothing);
      expect(find.text('T-9'), findsNothing);
      expect(controller.account, isNull);
      expect(controller.transactions, isEmpty);
      expect(adapter.requests.length, requests);
      if (ending != null) {
        auth.release.complete();
        final completion = ending;
        await tester.runAsync(() => completion);
      }
    }, timeout: const Timeout(Duration(seconds: 20)));
  }
  for (final pagination in [false, true]) {
    testWidgets(
        'history ${pagination ? 'pagination' : 'refresh'} retry requests correct cursor',
        (tester) async {
      var phase = 0;
      Completer<void>? retry;
      final adapter = fixtures.BoundaryAdapter((r) {
        if (r.path.endsWith('/account')) {
          return fixtures.envelope(fixtures.accountJson());
        }
        if (phase == 0) {
          return fixtures.envelope([fixtures.transactionJson(9)],
              cursor: 'next', more: true);
        }
        if (phase == 1) {
          return fixtures.envelope(null);
        }
        if (retry != null && !retry!.isCompleted) {
          retry!.complete();
        }
        return fixtures.envelope([fixtures.transactionJson(10)]);
      });
      final controller = NajmBaharController(fixtures.repository(adapter));
      addTearDown(controller.dispose);
      await tester.runAsync(() => controller.load());
      phase = 1;
      await tester.runAsync(() =>
          pagination ? controller.loadMore() : controller.refreshHistory());
      await tester.pumpWidget(
          MaterialApp(home: NajmBaharScreen(controller: controller)));
      await tester.scrollUntilVisible(find.text('تلاش دوباره'), 300,
          scrollable: find.byType(Scrollable).first);
      phase = 2;
      await tester.runAsync(() async {
        retry = Completer<void>();
        tester
            .widget<TextButton>(find.widgetWithText(TextButton, 'تلاش دوباره'))
            .onPressed!();
        await retry!.future.timeout(const Duration(seconds: 5));
      });
      await tester.pumpAndSettle();
      expect(adapter.requests.last.queryParameters['page[cursor]'],
          pagination ? 'next' : null);
      expect(controller.transactions.map((t) => t.id),
          pagination ? [9, 10] : [10]);
    }, timeout: const Timeout(Duration(seconds: 20)));
  }
  testWidgets('wallet distinguishes main and aggregate balances and history',
      (tester) async {
    final adapter = fixtures.BoundaryAdapter((r) => fixtures.envelope(
        r.path.endsWith('/account')
            ? fixtures.accountJson()
            : [fixtures.transactionJson(9)]));
    final controller = NajmBaharController(fixtures.repository(adapter));
    addTearDown(controller.dispose);
    await tester.runAsync(() => controller.load());
    await tester
        .pumpWidget(MaterialApp(home: NajmBaharScreen(controller: controller)));
    expect(find.text('حساب اصلی'), findsOneWidget);
    expect(find.text('مجموع حساب‌ها'), findsOneWidget);
    expect(find.text('NB-7'), findsOneWidget);
    expect(find.textContaining('متعهد'), findsWidgets);
    await tester.scrollUntilVisible(find.text('T-9'), 300,
        scrollable: find.byType(Scrollable).first);
    expect(find.text('T-9'), findsOneWidget);
  }, timeout: const Timeout(Duration(seconds: 20)));
  testWidgets('empty history is explicit, not fabricated transaction',
      (tester) async {
    final controller = NajmBaharController(fixtures.repository(
        fixtures.BoundaryAdapter((r) => fixtures.envelope(
            r.path.endsWith('/account') ? fixtures.accountJson() : []))));
    addTearDown(controller.dispose);
    await tester.runAsync(() => controller.load());
    await tester
        .pumpWidget(MaterialApp(home: NajmBaharScreen(controller: controller)));
    await tester.scrollUntilVisible(find.text('تراکنشی یافت نشد.'), 300,
        scrollable: find.byType(Scrollable).first);
    expect(find.text('تراکنشی یافت نشد.'), findsOneWidget);
  }, timeout: const Timeout(Duration(seconds: 20)));
  testWidgets('missing account shows its own state without hiding history',
      (tester) async {
    final adapter = fixtures.BoundaryAdapter(
        (r) => r.path.endsWith('/account')
            ? fixtures.missingAccountEnvelope()
            : fixtures.envelope([fixtures.transactionJson(9)]),
        statusFor: (r) => r.path.endsWith('/account') ? 404 : 200);
    final controller = NajmBaharController(fixtures.repository(adapter));
    addTearDown(controller.dispose);
    await tester.runAsync(() => controller.load());
    await tester
        .pumpWidget(MaterialApp(home: NajmBaharScreen(controller: controller)));
    expect(find.text('حساب نجم بهار یافت نشد.'), findsOneWidget);
    expect(find.text('T-9'), findsOneWidget);
  }, timeout: const Timeout(Duration(seconds: 20)));
  testWidgets('failed refresh labels retained balance as dated',
      (tester) async {
    var good = true;
    final adapter = fixtures.BoundaryAdapter(
        (_) => fixtures.envelope(good ? fixtures.accountJson() : null));
    final controller = NajmBaharController(fixtures.repository(adapter));
    addTearDown(controller.dispose);
    await tester.runAsync(() => controller.refreshAccount());
    good = false;
    await tester.runAsync(() => controller.refreshAccount());
    await tester
        .pumpWidget(MaterialApp(home: NajmBaharScreen(controller: controller)));
    expect(find.text('NB-7'), findsOneWidget);
    expect(find.text('این موجودی مربوط به آخرین دریافت موفق است و تازه نیست.'),
        findsOneWidget);
    expect(find.text('تلاش دوباره'), findsOneWidget);
  }, timeout: const Timeout(Duration(seconds: 20)));
}
