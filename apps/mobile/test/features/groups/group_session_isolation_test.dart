import 'dart:async';
import 'dart:io';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/app/runtime/notification_account_storage.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/core/local/app_database.dart';
import 'package:earthcoop_mobile/features/groups/group_cache.dart';
import 'package:earthcoop_mobile/features/groups/group_repository.dart';
import 'package:flutter_test/flutter_test.dart';

import 'group_repository_test.dart' as fixtures;

void main() {
  test('late list response cannot refill cache after logout', () async {
    var current = true;
    final adapter = DelayedAdapter();
    final cache = fixtures.MemoryGroupCache();
    final repository = GroupRepository(
        apiClient: fixtures.buildClient(adapter),
        cache: cache,
        isCurrentSession: () => current);
    final result = repository.list();
    final rejected = expectLater(
        result,
        throwsA(isA<ApiFailure>()
            .having((failure) => failure.code, 'code', 'session_changed')));
    await adapter.started.future;
    current = false;
    adapter.response.complete(fixtures.jsonResponse(
        200, fixtures.successEnvelope([fixtures.groupJson()])));
    await rejected;
    expect(cache.items, isEmpty);
  });

  test('an old view cannot fetch activity or mark a new account group read',
      () async {
    final adapter = fixtures.RecordingAdapter([]);
    final repository = GroupRepository(
        apiClient: fixtures.buildClient(adapter),
        cache: fixtures.MemoryGroupCache(),
        isCurrentSession: () => false);
    for (final operation in <Future<Object?> Function()>[
      repository.list,
      () => repository.find(42),
      () => repository.activity(42),
      () => repository.unreadCount(42),
      () async {
        await repository.markRead(42, throughSequence: 8);
        return null;
      },
    ]) {
      await expectLater(
          operation(),
          throwsA(isA<ApiFailure>()
              .having((failure) => failure.code, 'code', 'session_changed')));
    }
    expect(adapter.requests, isEmpty);
  });

  test('the same group id in different account databases stays separate',
      () async {
    final directory = await Directory.systemTemp.createTemp('group-accounts-');
    final databases = <String, AppDatabase>{};
    addTearDown(() async {
      for (final database in databases.values) {
        await database.close();
      }
      await directory.delete(recursive: true);
    });
    final storage = NotificationAccountStorage(
        open: (userId, deviceId) async => databases.putIfAbsent(
            '$userId-$deviceId',
            () => AppDatabase.file(
                File('${directory.path}/$userId-$deviceId.sqlite'))));
    AccountGroupProjectionCache cache(int userId, String deviceId) =>
        AccountGroupProjectionCache(
            openDatabase: () =>
                storage.database(userId: userId, deviceId: deviceId),
            isCurrentSession: () => true);
    final first = cache(7, 'device-a');
    final second = cache(8, 'device-a');
    final otherDevice = cache(7, 'device-b');
    await first.writeAll([fixtures.groupJson(name: 'حساب اول')]);
    await second.writeAll([fixtures.groupJson(name: 'حساب دوم')]);
    expect((await first.readOne(42))?['name'], 'حساب اول');
    expect((await second.readOne(42))?['name'], 'حساب دوم');
    expect(await otherDevice.readAll(), isEmpty);
    await storage.clear(userId: 7, deviceId: 'device-a');
    expect(await first.readAll(), isEmpty);
    expect((await second.readOne(42))?['name'], 'حساب دوم');
  });

  test('logout during account database opening blocks the delayed write',
      () async {
    final directory = await Directory.systemTemp.createTemp('group-delayed-');
    final database = AppDatabase.file(File('${directory.path}/account.sqlite'));
    addTearDown(() async {
      await database.close();
      await directory.delete(recursive: true);
    });
    final opening = Completer<AppDatabase>();
    var current = true;
    final cache = AccountGroupProjectionCache(
        openDatabase: () => opening.future, isCurrentSession: () => current);
    final rejected = expectLater(
        cache.writeAll([fixtures.groupJson()]),
        throwsA(isA<ApiFailure>()
            .having((failure) => failure.code, 'code', 'session_changed')));
    current = false;
    opening.complete(database);
    await rejected;
    expect(await DriftGroupProjectionCache(database).readAll(), isEmpty);
  });

  test('a cache reporting an expired account prevents a live list escaping',
      () async {
    final adapter = fixtures.RecordingAdapter([
      fixtures.jsonResponse(
          200, fixtures.successEnvelope([fixtures.groupJson()])),
    ]);
    final repository = GroupRepository(
      apiClient: fixtures.buildClient(adapter),
      cache: ExpiredAccountCache(),
    );
    await expectLater(
        repository.list(),
        throwsA(isA<ApiFailure>()
            .having((failure) => failure.code, 'code', 'session_changed')));
  });

  test('an expired account cache cannot provide an offline fallback', () async {
    final adapter = fixtures.RecordingAdapter([
      fixtures.jsonResponse(503,
          fixtures.errorEnvelope('unavailable', retryable: true, status: 503)),
      fixtures.jsonResponse(503,
          fixtures.errorEnvelope('unavailable', retryable: true, status: 503)),
    ]);
    final repository = GroupRepository(
      apiClient: fixtures.buildClient(adapter),
      cache: ExpiredAccountCache(),
    );
    await expectLater(
        repository.list(),
        throwsA(isA<ApiFailure>()
            .having((failure) => failure.code, 'code', 'session_changed')));
  });
}

class DelayedAdapter extends fixtures.RecordingAdapter {
  DelayedAdapter() : super([]);
  final started = Completer<void>();
  final response = Completer<ResponseBody>();
  @override
  Future<ResponseBody> fetch(RequestOptions options,
      Stream<Uint8List>? requestStream, Future<void>? cancelFuture) {
    requests.add(options);
    started.complete();
    return response.future;
  }
}

class ExpiredAccountCache extends fixtures.MemoryGroupCache {
  Never expired() => throw const ApiFailure(
      code: 'session_changed', message: '', retryable: false, httpStatus: 401);
  @override
  Future<List<Map<String, Object?>>> readAll() async => expired();
  @override
  Future<void> writeAll(List<Map<String, Object?>> values) async => expired();
}
