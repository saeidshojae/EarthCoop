import '../api/api_error.dart';
import '../auth/session_controller.dart';
import '../../app/bootstrap/bootstrap_state.dart';
import 'offline_operation_registry.dart';
import 'offline_queue_repository.dart';

class OfflineReplayEngine {
  OfflineReplayEngine({
    required OfflineQueueRepository queue,
    required OfflineOperationRegistry registry,
    required BootstrapState Function() bootstrapState,
    required SessionState Function() sessionState,
  })  : _queue = queue,
        _registry = registry,
        _bootstrapState = bootstrapState,
        _sessionState = sessionState;

  final OfflineQueueRepository _queue;
  final OfflineOperationRegistry _registry;
  final BootstrapState Function() _bootstrapState;
  final SessionState Function() _sessionState;

  bool _running = false;

  Future<void> replayEligible() async {
    if (_running) return;
    final bootstrap = _bootstrapState();
    final session = _sessionState();
    if (!bootstrap.canReplayQueuedMutations ||
        session.phase != SessionPhase.authenticated) {
      return;
    }

    _running = true;
    try {
      final items = await _queue.pending();
      for (final operation in items) {
        try {
          final executor = _registry.executorFor(operation);
          await executor(operation);
          await _queue.remove(operation);
        } on ApiFailure catch (failure) {
          if (failure.retryable) {
            await _queue.markRetryableFailure(operation, failure.code);
          } else {
            await _queue.markBlocked(operation, failure.code);
          }
          break;
        }
      }
    } finally {
      _running = false;
    }
  }
}
