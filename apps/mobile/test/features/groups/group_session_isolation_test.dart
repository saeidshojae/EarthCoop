import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/features/groups/group_repository.dart';
import 'package:flutter_test/flutter_test.dart';

import 'group_repository_test.dart' as fixtures;

void main() {
  test('a cache reporting an expired account prevents a live list escaping', () async {
    final adapter = fixtures.RecordingAdapter([
      fixtures.jsonResponse(200, fixtures.successEnvelope([fixtures.groupJson()])),
    ]);
    final repository = GroupRepository(
      apiClient: fixtures.buildClient(adapter),
      cache: ExpiredAccountCache(),
    );
    await expectLater(repository.list(), throwsA(isA<ApiFailure>()
        .having((failure) => failure.code, 'code', 'session_changed')));
  });

  test('an expired account cache cannot provide an offline fallback', () async {
    final adapter = fixtures.RecordingAdapter([
      fixtures.jsonResponse(503, fixtures.errorEnvelope('unavailable', retryable: true, status: 503)),
      fixtures.jsonResponse(503, fixtures.errorEnvelope('unavailable', retryable: true, status: 503)),
    ]);
    final repository = GroupRepository(
      apiClient: fixtures.buildClient(adapter),
      cache: ExpiredAccountCache(),
    );
    await expectLater(repository.list(), throwsA(isA<ApiFailure>()
        .having((failure) => failure.code, 'code', 'session_changed')));
  });
}

class ExpiredAccountCache extends fixtures.MemoryGroupCache {
  Never expired() => throw const ApiFailure(
      code: 'session_changed', message: '', retryable: false, httpStatus: 401);
  @override
  Future<List<Map<String, Object?>>> readAll() async => expired();
  @override
  Future<void> writeAll(List<Map<String, Object?>> values) async => expired();
}
