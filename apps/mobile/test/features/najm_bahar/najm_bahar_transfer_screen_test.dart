import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_transfer_controller.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_transfer_section.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'najm_bahar_repository_test.dart' as f;
import 'najm_bahar_transfer_repository_test.dart' as p;

Future<void> show(
  WidgetTester tester,
  NajmBaharTransferController controller,
) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: Scaffold(
          body: SingleChildScrollView(
            child: NajmBaharTransferSection(controller: controller),
          ),
        ),
      ),
    ),
  );
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

Map<String, Object?> successFor(RequestOptions request) {
  if (request.path.endsWith('/capability')) {
    return f.envelope(p.capability());
  }
  if (request.path.endsWith('/destination')) {
    return f.envelope(p.destination());
  }
  if (request.path.contains('/by-idempotency/')) {
    return f.envelope({'transaction': p.transferTransaction()});
  }
  return f.envelope(p.mutationReceipt());
}

Future<void> previewDestination(
  WidgetTester tester,
  NajmBaharTransferController controller,
) async {
  await tester.enterText(
    find.byKey(const Key('transfer-destination-input')),
    '1000000011/002',
  );
  await tester.pump();

  final button = tester.widget<FilledButton>(
    find.byKey(const Key('transfer-preview-destination')),
  );
  await tester.runAsync(() async {
    button.onPressed!.call();
    for (var i = 0; i < 50 && controller.destination == null; i++) {
      await Future<void>.delayed(const Duration(milliseconds: 2));
    }
  });
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

Future<void> prepareReady(
  WidgetTester tester,
  NajmBaharTransferController controller,
) async {
  await tester.runAsync(controller.prepare);
  await show(tester, controller);
  await previewDestination(tester, controller);
}

void main() {
  testWidgets('locked capability exposes no transfer submit or Dim choice',
      (tester) async {
    final adapter = f.BoundaryAdapter((request) {
      if (request.path.endsWith('/capability')) {
        return f.envelope(p.capability(enabled: false));
      }
      return successFor(request);
    });
    final controller = NajmBaharTransferController(f.repository(adapter));
    addTearDown(controller.dispose);

    await tester.runAsync(controller.prepare);
    await show(tester, controller);

    expect(find.text('انتقال بهار'), findsOneWidget);
    expect(find.textContaining('هنوز طبق آستانه'), findsOneWidget);
    expect(find.byKey(const Key('transfer-review')), findsNothing);
    expect(find.textContaining('بهار کمرنگ'), findsOneWidget);
    expect(adapter.requests.every((r) => r.method == 'GET'), true);
  });

  testWidgets('destination preview and review are GET-only and cancel is safe',
      (tester) async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharTransferController(
      f.repository(adapter),
      keyFactory: () => 'widget-transfer-0001',
    );
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await tester.enterText(
      find.byKey(const Key('transfer-amount-input')),
      '10.25',
    );
    await tester.enterText(
      find.byKey(const Key('transfer-description-input')),
      '  کمک آزمایشی  ',
    );
    await tester.pump();

    expect(find.textContaining('مقصد تأییدشده'), findsOneWidget);
    expect(adapter.requests.where((r) => r.method == 'POST'), isEmpty);

    final review = tester.widget<FilledButton>(
      find.byKey(const Key('transfer-review')),
    );
    expect(review.onPressed, isNotNull);
    review.onPressed!.call();
    await tester.pump();

    expect(find.text('تأیید انتقال بهار'), findsOneWidget);
    expect(find.text('مبلغ: 10 بهار و 25 گل'), findsOneWidget);
    expect(find.textContaining('کمک آزمایشی'), findsOneWidget);
    expect(adapter.requests.where((r) => r.method == 'POST'), isEmpty);

    tester
        .widget<TextButton>(find.byKey(const Key('transfer-cancel')))
        .onPressed!
        .call();
    await tester.pump();

    expect(controller.state, NajmBaharTransferState.ready);
    expect(adapter.requests.where((r) => r.method == 'POST'), isEmpty);
  });

  testWidgets('explicit confirmation posts once with frozen Active intent',
      (tester) async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharTransferController(
      f.repository(adapter),
      keyFactory: () => 'widget-transfer-0002',
    );
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await tester.enterText(
      find.byKey(const Key('transfer-amount-input')),
      '2.50',
    );
    await tester.pump();

    tester
        .widget<FilledButton>(find.byKey(const Key('transfer-review')))
        .onPressed!
        .call();
    await tester.pump();

    final pay = tester.widget<FilledButton>(
      find.byKey(const Key('transfer-confirm')),
    );
    await tester.runAsync(() async {
      pay.onPressed!.call();
      await controller.confirm();
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    final posts = adapter.requests.where((r) => r.method == 'POST').toList();
    expect(posts, hasLength(1));
    expect(posts.single.headers['Idempotency-Key'], 'widget-transfer-0002');
    expect(posts.single.data, {
      'source_account_id': 31,
      'destination_account_number': '1000000011-002',
      'amount_gol': 250,
      'balance_bucket': 'active',
      'description': null,
      'expected': {
        'transfer_contract_version': 1,
        'source_account_number': '1000000007-001',
        'source_active_available_gol': 1200,
        'destination_token': 'opaque-destination-token',
      },
    });
    expect(find.text('انتقال با موفقیت ثبت شد'), findsOneWidget);
    expect(find.textContaining('T-81'), findsOneWidget);
    expect(find.text('بهار کمرنگ در انتقال بیرونی قابل انتخاب نیست.'),
        findsNothing);
  });

  testWidgets('unknown result freezes transfer and reconcile never POSTs again',
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
    final controller = NajmBaharTransferController(
      f.repository(adapter),
      keyFactory: () => 'widget-transfer-unknown',
    );
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await tester.enterText(
      find.byKey(const Key('transfer-amount-input')),
      '2.50',
    );
    await tester.pump();
    tester
        .widget<FilledButton>(find.byKey(const Key('transfer-review')))
        .onPressed!
        .call();
    await tester.pump();

    final confirm = tester.widget<FilledButton>(
      find.byKey(const Key('transfer-confirm')),
    );
    await tester.runAsync(() async {
      confirm.onPressed!.call();
      await controller.confirm();
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.text('نتیجهٔ انتقال هنوز مشخص نیست'), findsOneWidget);
    expect(find.text('مبدأ: 1000000007-001'), findsOneWidget);
    expect(find.text('مقصد: 1000000011-002'), findsOneWidget);
    expect(find.text('مبلغ: 2 بهار و 50 گل'), findsOneWidget);
    expect(adapter.requests.where((r) => r.method == 'POST'), hasLength(1));

    final reconcile = tester.widget<FilledButton>(
      find.byKey(const Key('transfer-reconcile')),
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
    expect(find.text('انتقال با موفقیت ثبت شد'), findsOneWidget);
  });

  testWidgets('narrow RTL layout accepts large exact integer values',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(320, 720));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final large = {
      ...p.transferSource(),
      'active_available_gol': 9007199254740000,
    };
    final adapter = f.BoundaryAdapter((request) {
      if (request.path.endsWith('/capability')) {
        return f.envelope({
          ...p.capability(),
          'sources': [large],
        });
      }
      return successFor(request);
    });
    final controller = NajmBaharTransferController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await tester.enterText(
      find.byKey(const Key('transfer-amount-input')),
      '90071992547400.00',
    );
    await tester.pump();

    expect(tester.takeException(), isNull);
    expect(
      tester
          .widget<FilledButton>(find.byKey(const Key('transfer-review')))
          .onPressed,
      isNotNull,
    );
  });
}
