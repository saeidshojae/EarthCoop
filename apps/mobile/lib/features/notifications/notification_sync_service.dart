import 'dart:async';
import 'dart:collection';
import 'dart:convert';

import 'package:drift/drift.dart';

import '../../core/api/api_error.dart';
import '../../core/local/app_database.dart';
import 'notification_dto.dart';
import 'notification_repository.dart';

abstract interface class NotificationProjectionStore {
  Future<List<NotificationDto>> readAll();

  Future<void> replaceAll(List<NotificationDto> values);

  Future<String?> readNextCursor();

  Future<void> writeNextCursor(String? cursor);
}

class DriftNotificationProjectionStore implements NotificationProjectionStore {
  DriftNotificationProjectionStore(this._database);

  final AppDatabase _database;

  Future<void> _ensureSchema() =>
      _database.ensureNotificationProjectionSchema();

  @override
  Future<List<NotificationDto>> readAll() async {
    await _ensureSchema();
    final rows = await _database
        .customSelect(
          'SELECT payload_json FROM notification_projection_cache '
          'ORDER BY position ASC',
        )
        .get();
    return rows
        .map((row) => NotificationDto.fromJson(
              jsonDecode(row.read<String>('payload_json')),
            ))
        .toList(growable: false);
  }

  @override
  Future<void> replaceAll(List<NotificationDto> values) async {
    await _ensureSchema();
    await _database.transaction(() async {
      await _database
          .customStatement('DELETE FROM notification_projection_cache');
      for (var index = 0; index < values.length; index += 1) {
        final value = values[index];
        await _database.customInsert(
          'INSERT INTO notification_projection_cache '
          '(id, payload_json, position) VALUES (?, ?, ?)',
          variables: [
            Variable.withString(value.id),
            Variable.withString(jsonEncode(value.toJson())),
            Variable.withInt(index),
          ],
        );
      }
    });
  }

  @override
  Future<String?> readNextCursor() async {
    await _ensureSchema();
    final row = await _database
        .customSelect(
          'SELECT next_cursor FROM notification_sync_state '
          'WHERE singleton_id = 1 LIMIT 1',
        )
        .getSingleOrNull();
    return row?.readNullable<String>('next_cursor');
  }

  @override
  Future<void> writeNextCursor(String? cursor) async {
    await _ensureSchema();
    await _database.customInsert(
      'INSERT OR REPLACE INTO notification_sync_state '
      '(singleton_id, next_cursor) VALUES (?, ?)',
      variables: [
        Variable.withInt(1),
        Variable<String>(cursor),
      ],
    );
  }
}

class NotificationSyncService {
  NotificationSyncService({
    required NotificationPageSource source,
    required NotificationProjectionStore store,
  })  : _source = source,
        _store = store;

  final NotificationPageSource _source;
  final NotificationProjectionStore _store;
  Future<List<NotificationDto>>? _activeSync;

  Future<List<NotificationDto>> syncOnResume() {
    final active = _activeSync;
    if (active != null) return active;

    final run = _runAuthoritativeSweep();
    _activeSync = run;
    unawaited(run.whenComplete(() {
      if (identical(_activeSync, run)) _activeSync = null;
    }));
    return run;
  }

  Future<List<NotificationDto>> syncFromHint() => syncOnResume();

  Future<List<NotificationDto>> _runAuthoritativeSweep() async {
    var cursor = null as String?;
    var restartedAfterInvalidCursor = false;
    final byId = LinkedHashMap<String, NotificationDto>();

    await _store.writeNextCursor(null);

    while (true) {
      NotificationPage page;
      try {
        page = await _source.fetchPage(cursor: cursor, limit: 50);
      } on ApiFailure catch (failure) {
        if (cursor != null &&
            !restartedAfterInvalidCursor &&
            failure.httpStatus == 422) {
          restartedAfterInvalidCursor = true;
          cursor = null;
          byId.clear();
          await _store.writeNextCursor(null);
          continue;
        }
        rethrow;
      }

      for (final item in page.items) {
        byId.putIfAbsent(item.id, () => item);
      }

      if (!page.hasMore) {
        final complete = List<NotificationDto>.unmodifiable(byId.values);
        await _store.replaceAll(complete);
        await _store.writeNextCursor(null);
        return complete;
      }

      final nextCursor = page.nextCursor;
      if (nextCursor == null || nextCursor.isEmpty) {
        throw const FormatException(
            'notification continuation cursor is missing');
      }
      cursor = nextCursor;
      await _store.writeNextCursor(cursor);
    }
  }
}
