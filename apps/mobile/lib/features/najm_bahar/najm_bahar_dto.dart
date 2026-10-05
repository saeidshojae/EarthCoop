Map<String, Object?> _object(Object? raw) {
  if (raw is! Map) throw const FormatException('Expected object');
  return Map<String, Object?>.from(raw);
}

int _integer(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value is! int) throw FormatException('Expected integer $key');
  return value;
}

String _string(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value is! String) throw FormatException('Expected string $key');
  return value;
}

String? _nullableString(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value == null) return null;
  if (value is! String) throw FormatException('Expected nullable string $key');
  return value;
}

String formatGol(int amount) {
  final absolute = BigInt.from(amount).abs();
  final bahar = absolute ~/ BigInt.from(100);
  final gol = absolute % BigInt.from(100);
  final parts = <String>[
    if (bahar > BigInt.zero) '$bahar بهار',
    if (gol > BigInt.zero || bahar == BigInt.zero) '$gol گل'
  ];
  return '${amount < 0 ? '-' : ''}${parts.join(' و ')}';
}

class NajmBaharBalance {
  NajmBaharBalance.fromJson(Object? raw) {
    final value = _object(raw);
    activeGol = _integer(value, 'active_gol');
    dimAvailableGol = _integer(value, 'dim_available_gol');
    dimCommittedGol = _integer(value, 'dim_committed_gol');
    dimTotalGol = _integer(value, 'dim_total_gol');
    totalGol = _integer(value, 'total_gol');
  }
  late final int activeGol,
      dimAvailableGol,
      dimCommittedGol,
      dimTotalGol,
      totalGol;
}

class NajmBaharAccount {
  NajmBaharAccount.fromJson(Object? raw) {
    final value = _object(raw);
    id = _integer(value, 'id');
    if (id <= 0) throw const FormatException('Invalid account id');
    accountNumber = _string(value, 'account_number');
    name = _string(value, 'name');
    type = _string(value, 'type');
    status = _integer(value, 'status');
    final balances = _object(value['balance']);
    local = NajmBaharBalance.fromJson(balances['local']);
    aggregate = NajmBaharBalance.fromJson(balances['aggregate']);
  }
  late final int id, status;
  late final String accountNumber, name, type;
  late final NajmBaharBalance local, aggregate;
}

class NajmBaharCounterparty {
  NajmBaharCounterparty.fromJson(Object? raw) {
    final value = _object(raw);
    accountNumber = _string(value, 'account_number');
    name = _string(value, 'name');
    type = _string(value, 'type');
  }
  late final String accountNumber, name, type;
}

class NajmBaharTransaction {
  NajmBaharTransaction.fromJson(Object? raw) {
    final value = _object(raw);
    id = _integer(value, 'id');
    if (id <= 0) throw const FormatException('Invalid transaction id');
    trackingNumber = _string(value, 'tracking_number');
    type = _string(value, 'type');
    status = _string(value, 'status');
    amountGol = _integer(value, 'amount_gol');
    balanceBucket = _string(value, 'balance_bucket');
    direction = _string(value, 'direction');
    counterparty = value['counterparty'] == null
        ? null
        : NajmBaharCounterparty.fromJson(value['counterparty']);
    description = _nullableString(value, 'description');
    createdAt = _nullableString(value, 'created_at');
  }
  late final int id, amountGol;
  late final String trackingNumber, type, status, balanceBucket, direction;
  late final String? description, createdAt;
  late final NajmBaharCounterparty? counterparty;
}

class NajmBaharHistoryPage {
  const NajmBaharHistoryPage(
      {required this.items, required this.nextCursor, required this.hasMore});
  final List<NajmBaharTransaction> items;
  final String? nextCursor;
  final bool hasMore;
}
