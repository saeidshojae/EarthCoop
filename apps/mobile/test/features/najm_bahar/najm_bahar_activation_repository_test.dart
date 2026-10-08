import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_activation_dto.dart';

import 'najm_bahar_activation_controller_test.dart' as fixture;
import 'najm_bahar_repository_test.dart' as f;

NajmBaharActivationIntent intent() => NajmBaharActivationIntent(
      terms: NajmBaharActivationTerms.fromJson(fixture.terms()),
      points: 200,
      key: 'activation-repository-key-0001',
    );

void main() {
  test('eligibility is GET-only with strict snapshot decoding', () async {
    final adapter = f.BoundaryAdapter((_) => f.envelope(fixture.terms()));
    final terms = await f.repository(adapter).activationTerms();
    expect(terms.maxActivationPoints, 300);
    expect(adapter.requests.map((r) => r.method), ['GET']);
    expect(adapter.requests.single.path,
        '/najm-bahar/activation/eligibility');
  });

  test('activation POST carries exact terms, key and does not retry', () async {
    final value = intent();
    final adapter = f.BoundaryAdapter((_) => f.envelope(fixture.receipt()));
    final result = await f.repository(adapter).activateParticipation(value);
    expect(result.matches(value), isTrue);
    expect(adapter.requests.length, 1);
    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/najm-bahar/activation');
    expect(adapter.requests.single.headers['Idempotency-Key'], value.key);
    expect(adapter.requests.single.data, value.toJson());
  });

  test('timeout never automatically replays financial POST', () async {
    final adapter = f.BoundaryAdapter((request) => throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        ));
    await expectLater(
      f.repository(adapter).activateParticipation(intent()),
      throwsA(isA<ApiFailure>()),
    );
    expect(adapter.requests.length, 1);
  });

  test('mismatched successful receipt is rejected as malformed', () async {
    final adapter = f.BoundaryAdapter((_) => f.envelope({
          ...fixture.receipt(),
          'activated_gol': 3,
        }));
    await expectLater(
      f.repository(adapter).activateParticipation(intent()),
      throwsA(isA<ApiFailure>()
          .having((e) => e.code, 'code', 'malformed_response')),
    );
    expect(adapter.requests.length, 1);
  });

  test('reconciliation uses GET only and rejects mismatched receipt',
      () async {
    final value = intent();
    final adapter = f.BoundaryAdapter((_) => f.envelope(fixture.receipt()));
    final result = await f.repository(adapter).reconcileActivation(value);
    expect(result.matches(value), isTrue);
    expect(adapter.requests.length, 1);
    expect(adapter.requests.single.method, 'GET');
    expect(
      adapter.requests.single.path,
      '/najm-bahar/activation/by-idempotency/${value.key}',
    );
  });

  test('session change prevents accepting an otherwise valid receipt',
      () async {
    var current = true;
    final adapter = f.BoundaryAdapter((_) {
      current = false;
      return f.envelope(fixture.receipt());
    });
    await expectLater(
      f.repository(adapter, current: () => current)
          .activateParticipation(intent()),
      throwsA(isA<ApiFailure>()
          .having((e) => e.code, 'code', 'session_changed')),
    );
    expect(adapter.requests.length, 1);
  });
}
