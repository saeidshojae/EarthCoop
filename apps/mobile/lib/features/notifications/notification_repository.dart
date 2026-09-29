import '../../core/api/api_client.dart';
import 'notification_dto.dart';

class NotificationPage {
  const NotificationPage({
    required this.items,
    required this.nextCursor,
    required this.hasMore,
  });

  final List<NotificationDto> items;
  final String? nextCursor;
  final bool hasMore;
}

abstract interface class NotificationPageSource {
  Future<NotificationPage> fetchPage({String? cursor, int limit = 20});
}

class NotificationRepository implements NotificationPageSource {
  NotificationRepository({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  @override
  Future<NotificationPage> fetchPage({String? cursor, int limit = 20}) async {
    if (limit < 1 || limit > 50) {
      throw ArgumentError.value(limit, 'limit', 'must be between 1 and 50');
    }

    final response = await _apiClient.get<List<NotificationDto>>(
      '/notifications',
      queryParameters: <String, dynamic>{
        'page[limit]': limit,
        if (cursor != null && cursor.isNotEmpty) 'page[cursor]': cursor,
      },
      decodeData: _decodeItems,
    );

    final paginationRaw = response.meta['pagination'];
    if (paginationRaw is! Map) {
      throw const FormatException('notification pagination metadata is missing');
    }
    final pagination = Map<String, Object?>.from(paginationRaw);
    final hasMore = pagination['has_more'];
    final nextCursor = pagination['next_cursor'];
    if (hasMore is! bool || (nextCursor != null && nextCursor is! String)) {
      throw const FormatException('notification pagination metadata is invalid');
    }
    if (hasMore && (nextCursor is! String || nextCursor.isEmpty)) {
      throw const FormatException('notification continuation cursor is missing');
    }

    return NotificationPage(
      items: response.data,
      nextCursor: nextCursor as String?,
      hasMore: hasMore,
    );
  }

  List<NotificationDto> _decodeItems(Object? raw) {
    if (raw is! List) {
      throw const FormatException('notifications data must be a list');
    }
    return raw.map(NotificationDto.fromJson).toList(growable: false);
  }
}
