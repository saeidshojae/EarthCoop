import 'package:earthcoop_mobile/app/bootstrap/bootstrap_state.dart';
import 'package:earthcoop_mobile/core/auth/session_controller.dart';
import 'package:earthcoop_mobile/core/auth/session_models.dart';
import 'package:earthcoop_mobile/core/offline/offline_operation.dart';
import 'package:earthcoop_mobile/core/offline/offline_operation_registry.dart';
import 'package:earthcoop_mobile/core/offline/offline_queue_repository.dart';
import 'package:earthcoop_mobile/core/offline/offline_replay_engine.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('queued notification read waits for fresh bootstrap after reconnect',
      () async {
    final queue = MemoryOfflineQueueRepository();
    final operation = OfflineOperation.notificationRead(
      notificationId: 'notification-1',
      idempotencyKey: 'acceptance-idempotency-key',
      createdAt: DateTime.utc(2026, 9, 29, 10),
    );
    await queue.enqueue(operation);

    var bootstrap = const BootstrapState.degradedOffline();
    final replayed = <OfflineOperation>[];
    final engine = OfflineReplayEngine(
      queue: queue,
      registry: OfflineOperationRegistry.notificationReadOnly(
        markNotificationRead: (item) async => replayed.add(item),
      ),
      bootstrapState: () => bootstrap,
      sessionState: authenticatedSession,
    );

    await engine.replayEligible();
    expect(replayed, isEmpty);
    expect(await queue.pending(), hasLength(1));

    bootstrap = const BootstrapState.compatible();
    await engine.replayEligible();

    expect(replayed, hasLength(1));
    expect(replayed.single.idempotencyKey, 'acceptance-idempotency-key');
    expect(replayed.single.payload['notification_id'], 'notification-1');
    expect(await queue.pending(), isEmpty);
  });

  test('required update keeps reconnect replay blocked', () async {
    final queue = MemoryOfflineQueueRepository();
    await queue.enqueue(
      OfflineOperation.notificationRead(
        notificationId: 'notification-2',
        idempotencyKey: 'required-update-idem',
        createdAt: DateTime.utc(2026, 9, 29, 10, 1),
      ),
    );
    var calls = 0;
    final engine = OfflineReplayEngine(
      queue: queue,
      registry: OfflineOperationRegistry.notificationReadOnly(
        markNotificationRead: (_) async => calls += 1,
      ),
      bootstrapState: () => const BootstrapState.requiredUpdate(),
      sessionState: authenticatedSession,
    );

    await engine.replayEligible();

    expect(calls, 0);
    expect(await queue.pending(), hasLength(1));
  });
}

SessionState authenticatedSession() => SessionState.authenticated(
      NativeSession(
        token: 'acceptance-session-token',
        expiresAt: DateTime.utc(2026, 10, 29),
        user: const SessionUser(
          id: 42,
          firstName: 'Test',
          lastName: 'Member',
        ),
        device: const SessionDevice(
          id: 'device-acceptance',
          platform: 'android',
          appVersion: '0.1.0',
          locale: 'fa',
          timezone: 'Asia/Tehran',
          pushCapable: true,
        ),
      ),
    );
