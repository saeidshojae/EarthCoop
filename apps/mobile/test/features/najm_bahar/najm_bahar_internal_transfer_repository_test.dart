import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_internal_transfer_dto.dart';
import 'najm_bahar_repository_test.dart' as f;

Map<String, Object?> mainAccount() => {
      'account_id': 7,
      'account_number': '1000000007',
      'name': 'حساب اصلی',
      'active_gol': 800,
      'active_available_gol': 500,
      'dim_available_gol': 900,
      'dim_committed_gol': 100,
    };

Map<String, Object?> subAccount({
  int subId = 11,
  int accountId = 22,
  String number = '1000000007-001',
  String name = 'روزمره',
  int active = 300,
  int activeAvailable = 250,
  int dim = 400,
}) =>
    {
      'sub_account_id': subId,
      'account_id': accountId,
      'account_number': number,
      'name': name,
      'status': 1,
      'active_gol': active,
      'active_available_gol': activeAvailable,
      'dim_available_gol': dim,
    };

Map<String, Object?> snapshot() => {
      'internal_transfer_contract_version': 1,
      'main': mainAccount(),
      'subaccounts': [
        subAccount(),
        subAccount(
          subId: 12,
          accountId: 23,
          number: '1000000007-002',
          name: 'پس‌انداز',
          active: 0,
          activeAvailable: 0,
          dim: 50,
        ),
      ],
    };

Map<String, Object?> transaction({
  int amount = 125,
  String bucket = 'dim',
}) =>
    {
      'id': 201,
      'tracking_number': 'T-201',
      'type': 'immediate',
      'status': 'completed',
      'amount_gol': amount,
      'balance_bucket': bucket,
      'direction': 'internal',
      'counterparty': null,
      'description': 'جابجایی داخلی',
      'created_at': '2026-10-07T12:00:00Z',
    };

Map<String, Object?> receipt() => {
      'transaction': transaction(),
      'source': subAccount(dim: 275),
      'destination': subAccount(
        subId: 12,
        accountId: 23,
        number: '1000000007-002',
        name: 'پس‌انداز',
        active: 0,
        activeAvailable: 0,
        dim: 175,
      ),
    };

NajmBaharInternalTransferIntent intent() {
  final model = NajmBaharSubAccountSnapshot.fromJson(snapshot());
  return NajmBaharInternalTransferIntent(
    contractVersion: model.contractVersion,
    direction: 'sub_to_sub',
    source: model.subaccounts.first,
    destination: model.subaccounts.last,
    balanceBucket: 'dim',
    amountGol: 125,
    description: '  جابجایی داخلی  ',
    key: 'internal-wire-0001',
  );
}

