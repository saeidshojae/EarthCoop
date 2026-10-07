import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_internal_transfer_controller.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_internal_transfer_dto.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_internal_transfer_section.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'najm_bahar_internal_transfer_repository_test.dart' as p;
import 'najm_bahar_repository_test.dart' as f;

Map<String, Object?> mainToSubReceipt({String bucket = 'active'}) => {
      'transaction': {
        ...p.transaction(amount: 125, bucket: bucket),
        'direction': 'internal',
      },
      'source': {
        ...p.mainAccount(),
        'active_gol': 675,
        'active_available_gol': 375,
      },
      'destination': {
        ...p.subAccount(),
        'active_gol': 425,
        'active_available_gol': 375,
      },
    };

Map<String, Object?> successFor(RequestOptions request) {
  if (request.path == '/najm-bahar/subaccounts') {
    if (request.method == 'GET') return f.envelope(p.snapshot());
    return f.envelope(
      p.subAccount(name: request.data?['name'] as String? ?? 'روزمره'),
    );
  }
  if (request.path.startsWith('/najm-bahar/subaccounts/')) {
    return f.envelope(
      p.subAccount(name: request.data?['name'] as String? ?? 'روزمره'),
    );
  }
  return f.envelope(mainToSubReceipt());
}

Future<void> show(
  WidgetTester tester,
  NajmBaharInternalTransferController controller,
) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: Scaffold(
          body: SingleChildScrollView(
            child: NajmBaharInternalTransferSection(controller: controller),
          ),
        ),
      ),
    ),
  );
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

Future<void> prepareReady(
  WidgetTester tester,
  NajmBaharInternalTransferController controller,
) async {
  await tester.runAsync(controller.prepare);
  await show(tester, controller);
}

Future<void> beginUiReview(WidgetTester tester) async {
  await tester.enterText(find.byKey(const Key('internal-amount')), '1.25');
  await tester.pump();
  final review = tester.widget<FilledButton>(
    find.byKey(const Key('internal-review')),
  );
  expect(review.onPressed, isNotNull);
  review.onPressed!.call();
  await tester.pump();
}

