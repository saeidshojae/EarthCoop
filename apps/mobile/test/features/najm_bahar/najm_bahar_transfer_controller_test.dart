import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_transfer_controller.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_transfer_dto.dart';
import 'najm_bahar_repository_test.dart' as f;
import 'najm_bahar_transfer_repository_test.dart' as p;

Map<String, Object?> error(String code, {bool retryable = false}) => {
      'status': 'error',
      'data': null,
      'meta': {'api_version': 'v1'},
      'request_id': 'transfer-controller-test',
      'error': {'code': code, 'message': '', 'retryable': retryable},
    };

Future<void> prepareTransfer(NajmBaharTransferController controller) async {
  await controller.prepare();
  controller.selectSource(controller.capability!.sources.first);
  await controller.resolveDestination('1000000011/002');
  controller.beginReview(amountGol: 250, description: '  کمک  ');
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

void main() {
  test('prepare preview and review are GET-only and never transfer', () async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharTransferController(
      f.repository(adapter),
      keyFactory: () => 'controller-key-0001',
    );
    addTearDown(controller.dispose);

    await prepareTransfer(controller);

    expect(controller.state, NajmBaharTransferState.reviewing);
    expect(controller.selectedSource?.accountNumber, '1000000007-001');
    expect(controller.destination?.accountNumber, '1000000011-002');
    expect(controller.reviewAmountGol, 250);
    expect(controller.reviewDescription, 'کمک');
    expect(adapter.requests.map((r) => r.method), ['GET', 'GET']);
  });

  test('repeated confirm shares one pending POST and success stays confirmed',
      () async {
    final pending = Completer<Map<String, Object?>>();
    final adapter = f.BoundaryAdapter((request) async {
      if (request.method == 'POST') return pending.future;
      return successFor(request);
    });
    final controller = NajmBaharTransferController(
      f.repository(adapter),
      keyFactory: () => 'controller-key-0002',
    );
    addTearDown(controller.dispose);

    await prepareTransfer(controller);
    final first = controller.confirm();
    final second = controller.confirm();
    await Future<void>.delayed(Duration.zero);

    expect(controller.state, NajmBaharTransferState.submitting);
    expect(adapter.requests.where((r) => r.method == 'POST').length, 1);

    pending.complete(f.envelope(p.mutationReceipt()));
    await Future.wait([first, second]);

    expect(controller.state, NajmBaharTransferState.confirmed);
    expect(controller.receipt?.transaction.trackingNumber, 'T-81');
  });

  test('unknown result freezes intent and retries exact same body and key',
      () async {
    var fail = true;
    final adapter = f.BoundaryAdapter((request) {
      if (request.method != 'POST') return successFor(request);
      if (fail) {
        throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        );
      }
      return f.envelope(p.mutationReceipt());
    });
    final controller = NajmBaharTransferController(
      f.repository(adapter),
      keyFactory: () => 'stable-transfer-key',
    );
    addTearDown(controller.dispose);

    await prepareTransfer(controller);
    await controller.confirm();
    expect(controller.state, NajmBaharTransferState.outcomeUnknown);

    final originalSource = controller.selectedSource;
    controller.selectSource(
      NajmBaharTransferSource.fromJson({
        ...p.transferSource(),
        'account_id': 99,
        'sub_account_id': 99,
        'account_number': '1000000007-099',
      }),
    );
    controller.beginReview(amountGol: 100, description: 'changed');
    expect(controller.selectedSource, same(originalSource));
    expect(controller.reviewAmountGol, 250);

    fail = false;
    await controller.retrySameIntent();

    final posts = adapter.requests.where((r) => r.method == 'POST').toList();
    expect(posts.length, 2);
    expect(posts[1].headers['Idempotency-Key'],
        posts[0].headers['Idempotency-Key']);
    expect(posts[1].data, posts[0].data);
    expect(controller.state, NajmBaharTransferState.confirmed);
  });

  test('GET-only reconciliation resolves matching ambiguous transfer', () async {
    var postFails = true;
    final adapter = f.BoundaryAdapter((request) {
      if (request.method == 'POST' && postFails) {
        throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        );
      }
      return successFor(request);
    });
    final controller = NajmBaharTransferController(
      f.repository(adapter),
      keyFactory: () => 'reconcile-transfer-key',
    );
    addTearDown(controller.dispose);

    await prepareTransfer(controller);
    await controller.confirm();
    expect(controller.state, NajmBaharTransferState.outcomeUnknown);

    await controller.reconcile();

    expect(controller.state, NajmBaharTransferState.confirmed);
    expect(adapter.requests.where((r) => r.method == 'POST').length, 1);
    expect(
      adapter.requests
          .where((r) => r.path.contains('/by-idempotency/'))
          .every((r) => r.method == 'GET'),
      true,
    );
  });

  test('not-found reconciliation keeps outcome unknown without new POST',
      () async {
    final adapter = f.BoundaryAdapter(
      (request) {
        if (request.method == 'POST') {
          throw DioException(
            requestOptions: request,
            type: DioExceptionType.receiveTimeout,
          );
        }
        if (request.path.contains('/by-idempotency/')) {
          return error('not_found');
        }
        return successFor(request);
      },
      statusFor: (request) =>
          request.path.contains('/by-idempotency/') ? 404 : 200,
    );
    final controller = NajmBaharTransferController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareTransfer(controller);
    await controller.confirm();
    await controller.reconcile();

    expect(controller.state, NajmBaharTransferState.outcomeUnknown);
    expect(adapter.requests.where((r) => r.method == 'POST').length, 1);
  });

  test('monotonic 23 hour expiry prevents mutation replay', () async {
    var elapsed = Duration.zero;
    final adapter = f.BoundaryAdapter((request) {
      if (request.method == 'POST') {
        throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        );
      }
      return successFor(request);
    });
    final controller = NajmBaharTransferController(
      f.repository(adapter),
      elapsedSinceStart: () => elapsed,
    );
    addTearDown(controller.dispose);

    await prepareTransfer(controller);
    await controller.confirm();
    elapsed = const Duration(hours: 23);
    await controller.retrySameIntent();

    expect(adapter.requests.where((r) => r.method == 'POST').length, 1);
    expect(controller.failure?.code, 'transfer_intent_expired');
    expect(controller.state, NajmBaharTransferState.outcomeUnknown);
  });

  test('bootstrap pause before submit sends no POST and is recoverable',
      () async {
    var allowed = true;
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharTransferController(
      f.repository(adapter, allowed: () => allowed),
    );
    addTearDown(controller.dispose);

    await prepareTransfer(controller);
    allowed = false;
    await controller.confirm();

    expect(adapter.requests.where((r) => r.method == 'POST'), isEmpty);
    expect(controller.hasFrozenIntent, false);

    allowed = true;
    await controller.prepare();
    expect(controller.state, NajmBaharTransferState.ready);
  });

  test('definite rejection clears frozen intent and reloads capability',
      () async {
    var reject = true;
    final adapter = f.BoundaryAdapter(
      (request) {
        if (request.method == 'POST' && reject) {
          return error('transfer_terms_changed');
        }
        return successFor(request);
      },
      statusFor: (request) =>
          request.method == 'POST' && reject ? 409 : 200,
    );
    final controller = NajmBaharTransferController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareTransfer(controller);
    await controller.confirm();

    expect(controller.state, NajmBaharTransferState.definiteRejected);
    expect(controller.hasFrozenIntent, false);
    reject = false;
    await controller.prepare();
    expect(controller.state, NajmBaharTransferState.ready);
  });

  test('confirmed success survives financial refresh failure', () async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharTransferController(
      f.repository(adapter),
      refreshFinancialViews: () async => throw StateError('refresh failed'),
    );
    addTearDown(controller.dispose);

    await prepareTransfer(controller);
    await controller.confirm();

    expect(controller.state, NajmBaharTransferState.confirmed);
    expect(controller.receipt?.transaction.id, 81);
  });

  test('restart loads capability and never replays a mutation', () async {
    final adapter = f.BoundaryAdapter(successFor);
    final first = NajmBaharTransferController(f.repository(adapter));
    await first.prepare();
    first.dispose();

    final restarted = NajmBaharTransferController(f.repository(adapter));
    addTearDown(restarted.dispose);
    await restarted.prepare();

    expect(adapter.requests.every((r) => r.method == 'GET'), true);
  });

  test('401 and logout suppress financial state publication', () async {
    var invalidations = 0;
    final unauthorized = f.BoundaryAdapter(
      (request) => request.method == 'POST'
          ? error('unauthenticated')
          : successFor(request),
      statusFor: (request) => request.method == 'POST' ? 401 : 200,
    );
    final controller = NajmBaharTransferController(
      f.repository(unauthorized),
      onSessionInvalidated: () => invalidations++,
    );
    await prepareTransfer(controller);
    await controller.confirm();
    expect(controller.capability, isNull);
    expect(controller.receipt, isNull);
    expect(invalidations, 1);
    controller.dispose();

    var current = true;
    final changes = ChangeNotifier();
    final pending = Completer<Map<String, Object?>>();
    final delayedAdapter = f.BoundaryAdapter((request) async {
      if (request.method == 'POST') return pending.future;
      return successFor(request);
    });
    final delayed = NajmBaharTransferController(
      f.repository(delayedAdapter, current: () => current),
      sessionChanges: changes,
    );
    await prepareTransfer(delayed);
    final task = delayed.confirm();
    await Future<void>.delayed(Duration.zero);
    current = false;
    changes.notifyListeners();
    pending.complete(f.envelope(p.mutationReceipt()));
    await task;

    expect(delayed.receipt, isNull);
    expect(delayed.capability, isNull);
    delayed.dispose();
    changes.dispose();
  });
}