void main() {
  test('subaccount snapshot preserves exact spendable integer contract', () {
    final value = NajmBaharSubAccountSnapshot.fromJson(snapshot());

    expect(value.contractVersion, 1);
    expect(value.main.accountId, 7);
    expect(value.main.activeAvailableGol, 500);
    expect(value.main.dimCommittedGol, 100);
    expect(value.subaccounts, hasLength(2));
    expect(value.subaccounts.first.subAccountId, 11);
    expect(value.subaccounts.first.activeAvailableGol, 250);
    expect(value.subaccounts.first.dimAvailableGol, 400);
    expect(() => value.subaccounts.clear(), throwsUnsupportedError);
  });

  test('subaccount DTO rejects fractional, unsupported or inactive values', () {
    for (final bad in [
      {...subAccount(), 'active_available_gol': 2.5},
      {...subAccount(), 'sub_account_id': 0},
      {...subAccount(), 'status': 0},
      {...subAccount(), 'account_number': ''},
    ]) {
      expect(() => NajmBaharSubAccount.fromJson(bad), throwsFormatException);
    }

    expect(
      () => NajmBaharSubAccountSnapshot.fromJson({
        ...snapshot(),
        'internal_transfer_contract_version': 2,
      }),
      throwsFormatException,
    );
  });

  test('internal intent freezes exact direction source destination bucket terms',
      () {
    final value = intent();
    expect(value.description, 'جابجایی داخلی');
    expect(value.toJson(), {
      'direction': 'sub_to_sub',
      'source_sub_account_id': 11,
      'destination_sub_account_id': 12,
      'amount_gol': 125,
      'balance_bucket': 'dim',
      'description': 'جابجایی داخلی',
      'expected': {
        'internal_transfer_contract_version': 1,
        'source_account_number': '1000000007-001',
        'source_available_gol': 400,
        'destination_account_number': '1000000007-002',
      },
    });
  });

  test('repository list create and rename use exact non-financial endpoints',
      () async {
    final adapter = f.BoundaryAdapter((request) {
      if (request.method == 'GET') return f.envelope(snapshot());
      return f.envelope(
        subAccount(name: request.data?['name'] as String? ?? 'روزمره'),
      );
    });
    final repository = f.repository(adapter);

    final listed = await repository.subAccounts();
    final created =
        await repository.createSubAccount('روزانه', 'create-sub-0001');
    final renamed =
        await repository.renameSubAccount(11, 'روزمره نو', 'rename-sub-0001');

    expect(listed.subaccounts, hasLength(2));
    expect(created.name, 'روزانه');
    expect(renamed.name, 'روزمره نو');
    expect(adapter.requests.map((r) => r.method), ['GET', 'POST', 'PATCH']);
    expect(adapter.requests[0].path, '/najm-bahar/subaccounts');
    expect(adapter.requests[1].path, '/najm-bahar/subaccounts');
    expect(adapter.requests[1].headers['Idempotency-Key'], 'create-sub-0001');
    expect(adapter.requests[1].data, {'name': 'روزانه'});
    expect(adapter.requests[2].path, '/najm-bahar/subaccounts/11');
    expect(adapter.requests[2].headers['Idempotency-Key'], 'rename-sub-0001');
    expect(adapter.requests[2].data, {'name': 'روزمره نو'});
  });

  test('financial internal transfer sends exact body once with retry disabled',
      () async {
    final value = intent();
    final adapter = f.BoundaryAdapter((_) => f.envelope(receipt()));

    final result = await f.repository(adapter).sendInternalTransfer(value);

    expect(result.matches(value), true);
    expect(adapter.requests, hasLength(1));
    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/najm-bahar/internal-transfers');
    expect(adapter.requests.single.headers['Idempotency-Key'], value.key);
    expect(adapter.requests.single.data, value.toJson());
  });

  test('financial timeout never automatically repeats internal transfer POST',
      () async {
    final adapter = f.BoundaryAdapter((request) => throw DioException(
          requestOptions: request,
          type: DioExceptionType.receiveTimeout,
        ));

    await expectLater(
      f.repository(adapter).sendInternalTransfer(intent()),
      throwsA(isA<ApiFailure>()),
    );
    expect(adapter.requests, hasLength(1));
  });

  test('mismatched successful internal receipt is malformed', () async {
    final adapter = f.BoundaryAdapter((_) => f.envelope({
          ...receipt(),
          'transaction': transaction(amount: 126),
        }));

    await expectLater(
      f.repository(adapter).sendInternalTransfer(intent()),
      throwsA(
        isA<ApiFailure>().having((e) => e.code, 'code', 'malformed_response'),
      ),
    );
    expect(adapter.requests, hasLength(1));
  });

  test('internal reconciliation is GET-only and must match frozen intent',
      () async {
    final value = intent();
    final adapter = f.BoundaryAdapter((_) => f.envelope(receipt()));

    final result = await f.repository(adapter).reconcileInternalTransfer(value);

    expect(result.matches(value), true);
    expect(adapter.requests.single.method, 'GET');
    expect(
      adapter.requests.single.path,
      '/najm-bahar/internal-transfers/by-idempotency/${value.key}',
    );
  });

  test('bootstrap and changed session send no internal financial mutation',
      () async {
    final adapter = f.BoundaryAdapter((_) => f.envelope(snapshot()));

    await expectLater(
      f.repository(adapter, allowed: () => false).subAccounts(),
      throwsA(
        isA<ApiFailure>()
            .having((e) => e.code, 'code', 'bootstrap_unavailable'),
      ),
    );
    await expectLater(
      f.repository(adapter, current: () => false).sendInternalTransfer(intent()),
      throwsA(
        isA<ApiFailure>().having((e) => e.code, 'code', 'session_changed'),
      ),
    );
    expect(adapter.requests, isEmpty);
  });
}
