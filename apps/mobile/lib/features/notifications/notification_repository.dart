import '../../core/api/api_client.dart';
import '../../core/api/api_error.dart';
import '../../core/api/request_context.dart';
import '../../core/offline/offline_operation.dart';
import '../../core/offline/offline_queue_repository.dart';
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

abstract interface class NotificationReadSource {
  Future<NotificationDto?> markRead(
    String notificationId, {
    required String idempotencyKey,
    required bool networkAllowed,
  });
}

typedef OptimisticNotificationRead = Future<void> Function(
  String notificationId,
);

class NotificationRepository
    implements NotificationPageSource, NotificationReadSource {
  NotificationRepository({
    required ApiClient apiClient,
    OfflineQueueRepository? offlineQueue,
    bool Function()? isCurrentSession,
    OptimisticNotificationRead? optimisticMarkRead,
  })  : _apiClient = apiClient,
        _offlineQueue = offlineQueue,
        _isCurrentSession = isCurrentSession ?? (() => true),
        _optimisticMarkRead = optimisticMarkRead;

  final ApiClient _apiClient;
  final OfflineQueueRepository? _offlineQueue;
  final bool Function() _isCurrentSession;

  void _checkSession() {
    if (!_isCurrentSession()) {
      throw const ApiFailure(
        code: 'session_changed',
        message: 'Session changed.',
        retryable: false,
        httpStatus: 401,
      );
    }
  }

  final OptimisticNotificationRead? _optimisticMarkRead;

  @override
  Future<NotificationPage> fetchPage({String? cursor, int limit = 20}) async {
    _checkSession();
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

    _checkSession();
    final paginationRaw = response.meta['pagination'];
    if (paginationRaw is! Map) {
      throw const FormatException(
        'notification pagination metadata is missing',
      );
    }
    final pagination = Map<String, Object?>.from(paginationRaw);
    final hasMore = pagination['has_more'];
    final nextCursor = pagination['next_cursor'];
    if (hasMore is! bool || (nextCursor != null && nextCursor is! String)) {
      throw const FormatException(
        'notification pagination metadata is invalid',
      );
    }
    if (hasMore && (nextCursor is! String || nextCursor.isEmpty)) {
      throw const FormatException(
        'notification continuation cursor is missing',
      );
    }

    return NotificationPage(
      items: response.data,
      nextCursor: nextCursor as String?,
      hasMore: hasMore,
    );
  }

  @override
  Future<NotificationDto?> markRead(
    String notificationId, {
    required String idempotencyKey,
    required bool networkAllowed,
  }) async {
    _checkSession();
    if (notificationId.isEmpty) {
      throw ArgumentError.value(notificationId, 'notificationId');
    }
    if (idempotencyKey.isEmpty) {
      throw ArgumentError.value(idempotencyKey, 'idempotencyKey');
    }

    if (!networkAllowed) {
      final queue = _offlineQueue;
      if (queue == null) {
        throw StateError('Offline queue is not configured.');
      }
      await queue.enqueue(
        OfflineOperation.notificationRead(
          notificationId: notificationId,
          idempotencyKey: idempotencyKey,
          createdAt: DateTime.now().toUtc(),
        ),
      );
      await _optimisticMarkRead?.call(notificationId);
      return null;
    }

    try {
      final notification = await _postMarkRead(
        notificationId,
        idempotencyKey: idempotencyKey,
      );
      await _optimisticMarkRead?.call(notificationId);
      return notification;
    } on ApiFailure catch (failure) {
      if (!failure.retryable ||
          _offlineQueue == null ||
          failure.httpStatus == 401 ||
          failure.httpStatus == 403) {
        rethrow;
      }
      await markRead(
        notificationId,
        idempotencyKey: idempotencyKey,
        networkAllowed: false,
      );
      return null;
    }
  }

  Future<void> replayMarkRead(OfflineOperation operation) async {
    if (operation.operation != notificationMarkReadOperation) {
      throw UnsupportedError(
        'Offline operation is not notification.mark_read.',
      );
    }
    final notificationId = operation.payload['notification_id'];
    if (notificationId is! String || notificationId.isEmpty) {
      throw const FormatException('notification.mark_read payload is invalid');
    }
    await _postMarkRead(
      notificationId,
      idempotencyKey: operation.idempotencyKey,
    );
    await _optimisticMarkRead?.call(notificationId);
  }

  Future<NotificationDto> _postMarkRead(
    String notificationId, {
    required String idempotencyKey,
  }) async {
    _checkSession();
    final response = await _apiClient.post<NotificationDto>(
      '/notifications/$notificationId/read',
      context: RequestContext(idempotencyKey: idempotencyKey),
      decodeData: NotificationDto.fromJson,
    );
    _checkSession();
    return response.data;
  }

  List<NotificationDto> _decodeItems(Object? raw) {
    if (raw is! List) {
      throw const FormatException('notifications data must be a list');
    }
    return raw.map(NotificationDto.fromJson).toList(growable: false);
  }
}
