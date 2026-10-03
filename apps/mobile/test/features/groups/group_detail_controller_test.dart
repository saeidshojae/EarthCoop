import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/api/retry_policy.dart';
import 'package:earthcoop_mobile/features/groups/group_cache.dart';
import 'package:earthcoop_mobile/features/groups/group_dto.dart';
import 'package:earthcoop_mobile/features/groups/group_feed_dto.dart';
import 'package:earthcoop_mobile/features/groups/group_repository.dart';
import 'package:earthcoop_mobile/features/groups/groups_controller.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('opening a live group marks its feed read and clears unread count', () async {
    final repository = _FakeGroupRepository();
    final controller = GroupDetailController(repository, 42);

    await controller.load();

    expect(repository.markReadCalls, 1);
    expect(controller.state.phase, GroupDetailPhase.ready);
    expect(controller.state.unreadCount, 0);
  });
}

class _FakeGroupRepository extends GroupRepository {
  _FakeGroupRepository()
      : super(
          apiClient: ApiClient(
            dio: Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1')),
            bearerTokenProvider: () async => 'token',
            requestIdFactory: () => 'req-group-detail-controller',
            retryDelay: (_) async {},
            retryPolicy: const RetryPolicy(maxAttempts: 1),
          ),
          cache: _NoopGroupCache(),
        );

  int markReadCalls = 0;

  @override
  Future<GroupProjection<GroupDto>> find(int id) async => GroupProjection(
        value: GroupDto.fromJson({
          'id': id,
          'name': 'مجمع عمومی محله نمونه',
          'identity': {
            'governance_area_id': 7,
            'dimension_key': 'public',
            'dimension_value_key': 'assembly',
          },
          'membership': {
            'role': 1,
            'role_label': 'فعال',
            'status': 1,
          },
          'members_count': 12,
          'last_activity_at': '2026-10-03T00:00:00Z',
        }),
        isStale: false,
      );

  @override
  Future<int> unreadCount(int id) async => 3;

  @override
  Future<List<GroupFeedEvent>> activity(int id, {int limit = 20}) async =>
      const <GroupFeedEvent>[];

  Future<void> markRead(int id) async {
    markReadCalls += 1;
  }
}

class _NoopGroupCache implements GroupProjectionCache {
  @override
  Future<List<Map<String, Object?>>> readAll() async =>
      const <Map<String, Object?>>[];

  @override
  Future<Map<String, Object?>?> readOne(int id) async => null;

  @override
  Future<void> writeAll(List<Map<String, Object?>> values) async {}
}
