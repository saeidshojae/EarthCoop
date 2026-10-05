import 'dart:async';

import 'package:earthcoop_mobile/core/auth/session_controller.dart';
import 'package:earthcoop_mobile/core/auth/session_models.dart';
import 'package:earthcoop_mobile/core/auth/session_repository.dart';
import 'package:earthcoop_mobile/core/device/device_context.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('SessionController', () {
    test('authenticated hook follows restore login and credential rotation',
        () async {
      final seen = <String>[];
      final repository = FakeSessionRepository(
        restored: sampleSession(token: 'restored'),
        loginResult: sampleSession(token: 'login'),
        rotateResult: sampleSession(token: 'rotated'),
      );
      final controller = SessionController(
          repository: repository,
          onAuthenticated: (session) async => seen.add(session.token));
      await controller.restore();
      await controller.login(
          email: 'member@example.test',
          password: 'secret',
          device: sampleDeviceContext());
      await controller.rotate();
      expect(seen, ['restored', 'login', 'rotated']);
    });

    test('pending or failed push hook cannot delay or reject authentication',
        () async {
      final pending = Completer<void>();
      final controller = SessionController(
        repository: FakeSessionRepository(restored: sampleSession()),
        onAuthenticated: (_) => pending.future,
      );
      await controller.restore().timeout(const Duration(seconds: 1));
      expect(controller.state.phase, SessionPhase.authenticated);
      pending.completeError(StateError('provider unavailable'));
      await Future<void>.delayed(Duration.zero);
      expect(controller.state.phase, SessionPhase.authenticated);
    });

    test(
        'restore validates stored credentials before entering authenticated state',
        () async {
      final repository = FakeSessionRepository(
        restored: sampleSession(token: 'restored-token'),
      );
      final controller = SessionController(repository: repository);

      await controller.restore();

      expect(repository.calls, ['restore']);
      expect(controller.state.phase, SessionPhase.authenticated);
      expect(controller.state.session!.token, 'restored-token');
    });

    test(
        'revoked stored session clears local credentials and becomes unauthenticated',
        () async {
      final repository = FakeSessionRepository(restored: null);
      final controller = SessionController(repository: repository);

      await controller.restore();

      expect(repository.calls, ['restore']);
      expect(controller.state.phase, SessionPhase.unauthenticated);
    });

    test('login and rotate replace the in-memory session atomically', () async {
      final repository = FakeSessionRepository(
        loginResult: sampleSession(token: 'login-token'),
        rotateResult: sampleSession(token: 'rotated-token'),
      );
      final controller = SessionController(repository: repository);

      await controller.login(
        email: 'member@example.test',
        password: 'secret-password',
        device: sampleDeviceContext(),
      );
      expect(controller.state.session!.token, 'login-token');

      await controller.rotate();
      expect(controller.state.session!.token, 'rotated-token');
      expect(repository.calls, ['login', 'rotate']);
    });

    test('pending logout closes authorization immediately and retains cleanup identity', () async {
      final repository = PendingRevokeRepository();
      NativeSession? cleanupSession;
      late final SessionController controller;
      controller = SessionController(
        repository: repository,
        clearUserScopedLocalState: () async {
          cleanupSession = controller.state.session;
        },
      );
      controller.state = SessionState.authenticated(sampleSession());
      final loggingOut = controller.logout();
      expect(controller.state.phase, isNot(SessionPhase.authenticated));
      expect(controller.state.session?.user.id, 42);
      repository.release.complete();
      await loggingOut;
      expect(cleanupSession?.user.id, 42);
      expect(controller.state.session, isNull);
    });

    test('repeated logout during revoke shares one cleanup', () async {
      final repository = PendingRevokeRepository();
      var clears = 0;
      final controller = SessionController(
        repository: repository,
        clearUserScopedLocalState: () async { clears++; },
      )..state = SessionState.authenticated(sampleSession());
      final first = controller.logout();
      final second = controller.logout();
      repository.release.complete();
      await Future.wait([first, second]);
      expect(repository.revokeCalls, 1);
      expect(clears, 1);
    });

    test('cleanup retry retains the outgoing account after a local failure', () async {
      final identities = <int?>[];
      var clears = 0;
      late final SessionController controller;
      controller = SessionController(
        repository: FakeSessionRepository(),
        clearUserScopedLocalState: () async {
          identities.add(controller.state.session?.user.id);
          if (++clears == 1) throw StateError('local storage unavailable');
        },
      )..state = SessionState.authenticated(sampleSession());
      await expectLater(controller.logout(), throwsStateError);
      expect(controller.state.phase, SessionPhase.unauthenticated);
      await controller.logout();
      expect(identities, [42, 42]);
      expect(controller.state.session, isNull);
    });

    test('logout preserves cleanup ordering when server revoke succeeds',
        () async {
      final events = <String>[];
      final repository = FakeSessionRepository(
        restored: sampleSession(),
        events: events,
      );
      final controller = SessionController(
        repository: repository,
        disablePush: () async => events.add('push-disable'),
        clearUserScopedLocalState: () async => events.add('local-clear'),
      );
      await controller.restore();
      events.clear();

      await controller.logout();

      expect(events, [
        'server-revoke',
        'push-disable',
        'credentials-clear',
        'local-clear',
      ]);
      expect(controller.state.phase, SessionPhase.unauthenticated);
    });

    test(
        'logout still clears sensitive local state when server revoke is unreachable',
        () async {
      final events = <String>[];
      final repository = FakeSessionRepository(
        restored: sampleSession(),
        events: events,
        revokeThrows: true,
      );
      final controller = SessionController(
        repository: repository,
        disablePush: () async => events.add('push-disable'),
        clearUserScopedLocalState: () async => events.add('local-clear'),
      );
      await controller.restore();
      events.clear();

      await controller.logout();

      expect(events, [
        'server-revoke',
        'push-disable',
        'credentials-clear',
        'local-clear',
      ]);
      expect(controller.state.phase, SessionPhase.unauthenticated);
    });
  });
}

