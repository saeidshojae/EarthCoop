import 'dart:async';
import 'dart:io';

import 'package:earthcoop_mobile/app/app.dart';
import 'package:earthcoop_mobile/app/bootstrap/bootstrap_state.dart';
import 'package:earthcoop_mobile/app/runtime/mobile_app_runtime.dart';
import 'package:earthcoop_mobile/core/auth/session_controller.dart';
import 'package:earthcoop_mobile/core/auth/session_models.dart';
import 'package:earthcoop_mobile/core/auth/session_repository.dart';
import 'package:earthcoop_mobile/core/device/device_context.dart';
import 'package:earthcoop_mobile/core/deep_links/semantic_link.dart';
import 'package:earthcoop_mobile/features/auth/login_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('runtime is retained across rebuild and disposed with app',
      (tester) async {
    var created = 0;
    var disposed = 0;
    var foreground = 0;
    final session = SessionController(repository: _FakeSessionRepository())
      ..state = const SessionState.unauthenticated();
    final runtime = MobileAppRuntime(
        bootstrap: const BootstrapState.compatible(),
        sessionController: session,
        loginController: LoginController(
            sessionController: session,
            deviceContext: () => const DeviceContext(
                platform: 'android',
                appVersion: '1',
                locale: 'fa',
                timezone: 'Asia/Tehran',
                pushCapable: true)),
        onForeground: () async {
          foreground++;
        },
        onDispose: () async {
          disposed++;
        });
    Future<MobileAppRuntime> factory() async {
      created++;
      return runtime;
    }

    await tester.pumpWidget(EarthCoopApp(runtimeFactory: factory));
    await tester.pumpAndSettle();
    await tester.pumpWidget(EarthCoopApp(runtimeFactory: factory));
    await tester.pumpAndSettle();
    expect(created, 1);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await tester.pump();
    expect(foreground, 1);
    await tester.pumpWidget(const SizedBox());
    await tester.pump();
    expect(disposed, 1);
  });

  testWidgets(
      'late runtime completion after app removal disposes its resources',
      (tester) async {
    var disposed = 0;
    final pending = Completer<MobileAppRuntime>();
    final session = SessionController(repository: _FakeSessionRepository())
      ..state = const SessionState.unauthenticated();
    await tester.pumpWidget(EarthCoopApp(runtimeFactory: () => pending.future));
    await tester.pumpWidget(const SizedBox());
    pending.complete(MobileAppRuntime(
        bootstrap: const BootstrapState.compatible(),
        sessionController: session,
        loginController: LoginController(
            sessionController: session,
            deviceContext: () => const DeviceContext(
                platform: 'android',
                appVersion: '1',
                locale: 'fa',
                timezone: 'Asia/Tehran',
                pushCapable: true)),
        onDispose: () async {
          disposed++;
        }));
    await tester.pump();
    expect(disposed, 1);
  });

  testWidgets('push navigation uses live login and fresh bootstrap guards',
      (tester) async {
    final session = SessionController(repository: _FakeSessionRepository())
      ..state = const SessionState.unauthenticated();
    final runtime = MobileAppRuntime(
      bootstrap: const BootstrapState.compatible(),
      sessionController: session,
      loginController: LoginController(
          sessionController: session,
          deviceContext: () => const DeviceContext(
              platform: 'android',
              appVersion: '1',
              locale: 'fa',
              timezone: 'Asia/Tehran',
              pushCapable: true)),
      groupDetailBuilder: (_, id) => _Marker('group-$id-live'),
    );
    const link = SemanticLink(
        version: 1, route: 'group.detail', params: {'group_id': 42});
    await tester.pumpWidget(EarthCoopApp(runtimeFactory: () async => runtime));
    await tester.pumpAndSettle();
    expect(
        runtime.openSemanticLink(link,
            currentBootstrap: const BootstrapState.compatible()),
        isFalse);
    session.state = SessionState.authenticated(_session());
    expect(
        runtime.openSemanticLink(link,
            currentBootstrap: const BootstrapState.degradedOffline()),
        isFalse);
    expect(
        runtime.openSemanticLink(
            const SemanticLink(
                version: 1, route: 'https://evil.example', params: {}),
            currentBootstrap: const BootstrapState.compatible()),
        isFalse);
    expect(
        runtime.openSemanticLink(link,
            currentBootstrap: const BootstrapState.compatible()),
        isTrue);
    await tester.pumpAndSettle();
    expect(find.text('group-42-live'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    expect(
        runtime.openSemanticLink(link,
            currentBootstrap: const BootstrapState.compatible()),
        isFalse);
  });

  testWidgets(
      'native logout blocks pending push opens and returns to login after offline revoke',
      (tester) async {
    final repository = _PendingRevokeRepository();
    final session = SessionController(repository: repository)
      ..state = SessionState.authenticated(_session());
    final runtime = MobileAppRuntime(
      bootstrap: const BootstrapState.compatible(),
      sessionController: session,
      loginController: LoginController(
          sessionController: session,
          deviceContext: () => const DeviceContext(
              platform: 'android',
              appVersion: '1',
              locale: 'fa',
              timezone: 'Asia/Tehran',
              pushCapable: true)),
    );
    await tester.pumpWidget(EarthCoopApp(runtimeFactory: () async => runtime));
    await tester.pumpAndSettle();
    final logout = find.byKey(const Key('home-logout-action'));
    expect(logout, findsOneWidget);
    await tester.tap(logout);
    await tester.pump();
    expect(repository.revokeCalls, 1);
    expect(
        tester
            .widget<FilledButton>(find.byKey(const Key('home-groups-action')))
            .onPressed,
        isNull);
    expect(
        runtime.openSemanticLink(
            const SemanticLink(
                version: 1, route: 'group.detail', params: {'group_id': 42}),
            currentBootstrap: const BootstrapState.compatible()),
        isFalse);
    repository.release.completeError(StateError('server unreachable'));
    await tester.pumpAndSettle();
    expect(repository.cleared, isTrue);
    expect(find.byKey(const Key('login-submit')), findsOneWidget);
    expect(runtime.router.canPop(), isFalse);
    expect(find.byKey(const Key('home-route-screen')), findsNothing);
    await tester.pumpWidget(const SizedBox());
  });

  test('production runtime derives version from installed package metadata',
      () {
    final source = File(
      'lib/app/runtime/production_runtime.dart',
    ).readAsStringSync();

    expect(source, contains('PackageInfo.fromPlatform()'));
    expect(source, isNot(contains('EARTHCOOP_APP_VERSION')));
  });

  testWidgets(
    'executable root routes unauthenticated user through real login',
    (tester) async {
      final repository = _FakeSessionRepository();
      final session = SessionController(repository: repository);
      session.state = const SessionState.unauthenticated();
      final runtime = MobileAppRuntime(
        bootstrap: const BootstrapState.compatible(),
        sessionController: session,
        loginController: LoginController(
          sessionController: session,
          deviceContext: () => const DeviceContext(
            platform: 'android',
            appVersion: '0.1.0',
            locale: 'fa',
            timezone: 'Asia/Tehran',
            pushCapable: true,
          ),
        ),
        groupsBuilder: (context, openGroup) => const _Marker('groups-live'),
        groupDetailBuilder: (context, groupId) =>
            _Marker('group-$groupId-live'),
        notificationsBuilder: (context, openLink) =>
            const _Marker('notifications-live'),
      );

      await tester.pumpWidget(
        EarthCoopApp(runtimeFactory: () async => runtime),
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('login-submit')), findsOneWidget);
      expect(find.text('EarthCoop'), findsNothing);

      await tester.enterText(
        find.byKey(const Key('login-email')),
        'member@example.test',
      );
      await tester.enterText(find.byKey(const Key('login-password')), 'secret');
      await tester.tap(find.byKey(const Key('login-submit')));
      await tester.pumpAndSettle();

      expect(repository.loginCalls, 1);
      expect(find.byKey(const Key('home-route-screen')), findsOneWidget);
    },
  );

  testWidgets('executable root wires vertical-slice route builders', (
    tester,
  ) async {
    final repository = _FakeSessionRepository();
    final session = SessionController(repository: repository)
      ..state = SessionState.authenticated(_session());
    final runtime = MobileAppRuntime(
      bootstrap: const BootstrapState.compatible(),
      sessionController: session,
      loginController: LoginController(
        sessionController: session,
        deviceContext: () => const DeviceContext(
          platform: 'android',
          appVersion: '0.1.0',
          locale: 'fa',
          timezone: 'Asia/Tehran',
          pushCapable: true,
        ),
      ),
      groupsBuilder: (context, openGroup) => const _Marker('groups-live'),
      groupDetailBuilder: (context, groupId) => _Marker('group-$groupId-live'),
      notificationsBuilder: (context, openLink) =>
          const _Marker('notifications-live'),
    );

    await tester.pumpWidget(EarthCoopApp(runtimeFactory: () async => runtime));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('home-groups-action')));
    await tester.pumpAndSettle();
    expect(find.text('groups-live'), findsOneWidget);
  });
}

