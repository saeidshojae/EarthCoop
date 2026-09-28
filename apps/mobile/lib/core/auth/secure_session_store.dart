import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract interface class SecureSessionStore {
  Future<String?> readToken();

  Future<String?> readDeviceId();

  Future<void> write({required String token, required String deviceId});

  Future<void> clear();
}

class FlutterSecureSessionStore implements SecureSessionStore {
  FlutterSecureSessionStore({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const _tokenKey = 'earthcoop.native.token';
  static const _deviceIdKey = 'earthcoop.native.device_id';

  final FlutterSecureStorage _storage;

  @override
  Future<String?> readToken() => _storage.read(key: _tokenKey);

  @override
  Future<String?> readDeviceId() => _storage.read(key: _deviceIdKey);

  @override
  Future<void> write({required String token, required String deviceId}) async {
    await _storage.write(key: _deviceIdKey, value: deviceId);
    await _storage.write(key: _tokenKey, value: token);
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _deviceIdKey);
  }
}
