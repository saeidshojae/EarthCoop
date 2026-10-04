import '../auth/session_models.dart';
import '../logging/diagnostics.dart';
import 'push_registration_service.dart';

typedef PushRegistrationFactory = Future<PushRegistrationService> Function(
    NativeSession session);

/// Owns one registration service with credentials captured from one session.
class SessionPushCoordinator {
  SessionPushCoordinator(
      {required PushRegistrationFactory createRegistration,
      DiagnosticsSink diagnostics = const NoopDiagnosticsSink()})
      : _createRegistration = createRegistration,
        _diagnostics = diagnostics;

  final PushRegistrationFactory _createRegistration;
  final DiagnosticsSink _diagnostics;
  NativeSession? _session;
  PushRegistrationService? _service;
  Future<void>? _activation;
  int _epoch = 0;
  bool _disposed = false;

  Future<void> activate(NativeSession session) {
    if (_disposed) return Future<void>.value();
    if (_sameSession(session)) return _activation ?? retry();
    final epoch = ++_epoch;
    final previous = _service;
    _service = null;
    _session = session;
    // dispose() disables synchronously, before any old queued request can start.
    final cleanup = previous?.dispose();
    final activation = _activate(session, epoch, cleanup);
    _activation = activation;
    return activation;
  }

  Future<void> _activate(
      NativeSession session, int epoch, Future<void>? cleanup) async {
    try {
      await cleanup;
      if (_disposed || epoch != _epoch) return;
      final service = await _createRegistration(session);
      if (_disposed || epoch != _epoch) {
        await service.dispose();
        return;
      }
      _service = service;
      await service.initialize();
    } catch (_) {
      _diagnostics.record('push.session_setup_failed');
    } finally {
      if (epoch == _epoch) _activation = null;
    }
  }

  Future<void> retry() async {
    if (_disposed || _session == null) return;
    final pending = _activation;
    if (pending != null) return pending;
    final service = _service;
    if (service == null) {
      final session = _session!;
      _session = null;
      return activate(session);
    }
    try {
      await service.initialize();
    } catch (_) {
      _diagnostics.record('push.session_setup_failed');
    }
  }

  Future<void> disable() async {
    final service = _detach();
    await service?.disable();
  }

  Future<void> suspend() async {
    final service = _detach();
    await service?.dispose();
  }

  Future<void> dispose() async {
    _disposed = true;
    final service = _detach();
    await service?.dispose();
  }

  PushRegistrationService? _detach() {
    _epoch++;
    _session = null;
    _activation = null;
    final service = _service;
    _service = null;
    return service;
  }

  bool _sameSession(NativeSession session) =>
      _session?.token == session.token &&
      _session?.user.id == session.user.id &&
      _session?.device.id == session.device.id;
}
