import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

import 'fcm_client_configuration.dart';
import 'push_provider_selector.dart';
import 'push_token_source.dart';

abstract interface class FcmTokenRuntime {
  Future<void> initialize(FirebaseOptions options);
  Stream<String> get tokenChanges;
  Future<String?> token();
}

class FirebaseFcmTokenRuntime implements FcmTokenRuntime {
  const FirebaseFcmTokenRuntime();

  @override
  Future<void> initialize(FirebaseOptions options) async {
    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp(options: options);
    } else {
      final active = Firebase.app().options;
      if (active.appId != options.appId ||
          active.projectId != options.projectId) {
        throw StateError(
            'Firebase client configuration does not match initialized app');
      }
    }
  }

  @override
  Stream<String> get tokenChanges => FirebaseMessaging.instance.onTokenRefresh;

  @override
  Future<String?> token() => FirebaseMessaging.instance.getToken();
}

class FcmPushTokenSource implements PushTokenSource {
  FcmPushTokenSource({FirebaseOptions? options, FcmTokenRuntime? runtime})
      : _options = options ?? fcmOptionsFromEnvironment(),
        _runtime = runtime ?? const FirebaseFcmTokenRuntime();

  final FirebaseOptions? _options;
  final FcmTokenRuntime _runtime;
  final StreamController<String> _changes =
      StreamController<String>.broadcast();
  StreamSubscription<String>? _subscription;
  Future<String?>? _initialization;
  Future<void>? _disposal;
  bool _disposed = false;

  @override
  PushProvider get provider => PushProvider.fcm;

  @override
  Future<String?> initialize() {
    return _initialization ??= _initializeOnce().whenComplete(() {
      _initialization = null;
    });
  }

  Future<String?> _initializeOnce() async {
    final options = _options;
    if (_disposed || options == null) return null;
    await _runtime.initialize(options);
    if (_disposed) return null;
    _subscription ??= _runtime.tokenChanges.listen((token) {
      if (!_disposed) _changes.add(token);
    }, onError: (Object error, StackTrace stack) {
      if (!_disposed) _changes.addError(error, stack);
    });
    final token = await _runtime.token();
    return _disposed ? null : token;
  }

  // Safe before Firebase.initializeApp; the SDK stream is attached afterwards.
  @override
  Stream<String> get tokenChanges => _changes.stream;

  @override
  Future<void> dispose() {
    _disposed = true;
    return _disposal ??= _disposeOnce();
  }

  Future<void> _disposeOnce() async {
    await _subscription?.cancel();
    await _changes.close();
  }
}
