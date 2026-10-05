import 'dart:async';
import 'package:earthcoop_mobile/core/api/api_error.dart';
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
  test('superseded load failure cannot overwrite a newer ready state',
      () async {
    final repository = _DelayedGroupRepository();
    final controller = GroupDetailController(repository, 42);
    final first = controller.load();
    await controller.load();
    expect(controller.state.phase, GroupDetailPhase.ready);
    repository.first.completeError(
        const ApiFailure(code: 'network_error', message: '', retryable: true));
    await first;
    expect(controller.state.phase, GroupDetailPhase.ready);
    controller.dispose();
  });

  test('opening a live group marks its feed read and clears unread count',
      () async {
    final repository = _FakeGroupRepository();
    final controller = GroupDetailController(repository, 42);

    await controller.load();

    expect(repository.markReadCalls, 1);
    expect(repository.markedThroughSequence, 8);
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
  int? markedThroughSequence;

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
  Future<GroupFeedPage> activityPage(int id, {int limit = 20}) async =>
      const GroupFeedPage(
        events: <GroupFeedEvent>[
          GroupFeedEvent.message(
            sequence: 8,
            sender: 'کاربر نمونه',
            message: 'سلام',
          ),
        ],
        latestSequence: 8,
        hasMore: false,
      );

  @override
  Future<void> markRead(int id, {required int throughSequence}) async {
    markReadCalls += 1;
    markedThroughSequence = throughSequence;
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

class _DelayedGroupRepository extends _FakeGroupRepository {
  final first = Completer<GroupProjection<GroupDto>>();
  var calls = 0;
  @override
  Future<GroupProjection<GroupDto>> find(int id) {
    if (++calls == 1) return first.future;
    return super.find(id);
  }
}