void main() {
  testWidgets('opening is GET-only and shows owned account projections',
      (tester) async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharInternalTransferController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);

    expect(find.text('حساب‌های فرعی من'), findsOneWidget);
    expect(find.textContaining('حساب اصلی'), findsWidgets);
    expect(find.textContaining('1000000007-001'), findsWidgets);
    expect(find.text('بهار فعال'), findsOneWidget);
    expect(find.text('بهار کمرنگ'), findsOneWidget);
    expect(adapter.requests.map((r) => r.method), ['GET']);
  });

  testWidgets('create and rename never invoke internal financial transfer',
      (tester) async {
    var next = 0;
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharInternalTransferController(
      f.repository(adapter),
      keyFactory: () => 'widget-internal-${next++}',
    );
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);

    await tester.enterText(
      find.byKey(const Key('subaccount-create-name')),
      'روزانه',
    );
    final create = tester.widget<FilledButton>(
      find.byKey(const Key('subaccount-create')),
    );
    await tester.runAsync(() async {
      create.onPressed!.call();
      for (var i = 0; i < 50; i++) {
        if (adapter.requests.any((r) =>
            r.method == 'POST' && r.path == '/najm-bahar/subaccounts')) {
          break;
        }
        await Future<void>.delayed(const Duration(milliseconds: 2));
      }
    });
    await tester.pump();

    final renameSelect = tester.widget<DropdownButtonFormField<int>>(
      find.byKey(const Key('subaccount-rename-select')),
    );
    renameSelect.onChanged!.call(11);
    await tester.pump();
    await tester.enterText(
      find.byKey(const Key('subaccount-rename-name')),
      'روزمره نو',
    );
    await tester.pump();
    final rename = tester.widget<OutlinedButton>(
      find.byKey(const Key('subaccount-rename')),
    );
    await tester.runAsync(() async {
      rename.onPressed!.call();
      for (var i = 0; i < 50; i++) {
        if (adapter.requests.any((r) => r.method == 'PATCH')) break;
        await Future<void>.delayed(const Duration(milliseconds: 2));
      }
    });
    await tester.pump();

    expect(
      adapter.requests.where((r) => r.path == '/najm-bahar/internal-transfers'),
      isEmpty,
    );
    expect(adapter.requests.any((r) => r.method == 'POST'), true);
    expect(adapter.requests.any((r) => r.method == 'PATCH'), true);
  });

  testWidgets('review and cancel are financial-POST free', (tester) async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharInternalTransferController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await beginUiReview(tester);

    expect(find.text('تأیید انتقال داخلی'), findsOneWidget);
    expect(find.textContaining('1 بهار و 25 گل'), findsOneWidget);
    expect(adapter.requests.where((r) =>
        r.method == 'POST' &&
        r.path == '/najm-bahar/internal-transfers'), isEmpty);

    tester
        .widget<TextButton>(find.byKey(const Key('internal-cancel')))
        .onPressed!
        .call();
    await tester.pump();

    expect(controller.state, NajmBaharInternalTransferState.ready);
    expect(adapter.requests.where((r) =>
        r.method == 'POST' &&
        r.path == '/najm-bahar/internal-transfers'), isEmpty);
  });

  testWidgets('explicit confirm posts once and renders matched receipt',
      (tester) async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharInternalTransferController(
      f.repository(adapter),
      keyFactory: () => 'widget-internal-confirm',
    );
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await beginUiReview(tester);

    final confirm = tester.widget<FilledButton>(
      find.byKey(const Key('internal-confirm')),
    );
    await tester.runAsync(() async {
      confirm.onPressed!.call();
      await controller.confirm();
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    final posts = adapter.requests.where((r) =>
        r.method == 'POST' &&
        r.path == '/najm-bahar/internal-transfers').toList();
    expect(posts, hasLength(1));
    expect(posts.single.headers['Idempotency-Key'], 'widget-internal-confirm');
    expect(find.text('انتقال داخلی با موفقیت ثبت شد'), findsOneWidget);
    expect(find.textContaining('T-201'), findsOneWidget);
  });

  testWidgets('unknown result reconciles with GET and never adds a POST',
      (tester) async {
    var firstPost = true;
    final adapter = f.BoundaryAdapter((request) {
      if (request.method == 'POST' &&
          request.path == '/najm-bahar/internal-transfers' &&
          firstPost) {
        firstPost = false;
        throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        );
      }
      return successFor(request);
    });
    final controller = NajmBaharInternalTransferController(
      f.repository(adapter),
      keyFactory: () => 'widget-internal-unknown',
    );
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await beginUiReview(tester);

    final confirm = tester.widget<FilledButton>(
      find.byKey(const Key('internal-confirm')),
    );
    await tester.runAsync(() async {
      confirm.onPressed!.call();
      await controller.confirm();
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.text('نتیجهٔ انتقال داخلی هنوز مشخص نیست'), findsOneWidget);
    expect(adapter.requests.where((r) =>
        r.method == 'POST' &&
        r.path == '/najm-bahar/internal-transfers'), hasLength(1));

    final reconcile = tester.widget<FilledButton>(
      find.byKey(const Key('internal-reconcile')),
    );
    await tester.runAsync(() async {
      reconcile.onPressed!.call();
      await controller.reconcile();
    });
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(adapter.requests.where((r) =>
        r.method == 'POST' &&
        r.path == '/najm-bahar/internal-transfers'), hasLength(1));
    expect(
      adapter.requests
          .where((r) => r.path.contains('/by-idempotency/'))
          .every((r) => r.method == 'GET'),
      true,
    );
    expect(find.text('انتقال داخلی با موفقیت ثبت شد'), findsOneWidget);
  });

  testWidgets('narrow RTL layout accepts large exact values', (tester) async {
    await tester.binding.setSurfaceSize(const Size(320, 760));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final adapter = f.BoundaryAdapter((request) {
      if (request.path == '/najm-bahar/subaccounts' &&
          request.method == 'GET') {
        return f.envelope({
          ...p.snapshot(),
          'main': {
            ...p.mainAccount(),
            'active_gol': 9007199254740000,
            'active_available_gol': 9007199254740000,
          },
        });
      }
      return successFor(request);
    });
    final controller = NajmBaharInternalTransferController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareReady(tester, controller);
    await tester.enterText(
      find.byKey(const Key('internal-amount')),
      '90071992547400.00',
    );
    await tester.pump();

    expect(tester.takeException(), isNull);
    expect(
      tester.widget<FilledButton>(
        find.byKey(const Key('internal-review')),
      ).onPressed,
      isNotNull,
    );
  });
}
