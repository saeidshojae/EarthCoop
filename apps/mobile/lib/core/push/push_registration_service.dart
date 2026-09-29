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
    DiagnosticsSink diagnostics = const NoopDiagnosticsSink(),
  })  : _apiClient = apiClient,
        _deviceId = deviceId,
        _tokenSource = tokenSource,
        _diagnostics = diagnostics;

  final ApiClient _apiClient;
  final String _deviceId;
  final PushTokenSource? _tokenSource;
  final DiagnosticsSink _diagnostics;

  Future<PushRegistrationStatus>? _initialization;
  StreamSubscription<String>? _tokenSubscription;
  Future<void> _registrationTail = Future<void>.value();
  String? _lastRegisteredToken;
  bool _disabled = false;

  Future<PushRegistrationStatus> initialize() =>
      _initialization ??= _initializeOnce();

  Future<PushRegistrationStatus> _initializeOnce() async {
    final source = _tokenSource;
    if (source == null) {
      return PushRegistrationStatus.unavailable;
    }

    final initialToken = await source.initialize();
    if (_disabled) return PushRegistrationStatus.disabled;

    _tokenSubscription ??= source.tokenChanges.listen((token) {
      _enqueueRegistration(token);
    });

    if (initialToken == null || initialToken.isEmpty) {
      _diagnostics.record(
        'push.awaiting_token',
        data: {'provider': source.provider.wireName, 'device_id': _deviceId},
      );
      return PushRegistrationStatus.awaitingToken;
    }

    await _enqueueRegistration(initialToken);
    return PushRegistrationStatus.registered;
  }

  Future<void> _enqueueRegistration(String token) {
    if (_disabled || token.isEmpty || token == _lastRegisteredToken) {
      return _registrationTail;
    }

    _registrationTail = _registrationTail.then((_) async {
      if (_disabled || token == _lastRegisteredToken) return;
      final source = _tokenSource;
      if (source == null) return;

      await _apiClient.put<Object?>(
        '/devices/$_deviceId/push',
        data: {
          'provider': source.provider.wireName,
          'token': token,
        },
        decodeData: _decodePushResponse,
      );
      _lastRegisteredToken = token;
      _diagnostics.record(
        'push.registered',
        data: {'provider': source.provider.wireName, 'device_id': _deviceId},
      );
    });
    return _registrationTail;
  }

  Future<void> disable() async {
    if (_disabled) return;
    _disabled = true;

    await _tokenSubscription?.cancel();
    _tokenSubscription = null;
    try {
      await _registrationTail;
    } catch (_) {
      // A failed prior registration must not block local/server disable cleanup.
    }

    try {
      await _apiClient.delete('/devices/$_deviceId/push');
      _diagnostics.record(
        'push.disabled',
        data: {'device_id': _deviceId},
      );
    } finally {
      await _tokenSource?.dispose();
    }
  }
}