class _Marker extends StatelessWidget {
  const _Marker(this.label);

  final String label;

  @override
  Widget build(BuildContext context) => Scaffold(body: Text(label));
}

class _FakeSessionRepository implements SessionRepository {
  int loginCalls = 0;

  @override
  Future<NativeSession> login({
    required String email,
    required String password,
    required DeviceContext device,
  }) async {
    loginCalls += 1;
    return _session();
  }

  @override
  Future<NativeSession?> restoreAndValidate() async => null;

  @override
  Future<NativeSession> rotateCurrent() async => _session();

  @override
  Future<void> revokeCurrent() async {}

  @override
  Future<void> clearLocalCredentials() async {}
}

NativeSession _session() => NativeSession(
      token: 'test-token',
      expiresAt: DateTime.utc(2026, 10, 1),
      user: const SessionUser(id: 42, firstName: 'Test', lastName: 'Member'),
      device: const SessionDevice(
        id: 'device-1',
        platform: 'android',
        appVersion: '0.1.0',
        locale: 'fa',
        timezone: 'Asia/Tehran',
        pushCapable: true,
      ),
    );

class _PendingRevokeRepository extends _FakeSessionRepository {
  final release = Completer<void>();
  int revokeCalls = 0;
  bool cleared = false;
  @override
  Future<void> revokeCurrent() async {
    revokeCalls++;
    await release.future;
  }

  @override
  Future<void> clearLocalCredentials() async {
    cleared = true;
  }
}
