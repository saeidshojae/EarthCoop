import 'dart:async';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_controller.dart';
import 'najm_bahar_repository_test.dart' as fixtures;

void main() {
  test('loads wallet and history independently', () async {
    final adapter = fixtures.BoundaryAdapter((r) => fixtures.envelope(
        r.path.endsWith('/account')
            ? fixtures.accountJson()
            : [fixtures.transactionJson(9)]));
    final controller = NajmBaharController(fixtures.repository(adapter));
    addTearDown(controller.dispose);
    await controller.load();
    expect(controller.account?.accountNumber, 'NB-7');
    expect(controller.transactions.single.id, 9);
    expect(controller.accountLoading, false);
    expect(controller.historyLoading, false);
  });
  test('missing wallet does not hide independently authorized history',
      () async {
    final adapter = fixtures.BoundaryAdapter((r) {
      if (r.path.endsWith('/account')) {
        return fixtures.envelope(null);
      }
      return fixtures.envelope([fixtures.transactionJson(8)]);
    });
    final controller = NajmBaharController(fixtures.repository(adapter));
    addTearDown(controller.dispose);
    await controller.load();
    expect(controller.account, null);
    expect(controller.accountFailure, isNotNull);
    expect(controller.transactions.single.id, 8);
    expect(controller.historyFailure, null);
  });
  test('pagination deduplicates and simultaneous loadMore consumes cursor once',
      () async {
    final page = Completer<Map<String, Object?>>();
    final adapter = fixtures.BoundaryAdapter((r) {
      if (r.queryParameters['page[cursor]'] != null) return page.future;
      return fixtures
          .envelope([fixtures.transactionJson(9)], cursor: 'next', more: true);
    });
    final controller = NajmBaharController(fixtures.repository(adapter));
    addTearDown(controller.dispose);
    await controller.refreshHistory();
    final first = controller.loadMore();
    final second = controller.loadMore();
    await Future<void>.delayed(Duration.zero);
    page.complete(fixtures
        .envelope([fixtures.transactionJson(9), fixtures.transactionJson(8)]));
    await Future.wait([first, second]);
    expect(controller.transactions.map((t) => t.id), [9, 8]);
    expect(
        adapter.requests
            .where((r) => r.queryParameters['page[cursor]'] == 'next'),
        hasLength(1));
  });
  test('refresh overtaking a page discards old page', () async {
    final page = Completer<Map<String, Object?>>();
    var count = 0;
    final adapter = fixtures.BoundaryAdapter((r) {
      if (r.queryParameters['page[cursor]'] != null) return page.future;
      return ++count == 1
          ? fixtures.envelope([fixtures.transactionJson(9)],
              cursor: 'next', more: true)
          : fixtures.envelope([fixtures.transactionJson(10)]);
    });
    final controller = NajmBaharController(fixtures.repository(adapter));
    addTearDown(controller.dispose);
    await controller.refreshHistory();
    final old = controller.loadMore();
    await controller.refreshHistory();
    page.complete(fixtures.envelope([fixtures.transactionJson(8)]));
    await old;
    expect(controller.transactions.map((t) => t.id), [10]);
    expect(controller.hasMore, false);
  });
  test('dispose prevents late notification and data publication', () async {
    final pending = Completer<Map<String, Object?>>();
    final controller = NajmBaharController(
        fixtures.repository(fixtures.BoundaryAdapter((_) => pending.future)));
    var changes = 0;
    controller.addListener(() => changes++);
    final request = controller.refreshAccount();
    controller.dispose();
    final before = changes;
    pending.complete(fixtures.envelope(fixtures.accountJson()));
    await request;
    expect(changes, before);
    expect(controller.account, null);
  });
  test('failed refresh retains dated wallet and exposes error', () async {
    var good = true;
    final adapter = fixtures.BoundaryAdapter(
        (_) => fixtures.envelope(good ? fixtures.accountJson() : null));
    final controller = NajmBaharController(fixtures.repository(adapter));
    addTearDown(controller.dispose);
    await controller.refreshAccount();
    final date = controller.receivedAt;
    good = false;
    await controller.refreshAccount();
    expect(controller.account?.accountNumber, 'NB-7');
    expect(controller.receivedAt, date);
    expect(controller.accountFailure, isNotNull);
  });
}
