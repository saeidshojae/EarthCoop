import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_controller.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_screen.dart';
import 'najm_bahar_repository_test.dart' as fixtures;

void main() {
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
    await tester.scrollUntilVisible(find.text('T-9'), 300, scrollable: find.byType(Scrollable).first);
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
    await tester.scrollUntilVisible(find.text('تراکنشی یافت نشد.'), 300, scrollable: find.byType(Scrollable).first);
    expect(find.text('تراکنشی یافت نشد.'), findsOneWidget);
  }, timeout: const Timeout(Duration(seconds: 20)));
  testWidgets('missing account shows its own state without hiding history', (tester) async {
    final adapter=fixtures.BoundaryAdapter((r)=>r.path.endsWith('/account')?fixtures.missingAccountEnvelope():fixtures.envelope([fixtures.transactionJson(9)]),statusFor:(r)=>r.path.endsWith('/account')?404:200);
    final controller=NajmBaharController(fixtures.repository(adapter));addTearDown(controller.dispose);
    await tester.runAsync(()=>controller.load());
    await tester.pumpWidget(MaterialApp(home:NajmBaharScreen(controller:controller)));
    expect(find.text('حساب نجم بهار یافت نشد.'),findsOneWidget);expect(find.text('T-9'),findsOneWidget);
  },timeout:const Timeout(Duration(seconds:20)));
  testWidgets('failed refresh labels retained balance as dated', (tester) async {
    var good=true;
    final adapter=fixtures.BoundaryAdapter((_)=>fixtures.envelope(good?fixtures.accountJson():null));
    final controller=NajmBaharController(fixtures.repository(adapter));addTearDown(controller.dispose);
    await tester.runAsync(()=>controller.refreshAccount());good=false;
    await tester.runAsync(()=>controller.refreshAccount());
    await tester.pumpWidget(MaterialApp(home:NajmBaharScreen(controller:controller)));
    expect(find.text('NB-7'),findsOneWidget);
    expect(find.text('این موجودی مربوط به آخرین دریافت موفق است و تازه نیست.'),findsOneWidget);
    expect(find.text('تلاش دوباره'),findsOneWidget);
  },timeout:const Timeout(Duration(seconds:20)));

}
