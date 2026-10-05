import 'dart:convert';

import '../../core/local/app_database.dart';
import 'bootstrap_models.dart';

abstract interface class BootstrapSnapshotStore {
  Future<BootstrapSnapshot?> read();

  Future<void> write(BootstrapSnapshot snapshot);

  Future<void> markAuthenticatedSessionObserved();
}

class DriftBootstrapSnapshotStore implements BootstrapSnapshotStore {
  DriftBootstrapSnapshotStore(this._database);

  final AppDatabase _database;

  @override
  Future<BootstrapSnapshot?> read() async {
    final row = await _database.readBootstrapSnapshot();
    if (row == null) return null;

    final decoded = jsonDecode(row.payloadJson);
    return BootstrapSnapshot(
      payload: BootstrapPayload.fromJson(decoded),
      fetchedAt: DateTime.fromMillisecondsSinceEpoch(
        row.fetchedAtMilliseconds,
        isUtc: true,
      ),
      hadAuthenticatedSession: row.hadAuthenticatedSession,
    );
  }

  @override
  Future<void> write(BootstrapSnapshot snapshot) =>
      _database.writeBootstrapSnapshot(
        payloadJson: jsonEncode(snapshot.payload.toJson()),
        fetchedAtMilliseconds:
            snapshot.fetchedAt.toUtc().millisecondsSinceEpoch,
        hadAuthenticatedSession: snapshot.hadAuthenticatedSession,
      );

  @override
  Future<void> markAuthenticatedSessionObserved() =>
      _database.markBootstrapAuthenticatedSessionObserved();
}
