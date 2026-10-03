import '../../core/api/api_error.dart';
import '../../core/auth/session_controller.dart';
import '../../core/device/device_context.dart';

class LoginFailure implements Exception {
  const LoginFailure._(this.kind);

  const LoginFailure.invalidCredentials() : this._('invalid_credentials');

  const LoginFailure.unavailable() : this._('unavailable');

  final String kind;
}

class LoginController {
  LoginController({
    required SessionController sessionController,
    required DeviceContext Function() deviceContext,
  })  : _sessionController = sessionController,
        _deviceContext = deviceContext;

  final SessionController _sessionController;
  final DeviceContext Function() _deviceContext;

  Future<void> submit({required String email, required String password}) async {
    try {
      await _sessionController.login(
        email: email.trim(),
        password: password,
        device: _deviceContext(),
      );
    } on ApiFailure catch (failure) {
      if (failure.code == 'invalid_credentials') {
        throw const LoginFailure.invalidCredentials();
      }
      throw const LoginFailure.unavailable();
    }
  }
}
