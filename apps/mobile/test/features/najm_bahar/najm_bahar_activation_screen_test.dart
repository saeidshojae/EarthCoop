import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_activation_controller.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_activation_section.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'najm_bahar_activation_repository_test.dart' as p;
import 'najm_bahar_repository_test.dart' as f;

Future<void> show(
  WidgetTester tester,
  NajmBaharActivationController controller,
) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: Scaffold(
          body: SingleChildScrollView(
            child: NajmBaharActivationSection(controller: controller),
          ),
        ),
      ),
    ),
  );
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

Map<String, Object?> successFor(RequestOptions request) {
  if (request.path.endsWith('/eligibility')) {
    return f.envelope(p.eligibility());
  }
  return f.envelope(p.receipt());
}

Future<void> prepareReady(
  WidgetTester tester,
  NajmBaharActivationController controller,
) async {
  await tester.runAsync(controller.prepare);
  await show(tester, controller);
}

void main() {
  testWidgets('opening activation is GET-only and explains Dim to Active',
      (tester) async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharActivationController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);

    expect(find.text('فعال‌سازی بهار از امتیاز مشارکت'), findsOneWidget);
    expect(find.textContaining('پول جدید ایجاد نمی‌کند'), findsOneWidget);
    expect(find.textContaining('350'), findsWidgets);
    expect(find.textContaining('300'), findsWidgets);
    expect(find.textContaining('3 گل'), findsWidgets);
    expect(adapter.requests.map((r) => r.method), ['GET']);
  });

  testWidgets('invalid ratio cannot review and cancel never POSTs',
      (tester) async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharActivationController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await tester.enterText(
      find.byKey(const Key('activation-points-input')),
      '250',
    );
    await tester.pump();

    final invalidReview = tester.widget<FilledButton>(
      find.byKey(const Key('activation-review')),
    );
    expect(invalidReview.onPressed, isNull);

    await tester.enterText(
      find.byKey(const Key('activation-points-input')),
      '200',
    );
    await tester.pump();

    final review = tester.widget<FilledButton>(
      find.byKey(const Key('activation-review')),
    );
    review.onPressed!.call();
    await tester.pump();

    expect(find.text('تأیید فعال‌سازی بهار'), findsOneWidget);
    expect(find.text('امتیاز مصرفی: 200'), findsOneWidget);
    expect(find.text('مقدار فعال‌شونده: 2 گل'), findsOneWidget);
    expect(find.text('کمرنگ قبل: 10 گل'), findsOneWidget);
    expect(find.text('کمرنگ بعد: 8 گل'), findsOneWidget);
    expect(find.text('فعال قبل: 5 گل'), findsOneWidget);
    expect(find.text('فعال بعد: 7 گل'), findsOneWidget);
    expect(adapter.requests.where((r) => r.method == 'POST'), isEmpty);

    tester
        .widget<TextButton>(find.byKey(const Key('activation-cancel')))
        .onPressed!
        .call();
    await tester.pump();

    expect(controller.state, NajmBaharActivationState.ready);
    expect(adapter.requests.where((r) => r.method == 'POST'), isEmpty);
  });

  testWidgets('explicit confirmation posts once and shows receipt',
      (tester) async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      keyFactory: () => 'widget-activation-0001',
    );
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await tester.enterText(
      find.byKey(const Key('activation-points-input')),
      '200',
    );
    await tester.pump();
    tester
        .widget<FilledButton>(find.byKey(const Key('activation-review')))
        .onPressed!
        .call();
    await tester.pump();

    final confirm = tester.widget<FilledButton>(
      find.byKey(const Key('activation-confirm')),
    );
    await tester.runAsync(() async {
      confirm.onPressed!.call();
      await controller.confirm();
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    final posts = adapter.requests.where((r) => r.method == 'POST').toList();
    expect(posts, hasLength(1));
    expect(posts.single.headers['Idempotency-Key'], 'widget-activation-0001');
    expect(posts.single.data, p.intent().toJson());
    expect(find.text('فعال‌سازی با موفقیت ثبت شد'), findsOneWidget);
    expect(find.textContaining('T-91'), findsOneWidget);
  });

  testWidgets('unknown result freezes intent and reconciliation adds no POST',
      (tester) async {
    var firstPost = true;
    final adapter = f.BoundaryAdapter((request) {
      if (request.method == 'POST' && firstPost) {
        firstPost = false;
        throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        );
      }
      return successFor(request);
    });
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      keyFactory: () => 'widget-activation-unknown',
    );
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await tester.enterText(
      find.byKey(const Key('activation-points-input')),
      '200',
    );
    await tester.pump();
    tester
        .widget<FilledButton>(find.byKey(const Key('activation-review')))
        .onPressed!
        .call();
    await tester.pump();

    final confirm = tester.widget<FilledButton>(
      find.byKey(const Key('activation-confirm')),
    );
    await tester.runAsync(() async {
      confirm.onPressed!.call();
      await controller.confirm();
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.text('نتیجهٔ فعال‌سازی هنوز مشخص نیست'), findsOneWidget);
    expect(find.text('امتیاز ثابت‌شده: 200'), findsOneWidget);
    expect(find.text('بهار فعال‌شونده: 2 گل'), findsOneWidget);
    expect(adapter.requests.where((r) => r.method == 'POST'), hasLength(1));

    final reconcile = tester.widget<FilledButton>(
      find.byKey(const Key('activation-reconcile')),
    );
    await tester.runAsync(() async {
      reconcile.onPressed!.call();
      await controller.reconcile();
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(adapter.requests.where((r) => r.method == 'POST'), hasLength(1));
    expect(
      adapter.requests
          .where((r) => r.path.contains('/by-idempotency/'))
          .every((r) => r.method == 'GET'),
      true,
    );
    expect(find.text('فعال‌سازی با موفقیت ثبت شد'), findsOneWidget);
  });

  testWidgets('narrow RTL layout handles large exact point values',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(320, 720));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final largeEligibility = {
      ...p.eligibility(),
      'remaining_convertible_points': 9007199254740000,
      'max_convertible_points': 9007199254740000,
      'max_activation_points': 9007199254740000,
      'max_activation_gol': 90071992547400,
      'dim_available_gol': 90071992547400,
    };
    final adapter = f.BoundaryAdapter((request) {
      if (request.path.endsWith('/eligibility')) {
        return f.envelope(largeEligibility);
      }
      return f.envelope(p.receipt());
    });
    final controller = NajmBaharActivationController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await tester.enterText(
      find.byKey(const Key('activation-points-input')),
      '9007199254740000',
    );
    await tester.pump();

    expect(tester.takeException(), isNull);
    expect(
      tester
          .widget<FilledButton>(find.byKey(const Key('activation-review')))
          .onPressed,
      isNotNull,
    );
  });
}
