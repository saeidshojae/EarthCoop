import 'dart:convert';

import 'package:drift/drift.dart';

import '../../core/local/app_database.dart';
import '../../core/api/api_error.dart';

abstract interface class GroupProjectionCache {
  Future<List<Map<String, Object?>>> readAll();

  Future<Map<String, Object?>?> readOne(int id);

  Future<void> writeAll(List<Map<String, Object?>> values);
}

class DriftGroupProjectionCache implements GroupProjectionCache {
  DriftGroupProjectionCache(
    this._database, {
    this.maxEntries = 100,
    bool Function()? isCurrentSession,
  }) : _isCurrentSession = isCurrentSession ?? (() => true) {
    if (maxEntries < 1) {
      throw ArgumentError.value(maxEntries, 'maxEntries', 'must be positive');
    }
  }

  final AppDatabase _database;
  final int maxEntries;
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

  Future<void> _ensureTable() => _database.customStatement(
        'CREATE TABLE IF NOT EXISTS group_projection_cache ('
        'id INTEGER NOT NULL PRIMARY KEY, '
        'payload_json TEXT NOT NULL, '
        'position INTEGER NOT NULL'
        ')',
      );

  @override
  Future<List<Map<String, Object?>>> readAll() async {
    _checkSession();
    await _ensureTable();
    _checkSession();
    final rows = await _database
        .customSelect(
          'SELECT payload_json FROM group_projection_cache ORDER BY position ASC',
        )
        .get();
    _checkSession();
    return rows
        .map((row) => _decodeMap(row.read<String>('payload_json')))
        .toList(growable: false);
  }

  @override
  Future<Map<String, Object?>?> readOne(int id) async {
    _checkSession();
    await _ensureTable();
    _checkSession();
    final row = await _database.customSelect(
      'SELECT payload_json FROM group_projection_cache WHERE id = ? LIMIT 1',
      variables: [Variable.withInt(id)],
    ).getSingleOrNull();
    _checkSession();
    if (row == null) return null;
    return _decodeMap(row.read<String>('payload_json'));
  }

  @override
  Future<void> writeAll(List<Map<String, Object?>> values) async {
    _checkSession();
    await _ensureTable();
    _checkSession();
    final bounded = values.length <= maxEntries
        ? values
        : values.sublist(values.length - maxEntries);

    await _database.transaction(() async {
      _checkSession();
      await _database.customStatement('DELETE FROM group_projection_cache');
      for (var index = 0; index < bounded.length; index += 1) {
        _checkSession();
        final value = bounded[index];
        final id = value['id'];
        if (id is! int) {
          throw const FormatException(
              'Cached group projection requires integer id');
        }
        await _database.customInsert(
          'INSERT INTO group_projection_cache (id, payload_json, position) '
          'VALUES (?, ?, ?)',
          variables: [
            Variable.withInt(id),
            Variable.withString(jsonEncode(value)),
            Variable.withInt(index),
          ],
        );
      }
      _checkSession();
    });
  }
}

/// Opens only the database belonging to the account captured by the view.
class AccountGroupProjectionCache implements GroupProjectionCache {
  AccountGroupProjectionCache({
    required Future<AppDatabase> Function() openDatabase,
    required bool Function() isCurrentSession,
  })  : _openDatabase = openDatabase,
        _isCurrentSession = isCurrentSession;

  final Future<AppDatabase> Function() _openDatabase;
  final bool Function() _isCurrentSession;

  Future<DriftGroupProjectionCache> _cache() async {
    if (!_isCurrentSession()) {
      throw const ApiFailure(
          code: 'session_changed',
          message: '',
          retryable: false,
          httpStatus: 401);
    }
    final database = await _openDatabase();
    return DriftGroupProjectionCache(database,
        isCurrentSession: _isCurrentSession);
  }

  @override
  Future<List<Map<String, Object?>>> readAll() async =>
      (await _cache()).readAll();

  @override
  Future<Map<String, Object?>?> readOne(int id) async =>
      (await _cache()).readOne(id);

  @override
  Future<void> writeAll(List<Map<String, Object?>> values) async =>
      (await _cache()).writeAll(values);
}

Map<String, Object?> _decodeMap(String raw) {
  final decoded = jsonDecode(raw);
  if (decoded is! Map) {
    throw const FormatException('Cached group projection must be an object');
  }
  return Map<String, Object?>.from(decoded);
}
