import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

class BootstrapSnapshotRecord {
  const BootstrapSnapshotRecord({
    required this.payloadJson,
    required this.fetchedAtMilliseconds,
    required this.hadAuthenticatedSession,
  });

  final String payloadJson;
  final int fetchedAtMilliseconds;
  final bool hadAuthenticatedSession;
}

class AppDatabase {
  AppDatabase._(this._executor);

  factory AppDatabase.file(File file) =>
      AppDatabase._(NativeDatabase.createInBackground(file));

  factory AppDatabase.inMemory() => AppDatabase._(NativeDatabase.memory());

  static Future<AppDatabase> openDefault() async {
    final directory = await getApplicationSupportDirectory();
    return AppDatabase.file(File(p.join(directory.path, 'earthcoop.sqlite')));
  }

  final QueryExecutor _executor;
  final _AppDatabaseUser _user = _AppDatabaseUser();

  Future<void> _ensureOpen() async {
    await _executor.ensureOpen(_user);
  }

  Future<BootstrapSnapshotRecord?> readBootstrapSnapshot() async {
    await _ensureOpen();
    final rows = await _executor.runSelect(
      'SELECT payload_json, fetched_at_ms, had_authenticated_session '
      'FROM app_bootstrap_snapshot WHERE singleton_id = 1 LIMIT 1',
      const [],
    );
    if (rows.isEmpty) return null;

    final row = rows.single;
    return BootstrapSnapshotRecord(
      payloadJson: row['payload_json']! as String,
      fetchedAtMilliseconds: row['fetched_at_ms']! as int,
      hadAuthenticatedSession: (row['had_authenticated_session']! as int) == 1,
    );
  }

  Future<void> writeBootstrapSnapshot({
    required String payloadJson,
    required int fetchedAtMilliseconds,
    required bool hadAuthenticatedSession,
  }) async {
    await _ensureOpen();
    await _executor.runInsert(
      'INSERT OR REPLACE INTO app_bootstrap_snapshot '
      '(singleton_id, payload_json, fetched_at_ms, had_authenticated_session) '
      'VALUES (?, ?, ?, ?)',
      [
        1,
        payloadJson,
        fetchedAtMilliseconds,
        hadAuthenticatedSession ? 1 : 0,
      ],
    );
  }

  Future<void> markBootstrapAuthenticatedSessionObserved() async {
    await _ensureOpen();
    await _executor.runUpdate(
      'UPDATE app_bootstrap_snapshot '
      'SET had_authenticated_session = 1 WHERE singleton_id = 1',
      const [],
    );
  }

  Future<void> close() => _executor.close();
}

class _AppDatabaseUser extends QueryExecutorUser {
  @override
  int get schemaVersion => 1;

  @override
  Future<void> beforeOpen(
    QueryExecutor executor,
    OpeningDetails details,
  ) async {
    await executor.runCustom(
      'CREATE TABLE IF NOT EXISTS app_bootstrap_snapshot ('
      'singleton_id INTEGER NOT NULL PRIMARY KEY CHECK (singleton_id = 1), '
      'payload_json TEXT NOT NULL, '
      'fetched_at_ms INTEGER NOT NULL, '
      'had_authenticated_session INTEGER NOT NULL '
      'CHECK (had_authenticated_session IN (0, 1))'
      ')',
    );
  }
}
