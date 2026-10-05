import 'dart:async';
import 'package:flutter/foundation.dart';

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

typedef SessionAuthenticatedHook = Future<void> Function(NativeSession session);

typedef SessionCleanupHook = Future<void> Function();

class SessionController extends ChangeNotifier {
  SessionController({
    required SessionRepository repository,
    SessionCleanupHook? disablePush,
    SessionAuthenticatedHook? onAuthenticated,
    SessionCleanupHook? clearUserScopedLocalState,
  })  : _repository = repository,
        _onAuthenticated = onAuthenticated,
        _disablePush = disablePush ?? _noop,
        _clearUserScopedLocalState = clearUserScopedLocalState ?? _noop;

  final SessionRepository _repository;
  final SessionAuthenticatedHook? _onAuthenticated;
  final SessionCleanupHook _disablePush;
  final SessionCleanupHook _clearUserScopedLocalState;

  SessionState _state = const SessionState.checking();
  SessionState get state => _state;
  set state(SessionState value) {
    _state = value;
    notifyListeners();
  }

  Future<void>? _logoutInFlight;
  NativeSession? _logoutOwner;

  Future<void> restore() async {
    state = const SessionState.checking();
    try {
      final session = await _repository.restoreAndValidate();
      state = session == null
          ? const SessionState.unauthenticated()
          : SessionState.authenticated(session);
      if (session != null) _notifyAuthenticated(session);
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
    _notifyAuthenticated(session);
  }

  Future<void> rotate() async {
    final session = await _repository.rotateCurrent();
    state = SessionState.authenticated(session);
    _notifyAuthenticated(session);
  }

  void _notifyAuthenticated(NativeSession session) {
    final hook = _onAuthenticated;
    if (hook == null) return;
    try {
      unawaited(hook(session).catchError((Object _) {}));
    } catch (_) {
      // Optional provider setup cannot prevent an authenticated session.
    }
  }

  Future<void> logout() => _logoutInFlight ??= _logoutOnce().whenComplete(() {
        _logoutInFlight = null;
      });

  Future<void> _logoutOnce() async {
    _logoutOwner ??= state.session;
    // Retain identity for cleanup only; scoped APIs reject checking phase.
    state = SessionState._(SessionPhase.checking, _logoutOwner);
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
    _logoutOwner = null;
  }
}

Future<void> _noop() async {}
