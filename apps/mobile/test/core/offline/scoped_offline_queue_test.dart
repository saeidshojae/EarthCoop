import 'dart:async';

import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/core/offline/offline_operation.dart';
import 'package:earthcoop_mobile/core/offline/offline_queue_repository.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'persisted queues isolate the same notification across accounts',
    () async {
      final storage = MemoryOfflineQueueRepository();
      final first = ScopedOfflineQueueRepository(
        storage,
        scope: '7:device',
        isCurrent: () => true,
      );
      final second = ScopedOfflineQueueRepository(
        storage,
        scope: '8:device',
        isCurrent: () => true,
      );
      await first.enqueue(operation('first-key'));
      await second.enqueue(operation('second-key'));
      expect((await first.pending()).single.idempotencyKey, 'first-key');
      expect((await second.pending()).single.idempotencyKey, 'second-key');
      await first.clearAll();
      expect(await first.all(), isEmpty);
      expect(await second.pending(), hasLength(1));
      // A new repository instance restores only its persisted owner scope.
      final restored = ScopedOfflineQueueRepository(
        storage,
        scope: '8:device',
        isCurrent: () => true,
      );
      expect((await restored.pending()).single.idempotencyKey, 'second-key');
    },
  );

  test('logout while enqueue awaits removes the old intent', () async {
    final storage = DelayedQueue();
    var current = true;
    final scoped = ScopedOfflineQueueRepository(
      storage,
      scope: '7:device',
      isCurrent: () => current,
    );
    final write = scoped.enqueue(operation('key'));
    await storage.started.future;
    current = false;
    storage.release.complete();
    await expectLater(write, throwsA(isA<ApiFailure>()));
    expect(await storage.all(), isEmpty);
  });
}

OfflineOperation operation(String key) => OfflineOperation.notificationRead(
  notificationId: 'n-1',
  idempotencyKey: key,
  createdAt: DateTime.utc(2026),
);

class DelayedQueue extends MemoryOfflineQueueRepository {
  final started = Completer<void>();
  final release = Completer<void>();
  @override
  Future<OfflineOperation> enqueue(OfflineOperation operation) async {
    started.complete();
    await release.future;
    return super.enqueue(operation);
  }
}
