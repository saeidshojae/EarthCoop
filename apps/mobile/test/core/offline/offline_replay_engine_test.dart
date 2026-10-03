import 'package:earthcoop_mobile/app/bootstrap/bootstrap_state.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/core/auth/session_controller.dart';
import 'package:earthcoop_mobile/core/auth/session_models.dart';
import 'package:earthcoop_mobile/core/offline/offline_operation.dart';
import 'package:earthcoop_mobile/core/offline/offline_operation_registry.dart';
import 'package:earthcoop_mobile/core/offline/offline_queue_repository.dart';
import 'package:earthcoop_mobile/core/offline/offline_replay_engine.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('queue coalesces duplicate notification read and preserves original key',
      () async {
    final queue = MemoryOfflineQueueRepository();
    final first = OfflineOperation.notificationRead(
      notificationId: 'n-1',
      idempotencyKey: 'idem-original',
      createdAt: DateTime.utc(2026, 9, 29),
    );
    final duplicate = OfflineOperation.notificationRead(
      notificationId: 'n-1',
      idempotencyKey: 'idem-other',
      createdAt: DateTime.utc(2026, 9, 29, 0, 1),
    );

    await queue.enqueue(first);
    await queue.enqueue(duplicate);

    final pending = await queue.pending();
    expect(pending, hasLength(1));
    expect(pending.single.idempotencyKey, 'idem-original');
    expect(pending.single.payloadHash, first.payloadHash);
    expect(pending.single.clientSequence, 1);
  });

  test('registry rejects operations outside the explicit allowlist', () {
    final registry = OfflineOperationRegistry.notificationReadOnly(
      markNotificationRead: (_) async {},
    );
    final unknown = OfflineOperation(
      idempotencyKey: 'idem-x',
      createdAt: DateTime.utc(2026, 9, 29),
      resource: 'najm-bahar',
      operation: 'transfer',
      payload: const {'amount': 1},
      clientSequence: 1,
    );

    expect(() => registry.executorFor(unknown), throwsUnsupportedError);
  });

  test('replay keeps exact key and removes successful operation', () async {
    final queue = MemoryOfflineQueueRepository();
    final operation = OfflineOperation.notificationRead(
      notificationId: 'n-1',
      idempotencyKey: 'idem-keep-me',
      createdAt: DateTime.utc(2026, 9, 29),
    );
    await queue.enqueue(operation);
    final seen = <OfflineOperation>[];
    final engine = OfflineReplayEngine(
      queue: queue,
      registry: OfflineOperationRegistry.notificationReadOnly(
        markNotificationRead: (item) async => seen.add(item),
      ),
      bootstrapState: () => const BootstrapState.compatible(),
      sessionState: authenticatedSession,
    );

    await engine.replayEligible();

    expect(seen.single.idempotencyKey, 'idem-keep-me');
    expect(await queue.pending(), isEmpty);
  });

  test('retryable failure remains queued and increments attempt count',
      () async {
    final queue = MemoryOfflineQueueRepository();
    await queue.enqueue(readOperation());
    final engine = OfflineReplayEngine(
      queue: queue,
      registry: OfflineOperationRegistry.notificationReadOnly(
        markNotificationRead: (_) async => throw const ApiFailure(
          code: 'network_error',
          message: 'offline',
          retryable: true,
        ),
      ),
      bootstrapState: () => const BootstrapState.compatible(),
      sessionState: authenticatedSession,
    );

    await engine.replayEligible();

    final pending = await queue.pending();
    expect(pending.single.attemptCount, 1);
    expect(pending.single.lastErrorCode, 'network_error');
    expect(pending.single.state, OfflineOperationState.pending);
  });

  test('non-retryable conflict stops operation without retry loop', () async {
    final queue = MemoryOfflineQueueRepository();
    await queue.enqueue(readOperation());
    var calls = 0;
    final engine = OfflineReplayEngine(
      queue: queue,
      registry: OfflineOperationRegistry.notificationReadOnly(
        markNotificationRead: (_) async {
          calls += 1;
          throw const ApiFailure(
            code: 'conflict',
            message: 'conflict',
            retryable: false,
            httpStatus: 409,
          );
        },
      ),
      bootstrapState: () => const BootstrapState.compatible(),
      sessionState: authenticatedSession,
    );

    await engine.replayEligible();
    await engine.replayEligible();

    final all = await queue.all();
    expect(calls, 1);
    expect(all.single.state, OfflineOperationState.blocked);
    expect(all.single.lastErrorCode, 'conflict');
  });

  test('revoked session and stale bootstrap both block replay', () async {
    final queue = MemoryOfflineQueueRepository();
    await queue.enqueue(readOperation());
    var calls = 0;
    final registry = OfflineOperationRegistry.notificationReadOnly(
      markNotificationRead: (_) async => calls += 1,
    );

    final staleEngine = OfflineReplayEngine(
      queue: queue,
      registry: registry,
      bootstrapState: () => const BootstrapState.degradedOffline(),
      sessionState: authenticatedSession,
    );
    await staleEngine.replayEligible();

    final revokedEngine = OfflineReplayEngine(
      queue: queue,
      registry: registry,
      bootstrapState: () => const BootstrapState.compatible(),
      sessionState: () => const SessionState.unauthenticated(),
    );
    await revokedEngine.replayEligible();

    expect(calls, 0);
    expect(await queue.pending(), hasLength(1));
  });

  test('required update blocks replay even with authenticated session',
      () async {
    final queue = MemoryOfflineQueueRepository();
    await queue.enqueue(readOperation());
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
  });

  test('queue clearAll removes user-owned offline intent on logout cleanup',
      () async {
    final queue = MemoryOfflineQueueRepository();
    await queue.enqueue(readOperation());

    await queue.clearAll();

    expect(await queue.all(), isEmpty);
  });
}

OfflineOperation readOperation() => OfflineOperation.notificationRead(
      notificationId: 'n-1',
      idempotencyKey: 'idem-1',
      createdAt: DateTime.utc(2026, 9, 29),
    );

SessionState authenticatedSession() => SessionState.authenticated(
      NativeSession(
        token: 'secret-token',
        expiresAt: DateTime.utc(2026, 10, 29),
        user: const SessionUser(
          id: 7,
          firstName: 'کاربر',
          lastName: 'آزمایشی',
        ),
        device: const SessionDevice(
          id: 'device-1',
          platform: 'android',
          appVersion: '0.1.0',
          locale: 'fa',
          timezone: 'Asia/Tehran',
          pushCapable: true,
        ),
      ),
    );
