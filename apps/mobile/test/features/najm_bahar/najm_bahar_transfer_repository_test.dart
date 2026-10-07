import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_transfer_dto.dart';
import 'najm_bahar_repository_test.dart' as f;

Map<String, Object?> transferSource() => {
      'account_id': 31,
      'sub_account_id': 7,
      'account_number': '1000000007-001',
      'name': 'حساب روزمره',
      'kind': 'subaccount',
      'status': 1,
      'active_available_gol': 1200,
      'can_transfer_active': true,
    };

Map<String, Object?> capability({bool enabled = true}) => {
      'transfer_contract_version': 1,
      'external_transfer_enabled': enabled,
      'disabled_reason': enabled ? null : 'threshold_not_met',
      'sources': [transferSource()],
    };

Map<String, Object?> destination() => {
      'account_number': '1000000011-002',
      'name': 'پس‌انداز',
      'owner_type': 'user',
      'owner_display_name': 'عضو مقصد',
      'kind': 'subaccount',
      'status': 1,
      'destination_token': 'opaque-destination-token',
    };

Map<String, Object?> transferTransaction({
  String account = '1000000011-002',
  int amount = 250,
}) =>
    {
      'id': 81,
      'tracking_number': 'T-81',
      'type': 'immediate',
      'status': 'completed',
      'amount_gol': amount,
      'balance_bucket': 'active',
      'direction': 'outgoing',
      'counterparty': {
        'account_number': account,
        'name': 'پس‌انداز',
        'type': 'subaccount',
      },
      'description': 'کمک',
      'created_at': '2026-10-07T03:00:00Z',
    };

Map<String, Object?> mutationReceipt() => {
      'transaction': transferTransaction(),
      'source_balance': {
        'local': f.balance(),
        'aggregate': f.balance(),
      },
    };

NajmBaharTransferIntent intent() => NajmBaharTransferIntent(
      contractVersion: 1,
      source: NajmBaharTransferSource.fromJson(transferSource()),
      destination: NajmBaharTransferDestination.fromJson(destination()),
      amountGol: 250,
      description: '  کمک  ',
      key: 'native-transfer-intent-0001',
    );

void main() {
  test('capability preserves exact reservation-aware source contract', () {
    final value = NajmBaharTransferCapability.fromJson(capability());
    expect(value.contractVersion, 1);
    expect(value.externalTransferEnabled, true);
    expect(value.disabledReason, isNull);
    expect(value.sources.single.accountId, 31);
    expect(value.sources.single.activeAvailableGol, 1200);
    expect(value.sources.single.canTransferActive, true);
    expect(() => value.sources.clear(), throwsUnsupportedError);
  });

  test('capability and source reject malformed financial values', () {
    for (final bad in [
      {...transferSource(), 'active_available_gol': 12.5},
      {...transferSource(), 'account_id': 0},
      {...transferSource(), 'kind': 'main'},
      {...transferSource(), 'account_number': ''},
    ]) {
      expect(
          () => NajmBaharTransferSource.fromJson(bad), throwsFormatException);
    }

    expect(
        () => NajmBaharTransferCapability.fromJson({
              ...capability(),
              'transfer_contract_version': 2,
            }),
        throwsFormatException);
  });

  test('destination preview is exact and privacy-bounded', () {
    final value = NajmBaharTransferDestination.fromJson(destination());
    expect(value.accountNumber, '1000000011-002');
    expect(value.ownerType, 'user');
    expect(value.ownerDisplayName, 'عضو مقصد');
    expect(value.token, 'opaque-destination-token');
  });

  test('intent freezes Active-only exact consent payload', () {
    final value = intent();
    expect(value.description, 'کمک');
    expect(value.toJson(), {
      'source_account_id': 31,
      'destination_account_number': '1000000011-002',
      'amount_gol': 250,
      'balance_bucket': 'active',
      'description': 'کمک',
      'expected': {
        'transfer_contract_version': 1,
        'source_account_number': '1000000007-001',
        'source_active_available_gol': 1200,
        'destination_token': 'opaque-destination-token',
      },
    });

    expect(
        () => NajmBaharTransferIntent(
              contractVersion: 1,
              source: NajmBaharTransferSource.fromJson(transferSource()),
              destination: NajmBaharTransferDestination.fromJson(destination()),
              amountGol: 1201,
              description: null,
              key: 'too-large-0001',
            ),
        throwsArgumentError);
  });

  test('repository reads capability and exact destination with GET only',
      () async {
    final adapter = f.BoundaryAdapter((request) {
      if (request.path.endsWith('/capability')) {
        return f.envelope(capability());
      }
      return f.envelope(destination());
    });
    final repository = f.repository(adapter);

    final cap = await repository.transferCapability();
    final dest = await repository.transferDestination('1000000011/002');

    expect(cap.externalTransferEnabled, true);
    expect(dest.accountNumber, '1000000011-002');
    expect(adapter.requests.map((r) => r.method), ['GET', 'GET']);
    expect(adapter.requests.first.path, '/najm-bahar/transfers/capability');
    expect(adapter.requests.last.path, '/najm-bahar/transfers/destination');
    expect(adapter.requests.last.queryParameters,
        {'account_number': '1000000011/002'});
  });

  test('financial transfer sends exact body once with automatic retry disabled',
      () async {
    final value = intent();
    final adapter = f.BoundaryAdapter((_) => f.envelope(mutationReceipt()));
    final receipt = await f.repository(adapter).sendTransfer(value);

    expect(receipt.matches(value), true);
    expect(adapter.requests.length, 1);
    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/najm-bahar/transfers');
    expect(adapter.requests.single.headers['Idempotency-Key'], value.key);
    expect(adapter.requests.single.data, value.toJson());
  });

  test('financial timeout never automatically repeats transfer POST', () async {
    final adapter = f.BoundaryAdapter((request) => throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        ));

    await expectLater(f.repository(adapter).sendTransfer(intent()),
        throwsA(isA<ApiFailure>()));
    expect(adapter.requests.length, 1);
  });

  test('mismatched successful receipt is malformed instead of proof', () async {
    final adapter = f.BoundaryAdapter((_) => f.envelope({
          ...mutationReceipt(),
          'transaction':
              transferTransaction(account: '1000000099-009', amount: 250),
        }));

    await expectLater(
        f.repository(adapter).sendTransfer(intent()),
        throwsA(isA<ApiFailure>()
            .having((e) => e.code, 'code', 'malformed_response')));
    expect(adapter.requests.length, 1);
  });

  test('reconciliation is GET-only and must match frozen intent', () async {
    final value = intent();
    final adapter = f.BoundaryAdapter(
        (_) => f.envelope({'transaction': transferTransaction()}));
    final receipt = await f.repository(adapter).reconcileTransfer(value);

    expect(receipt.matches(value), true);
    expect(adapter.requests.single.method, 'GET');
    expect(adapter.requests.single.path,
        '/najm-bahar/transfers/by-idempotency/${value.key}');
  });

  test('bootstrap and changed session send no transfer requests', () async {
    final blocked = f.BoundaryAdapter((_) => f.envelope(capability()));
    await expectLater(
        f.repository(blocked, allowed: () => false).transferCapability(),
        throwsA(isA<ApiFailure>()
            .having((e) => e.code, 'code', 'bootstrap_unavailable')));
    await expectLater(
        f.repository(blocked, current: () => false).sendTransfer(intent()),
        throwsA(isA<ApiFailure>()
            .having((e) => e.code, 'code', 'session_changed')));
    expect(blocked.requests, isEmpty);
  });
}
