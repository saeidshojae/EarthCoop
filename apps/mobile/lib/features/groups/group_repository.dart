import '../../core/api/api_client.dart';
import '../../core/api/api_error.dart';
import 'group_cache.dart';
import 'group_dto.dart';

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
  })  : _apiClient = apiClient,
        _cache = cache;

  final ApiClient _apiClient;
  final GroupProjectionCache _cache;

  Future<GroupProjection<List<GroupDto>>> list() async {
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
      return GroupProjection(value: response.data, isStale: false);
    } on ApiFailure catch (failure) {
      if (!failure.retryable) rethrow;
      final cached = await _bestEffortReadAll();
      if (cached.isEmpty) rethrow;
      return GroupProjection(
        value: cached.map(GroupDto.fromJson).toList(growable: false),
        isStale: true,
      );
    }
  }

  Future<GroupProjection<GroupDto>> find(int id) async {
    try {
      final response = await _apiClient.get<GroupDto>(
        '/groups/$id',
        decodeData: GroupDto.fromJson,
      );
      await _bestEffortMerge(response.data);
      return GroupProjection(value: response.data, isStale: false);
    } on ApiFailure catch (failure) {
      if (!failure.retryable) rethrow;
      final cached = await _bestEffortReadOne(id);
      if (cached == null) rethrow;
      return GroupProjection(
        value: GroupDto.fromJson(cached),
        isStale: true,
      );
    }
  }

  List<GroupDto> _decodeList(Object? raw) {
    if (raw is! List) {
      throw const FormatException('groups data must be a list');
    }
    return raw.map(GroupDto.fromJson).toList(growable: false);
  }

  Future<void> _bestEffortWrite(List<Map<String, Object?>> values) async {
    try {
      await _cache.writeAll(values);
    } catch (_) {
      // Cache is explicitly non-authoritative and must not break a live API read.
    }
  }

  Future<void> _bestEffortMerge(GroupDto value) async {
    if (value.id == null || value.pending) return;
    try {
      final existing = await _cache.readAll();
      final merged = existing.where((item) => item['id'] != value.id).toList()
        ..add(value.toJson());
      await _cache.writeAll(merged);
    } catch (_) {
      // Cache is explicitly non-authoritative and must not break a live API read.
    }
  }

  Future<List<Map<String, Object?>>> _bestEffortReadAll() async {
    try {
      return await _cache.readAll();
    } catch (_) {
      return const <Map<String, Object?>>[];
    }
  }

  Future<Map<String, Object?>?> _bestEffortReadOne(int id) async {
    try {
      return await _cache.readOne(id);
    } catch (_) {
      return null;
    }
  }
}