class FakeSessionRepository implements SessionRepository {
  FakeSessionRepository({
    this.restored,
    this.loginResult,
    this.rotateResult,
    this.events,
    this.revokeThrows = false,
  });

  final NativeSession? restored;
  final NativeSession? loginResult;
  final NativeSession? rotateResult;
  final List<String>? events;
  final bool revokeThrows;
  final List<String> calls = [];

  @override
  Future<void> clearLocalCredentials() async {
    calls.add('clear');
    events?.add('credentials-clear');
  }

  @override
  Future<NativeSession> login({
    required String email,
    required String password,
    required DeviceContext device,
  }) async {
    calls.add('login');
    return loginResult!;
  }

  @override
  Future<void> revokeCurrent() async {
    calls.add('revoke');
    events?.add('server-revoke');
    if (revokeThrows) throw StateError('offline');
  }

  @override
  Future<NativeSession?> restoreAndValidate() async {
    calls.add('restore');
    return restored;
  }

  @override
  Future<NativeSession> rotateCurrent() async {
    calls.add('rotate');
    return rotateResult!;
  }
}

DeviceContext sampleDeviceContext() => const DeviceContext(
      platform: 'android',
      appVersion: '1.0.0',
      locale: 'fa',
      timezone: 'Asia/Tehran',
      pushCapable: true,
    );

NativeSession sampleSession({String token = 'token'}) => NativeSession(
      token: token,
      expiresAt: DateTime.utc(2026, 10, 1),
      user: const SessionUser(id: 42, firstName: 'Test', lastName: 'Member'),
      device: const SessionDevice(
        id: 'device-public-id',
        platform: 'android',
        appVersion: '1.0.0',
        locale: 'fa',
        timezone: 'Asia/Tehran',
        pushCapable: true,
      ),
    );

class PendingRevokeRepository extends FakeSessionRepository {
  final release = Completer<void>();
  int revokeCalls = 0;
  @override
  Future<void> revokeCurrent() async {
    revokeCalls++;
    await release.future;
  }
}
