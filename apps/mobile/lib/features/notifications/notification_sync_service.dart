import 'dart:async';
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
  DriftNotificationProjectionStore(this._database,
      {bool Function()? isCurrentSession})
      : _isCurrentSession = isCurrentSession ?? (() => true);
  final bool Function() _isCurrentSession;
  void _checkSession() {
    if (!_isCurrentSession()) {
      throw const ApiFailure(
          code: 'session_changed',
          message: '',
          retryable: false,
          httpStatus: 401);
    }
  }

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
    _checkSession();
    return rows
        .map(
          (row) => NotificationDto.fromJson(
            jsonDecode(row.read<String>('payload_json')),
          ),
        )
        .toList(growable: false);
  }

  @override
  Future<void> replaceAll(List<NotificationDto> values) async {
    await _ensureSchema();
    await _database.transaction(() async {
      _checkSession();
      await _database.customStatement(
        'DELETE FROM notification_projection_cache',
      );
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
    _checkSession();
    return row?.readNullable<String>('next_cursor');
  }

  @override
  Future<void> writeNextCursor(String? cursor) async {
    await _ensureSchema();
    _checkSession();
    await _database.customInsert(
      'INSERT OR REPLACE INTO notification_sync_state '
      '(singleton_id, next_cursor) VALUES (?, ?)',
      variables: [Variable.withInt(1), Variable<String>(cursor)],
    );
  }
}

class NotificationSyncService {
  NotificationSyncService({
    required NotificationPageSource source,
    required NotificationProjectionStore store,
    Future<void> Function()? beforeSync,
  })  : _source = source,
        _store = store,
        _beforeSync = beforeSync;

  final NotificationPageSource _source;
  final NotificationProjectionStore _store;
  final Future<void> Function()? _beforeSync;
  Future<List<NotificationDto>>? _activeSync;

  Future<List<NotificationDto>> syncOnResume() {
    final active = _activeSync;
    if (active != null) {
      return active;
    }

    final run = _runAuthoritativeSweep();
    _activeSync = run;
    void clear() {
      if (identical(_activeSync, run)) _activeSync = null;
    }

    // Handle the cleanup future's error as well as the returned sync future.
    unawaited(
      run.then<void>(
        (_) => clear(),
        onError: (Object _, StackTrace __) => clear(),
      ),
    );
    return run;
  }

  Future<List<NotificationDto>> syncFromHint() => syncOnResume();

  Future<void> markRead(String notificationId) async {
    final source = _source;
    if (source is! NotificationReadSource) {
      throw StateError('Notification read source is not configured.');
    }
    final readSource = source as NotificationReadSource;

    final markedAt = DateTime.now().toUtc();
    await _bestEffortPersistOptimisticRead(notificationId, markedAt);

    final authoritative = await readSource.markRead(
      notificationId,
      idempotencyKey: 'notification-read-$notificationId',
      networkAllowed: true,
    );
    if (authoritative != null) {
      await _bestEffortPersistAuthoritative(authoritative);
    }
  }

  Future<void> _bestEffortPersistOptimisticRead(
    String notificationId,
    DateTime markedAt,
  ) async {
    try {
      final existing = await _store.readAll();
      var changed = false;
      final updated = existing.map((item) {
        if (item.id != notificationId || item.read) {
          return item;
        }
        changed = true;
        return item.asRead(at: markedAt);
      }).toList(growable: false);
      if (changed) {
        await _store.replaceAll(updated);
      }
    } catch (_) {
      // Projection is non-authoritative; read mutation must still reach the API.
    }
  }

  Future<void> _bestEffortPersistAuthoritative(
    NotificationDto authoritative,
  ) async {
    try {
      final existing = await _store.readAll();
      var replaced = false;
      final updated = existing.map((item) {
        if (item.id != authoritative.id) {
          return item;
        }
        replaced = true;
        return authoritative;
      }).toList(growable: true);
      if (!replaced) updated.add(authoritative);
      await _store.replaceAll(updated);
    } catch (_) {
      // Projection is non-authoritative and will reconcile on the next sweep.
    }
  }

  Future<List<NotificationDto>> _runAuthoritativeSweep() async {
    await _beforeSync?.call();
    var cursor = null as String?;
    var restartedAfterInvalidCursor = false;
    final byId = <String, NotificationDto>{};

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
          'notification continuation cursor is missing',
        );
      }
      cursor = nextCursor;
      await _store.writeNextCursor(cursor);
    }
  }
}
