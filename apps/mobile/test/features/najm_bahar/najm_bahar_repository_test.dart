import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_dto.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_repository.dart';

Map<String, Object?> balance() => {
      'active_gol': 1500000001,
      'dim_available_gol': 2000000003,
      'dim_committed_gol': 400000005,
      'dim_total_gol': 2400000008,
      'total_gol': 3900000009,
    };
Map<String, Object?> accountJson() => {
      'id': 7,
      'account_number': 'NB-7',
      'name': 'حساب من',
      'type': 'main',
      'status': 1,
      'balance': {
        'local': balance(),
        'aggregate': {...balance(), 'total_gol': 3900000309}
      },
    };
Map<String, Object?> transactionJson(int id) => {
      'id': id,
      'tracking_number': 'T-$id',
      'type': 'transfer',
      'status': 'completed',
      'amount_gol': 101,
      'balance_bucket': 'active',
      'direction': 'outgoing',
      'counterparty': {'account_number': 'NB-8', 'name': 'عضو', 'type': 'main'},
      'description': null,
      'created_at': '2026-10-05T10:00:00Z',
    };
Map<String, Object?> envelope(Object? data,
        {String? cursor, bool more = false}) =>
    {
      'status': 'success',
      'data': data,
      'error': null,
      'request_id': 'test-nb',
      'meta': {
        'api_version': 'v1',
        'pagination': {'next_cursor': cursor, 'has_more': more}
      },
    };

class BoundaryAdapter implements HttpClientAdapter {
  BoundaryAdapter(this.respond, {this.statusFor});
  final int Function(RequestOptions)? statusFor;
  final FutureOr<Map<String, Object?>> Function(RequestOptions) respond;
  final requests = <RequestOptions>[];
  int status = 200;
  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? stream,
      Future<void>? cancel) async {
    requests.add(options);
    final body = await respond(options);
    return ResponseBody.fromString(jsonEncode(body), statusFor?.call(options) ?? status, headers: {
      'content-type': ['application/json']
    });
  }

  @override
  void close({bool force = false}) {}
}

NajmBaharRepository repository(BoundaryAdapter adapter,
    {bool Function()? current}) {
  final dio = Dio(BaseOptions(baseUrl: 'https://example.test/api/v1'))
    ..httpClientAdapter = adapter;
  return NajmBaharRepository(
      apiClient: ApiClient(
          dio: dio,
          bearerTokenProvider: () async => 'token-a',
          deviceIdProvider: () async => 'device-a',
          requestIdFactory: () => 'request-a',
          retryDelay: (_) async {}),
      isCurrentSession: current ?? () => true);
}

void main() {
  test('formats exact Gol without monetary rounding', () {
    expect(formatGol(0), '0 گل');
    expect(formatGol(100), '1 بهار');
    expect(formatGol(101), '1 بهار و 1 گل');
    expect(formatGol(-101), '-1 بهار و 1 گل');
    expect(formatGol(3900000309), '39000003 بهار و 9 گل');
  });
  test('preserves distinct local and aggregate integer balances', () async {
    final adapter = BoundaryAdapter((_) => envelope(accountJson()));
    final data = await repository(adapter).account();
    expect(data.local.totalGol, 3900000009);
    expect(data.aggregate.totalGol, 3900000309);
    expect(data.local.dimAvailableGol, 2000000003);
    expect(data.local.dimCommittedGol, 400000005);
    expect(adapter.requests.single.path, '/najm-bahar/account');
    expect(adapter.requests.single.headers['Authorization'], 'Bearer token-a');
    expect(adapter.requests.single.headers['X-Device-ID'], 'device-a');
  });
  test('rejects fractional balance instead of fabricating zero', () async {
    final body = accountJson();
    body['balance'] = {
      'local': {...balance(), 'total_gol': 3.5},
      'aggregate': balance()
    };
    await expectLater(
        repository(BoundaryAdapter((_) => envelope(body))).account(),
        throwsA(isA<ApiFailure>()
            .having((e) => e.code, 'code', 'malformed_response')));
  });
  test('reads opaque pagination from envelope metadata', () async {
    final adapter = BoundaryAdapter((_) =>
        envelope([transactionJson(4)], cursor: 'opaque-next', more: true));
    final page = await repository(adapter).history(cursor: 'opaque-start');
    expect(page.items.single.amountGol, 101);
    expect(page.items.single.counterparty?.name, 'عضو');
    expect(page.nextCursor, 'opaque-next');
    expect(page.hasMore, true);
    expect(adapter.requests.single.queryParameters,
        {'page[limit]': 20, 'page[cursor]': 'opaque-start'});
  });
  test('empty valid history remains empty', () async {
    final page =
        await repository(BoundaryAdapter((_) => envelope([]))).history();
    expect(page.items, isEmpty);
    expect(page.hasMore, false);
    expect(page.nextCursor, null);
  });
  test('malformed pagination does not masquerade as final page', () async {
    final bad = envelope([]);
    bad['meta'] = {
      'api_version': 'v1',
      'pagination': {'has_more': true, 'next_cursor': null}
    };
    await expectLater(
        repository(BoundaryAdapter((_) => bad)).history(),
        throwsA(isA<ApiFailure>()
            .having((e) => e.code, 'code', 'malformed_response')));
  });
  test('404 never provisions a financial account', () async {
    final adapter = BoundaryAdapter((_) => {
          'status': 'error',
          'data': null,
          'request_id': 'nb-404',
          'meta': {'api_version': 'v1'},
          'error': {
            'code': 'not_found',
            'message': 'missing',
            'retryable': false
          }
        })
      ..status = 404;
    await expectLater(repository(adapter).account(),
        throwsA(isA<ApiFailure>().having((e) => e.code, 'code', 'not_found')));
    expect(adapter.requests.map((r) => r.method), ['GET']);
  });
  test('blocked session sends no financial request', () async {
    final adapter = BoundaryAdapter((_) => envelope(accountJson()));
    await expectLater(
        repository(adapter, current: () => false).account(),
        throwsA(isA<ApiFailure>()
            .having((e) => e.code, 'code', 'session_changed')));
    expect(adapter.requests, isEmpty);
  });
  test('late account response after logout is rejected', () async {
    final pending = Completer<Map<String, Object?>>();
    var active = true;
    final result = repository(BoundaryAdapter((_) => pending.future),
        current: () => active).account();
    final assertion = expectLater(
        result,
        throwsA(isA<ApiFailure>()
            .having((e) => e.code, 'code', 'session_changed')));
    active = false;
    pending.complete(envelope(accountJson()));
    await assertion;
  });
}

Map<String, Object?> missingAccountEnvelope() => {
  'status': 'error', 'data': null, 'request_id': 'nb-missing',
  'meta': {'api_version': 'v1'},
  'error': {'code': 'not_found', 'message': 'missing', 'retryable': false},
};
