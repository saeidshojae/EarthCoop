import 'offline_operation.dart';

typedef OfflineOperationExecutor = Future<void> Function(
  OfflineOperation operation,
);

class OfflineOperationRegistry {
  OfflineOperationRegistry._(this._executors);

  factory OfflineOperationRegistry.notificationReadOnly({
    required OfflineOperationExecutor markNotificationRead,
  }) =>
      OfflineOperationRegistry._({
        notificationMarkReadOperation: markNotificationRead,
      });

  final Map<String, OfflineOperationExecutor> _executors;

  OfflineOperationExecutor executorFor(OfflineOperation operation) {
    final executor = _executors[operation.operation];
    if (executor == null) {
      throw UnsupportedError(
        'Offline operation is not allowlisted: ${operation.operation}',
      );
    }
    return executor;
  }
}
