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
  NotificationsState state = const NotificationsState.loading();

  Future<void> load() async {
    state = const NotificationsState.loading();
    notifyListeners();
    try {
      final items = await _syncService.syncOnResume();
      state = items.isEmpty
          ? const NotificationsState.empty()
          : NotificationsState.ready(items);
    } on ApiFailure catch (failure) {
      state = NotificationsState.failure(_mapFailure(failure));
    } catch (_) {
      state = const NotificationsState.failure(
        NotificationViewFailure.nonRetryable(
          'امکان دریافت اعلان‌ها وجود ندارد.',
        ),
      );
    }
    notifyListeners();
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
