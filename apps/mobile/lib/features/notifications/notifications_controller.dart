import 'package:flutter/foundation.dart';

import '../../core/api/api_error.dart';
import 'notification_dto.dart';
import 'notification_sync_service.dart';

enum NotificationViewFailureKind { retryable, forbidden, nonRetryable }

class NotificationViewFailure {
  const NotificationViewFailure._(this.kind, this.message);

  const NotificationViewFailure.retryable(String message)
      : this._(NotificationViewFailureKind.retryable, message);

  const NotificationViewFailure.forbidden(String message)
      : this._(NotificationViewFailureKind.forbidden, message);

  const NotificationViewFailure.nonRetryable(String message)
      : this._(NotificationViewFailureKind.nonRetryable, message);

  final NotificationViewFailureKind kind;
  final String message;

  bool get canRetry => kind == NotificationViewFailureKind.retryable;
}

enum NotificationsPhase { loading, empty, ready, failure }

class NotificationsState {
  const NotificationsState._({
    required this.phase,
    this.items = const <NotificationDto>[],
    this.failure,
  });

  const NotificationsState.loading()
      : this._(phase: NotificationsPhase.loading);

  const NotificationsState.empty() : this._(phase: NotificationsPhase.empty);

  NotificationsState.ready(List<NotificationDto> items)
      : this._(
          phase: NotificationsPhase.ready,
          items: List<NotificationDto>.unmodifiable(items),
        );

  const NotificationsState.failure(NotificationViewFailure failure)
      : this._(phase: NotificationsPhase.failure, failure: failure);

  final NotificationsPhase phase;
  final List<NotificationDto> items;
  final NotificationViewFailure? failure;
}

class NotificationsController extends ChangeNotifier {
  NotificationsController(this._syncService);

  final NotificationSyncService _syncService;
  bool _disposed = false;
  int _loadSequence = 0;

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  NotificationsState state = const NotificationsState.loading();

  Future<void> load() async {
    if (_disposed) { return; }
    final sequence = ++_loadSequence;
    state = const NotificationsState.loading();
    _notify();
    try {
      final items = await _syncService.syncOnResume();
      if (_disposed || sequence != _loadSequence) { return; }
      state = items.isEmpty
          ? const NotificationsState.empty()
          : NotificationsState.ready(items);
    } on ApiFailure catch (failure) {
      if (_disposed || sequence != _loadSequence) { return; }
      state = NotificationsState.failure(_mapFailure(failure));
    } catch (_) {
      if (_disposed || sequence != _loadSequence) { return; }
      state = const NotificationsState.failure(
        NotificationViewFailure.nonRetryable(
          'امکان دریافت اعلان‌ها وجود ندارد.',
        ),
      );
    }
    _notify();
  }

  Future<void> markRead(String notificationId) async {
    if (_disposed || state.phase != NotificationsPhase.ready) { return; }

    var changed = false;
    final markedAt = DateTime.now().toUtc();
    final updated = state.items.map((item) {
      if (item.id != notificationId || item.read) { return item; }
      changed = true;
      return item.asRead(at: markedAt);
    }).toList(growable: false);

    if (!changed) { return; }

    state = NotificationsState.ready(updated);
    _notify();

    try {
      await _syncService.markRead(notificationId);
    } catch (_) {
      // Keep the UI responsive. The next authoritative sync reconciles state.
    }
  }
}

NotificationViewFailure _mapFailure(ApiFailure failure) {
  if (failure.httpStatus == 401 || failure.httpStatus == 403) {
    return const NotificationViewFailure.forbidden(
      'دسترسی به اعلان‌ها مجاز نیست.',
    );
  }
  if (failure.retryable) {
    return const NotificationViewFailure.retryable(
      'ارتباط برقرار نشد. دوباره تلاش کنید.',
    );
  }
  return const NotificationViewFailure.nonRetryable(
    'امکان دریافت اعلان‌ها وجود ندارد.',
  );
}
