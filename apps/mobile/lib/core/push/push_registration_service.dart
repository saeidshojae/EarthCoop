import 'dart:async';

import '../api/api_client.dart';
import '../logging/diagnostics.dart';
import 'push_provider_selector.dart';
import 'push_token_source.dart';

Object? _decodePushResponse(Object? json) => json;

enum PushRegistrationStatus { registered, awaitingToken, unavailable, disabled }

class PushRegistrationService {
  PushRegistrationService({
    required ApiClient apiClient,
    required String deviceId,
    required PushTokenSource? tokenSource,
    bool Function()? isCurrent,
    DiagnosticsSink diagnostics = const NoopDiagnosticsSink(),
  })  : _apiClient = apiClient,
        _deviceId = deviceId,
        _tokenSource = tokenSource,
        _isCurrent = isCurrent ?? (() => true),
        _diagnostics = diagnostics;

  final ApiClient _apiClient;
  final String _deviceId;
  final PushTokenSource? _tokenSource;
  final bool Function() _isCurrent;
  final DiagnosticsSink _diagnostics;

  Future<PushRegistrationStatus>? _initialization;
  StreamSubscription<String>? _tokenSubscription;
  Future<void> _registrationTail = Future<void>.value();
  Future<void>? _disposal;
  Future<void>? _disable;
  String? _latestToken;
  String? _lastRegisteredToken;
  bool _sourceInitialized = false;
  bool _disabled = false;
  int _tokenRevision = 0;

  Future<PushRegistrationStatus> initialize() {
    return _initialization ??= _initializeOnce().then((status) {
      _initialization = null;
      return status;
    }, onError: (Object error, StackTrace stack) {
      _initialization = null;
      Error.throwWithStackTrace(error, stack);
    });
  }

  Future<PushRegistrationStatus> _initializeOnce() async {
    if (_disabled || !_isCurrent()) return PushRegistrationStatus.disabled;
    final source = _tokenSource;
    if (source == null) return PushRegistrationStatus.unavailable;

    // Listen before obtaining the initial token so a rotation cannot be lost.
    _tokenSubscription ??= source.tokenChanges.listen((token) {
      if (_disabled || !_isCurrent() || token.isEmpty) return;
      _tokenRevision++;
      _latestToken = token;
      unawaited(_enqueueRegistration(token).catchError((Object _) {
        _recordFailure();
      }));
    }, onError: (Object _) => _recordFailure());

    if (!_sourceInitialized) {
      final revision = _tokenRevision;
      final initialToken = await source.initialize();
      _sourceInitialized = true;
      if (revision == _tokenRevision &&
          initialToken != null &&
          initialToken.isNotEmpty) {
        _latestToken = initialToken;
      }
    }
    if (_disabled || !_isCurrent()) return PushRegistrationStatus.disabled;

    final token = _latestToken;
    if (token == null || token.isEmpty) {
      _diagnostics.record('push.awaiting_token', data: {
        'provider': source.provider.wireName,
        'device_id': _deviceId,
      });
      return PushRegistrationStatus.awaitingToken;
    }
    await _enqueueRegistration(token);
    return _disabled || !_isCurrent()
        ? PushRegistrationStatus.disabled
        : PushRegistrationStatus.registered;
  }

  Future<void> _enqueueRegistration(String token) {
    if (_disabled ||
        !_isCurrent() ||
        token.isEmpty ||
        token == _lastRegisteredToken) {
      return _registrationTail;
    }
    final operation = _registrationTail.then((_) async {
      // Superseded tokens must not overwrite the provider's latest token.
      if (_disabled ||
          !_isCurrent() ||
          token != _latestToken ||
          token == _lastRegisteredToken) return;
      final source = _tokenSource;
      if (source == null) return;
      await _apiClient.put<Object?>('/devices/$_deviceId/push',
          data: {
            'provider': source.provider.wireName,
            'token': token,
          },
          decodeData: _decodePushResponse);
      _lastRegisteredToken = token;
      _diagnostics.record('push.registered', data: {
        'provider': source.provider.wireName,
        'device_id': _deviceId,
      });
    });
    // Keep serialization usable after failure; the caller still observes its error.
    _registrationTail = operation.catchError((Object _) {});
    return operation;
  }

  void _recordFailure() => _diagnostics
      .record('push.registration_failed', data: {'device_id': _deviceId});

  Future<void> dispose() {
    _disabled = true;
    return _disposal ??= _disposeLocal();
  }

  Future<void> _disposeLocal() async {
    await _tokenSubscription?.cancel();
    _tokenSubscription = null;
    await _tokenSource?.dispose();
  }

  Future<void> disable() => _disable ??= _disableOnce();

  Future<void> _disableOnce() async {
    await dispose();
    await _registrationTail;
    if (_tokenSource == null) return;
    await _apiClient.delete('/devices/$_deviceId/push');
    _diagnostics.record('push.disabled', data: {'device_id': _deviceId});
  }
}
