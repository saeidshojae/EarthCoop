import 'package:earthcoop_mobile/app/bootstrap/app_bootstrap_service.dart';
import 'package:earthcoop_mobile/app/bootstrap/bootstrap_models.dart';
import 'package:earthcoop_mobile/app/bootstrap/bootstrap_snapshot_store.dart';
import 'package:earthcoop_mobile/app/bootstrap/bootstrap_state.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('AppBootstrapService', () {
    test('maps fresh compatible, recommended and required server outcomes',
        () async {
      for (final entry in <(BootstrapPayload, BootstrapDecision)>[
        (
          payload(updateRequired: false, updateRecommended: false),
          BootstrapDecision.compatible,
        ),
        (
          payload(updateRequired: false, updateRecommended: true),
          BootstrapDecision.recommendedUpdate,
        ),
        (
          payload(updateRequired: true, updateRecommended: true),
          BootstrapDecision.requiredUpdate,
        ),
      ]) {
        final store = MemoryBootstrapSnapshotStore();
        final service = AppBootstrapService(
          fetchRemote: () async => entry.$1,
          snapshotStore: store,
          clock: fixedClock,
        );

        final state = await service.start();

        expect(state.decision, entry.$2);
        expect(state.isFresh, isTrue);
        expect(
          state.canReplayQueuedMutations,
          entry.$2 != BootstrapDecision.requiredUpdate,
        );
        expect(await store.read(), isNotNull);
      }
    });

    test(
        'first-ever offline launch is unavailable and cannot enter product shell',
        () async {
      final service = AppBootstrapService(
        fetchRemote: transientFailure,
        snapshotStore: MemoryBootstrapSnapshotStore(),
        clock: fixedClock,
      );

      final state = await service.start();

      expect(state.decision, BootstrapDecision.unavailable);
      expect(state.allowsProductShell, isFalse);
      expect(state.canReplayQueuedMutations, isFalse);
    });

    test(
        'previous compatible bootstrap plus authenticated history may degrade offline',
        () async {
      final store = MemoryBootstrapSnapshotStore(
        seed: snapshot(
          hadAuthenticatedSession: true,
          updateRequired: false,
        ),
      );
      final service = AppBootstrapService(
        fetchRemote: transientFailure,
        snapshotStore: store,
        clock: fixedClock,
      );

      final state = await service.start();

      expect(state.decision, BootstrapDecision.degradedOffline);
      expect(state.allowsProductShell, isTrue);
      expect(state.allowsProtectedNetwork, isFalse);
      expect(state.canReplayQueuedMutations, isFalse);
      expect(state.requiresFreshBootstrap, isTrue);
    });

    test(
        'offline snapshot without authenticated history is not enough to degrade',
        () async {
      final store = MemoryBootstrapSnapshotStore(
        seed: snapshot(
          hadAuthenticatedSession: false,
          updateRequired: false,
        ),
      );
      final service = AppBootstrapService(
        fetchRemote: transientFailure,
        snapshotStore: store,
        clock: fixedClock,
      );

      final state = await service.start();

      expect(state.decision, BootstrapDecision.unavailable);
    });

    test(
        'a previously required-update snapshot never authorizes degraded start',
        () async {
      final store = MemoryBootstrapSnapshotStore(
        seed: snapshot(
          hadAuthenticatedSession: true,
          updateRequired: true,
        ),
      );
      final service = AppBootstrapService(
        fetchRemote: transientFailure,
        snapshotStore: store,
        clock: fixedClock,
      );

      final state = await service.start();

      expect(state.decision, BootstrapDecision.unavailable);
      expect(state.canReplayQueuedMutations, isFalse);
    });

    test(
        'malformed bootstrap fails closed instead of silently using stale cache',
        () async {
      final store = MemoryBootstrapSnapshotStore(
        seed: snapshot(
          hadAuthenticatedSession: true,
          updateRequired: false,
        ),
      );
      final service = AppBootstrapService(
        fetchRemote: () => throw const ApiFailure(
          code: 'malformed_response',
          message: 'Malformed bootstrap.',
          retryable: false,
        ),
        snapshotStore: store,
        clock: fixedClock,
      );

      final state = await service.start();

      expect(state.decision, BootstrapDecision.unavailable);
      expect(state.requiresFreshBootstrap, isTrue);
    });

    test(
        'reconnect requires a fresh successful bootstrap before replay resumes',
        () async {
      final store = MemoryBootstrapSnapshotStore(
        seed: snapshot(
          hadAuthenticatedSession: true,
          updateRequired: false,
        ),
      );
      var online = false;
      final service = AppBootstrapService(
        fetchRemote: () async {
          if (!online) return transientFailure();
          return payload(updateRequired: false, updateRecommended: false);
        },
        snapshotStore: store,
        clock: fixedClock,
      );

      final degraded = await service.start();
      expect(degraded.decision, BootstrapDecision.degradedOffline);
      expect(degraded.canReplayQueuedMutations, isFalse);

      online = true;
      final refreshed = await service.start();
      expect(refreshed.decision, BootstrapDecision.compatible);
      expect(refreshed.isFresh, isTrue);
      expect(refreshed.canReplayQueuedMutations, isTrue);
    });

    test(
        'fresh update-required after reconnect keeps replay and protected network blocked',
        () async {
      final store = MemoryBootstrapSnapshotStore(
        seed: snapshot(
          hadAuthenticatedSession: true,
          updateRequired: false,
        ),
      );
      final service = AppBootstrapService(
        fetchRemote: () async =>
            payload(updateRequired: true, updateRecommended: true),
        snapshotStore: store,
        clock: fixedClock,
      );

      final state = await service.start();

      expect(state.decision, BootstrapDecision.requiredUpdate);
      expect(state.allowsProtectedNetwork, isFalse);
      expect(state.canReplayQueuedMutations, isFalse);
    });
  });
}

DateTime fixedClock() => DateTime.utc(2026, 9, 29, 0, 0);

Future<BootstrapPayload> transientFailure() => throw const ApiFailure(
      code: 'network_error',
      message: 'Offline',
      retryable: true,
    );

BootstrapPayload payload({
  required bool updateRequired,
  required bool updateRecommended,
}) =>
    BootstrapPayload(
      apiVersion: 'v1',
      client: BootstrapClientPolicy(
        platform: 'android',
        minimumVersion: '1.0.0',
        latestVersion: '1.4.0',
        updateRequired: updateRequired,
        updateRecommended: updateRecommended,
      ),
    );

BootstrapSnapshot snapshot({
  required bool hadAuthenticatedSession,
  required bool updateRequired,
}) =>
    BootstrapSnapshot(
      payload: payload(
        updateRequired: updateRequired,
        updateRecommended: updateRequired,
      ),
      fetchedAt: DateTime.utc(2026, 9, 28, 12),
      hadAuthenticatedSession: hadAuthenticatedSession,
    );

class MemoryBootstrapSnapshotStore implements BootstrapSnapshotStore {
  MemoryBootstrapSnapshotStore({BootstrapSnapshot? seed}) : _snapshot = seed;

  BootstrapSnapshot? _snapshot;

  @override
  Future<BootstrapSnapshot?> read() async => _snapshot;

  @override
  Future<void> write(BootstrapSnapshot snapshot) async {
    _snapshot = snapshot;
  }

  @override
  Future<void> markAuthenticatedSessionObserved() async {
    final current = _snapshot;
    if (current != null) {
      _snapshot = current.copyWith(hadAuthenticatedSession: true);
    }
  }
}
