import 'dart:convert';

import 'package:drift/drift.dart';

import '../../core/local/app_database.dart';

abstract interface class GroupProjectionCache {
  Future<List<Map<String, Object?>>> readAll();

  Future<Map<String, Object?>?> readOne(int id);

  Future<void> writeAll(List<Map<String, Object?>> values);
}

class DriftGroupProjectionCache implements GroupProjectionCache {
  DriftGroupProjectionCache(
    this._database, {
    this.maxEntries = 100,
  }) {
    if (maxEntries < 1) {
      throw ArgumentError.value(maxEntries, 'maxEntries', 'must be positive');
    }
  }

  final AppDatabase _database;
  final int maxEntries;

  Future<void> _ensureTable() => _database.customStatement(
        'CREATE TABLE IF NOT EXISTS group_projection_cache ('
        'id INTEGER NOT NULL PRIMARY KEY, '
        'payload_json TEXT NOT NULL, '
        'position INTEGER NOT NULL'
        ')',
      );

  @override
  Future<List<Map<String, Object?>>> readAll() async {
    await _ensureTable();
    final rows = await _database.customSelect(
      'SELECT payload_json FROM group_projection_cache ORDER BY position ASC',
    ).get();

    return rows
        .map((row) => _decodeMap(row.read<String>('payload_json')))
        .toList(growable: false);
  }

  @override
  Future<Map<String, Object?>?> readOne(int id) async {
    await _ensureTable();
    final row = await _database.customSelect(
      'SELECT payload_json FROM group_projection_cache WHERE id = ? LIMIT 1',
      variables: [Variable.withInt(id)],
    ).getSingleOrNull();
    if (row == null) return null;
    return _decodeMap(row.read<String>('payload_json'));
  }

  @override
  Future<void> writeAll(List<Map<String, Object?>> values) async {
    await _ensureTable();
    final bounded = values.length <= maxEntries
        ? values
        : values.sublist(values.length - maxEntries);

    await _database.transaction(() async {
      await _database.customStatement('DELETE FROM group_projection_cache');
      for (var index = 0; index < bounded.length; index += 1) {
        final value = bounded[index];
        final id = value['id'];
        if (id is! int) {
          throw const FormatException('Cached group projection requires integer id');
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
    });
  }
}

Map<String, Object?> _decodeMap(String raw) {
  final decoded = jsonDecode(raw);
  if (decoded is! Map) {
    throw const FormatException('Cached group projection must be an object');
  }
  return Map<String, Object?>.from(decoded);
}
