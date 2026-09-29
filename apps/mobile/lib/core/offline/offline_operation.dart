import 'dart:convert';

const notificationMarkReadOperation = 'notification.mark_read';

enum OfflineOperationState { pending, blocked }

class OfflineOperation {
  const OfflineOperation({
    required this.idempotencyKey,
    required this.createdAt,
    required this.resource,
    required this.operation,
    required this.payload,
    required this.clientSequence,
    this.state = OfflineOperationState.pending,
    this.attemptCount = 0,
    this.lastErrorCode,
    String? payloadHash,
  }) : payloadHash = payloadHash ?? '';

  factory OfflineOperation.notificationRead({
    required String notificationId,
    required String idempotencyKey,
    required DateTime createdAt,
  }) {
    final payload = <String, Object?>{'notification_id': notificationId};
    return OfflineOperation(
      idempotencyKey: idempotencyKey,
      createdAt: createdAt.toUtc(),
      resource: 'notification:$notificationId',
      operation: notificationMarkReadOperation,
      payload: payload,
      clientSequence: 0,
      payloadHash: deterministicPayloadHash(payload),
    );
  }

  final String idempotencyKey;
  final DateTime createdAt;
  final String resource;
  final String operation;
  final Map<String, Object?> payload;
  final String payloadHash;
  final int clientSequence;
  final OfflineOperationState state;
  final int attemptCount;
  final String? lastErrorCode;

  OfflineOperation copyWith({
    int? clientSequence,
    OfflineOperationState? state,
    int? attemptCount,
    String? lastErrorCode,
    bool clearLastErrorCode = false,
  }) =>
      OfflineOperation(
        idempotencyKey: idempotencyKey,
        createdAt: createdAt,
        resource: resource,
        operation: operation,
        payload: payload,
        payloadHash:
            payloadHash.isEmpty ? deterministicPayloadHash(payload) : payloadHash,
        clientSequence: clientSequence ?? this.clientSequence,
        state: state ?? this.state,
        attemptCount: attemptCount ?? this.attemptCount,
        lastErrorCode:
            clearLastErrorCode ? null : (lastErrorCode ?? this.lastErrorCode),
      );
}

String deterministicPayloadHash(Map<String, Object?> payload) {
  final canonical = _canonicalJson(payload);
  var hash = 0xcbf29ce484222325;
  const prime = 0x100000001b3;
  for (final byte in utf8.encode(canonical)) {
    hash ^= byte;
    hash = (hash * prime) & 0xFFFFFFFFFFFFFFFF;
  }
  return hash.toRadixString(16).padLeft(16, '0');
}

String _canonicalJson(Object? value) {
  if (value is Map) {
    final keys = value.keys.map((key) => key.toString()).toList()..sort();
    return '{${keys.map((key) => '${jsonEncode(key)}:${_canonicalJson(value[key])}').join(',')}}';
  }
  if (value is List) {
    return '[${value.map(_canonicalJson).join(',')}]';
  }
  return jsonEncode(value);
}
