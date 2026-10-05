import '../api/api_client.dart';
import '../api/api_error.dart';
import '../api/request_context.dart';
import '../device/device_context.dart';
import 'secure_session_store.dart';
import 'session_models.dart';

abstract interface class SessionRepository {
  Future<NativeSession> login({
    required String email,
    required String password,
    required DeviceContext device,
  });

  Future<NativeSession?> restoreAndValidate();

  Future<NativeSession> rotateCurrent();

  Future<void> revokeCurrent();

  Future<void> clearLocalCredentials();
}

class ApiSessionRepository implements SessionRepository {
  ApiSessionRepository({
    required ApiClient apiClient,
    required SecureSessionStore secureStore,
  })  : _apiClient = apiClient,
        _secureStore = secureStore;

  final ApiClient _apiClient;
  final SecureSessionStore _secureStore;

  @override
  Future<NativeSession> login({
    required String email,
    required String password,
    required DeviceContext device,
  }) async {
    final result = await _apiClient.post<NativeSession>(
      '/auth/session',
      data: {
        'email': email,
        'password': password,
        ...device.toJson(),
      },
      decodeData: (json) => NativeSession.fromJson(json, requireToken: true),
    );
    await _secureStore.write(
      token: result.data.token,
      deviceId: result.data.device.id,
    );
    return result.data;
  }

  @override
  Future<NativeSession?> restoreAndValidate() async {
    final local = await _readLocalBinding();
    if (local == null) {
      await _secureStore.clear();
      return null;
    }

    try {
      final result = await _apiClient.get<NativeSession>(
        '/auth/session',
        context: RequestContext(deviceId: local.deviceId),
        decodeData: (json) => NativeSession.fromJson(
          json,
          fallbackToken: local.token,
        ),
      );
      if (result.data.device.id != local.deviceId) {
        await _secureStore.clear();
        return null;
      }
      return result.data;
    } on ApiFailure catch (failure) {
      if (_invalidatesLocalSession(failure)) {
        await _secureStore.clear();
        return null;
      }
      rethrow;
    }
  }

  @override
  Future<NativeSession> rotateCurrent() async {
    final local = await _requireLocalBinding();
    final result = await _apiClient.post<NativeSession>(
      '/auth/session/rotate',
      context: RequestContext(deviceId: local.deviceId),
      decodeData: (json) => NativeSession.fromJson(json, requireToken: true),
    );
    if (result.data.device.id != local.deviceId) {
      throw const FormatException(
          'Rotated session returned a different device.');
    }
    await _secureStore.write(
      token: result.data.token,
      deviceId: result.data.device.id,
    );
    return result.data;
  }

  @override
  Future<void> revokeCurrent() async {
    final local = await _requireLocalBinding();
    await _apiClient.delete(
      '/auth/session',
      context: RequestContext(deviceId: local.deviceId),
    );
  }

  @override
  Future<void> clearLocalCredentials() => _secureStore.clear();

  Future<_LocalBinding?> _readLocalBinding() async {
    final token = await _secureStore.readToken();
    final deviceId = await _secureStore.readDeviceId();
    if (token == null ||
        token.isEmpty ||
        deviceId == null ||
        deviceId.isEmpty) {
      return null;
    }
    return _LocalBinding(token: token, deviceId: deviceId);
  }

  Future<_LocalBinding> _requireLocalBinding() async {
    final binding = await _readLocalBinding();
    if (binding == null) {
      throw const ApiFailure(
        code: 'unauthenticated',
        message: 'No local native session is available.',
        retryable: false,
      );
    }
    return binding;
  }

  bool _invalidatesLocalSession(ApiFailure failure) =>
      failure.code == 'unauthenticated' ||
      failure.code == 'device_mismatch' ||
      failure.code == 'device_revoked';
}

class _LocalBinding {
  const _LocalBinding({required this.token, required this.deviceId});

  final String token;
  final String deviceId;
}
