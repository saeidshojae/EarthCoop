import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_activation_controller.dart';

import 'najm_bahar_activation_repository_test.dart' as p;
import 'najm_bahar_repository_test.dart' as f;

Map<String, Object?> error(String code, {bool retryable = false}) => {
      'status': 'error',
      'data': null,
      'meta': {'api_version': 'v1'},
      'request_id': 'activation-controller-test',
      'error': {'code': code, 'message': '', 'retryable': retryable},
    };

Map<String, Object?> successFor(RequestOptions request) {
  if (request.path.endsWith('/eligibility')) {
    return f.envelope(p.eligibility());
  }
  return f.envelope(p.receipt());
}

Future<void> waitForPost(f.BoundaryAdapter adapter) async {
  for (var i = 0; i < 50; i++) {
    if (adapter.requests.any((request) => request.method == 'POST')) return;
    await Future<void>.delayed(const Duration(milliseconds: 2));
  }
  fail('Activation POST did not start within the bounded test window.');
}

Future<void> prepareReview(
  NajmBaharActivationController controller, {
  int points = 200,
}) async {
  await controller.prepare();
  controller.beginReview(points);
}

void main() {
  test('prepare and review are GET-only and invalid multiple cannot review',
      () async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      keyFactory: () => 'activation-controller-0001',
    );
    addTearDown(controller.dispose);

    await controller.prepare();
    controller.beginReview(250);
    expect(controller.state, NajmBaharActivationState.ready);
    expect(controller.reviewPoints, isNull);

    controller.beginReview(200);
    expect(controller.state, NajmBaharActivationState.reviewing);
    expect(controller.reviewPoints, 200);
    expect(controller.reviewActivatedGol, 2);
    expect(adapter.requests.map((r) => r.method), ['GET']);
  });

  test('repeated confirm shares one pending POST and success stays confirmed',
      () async {
    final pending = Completer<Map<String, Object?>>();
    final adapter = f.BoundaryAdapter((request) async {
      if (request.method == 'POST') return pending.future;
      return successFor(request);
    });
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      keyFactory: () => 'activation-controller-0002',
    );
    addTearDown(controller.dispose);

    await prepareReview(controller);
    final first = controller.confirm();
    final second = controller.confirm();
    await waitForPost(adapter);

    expect(controller.state, NajmBaharActivationState.submitting);
    expect(adapter.requests.where((r) => r.method == 'POST'), hasLength(1));

    pending.complete(f.envelope(p.receipt()));
    await Future.wait([first, second]);

    expect(controller.state, NajmBaharActivationState.confirmed);
    expect(controller.receipt?.transaction.trackingNumber, 'T-91');
  });

  test('unknown result freezes intent and retries exact same body and key',
      () async {
    var failPost = true;
    final adapter = f.BoundaryAdapter((request) {
      if (request.method != 'POST') return successFor(request);
      if (failPost) {
        throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        );
      }
      return f.envelope(p.receipt());
    });
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      keyFactory: () => 'stable-activation-key',
    );
    addTearDown(controller.dispose);

    await prepareReview(controller);
    await controller.confirm();
    expect(controller.state, NajmBaharActivationState.outcomeUnknown);
    expect(controller.hasFrozenIntent, true);

    controller.beginReview(100);
    expect(controller.reviewPoints, 200);

    failPost = false;
    await controller.retrySameIntent();

    final posts = adapter.requests.where((r) => r.method == 'POST').toList();
    expect(posts, hasLength(2));
    expect(
      posts[1].headers['Idempotency-Key'],
      posts[0].headers['Idempotency-Key'],
    );
    expect(posts[1].data, posts[0].data);
    expect(controller.state, NajmBaharActivationState.confirmed);
  });

  test('GET-only reconciliation resolves matching ambiguous activation',
      () async {
    final adapter = f.BoundaryAdapter((request) {
      if (request.method == 'POST') {
        throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        );
      }
      return successFor(request);
    });
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      keyFactory: () => 'reconcile-activation-key',
    );
    addTearDown(controller.dispose);

    await prepareReview(controller);
    await controller.confirm();
    expect(controller.state, NajmBaharActivationState.outcomeUnknown);

    await controller.reconcile();

    expect(controller.state, NajmBaharActivationState.confirmed);
    expect(adapter.requests.where((r) => r.method == 'POST'), hasLength(1));
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
    final controller = NajmBaharActivationController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareReview(controller);
    await controller.confirm();
    await controller.reconcile();

    expect(controller.state, NajmBaharActivationState.outcomeUnknown);
    expect(adapter.requests.where((r) => r.method == 'POST'), hasLength(1));
  });

  test('monotonic 23 hour expiry prevents activation replay', () async {
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
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      elapsedSinceStart: () => elapsed,
    );
    addTearDown(controller.dispose);

    await prepareReview(controller);
    await controller.confirm();
    elapsed = const Duration(hours: 23);
    await controller.retrySameIntent();

    expect(adapter.requests.where((r) => r.method == 'POST'), hasLength(1));
    expect(controller.failure?.code, 'activation_intent_expired');
    expect(controller.state, NajmBaharActivationState.outcomeUnknown);
  });

  test('bootstrap pause before submit sends no POST and is recoverable',
      () async {
    var allowed = true;
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharActivationController(
      f.repository(adapter, allowed: () => allowed),
    );
    addTearDown(controller.dispose);

    await prepareReview(controller);
    allowed = false;
    await controller.confirm();

    expect(adapter.requests.where((r) => r.method == 'POST'), isEmpty);
    expect(controller.hasFrozenIntent, false);

    allowed = true;
    await controller.prepare();
    expect(controller.state, NajmBaharActivationState.ready);
  });

  test('definite rejection clears frozen intent and can reload eligibility',
      () async {
    var reject = true;
    final adapter = f.BoundaryAdapter(
      (request) {
        if (request.method == 'POST' && reject) {
          return error('activation_terms_changed');
        }
        return successFor(request);
      },
      statusFor: (request) => request.method == 'POST' && reject ? 409 : 200,
    );
    final controller = NajmBaharActivationController(f.repository(adapter));
    addTearDown(controller.dispose);

    await prepareReview(controller);
    await controller.confirm();

    expect(controller.state, NajmBaharActivationState.definiteRejected);
    expect(controller.hasFrozenIntent, false);

    reject = false;
    await controller.prepare();
    expect(controller.state, NajmBaharActivationState.ready);
  });

  test('known success survives display refresh failure', () async {
    final adapter = f.BoundaryAdapter(successFor);
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      refreshFinancialViews: () async => throw StateError('refresh failed'),
    );
    addTearDown(controller.dispose);

    await prepareReview(controller);
    await controller.confirm();

    expect(controller.state, NajmBaharActivationState.confirmed);
    expect(controller.receipt?.activatedGol, 2);
  });

  test('restart loads eligibility and never replays mutation', () async {
    final adapter = f.BoundaryAdapter(successFor);
    final first = NajmBaharActivationController(f.repository(adapter));
    await first.prepare();
    first.dispose();

    final restarted = NajmBaharActivationController(f.repository(adapter));
    addTearDown(restarted.dispose);
    await restarted.prepare();

    expect(adapter.requests.every((r) => r.method == 'GET'), true);
  });

  test('401 and logout suppress delayed financial publication', () async {
    var invalidations = 0;
    final unauthorized = f.BoundaryAdapter(
      (request) => request.method == 'POST'
          ? error('unauthenticated')
          : successFor(request),
      statusFor: (request) => request.method == 'POST' ? 401 : 200,
    );
    final controller = NajmBaharActivationController(
      f.repository(unauthorized),
      onSessionInvalidated: () => invalidations++,
    );
    await prepareReview(controller);
    await controller.confirm();

    expect(controller.eligibility, isNull);
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
    final delayed = NajmBaharActivationController(
      f.repository(delayedAdapter, current: () => current),
      sessionChanges: changes,
    );
    await prepareReview(delayed);
    final task = delayed.confirm();
    await Future<void>.delayed(Duration.zero);
    current = false;
    changes.notifyListeners();
    pending.complete(f.envelope(p.receipt()));
    await task;

    expect(delayed.receipt, isNull);
    expect(delayed.eligibility, isNull);
    delayed.dispose();
    changes.dispose();
  });
}
