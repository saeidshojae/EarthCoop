import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_activation_controller.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_activation_section.dart';

import 'najm_bahar_activation_controller_test.dart' as fixture;
import 'najm_bahar_repository_test.dart' as f;

Future<void> showActivation(
  WidgetTester tester,
  NajmBaharActivationController controller,
) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: NajmBaharActivationSection(controller: controller),
        ),
      ),
    ),
  );
  await tester.pump();
}

void main() {
  testWidgets('native RTL preview requires exact points; never posts',
      (tester) async {
    final adapter = f.BoundaryAdapter(
      (_) => f.envelope(fixture.terms()),
    );
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      keyFactory: () => 'activation-widget-0001',
    );
    addTearDown(controller.dispose);
    await tester.runAsync(controller.prepare);
    await showActivation(tester, controller);

    expect(
      tester
          .widget<Directionality>(
            find
                .descendant(
                  of: find.byType(NajmBaharActivationSection),
                  matching: find.byType(Directionality),
                )
                .first,
          )
          .textDirection,
      TextDirection.rtl,
    );
    await tester.enterText(
        find.byKey(const Key('activation-points-input')), '250');
    await tester.pump();
    expect(
      tester
          .widget<FilledButton>(find.byKey(const Key('activation-review')))
          .onPressed,
      isNull,
    );
    await tester.enterText(
        find.byKey(const Key('activation-points-input')), '200');
    await tester.pump();
    expect(
      tester
          .widget<FilledButton>(find.byKey(const Key('activation-review')))
          .onPressed,
      isNotNull,
    );
    expect(adapter.requests.where((r) => r.method == 'POST'), isEmpty);
  });

  testWidgets('review requires explicit user confirmation', (tester) async {
    final adapter = f.BoundaryAdapter(
      (request) => f.envelope(
        request.method == 'POST' ? fixture.receipt() : fixture.terms(),
      ),
    );
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      keyFactory: () => 'activation-widget-0002',
    );
    addTearDown(controller.dispose);
    await tester.runAsync(controller.prepare);
    await showActivation(tester, controller);
    await tester.enterText(
        find.byKey(const Key('activation-points-input')), '200');
    await tester.pump();
    await tester.tap(find.byKey(const Key('activation-review')));
    await tester.pump();
    expect(find.text('بررسی نهایی درخواست'), findsOneWidget);
    expect(adapter.requests.where((r) => r.method == 'POST'), isEmpty);
  });
}
