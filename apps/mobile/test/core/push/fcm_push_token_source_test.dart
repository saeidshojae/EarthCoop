import 'dart:async';

import 'package:earthcoop_mobile/core/push/fcm_push_token_source.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter_test/flutter_test.dart';

const options = FirebaseOptions(
    apiKey: 'synthetic-client-key',
    appId: '1:123:android:a',
    messagingSenderId: '123',
    projectId: 'earthcoop-test');

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('FCM token changes can be observed before Firebase initialization',
      () async {
    final source = FcmPushTokenSource();
    expect(() => source.tokenChanges, returnsNormally);
    await source.dispose();
  });

  test(
      'SDK initializes before its refresh stream is accessed and rotations arrive',
      () async {
    final runtime = FakeRuntime();
    final source = FcmPushTokenSource(options: options, runtime: runtime);
    final next = source.tokenChanges.first;
    expect(await source.initialize(), 'initial');
    runtime.changes.add('rotated');
    expect(await next, 'rotated');
    await source.dispose();
    await runtime.changes.close();
  });

  test('failed SDK setup can be retried without a cached failed future',
      () async {
    final runtime = FakeRuntime()..failOnce = true;
    final source = FcmPushTokenSource(options: options, runtime: runtime);
    await expectLater(source.initialize(), throwsStateError);
    expect(await source.initialize(), 'initial');
    await source.dispose();
    await runtime.changes.close();
  });

  test('notification permission is checked before acquiring a token', () async {
    final runtime = FakeRuntime();
    runtime.tokenHook = () async =>
        runtime.permissionRequested ? 'authorized-token' : 'unapproved-token';
    final source = FcmPushTokenSource(options: options, runtime: runtime);
    expect(await source.initialize(), 'authorized-token');
    await source.dispose();
    await runtime.changes.close();
  });

  test('denied notification permission leaves FCM unavailable', () async {
    final runtime = FakeRuntime()..permissionGranted = false;
    final source = FcmPushTokenSource(options: options, runtime: runtime);
    expect(await source.initialize(), isNull);
    expect(runtime.tokenCalls, 0);
    await source.dispose();
    await runtime.changes.close();
  });

  test('permission granted after denial can acquire a token on retry', () async {
    final runtime = FakeRuntime()..permissionGranted = false;
    final source = FcmPushTokenSource(options: options, runtime: runtime);
    expect(await source.initialize(), isNull);
    runtime.permissionGranted = true;
    expect(await source.initialize(), 'initial');
    expect(runtime.tokenCalls, 1);
    await source.dispose();
    await runtime.changes.close();
  });

  test('disposal during permission prompt prevents late token acquisition',
      () async {
    final entered = Completer<void>();
    final pending = Completer<bool>();
    final runtime = FakeRuntime()
      ..permissionHook = () {
        entered.complete();
        return pending.future;
      };
    final source = FcmPushTokenSource(options: options, runtime: runtime);
    final initialization = source.initialize();
    await entered.future;
    await source.dispose();
    pending.complete(true);
    expect(await initialization, isNull);
    expect(runtime.tokenCalls, 0);
    await runtime.changes.close();
  });

  test('disposal during SDK setup prevents late token acquisition', () async {
    final entered = Completer<void>();
    final pending = Completer<void>();
    final runtime = FakeRuntime()
      ..initializeHook = () {
        entered.complete();
        return pending.future;
      }
      ..tokenHook =
          () => throw StateError('disposed source must not acquire a token');
    final source = FcmPushTokenSource(options: options, runtime: runtime);
    final initialization = source.initialize();
    await entered.future;
    await source.dispose();
    pending.complete();
    expect(await initialization, isNull);
    await runtime.changes.close();
  });
}

class FakeRuntime implements FcmTokenRuntime {
  final changes = StreamController<String>.broadcast();
  bool ready = false;
  bool failOnce = false;
  bool permissionRequested = false;
  bool permissionGranted = true;
  int tokenCalls = 0;
  Future<void> Function()? initializeHook;
  Future<bool> Function()? permissionHook;
  Future<String?> Function()? tokenHook;

  @override
  Future<void> initialize(FirebaseOptions options) async {
    if (failOnce) {
      failOnce = false;
      throw StateError('temporary SDK failure');
    }
    await initializeHook?.call();
    ready = true;
  }

  @override
  Stream<String> get tokenChanges {
    if (!ready) throw StateError('SDK not initialized');
    return changes.stream;
  }

  @override
  Future<bool> requestNotificationPermission() async {
    if (!ready) throw StateError('SDK not initialized');
    permissionRequested = true;
    return permissionHook == null ? permissionGranted : await permissionHook!();
  }

  @override
  Future<String?> token() async {
    tokenCalls++;
    return tokenHook == null ? 'initial' : await tokenHook!();
  }
}
