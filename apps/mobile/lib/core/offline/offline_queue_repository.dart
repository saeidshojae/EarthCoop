import 'dart:convert';

import 'package:drift/drift.dart';

import '../local/app_database.dart';
import 'offline_operation.dart';

abstract interface class OfflineQueueRepository {
  Future<OfflineOperation> enqueue(OfflineOperation operation);
  Future<List<OfflineOperation>> pending();
  Future<List<OfflineOperation>> all();
  Future<void> remove(OfflineOperation operation);
  Future<void> markRetryableFailure(
    OfflineOperation operation,
    String errorCode,
  );
  Future<void> markBlocked(
    OfflineOperation operation,
    String errorCode,
  );
  Future<void> clearAll();
}

class MemoryOfflineQueueRepository implements OfflineQueueRepository {
  final List<OfflineOperation> _items = [];
  int _nextSequence = 1;

  @override
  Future<OfflineOperation> enqueue(OfflineOperation operation) async {
    final normalized = _normalized(operation);
    final duplicateIndex = _items.indexWhere(
      (item) =>
          item.state == OfflineOperationState.pending &&
          item.resource == normalized.resource &&
          item.operation == normalized.operation &&
          item.payloadHash == normalized.payloadHash,
    );
    if (duplicateIndex >= 0) return _items[duplicateIndex];

    final sequenced = normalized.clientSequence > 0
        ? normalized
        : normalized.copyWith(clientSequence: _nextSequence++);
    _nextSequence = _nextSequence <= sequenced.clientSequence
        ? sequenced.clientSequence + 1
        : _nextSequence;
    _items.add(sequenced);
    _items.sort((a, b) => a.clientSequence.compareTo(b.clientSequence));
    return sequenced;
  }

  @override
  Future<List<OfflineOperation>> pending() async => List.unmodifiable(
        _items.where((item) => item.state == OfflineOperationState.pending),
      );

  @override
  Future<List<OfflineOperation>> all() async => List.unmodifiable(_items);

  @override
  Future<void> remove(OfflineOperation operation) async {
    _items.removeWhere(
      (item) => item.clientSequence == operation.clientSequence,
    );
  }

  @override
  Future<void> markRetryableFailure(
    OfflineOperation operation,
    String errorCode,
  ) async {
    _replace(
      operation,
      operation.copyWith(
        attemptCount: operation.attemptCount + 1,
        state: OfflineOperationState.pending,
        lastErrorCode: errorCode,
      ),
    );
  }

  @override
  Future<void> markBlocked(
    OfflineOperation operation,
    String errorCode,
  ) async {
    _replace(
      operation,
      operation.copyWith(
        attemptCount: operation.attemptCount + 1,
        state: OfflineOperationState.blocked,
        lastErrorCode: errorCode,
      ),
    );
  }

  @override
  Future<void> clearAll() async {
    _items.clear();
    _nextSequence = 1;
  }

  void _replace(OfflineOperation oldValue, OfflineOperation newValue) {
    final index = _items.indexWhere(
      (item) => item.clientSequence == oldValue.clientSequence,
    );
    if (index >= 0) _items[index] = newValue;
  }
}

class DriftOfflineQueueRepository implements OfflineQueueRepository {
  DriftOfflineQueueRepository(this._database);

  final AppDatabase _database;

  @override
  Future<OfflineOperation> enqueue(OfflineOperation operation) async {
    await _database.ensureOfflineQueueSchema();
    final normalized = _normalized(operation);
    return _database.transaction(() async {
      final duplicate = await _database.customSelect(
        'SELECT * FROM offline_operation_queue '
        'WHERE resource = ? AND operation = ? AND payload_hash = ? '
        "AND state = 'pending' ORDER BY client_sequence LIMIT 1",
        variables: [
          Variable.withString(normalized.resource),
          Variable.withString(normalized.operation),
          Variable.withString(normalized.payloadHash),
        ],
      ).getSingleOrNull();
      if (duplicate != null) return _fromRow(duplicate);

      final maxRow = await _database
          .customSelect(
            'SELECT COALESCE(MAX(client_sequence), 0) AS max_sequence '
            'FROM offline_operation_queue',
          )
          .getSingle();
      final nextSequence = normalized.clientSequence > 0
          ? normalized.clientSequence
          : maxRow.read<int>('max_sequence') + 1;
      final sequenced = normalized.copyWith(clientSequence: nextSequence);
      await _insert(sequenced);
      return sequenced;
    });
  }

