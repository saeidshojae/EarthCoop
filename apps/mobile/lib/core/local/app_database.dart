import 'dart:io';
import 'dart:convert';

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

const _createNotificationProjectionTableSql =
    'CREATE TABLE IF NOT EXISTS notification_projection_cache ('
    'id TEXT NOT NULL PRIMARY KEY, '
    'payload_json TEXT NOT NULL, '
    'position INTEGER NOT NULL'
    ')';

const _createNotificationSyncStateTableSql =
    'CREATE TABLE IF NOT EXISTS notification_sync_state ('
    'singleton_id INTEGER NOT NULL PRIMARY KEY CHECK (singleton_id = 1), '
    'next_cursor TEXT NULL'
    ')';

const _createOfflineOperationQueueTableSql =
    'CREATE TABLE IF NOT EXISTS offline_operation_queue ('
    'client_sequence INTEGER NOT NULL PRIMARY KEY, '
    'idempotency_key TEXT NOT NULL, '
    'created_at_ms INTEGER NOT NULL, '
    'resource TEXT NOT NULL, '
    'operation TEXT NOT NULL, '
    'payload_json TEXT NOT NULL, '
    'payload_hash TEXT NOT NULL, '
    'state TEXT NOT NULL, '
    'attempt_count INTEGER NOT NULL DEFAULT 0, '
    'last_error_code TEXT NULL'
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

  static Future<AppDatabase> openForAccount({
    required int userId,
    required String deviceId,
  }) async {
    final directory = await getApplicationSupportDirectory();
    final device = base64Url.encode(utf8.encode(deviceId));
    return AppDatabase.file(
      File(
        p.join(
          directory.path,
          'earthcoop-notifications-$userId-$device.sqlite',
        ),
      ),
    );
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
          await ensureNotificationProjectionSchema();
          await ensureOfflineQueueSchema();
        },
      );

  Future<void> ensureNotificationProjectionSchema() async {
    await customStatement(_createNotificationProjectionTableSql);
    await customStatement(_createNotificationSyncStateTableSql);
  }

  Future<void> ensureOfflineQueueSchema() async {
    await customStatement(_createOfflineOperationQueueTableSql);
  }

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
