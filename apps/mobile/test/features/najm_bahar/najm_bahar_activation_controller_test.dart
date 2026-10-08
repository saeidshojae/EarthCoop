import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_activation_controller.dart';
import 'najm_bahar_repository_test.dart' as f;

Map<String, Object?> terms() => {
      'activation_contract_version': 1,
      'enabled': true,
      'source': 'participation',
      'remaining_convertible_points': 350,
      'conversion_ratio_points_per_gol': 100,
      'max_convertible_points': 300,
      'max_activation_points': 300,
      'max_activation_gol': 3,
      'dim_available_gol': 10,
      'active_gol': 5,
      'policy_version_id': 12,
      'policy_version': 1,
      'policy_source': 'versioned_policy',
    };

Map<String, Object?> receipt() => {
      'source': 'participation',
      'requested_points': 200,
      'consumed_points': 200,
      'activated_gol': 2,
      'transaction': {
        'id': 9,
        'tracking_number': 'T-9',
        'type': 'adjustment',
        'status': 'completed',
        'amount_gol': 2,
        'balance_bucket': 'active',
        'direction': 'incoming',
        'counterparty': null,
        'description': null,
        'created_at': null,
      },
    };

void main() {
  test('preview and review never submit financial mutation', () async {
    final adapter = f.BoundaryAdapter((r) => f.envelope(terms()));
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      keyFactory: () => 'activation-controller-key1',
    );
    addTearDown(controller.dispose);
    await controller.prepare();
    controller.beginReview(200);
    expect(controller.state, NajmBaharActivationState.reviewing);
    expect(adapter.requests.map((r) => r.method), ['GET']);
  });

  test('confirm is single-flight and validates successful receipt', () async {
    final adapter = f.BoundaryAdapter(
      (r) => f.envelope(r.method == 'POST' ? receipt() : terms()),
    );
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      keyFactory: () => 'activation-controller-key2',
    );
    addTearDown(controller.dispose);
    await controller.prepare();
    controller.beginReview(200);
    await Future.wait([controller.confirm(), controller.confirm()]);
    expect(controller.state, NajmBaharActivationState.confirmed);
    expect(adapter.requests.where((r) => r.method == 'POST').length, 1);
  });

  test('ambiguous POST reconciles using GET without repeating mutation',
      () async {
    final adapter = f.BoundaryAdapter((r) {
      if (r.method == 'POST') {
        throw DioException(
          requestOptions: r,
          type: DioExceptionType.receiveTimeout,
        );
      }
      return f.envelope(
        r.path.contains('by-idempotency') ? receipt() : terms(),
      );
    });
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      keyFactory: () => 'activation-controller-key3',
    );
    addTearDown(controller.dispose);
    await controller.prepare();
    controller.beginReview(200);
    await controller.confirm();
    expect(controller.state, NajmBaharActivationState.outcomeUnknown);
    await controller.reconcile();
    expect(controller.state, NajmBaharActivationState.confirmed);
    expect(adapter.requests.where((r) => r.method == 'POST').length, 1);
  });
  test('23-hour expiry blocks replay of an unknown financial intent', () async {
    var elapsed = Duration.zero;
    final adapter = f.BoundaryAdapter((request) {
      if (request.method == 'POST') {
        throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        );
      }
      return f.envelope(terms());
    });
    final controller = NajmBaharActivationController(
      f.repository(adapter),
      elapsedSinceStart: () => elapsed,
      keyFactory: () => 'activation-expiry-key',
    );
    addTearDown(controller.dispose);
    await controller.prepare();
    controller.beginReview(200);
    await controller.confirm();
    expect(controller.state, NajmBaharActivationState.outcomeUnknown);
    elapsed = const Duration(hours: 23);
    await controller.retrySameIntent();
    expect(adapter.requests.where((r) => r.method == 'POST').length, 1);
    expect(controller.failure?.code, 'activation_intent_expired');
  });

  test('session change prevents stale financial result being published',
      () async {
    var current = true;
    final sessionChanges = ChangeNotifier();
    final pending = Completer<Map<String, Object?>>();
    final adapter = f.BoundaryAdapter((request) {
      if (request.method == 'POST') return pending.future;
      return f.envelope(terms());
    });
    final controller = NajmBaharActivationController(
      f.repository(adapter, current: () => current),
      sessionChanges: sessionChanges,
      keyFactory: () => 'activation-session-key',
    );
    addTearDown(controller.dispose);
    addTearDown(sessionChanges.dispose);
    await controller.prepare();
    controller.beginReview(200);
    final sending = controller.confirm();
    current = false;
    sessionChanges.notifyListeners();
    pending.complete(f.envelope(receipt()));
    await sending;
    expect(controller.receipt, isNull);
    expect(controller.failure?.code, 'session_changed');
    expect(adapter.requests.where((r) => r.method == 'POST').length, 1);
  });
}
