import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_activation_dto.dart';
import 'najm_bahar_repository_test.dart' as f;

Map<String, Object?> eligibility() => {
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

Map<String, Object?> activationTransaction({int amount = 2}) => {
      'id': 91,
      'tracking_number': 'T-91',
      'type': 'adjustment',
      'status': 'completed',
      'amount_gol': amount,
      'balance_bucket': 'dim',
      'direction': 'internal',
      'counterparty': null,
      'description': 'تبدیل 200 امتیاز به پول فعال',
      'created_at': '2026-10-07T10:00:00Z',
    };

Map<String, Object?> receipt({int points = 200, int amount = 2}) => {
      'source': 'participation',
      'requested_points': points,
      'consumed_points': points,
      'activated_gol': amount,
      'transaction': activationTransaction(amount: amount),
      'balance': {
        'local': f.balance(),
        'aggregate': f.balance(),
      },
    };

NajmBaharActivationIntent intent() => NajmBaharActivationIntent(
      eligibility: NajmBaharActivationEligibilityV1.fromJson(eligibility()),
      points: 200,
      key: 'native-activation-intent-0001',
    );

void main() {
  test('eligibility v1 preserves exact activation consent terms', () {
    final value = NajmBaharActivationEligibilityV1.fromJson(eligibility());

    expect(value.contractVersion, 1);
    expect(value.enabled, true);
    expect(value.remainingPoints, 350);
    expect(value.pointsPerGol, 100);
    expect(value.maxConvertiblePoints, 300);
    expect(value.maxActivationPoints, 300);
    expect(value.maxActivationGol, 3);
    expect(value.dimAvailableGol, 10);
    expect(value.activeGol, 5);
    expect(value.policyVersionId, 12);
    expect(value.policyVersion, 1);
    expect(value.policySource, 'versioned_policy');
  });

  test('eligibility rejects inconsistent or fractional activation limits', () {
    for (final bad in [
      {...eligibility(), 'max_activation_points': 301},
      {...eligibility(), 'max_activation_gol': 4},
      {...eligibility(), 'conversion_ratio_points_per_gol': 0},
      {...eligibility(), 'remaining_convertible_points': 350.5},
      {...eligibility(), 'activation_contract_version': 2},
    ]) {
      expect(
        () => NajmBaharActivationEligibilityV1.fromJson(bad),
        throwsFormatException,
      );
    }
  });

  test('intent freezes exact native activation snapshot', () {
    final value = intent();
    expect(value.points, 200);
    expect(value.activatedGol, 2);
    expect(value.toJson(), {
      'source': 'participation',
      'points': 200,
      'expected': {
        'activation_contract_version': 1,
        'remaining_convertible_points': 350,
        'conversion_ratio_points_per_gol': 100,
        'max_convertible_points': 300,
        'max_activation_points': 300,
        'max_activation_gol': 3,
        'dim_available_gol': 10,
        'policy_version_id': 12,
        'policy_version': 1,
        'policy_source': 'versioned_policy',
      },
    });

    expect(
      () => NajmBaharActivationIntent(
        eligibility: NajmBaharActivationEligibilityV1.fromJson(eligibility()),
        points: 250,
        key: 'not-multiple-0001',
      ),
      throwsArgumentError,
    );
  });

  test('repository reads exact eligibility with GET only', () async {
    final adapter = f.BoundaryAdapter((_) => f.envelope(eligibility()));
    final value = await f.repository(adapter).activationEligibilityV1();

    expect(value.maxActivationPoints, 300);
    expect(adapter.requests.single.method, 'GET');
    expect(
      adapter.requests.single.path,
      '/najm-bahar/activation/eligibility',
    );
  });

  test('activation POST sends exact body once with automatic retry disabled',
      () async {
    final value = intent();
    final adapter = f.BoundaryAdapter((_) => f.envelope(receipt()));
    final result = await f.repository(adapter).activateParticipation(value);

    expect(result.matches(value), true);
    expect(adapter.requests, hasLength(1));
    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/najm-bahar/activation');
    expect(adapter.requests.single.headers['Idempotency-Key'], value.key);
    expect(adapter.requests.single.data, value.toJson());
  });

  test('activation timeout never automatically repeats financial POST',
      () async {
    final adapter = f.BoundaryAdapter((request) => throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        ));

    await expectLater(
      f.repository(adapter).activateParticipation(intent()),
      throwsA(isA<ApiFailure>()),
    );
    expect(adapter.requests, hasLength(1));
  });

  test('mismatched successful activation receipt is malformed', () async {
    final adapter = f.BoundaryAdapter((_) => f.envelope(receipt(amount: 3)));

    await expectLater(
      f.repository(adapter).activateParticipation(intent()),
      throwsA(
        isA<ApiFailure>().having((e) => e.code, 'code', 'malformed_response'),
      ),
    );
    expect(adapter.requests, hasLength(1));
  });

  test('activation reconciliation is GET-only and must match frozen intent',
      () async {
    final value = intent();
    final adapter = f.BoundaryAdapter((_) => f.envelope(receipt()));
    final result = await f.repository(adapter).reconcileActivation(value);

    expect(result.matches(value), true);
    expect(adapter.requests.single.method, 'GET');
    expect(
      adapter.requests.single.path,
      '/najm-bahar/activation/by-idempotency/${value.key}',
    );
  });

  test('bootstrap and changed session send no activation mutation', () async {
    final adapter = f.BoundaryAdapter((_) => f.envelope(receipt()));

    await expectLater(
      f.repository(adapter, allowed: () => false).activationEligibilityV1(),
      throwsA(
        isA<ApiFailure>()
            .having((e) => e.code, 'code', 'bootstrap_unavailable'),
      ),
    );
    await expectLater(
      f
          .repository(adapter, current: () => false)
          .activateParticipation(intent()),
      throwsA(
        isA<ApiFailure>().having((e) => e.code, 'code', 'session_changed'),
      ),
    );
    expect(adapter.requests, isEmpty);
  });
}
