import 'dart:io';

import 'package:earthcoop_mobile/app/bootstrap/bootstrap_models.dart';
import 'package:earthcoop_mobile/app/bootstrap/bootstrap_snapshot_store.dart';
import 'package:earthcoop_mobile/core/local/app_database.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Drift snapshot survives closing and reopening the SQLite file', () async {
    final directory = await Directory.systemTemp.createTemp('earthcoop-bootstrap-');
    final file = File('${directory.path}/app.sqlite');
    addTearDown(() async {
      if (await directory.exists()) {
        await directory.delete(recursive: true);
      }
    });

    final firstDatabase = AppDatabase.file(file);
    final firstStore = DriftBootstrapSnapshotStore(firstDatabase);
    final expected = BootstrapSnapshot(
      payload: const BootstrapPayload(
        apiVersion: 'v1',
        client: BootstrapClientPolicy(
          platform: 'android',
          minimumVersion: '1.0.0',
          latestVersion: '1.4.0',
          updateRequired: false,
          updateRecommended: true,
        ),
      ),
      fetchedAt: DateTime.utc(2026, 9, 29, 0, 0),
      hadAuthenticatedSession: false,
    );

    await firstStore.write(expected);
    await firstDatabase.close();

    final reopenedDatabase = AppDatabase.file(file);
    addTearDown(reopenedDatabase.close);
    final reopenedStore = DriftBootstrapSnapshotStore(reopenedDatabase);

    final restored = await reopenedStore.read();

    expect(restored, isNotNull);
    expect(restored!.payload.apiVersion, 'v1');
    expect(restored.payload.client.platform, 'android');
    expect(restored.payload.client.minimumVersion, '1.0.0');
    expect(restored.payload.client.latestVersion, '1.4.0');
    expect(restored.payload.client.updateRequired, isFalse);
    expect(restored.payload.client.updateRecommended, isTrue);
    expect(restored.fetchedAt, DateTime.utc(2026, 9, 29, 0, 0));
    expect(restored.hadAuthenticatedSession, isFalse);

    await reopenedStore.markAuthenticatedSessionObserved();
    final updated = await reopenedStore.read();
    expect(updated!.hadAuthenticatedSession, isTrue);
  });
}
