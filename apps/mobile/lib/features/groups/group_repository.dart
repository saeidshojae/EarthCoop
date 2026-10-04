import '../../core/api/api_client.dart';
import '../../core/api/api_error.dart';
import '../../core/api/request_context.dart';
import 'group_cache.dart';
import 'group_dto.dart';
import 'group_feed_dto.dart';

class GroupProjection<T> {
  const GroupProjection({
    required this.value,
    required this.isStale,
  });

  final T value;
  final bool isStale;
}

class GroupRepository {
  GroupRepository({
    required ApiClient apiClient,
    required GroupProjectionCache cache,
    bool Function()? isCurrentSession,
  })  : _apiClient = apiClient,
        _cache = cache,
        _isCurrentSession = isCurrentSession ?? (() => true);

  final ApiClient _apiClient;
  final GroupProjectionCache _cache;
  final bool Function() _isCurrentSession;

  void _checkSession() {
    if (!_isCurrentSession()) {
      throw const ApiFailure(
          code: 'session_changed',
          message: '',
          retryable: false,
          httpStatus: 401);
    }
  }

  Future<GroupProjection<List<GroupDto>>> list() async {
    _checkSession();
    try {
      final response = await _apiClient.get<List<GroupDto>>(
        '/groups',
        decodeData: _decodeList,
      );
      final cacheable = response.data
          .where((group) => group.id != null && !group.pending)
          .map((group) => group.toJson())
          .toList(growable: false);
      await _bestEffortWrite(cacheable);
      _checkSession();
      return GroupProjection(value: response.data, isStale: false);
    } on ApiFailure catch (failure) {
      _checkSession();
      if (!failure.retryable) rethrow;
      final cached = await _bestEffortReadAll();
      _checkSession();
      if (cached.isEmpty) rethrow;
      return GroupProjection(
        value: cached.map(GroupDto.fromJson).toList(growable: false),
        isStale: true,
      );
    }
  }

  Future<GroupProjection<GroupDto>> find(int id) async {
    _checkSession();
    try {
      final response = await _apiClient.get<GroupDto>(
        '/groups/$id',
        decodeData: GroupDto.fromJson,
      );
      await _bestEffortMerge(response.data);
      _checkSession();
      return GroupProjection(value: response.data, isStale: false);
    } on ApiFailure catch (failure) {
      _checkSession();
      if (!failure.retryable) rethrow;
      final cached = await _bestEffortReadOne(id);
      _checkSession();
      if (cached == null) rethrow;
      return GroupProjection(
        value: GroupDto.fromJson(cached),
        isStale: true,
      );
    }
  }

  Future<GroupFeedPage> activityPage(int id, {int limit = 20}) async {
    _checkSession();
    final boundedLimit = limit < 1
        ? 1
        : limit > 100
            ? 100
            : limit;
    final response = await _apiClient.get<GroupFeedPage>(
      '/groups/$id/feed/delta',
      queryParameters: {
        'after_sequence': 0,
        'window': 'latest',
        'limit': boundedLimit,
      },
      decodeData: GroupFeedPage.fromJson,
    );
    _checkSession();
    return response.data;
  }

  Future<List<GroupFeedEvent>> activity(int id, {int limit = 20}) async {
    final page = await activityPage(id, limit: limit);
    return page.events;
  }

  Future<int> unreadCount(int id) async {
    _checkSession();
    final response = await _apiClient.get<GroupUnreadProjection>(
      '/groups/$id/unread',
      decodeData: GroupUnreadProjection.fromJson,
    );
    _checkSession();
    return response.data.total;
  }

  Future<void> markRead(int id, {required int throughSequence}) async {
    _checkSession();
    if (throughSequence < 0) {
      throw ArgumentError.value(
        throughSequence,
        'throughSequence',
        'must not be negative',
      );
    }

    await _apiClient.post<Object?>(
      '/groups/$id/read',
      data: {'through_sequence': throughSequence},
      context: RequestContext(
        idempotencyKey: 'group-read-$id-$throughSequence',
      ),
      decodeData: (raw) => raw,
    );
    _checkSession();
  }

  List<GroupDto> _decodeList(Object? raw) {
    if (raw is! List) {
      throw const FormatException('groups data must be a list');
    }
    return raw.map(GroupDto.fromJson).toList(growable: false);
  }

  Future<void> _bestEffortWrite(List<Map<String, Object?>> values) async {
    _checkSession();
    try {
      await _cache.writeAll(values);
    } on ApiFailure catch (failure) {
      if (!failure.retryable) rethrow;
    } catch (_) {
      // Cache is explicitly non-authoritative and must not break a live API read.
    }
  }

  Future<void> _bestEffortMerge(GroupDto value) async {
    _checkSession();
    if (value.id == null || value.pending) return;
    try {
      final existing = await _cache.readAll();
      _checkSession();
      final merged = existing.where((item) => item['id'] != value.id).toList()
        ..add(value.toJson());
      await _cache.writeAll(merged);
    } on ApiFailure catch (failure) {
      if (!failure.retryable) rethrow;
    } catch (_) {
      // Cache is explicitly non-authoritative and must not break a live API read.
    }
  }

  Future<List<Map<String, Object?>>> _bestEffortReadAll() async {
    _checkSession();
    try {
      return await _cache.readAll();
    } on ApiFailure catch (failure) {
      if (!failure.retryable) rethrow;
      return const <Map<String, Object?>>[];
    } catch (_) {
      return const <Map<String, Object?>>[];
    }
  }

  Future<Map<String, Object?>?> _bestEffortReadOne(int id) async {
    _checkSession();
    try {
      return await _cache.readOne(id);
    } on ApiFailure catch (failure) {
      if (!failure.retryable) rethrow;
      return null;
    } catch (_) {
      return null;
    }
  }
}
