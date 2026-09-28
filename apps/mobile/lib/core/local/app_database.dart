import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

const _createBootstrapSnapshotTableSql =
    'CREATE TABLE IF NOT EXISTS app_bootstrap_snapshot ('
    'singleton_id INTEGER NOT NULL PRIMARY KEY CHECK (singleton_id = 1), '
    'payload_json TEXT NOT NULL, '
    'fetched_at_ms INTEGER NOT NULL, '
    'had_authenticated_session INTEGER NOT NULL '
    'CHECK (had_authenticated_session IN (0, 1))'
    ')';

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

class AppDatabase extends GeneratedDatabase {
  AppDatabase._(super.executor);

  factory AppDatabase.file(File file) =>
      AppDatabase._(NativeDatabase.createInBackground(file));

  factory AppDatabase.inMemory() => AppDatabase._(NativeDatabase.memory());

  static Future<AppDatabase> openDefault() async {
    final directory = await getApplicationSupportDirectory();
    return AppDatabase.file(File(p.join(directory.path, 'earthcoop.sqlite')));
  }

  @override
  int get schemaVersion => 1;

  @override
  Iterable<TableInfo<Table, dynamic>> get allTables => const [];

  @override
  Iterable<DatabaseSchemaEntity> get allSchemaEntities => const [];

  @override
  MigrationStrategy get migration => MigrationStrategy(
        onCreate: (_) async {
          await customStatement(_createBootstrapSnapshotTableSql);
        },
      );

  Future<BootstrapSnapshotRecord?> readBootstrapSnapshot() async {
    final row = await customSelect(
      'SELECT payload_json, fetched_at_ms, had_authenticated_session '
      'FROM app_bootstrap_snapshot WHERE singleton_id = 1 LIMIT 1',
    ).getSingleOrNull();
    if (row == null) return null;

    return BootstrapSnapshotRecord(
      payloadJson: row.read<String>('payload_json'),
      fetchedAtMilliseconds: row.read<int>('fetched_at_ms'),
      hadAuthenticatedSession: row.read<int>('had_authenticated_session') == 1,
    );
  }

  Future<void> writeBootstrapSnapshot({
    required String payloadJson,
    required int fetchedAtMilliseconds,
    required bool hadAuthenticatedSession,
  }) async {
    await customInsert(
      'INSERT OR REPLACE INTO app_bootstrap_snapshot '
      '(singleton_id, payload_json, fetched_at_ms, had_authenticated_session) '
      'VALUES (?, ?, ?, ?)',
      variables: [
        Variable.withInt(1),
        Variable.withString(payloadJson),
        Variable.withInt(fetchedAtMilliseconds),
        Variable.withInt(hadAuthenticatedSession ? 1 : 0),
      ],
    );
  }

  Future<void> markBootstrapAuthenticatedSessionObserved() async {
    await customUpdate(
      'UPDATE app_bootstrap_snapshot '
      'SET had_authenticated_session = 1 WHERE singleton_id = 1',
    );
  }
}
