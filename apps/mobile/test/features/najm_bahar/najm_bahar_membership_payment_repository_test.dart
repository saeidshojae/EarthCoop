import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_policy_dto.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_membership_payment_dto.dart';
import 'najm_bahar_repository_test.dart' as f;
import 'najm_bahar_policy_test.dart' as p;

Map<String, Object?> source({int? subId}) => {
      'kind': subId == null ? 'main' : 'subaccount',
      'sub_account_id': subId,
      'account_number': subId == null ? 'NB-7' : 'NB-7-001',
      'name': 'حساب پرداخت',
      'active_available_gol': 2000,
      'dim_available_gol': subId == null ? 3000 : 0,
      'can_pay_active': true,
      'can_pay_dim': subId == null,
    };
Map<String, Object?> fee() => {
      ...p.fee(),
      'has_paid': false,
      'policy_version_id': 901,
      'payment_contract_version': 1,
      'payment_sources': [source(), source(subId: 11)],
    };
Map<String, Object?> receipt(
        {String bucket = 'dim', String account = 'NB-7'}) =>
    {
      'has_paid': true,
      'payment_year': 2025,
      'fee_gol': 1200,
      'breakdown': p.fee()['breakdown'],
      'payment_source': bucket,
      'payment_account_number': account,
    };
MembershipPaymentIntent intent({String bucket = 'dim', int? subId}) {
  final terms = NajmBaharMembershipFee.fromJson(fee());
  return MembershipPaymentIntent(
      terms: terms,
      source: MembershipSource.fromJson(source(subId: subId)),
      bucket: bucket,
      key: 'intent-key-0001');
}

void main() {
  test('old membership response remains read only', () {
    final old = NajmBaharMembershipFee.fromJson(p.fee());
    expect(old.paymentContractVersion, isNull);
    expect(old.paymentSources, isEmpty);
  });
  test('new sources preserve integers and immutable ownership selectors', () {
    final m = NajmBaharMembershipFee.fromJson(fee());
    expect(m.paymentContractVersion, 1);
    expect(m.policyVersionId, 901);
    expect(m.paymentSources.last.subAccountId, 11);
    expect(m.paymentSources.last.canPayDim, false);
    expect(() => m.paymentSources.clear(), throwsUnsupportedError);
  });
  test('malformed sources and inconsistent selected bucket reject', () {
    for (final bad in [
      {...source(), 'sub_account_id': 4},
      {...source(subId: 11), 'can_pay_dim': true},
      {...source(), 'active_available_gol': 2.0},
      {...source(), 'account_number': ''},
    ]) {
      expect(() => MembershipSource.fromJson(bad), throwsFormatException);
    }
    expect(() => intent(bucket: 'dim', subId: 11), throwsArgumentError);
  });
  for (final bucket in ['dim', 'active']) {
    test('$bucket sends immutable exact consent and one request', () async {
      final i = intent(bucket: bucket);
      final a = f.BoundaryAdapter((_) => f.envelope(receipt(bucket: bucket)));
      final result = await f.repository(a).payMembership(i);
      expect(result.hasPaid, true);
      expect(result.paymentAccountNumber, 'NB-7');
      expect(a.requests.single.path, '/najm-bahar/membership-fee/pay');
      expect(a.requests.single.method, 'POST');
      expect(a.requests.single.headers['Idempotency-Key'], i.key);
      expect(a.requests.single.data, i.toJson());
      expect((a.requests.single.data as Map)['expected'], {
        'payment_year': 2025,
        'fee_gol': 1200,
        'breakdown': p.fee()['breakdown'],
        'policy_version_id': 901,
        'account_number': 'NB-7'
      });
    });
  }
  test('financial timeout never automatically repeats POST', () async {
    final a = f.BoundaryAdapter((r) => throw DioException(
        requestOptions: r, type: DioExceptionType.receiveTimeout));
    await expectLater(
        f.repository(a).payMembership(intent()), throwsA(isA<ApiFailure>()));
    expect(a.requests.length, 1);
  });
  test('financial 503 never automatically repeats POST', () async {
    final a = f.BoundaryAdapter(
        (_) => {
              'status': 'error',
              'data': null,
              'meta': {'api_version': 'v1'},
              'request_id': 'failed',
              'error': {
                'code': 'temporarily_unavailable',
                'message': '',
                'retryable': true
              }
            },
        statusFor: (_) => 503);
    await expectLater(
        f.repository(a).payMembership(intent()), throwsA(isA<ApiFailure>()));
    expect(a.requests.length, 1);
  });
  test('malformed success is rejected instead of proving payment', () async {
    final a =
        f.BoundaryAdapter((_) => f.envelope({...receipt(), 'has_paid': false}));
    await expectLater(
        f.repository(a).payMembership(intent()), throwsA(isA<ApiFailure>()));
    expect(a.requests.length, 1);
  });
  test('bootstrap blockage and changed session send no payment', () async {
    final a = f.BoundaryAdapter((_) => f.envelope(receipt()));
    await expectLater(
        f.repository(a, allowed: () => false).payMembership(intent()),
        throwsA(isA<ApiFailure>()));
    await expectLater(
        f.repository(a, current: () => false).payMembership(intent()),
        throwsA(isA<ApiFailure>()));
    expect(a.requests, isEmpty);
  });
}
