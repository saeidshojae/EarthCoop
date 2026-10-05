import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_controller.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_screen.dart';
import 'najm_bahar_repository_test.dart' as fixtures;

void main() {
  testWidgets('wallet distinguishes main and aggregate balances and history', (tester) async {
    final adapter=fixtures.BoundaryAdapter((r)=>fixtures.envelope(r.path.endsWith('/account')?fixtures.accountJson():[fixtures.transactionJson(9)]));
    final controller=NajmBaharController(fixtures.repository(adapter));
    addTearDown(controller.dispose);
    await controller.load();
    await tester.pumpWidget(MaterialApp(home:NajmBaharScreen(controller:controller)));
    expect(find.text('حساب اصلی'),findsOneWidget);
    expect(find.text('مجموع حساب‌ها'),findsOneWidget);
    expect(find.text('NB-7'),findsOneWidget);
    expect(find.textContaining('متعهد'),findsWidgets);
    await tester.scrollUntilVisible(find.text('T-9'),300);
    expect(find.text('T-9'),findsOneWidget);
  });
  testWidgets('empty history is explicit, not fabricated transaction', (tester) async {
    final controller=NajmBaharController(fixtures.repository(fixtures.BoundaryAdapter((r)=>fixtures.envelope(r.path.endsWith('/account')?fixtures.accountJson():[]))));
    addTearDown(controller.dispose);await controller.load();
    await tester.pumpWidget(MaterialApp(home:NajmBaharScreen(controller:controller)));
    await tester.scrollUntilVisible(find.text('تراکنشی یافت نشد.'),300);
    expect(find.text('تراکنشی یافت نشد.'),findsOneWidget);
  });
}
