import '../device/device_context.dart';
import 'session_models.dart';
import 'session_repository.dart';

enum SessionPhase { checking, unauthenticated, authenticated }

class SessionState {
  const SessionState._(this.phase, this.session);

  const SessionState.checking() : this._(SessionPhase.checking, null);

  const SessionState.unauthenticated()
      : this._(SessionPhase.unauthenticated, null);

  const SessionState.authenticated(NativeSession session)
      : this._(SessionPhase.authenticated, session);

  final SessionPhase phase;
  final NativeSession? session;
}

typedef SessionCleanupHook = Future<void> Function();

class SessionController {
  SessionController({
    required SessionRepository repository,
    SessionCleanupHook? disablePush,
    SessionCleanupHook? clearUserScopedLocalState,
  })  : _repository = repository,
        _disablePush = disablePush ?? _noop,
        _clearUserScopedLocalState = clearUserScopedLocalState ?? _noop;

  final SessionRepository _repository;
  final SessionCleanupHook _disablePush;
  final SessionCleanupHook _clearUserScopedLocalState;

  SessionState state = const SessionState.checking();

  Future<void> restore() async {
    state = const SessionState.checking();
    try {
      final session = await _repository.restoreAndValidate();
      state = session == null
          ? const SessionState.unauthenticated()
          : SessionState.authenticated(session);
    } catch (_) {
      state = const SessionState.unauthenticated();
      rethrow;
    }
  }

  Future<void> login({
    required String email,
    required String password,
    required DeviceContext device,
  }) async {
    final session = await _repository.login(
      email: email,
      password: password,
      device: device,
    );
    state = SessionState.authenticated(session);
  }

  Future<void> rotate() async {
    final session = await _repository.rotateCurrent();
    state = SessionState.authenticated(session);
  }

  Future<void> logout() async {
    try {
      await _repository.revokeCurrent();
    } catch (_) {
      // Local logout must remain possible when the network or server is absent.
    }
    try {
      await _disablePush();
    } catch (_) {
      // Push cleanup is best-effort; credentials still must be removed.
    }
    try {
      await _repository.clearLocalCredentials();
    } finally {
      try {
        await _clearUserScopedLocalState();
      } finally {
        state = const SessionState.unauthenticated();
      }
    }
  }
}

Future<void> _noop() async {}
