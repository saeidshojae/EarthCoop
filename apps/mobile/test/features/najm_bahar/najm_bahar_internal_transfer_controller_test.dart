import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_internal_transfer_controller.dart';

import 'najm_bahar_internal_transfer_repository_test.dart' as p;
import 'najm_bahar_repository_test.dart' as f;

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
  return f.envelope(p.receipt());
}

Future<void> waitForPost(f.BoundaryAdapter adapter) async {
  for (var i = 0; i < 50; i++) {
    if (adapter.requests.any((request) =>
        request.method == 'POST' &&
        request.path == '/najm-bahar/internal-transfers')) {
      return;
    }
    await Future<void>.delayed(const Duration(milliseconds: 2));
  }
  fail('Internal transfer POST did not start in bounded window.');
}

Future<void> prepareReview(
  NajmBaharInternalTransferController controller, {
  String bucket = 'dim',
  int amount = 125,
}) async {
  await controller.prepare();
  final subs = controller.snapshot!.subaccounts;
  controller.beginReview(
    source: subs.first,
    destination: subs.last,
    balanceBucket: bucket,
    amountGol: amount,
    description: '  جابجایی داخلی  ',
  );
}

void main() {
  test('prepare is GET-only and create/rename remain non-financial', () async {
    var next = 0;
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharInternalTransferController(
      f.repository(adapter),
      keyFactory: () => 'internal-key-${next++}',
    );
    addTearDown(controller.dispose);

    await controller.prepare();
    expect(controller.state, NajmBaharInternalTransferState.ready);
    expect(controller.snapshot!.subaccounts, hasLength(2));
    expect(adapter.requests.map((r) => r.method), ['GET']);

    await controller.createSubAccount('روزانه');
    await controller.renameSubAccount(11, 'روزمره نو');

    expect(
      adapter.requests.where((r) => r.path == '/najm-bahar/internal-transfers'),
      isEmpty,
    );
    expect(adapter.requests.map((r) => r.method), containsAll(['POST', 'PATCH']));
  });

  test('review freezes exact internal direction bucket amount without POST',
      () async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharInternalTransferController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareReview(controller);

    expect(controller.state, NajmBaharInternalTransferState.reviewing);
    expect(controller.reviewIntent?.direction, 'sub_to_sub');
    expect(controller.reviewIntent?.balanceBucket, 'dim');
    expect(controller.reviewIntent?.amountGol, 125);
    expect(
      adapter.requests.where((r) => r.path == '/najm-bahar/internal-transfers'),
      isEmpty,
    );
  });

  test('repeated confirm shares one pending financial POST', () async {
    final pending = Completer<Map<String, Object?>>();
    final adapter = f.BoundaryAdapter((request) async {
      if (request.path == '/najm-bahar/internal-transfers' &&
          request.method == 'POST') {
        return pending.future;
      }
      return successFor(request);
    });
    final controller = NajmBaharInternalTransferController(
      f.repository(adapter),
      keyFactory: () => 'internal-controller-key',
    );
    addTearDown(controller.dispose);

    await prepareReview(controller);
    final first = controller.confirm();
    final second = controller.confirm();
    await waitForPost(adapter);

    expect(controller.state, NajmBaharInternalTransferState.submitting);
    expect(
      adapter.requests
          .where((r) =>
              r.method == 'POST' &&
              r.path == '/najm-bahar/internal-transfers')
          .length,
      1,
    );

    pending.complete(f.envelope(p.receipt()));
    await Future.wait([first, second]);

    expect(controller.state, NajmBaharInternalTransferState.confirmed);
    expect(controller.receipt?.transaction.trackingNumber, 'T-201');
  });

  test('unknown result retries byte-equivalent intent with same key', () async {
    var failPost = true;
    final adapter = f.BoundaryAdapter((request) {
      if (request.path == '/najm-bahar/internal-transfers' &&
          request.method == 'POST') {
        if (failPost) {
          throw DioException(
            requestOptions: request,
            type: DioExceptionType.receiveTimeout,
          );
        }
        return f.envelope(p.receipt());
      }
      return successFor(request);
    });
    final controller = NajmBaharInternalTransferController(
      f.repository(adapter),
      keyFactory: () => 'stable-internal-key',
    );
    addTearDown(controller.dispose);

    await prepareReview(controller);
    await controller.confirm();
    expect(controller.state, NajmBaharInternalTransferState.outcomeUnknown);

    failPost = false;
    await controller.retrySameIntent();

    final posts = adapter.requests
        .where((r) =>
            r.method == 'POST' &&
            r.path == '/najm-bahar/internal-transfers')
        .toList();
    expect(posts, hasLength(2));
    expect(posts[1].headers['Idempotency-Key'], posts[0].headers['Idempotency-Key']);
    expect(posts[1].data, posts[0].data);
    expect(controller.state, NajmBaharInternalTransferState.confirmed);
  });

  test('GET-only reconciliation resolves ambiguous result', () async {
    final adapter = f.BoundaryAdapter((request) {
      if (request.path == '/najm-bahar/internal-transfers' &&
          request.method == 'POST') {
        throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        );
      }
      return successFor(request);
    });
    final controller = NajmBaharInternalTransferController(
      f.repository(adapter),
      keyFactory: () => 'reconcile-internal-key',
    );
    addTearDown(controller.dispose);

    await prepareReview(controller);
    await controller.confirm();
    await controller.reconcile();

    expect(controller.state, NajmBaharInternalTransferState.confirmed);
    expect(
      adapter.requests
          .where((r) => r.path.contains('/by-idempotency/'))
          .every((r) => r.method == 'GET'),
      true,
    );
    expect(
      adapter.requests
          .where((r) =>
              r.method == 'POST' &&
              r.path == '/najm-bahar/internal-transfers')
          .length,
      1,
    );
  });

  test('23 hour expiry prevents ambiguous replay', () async {
    var elapsed = Duration.zero;
    final adapter = f.BoundaryAdapter((request) {
      if (request.path == '/najm-bahar/internal-transfers' &&
          request.method == 'POST') {
        throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        );
      }
      return successFor(request);
    });
    final controller = NajmBaharInternalTransferController(
      f.repository(adapter),
      elapsedSinceStart: () => elapsed,
    );
    addTearDown(controller.dispose);

    await prepareReview(controller);
    await controller.confirm();
    elapsed = const Duration(hours: 23);
    await controller.retrySameIntent();

    expect(controller.failure?.code, 'internal_transfer_intent_expired');
    expect(
      adapter.requests
          .where((r) =>
              r.method == 'POST' &&
              r.path == '/najm-bahar/internal-transfers')
          .length,
      1,
    );
  });

  test('restart and logout never replay or publish old financial result',
      () async {
    final adapter = f.BoundaryAdapter(successFor);
    final first = NajmBaharInternalTransferController(f.repository(adapter));
    await first.prepare();
    first.dispose();

    final restarted = NajmBaharInternalTransferController(f.repository(adapter));
    addTearDown(restarted.dispose);
    await restarted.prepare();
    expect(
      adapter.requests.where((r) => r.path == '/najm-bahar/internal-transfers'),
      isEmpty,
    );

    var current = true;
    final changes = ChangeNotifier();
    final pending = Completer<Map<String, Object?>>();
    final delayedAdapter = f.BoundaryAdapter((request) async {
      if (request.path == '/najm-bahar/internal-transfers' &&
          request.method == 'POST') {
        return pending.future;
      }
      return successFor(request);
    });
    final delayed = NajmBaharInternalTransferController(
      f.repository(delayedAdapter, current: () => current),
      sessionChanges: changes,
    );
    await prepareReview(delayed);
    final task = delayed.confirm();
    await waitForPost(delayedAdapter);
    current = false;
    changes.notifyListeners();
    pending.complete(f.envelope(p.receipt()));
    await task;

    expect(delayed.receipt, isNull);
    expect(delayed.snapshot, isNull);
    delayed.dispose();
    changes.dispose();
  });
}