  @override
  Future<List<OfflineOperation>> pending() async {
    await _database.ensureOfflineQueueSchema();
    final rows = await _database
        .customSelect(
          "SELECT * FROM offline_operation_queue WHERE state = 'pending' "
          'ORDER BY client_sequence',
        )
        .get();
    return rows.map(_fromRow).toList(growable: false);
  }

  @override
  Future<List<OfflineOperation>> all() async {
    await _database.ensureOfflineQueueSchema();
    final rows = await _database
        .customSelect(
          'SELECT * FROM offline_operation_queue ORDER BY client_sequence',
        )
        .get();
    return rows.map(_fromRow).toList(growable: false);
  }

  @override
  Future<void> remove(OfflineOperation operation) async {
    await _database.ensureOfflineQueueSchema();
    await _database.customUpdate(
      'DELETE FROM offline_operation_queue WHERE client_sequence = ?',
      variables: [Variable.withInt(operation.clientSequence)],
    );
  }

  @override
  Future<void> markRetryableFailure(
    OfflineOperation operation,
    String errorCode,
  ) =>
      _updateFailure(operation, errorCode, OfflineOperationState.pending);

  @override
  Future<void> markBlocked(
    OfflineOperation operation,
    String errorCode,
  ) =>
      _updateFailure(operation, errorCode, OfflineOperationState.blocked);

  @override
  Future<void> clearAll() async {
    await _database.ensureOfflineQueueSchema();
    await _database.customUpdate('DELETE FROM offline_operation_queue');
  }

  Future<void> _insert(OfflineOperation operation) async {
    await _database.customInsert(
      'INSERT INTO offline_operation_queue '
      '(client_sequence, idempotency_key, created_at_ms, resource, operation, '
      'payload_json, payload_hash, state, attempt_count, last_error_code) '
      'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      variables: [
        Variable.withInt(operation.clientSequence),
        Variable.withString(operation.idempotencyKey),
        Variable.withInt(operation.createdAt.millisecondsSinceEpoch),
        Variable.withString(operation.resource),
        Variable.withString(operation.operation),
        Variable.withString(jsonEncode(operation.payload)),
        Variable.withString(operation.payloadHash),
        Variable.withString(operation.state.name),
        Variable.withInt(operation.attemptCount),
        Variable<String>(operation.lastErrorCode),
      ],
    );
  }

  Future<void> _updateFailure(
    OfflineOperation operation,
    String errorCode,
    OfflineOperationState state,
  ) async {
    await _database.ensureOfflineQueueSchema();
    await _database.customUpdate(
      'UPDATE offline_operation_queue '
      'SET state = ?, attempt_count = attempt_count + 1, last_error_code = ? '
      'WHERE client_sequence = ?',
      variables: [
        Variable.withString(state.name),
        Variable.withString(errorCode),
        Variable.withInt(operation.clientSequence),
      ],
    );
  }

  OfflineOperation _fromRow(QueryRow row) {
    final payloadRaw = jsonDecode(row.read<String>('payload_json'));
    final payload = Map<String, Object?>.from(payloadRaw as Map);
    return OfflineOperation(
      idempotencyKey: row.read<String>('idempotency_key'),
      createdAt: DateTime.fromMillisecondsSinceEpoch(
        row.read<int>('created_at_ms'),
        isUtc: true,
      ),
      resource: row.read<String>('resource'),
      operation: row.read<String>('operation'),
      payload: payload,
      payloadHash: row.read<String>('payload_hash'),
      clientSequence: row.read<int>('client_sequence'),
      state: OfflineOperationState.values.byName(row.read<String>('state')),
      attemptCount: row.read<int>('attempt_count'),
      lastErrorCode: row.readNullable<String>('last_error_code'),
    );
  }
}

OfflineOperation _normalized(OfflineOperation operation) {
  if (operation.idempotencyKey.trim().isEmpty) {
    throw ArgumentError.value(
      operation.idempotencyKey,
      'idempotencyKey',
      'must not be empty',
    );
  }
  return operation.payloadHash.isEmpty
      ? OfflineOperation(
          idempotencyKey: operation.idempotencyKey,
          createdAt: operation.createdAt.toUtc(),
          resource: operation.resource,
          operation: operation.operation,
          payload: operation.payload,
          payloadHash: deterministicPayloadHash(operation.payload),
          clientSequence: operation.clientSequence,
          state: operation.state,
          attemptCount: operation.attemptCount,
          lastErrorCode: operation.lastErrorCode,
        )
      : operation;
}
