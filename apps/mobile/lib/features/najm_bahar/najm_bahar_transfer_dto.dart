import 'najm_bahar_dto.dart';

Map<String, Object?> _transferObject(Object? raw) {
  if (raw is! Map) throw const FormatException('Expected transfer object');
  return Map<String, Object?>.from(raw);
}

int _transferInt(Map<String, Object?> raw, String key,
    {bool positive = false}) {
  final value = raw[key];
  if (value is! int || (positive ? value <= 0 : value < 0)) {
    throw FormatException('Invalid $key');
  }
  return value;
}

String _transferString(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('Invalid $key');
  }
  return value;
}

class NajmBaharTransferSource {
  NajmBaharTransferSource.fromJson(Object? raw) {
    final value = _transferObject(raw);
    accountId = _transferInt(value, 'account_id', positive: true);
    subAccountId = _transferInt(value, 'sub_account_id', positive: true);
    accountNumber = _transferString(value, 'account_number');
    name = _transferString(value, 'name');
    kind = _transferString(value, 'kind');
    if (kind != 'subaccount') {
      throw const FormatException('Transfer source must be a subaccount');
    }
    status = _transferInt(value, 'status');
    if (status != 1) {
      throw const FormatException('Transfer source is not active');
    }
    activeAvailableGol =
        _transferInt(value, 'active_available_gol');
    final canTransfer = value['can_transfer_active'];
    if (canTransfer is! bool) {
      throw const FormatException('Invalid can_transfer_active');
    }
    canTransferActive = canTransfer;
    if (canTransferActive && activeAvailableGol <= 0) {
      throw const FormatException('Transfer source has no available Active');
    }
  }

  late final int accountId;
  late final int subAccountId;
  late final int status;
  late final int activeAvailableGol;
  late final String accountNumber;
  late final String name;
  late final String kind;
  late final bool canTransferActive;
}

class NajmBaharTransferCapability {
  NajmBaharTransferCapability.fromJson(Object? raw) {
    final value = _transferObject(raw);
    contractVersion =
        _transferInt(value, 'transfer_contract_version', positive: true);
    if (contractVersion != 1) {
      throw const FormatException('Unsupported transfer contract');
    }

    final enabled = value['external_transfer_enabled'];
    if (enabled is! bool) {
      throw const FormatException('Invalid external_transfer_enabled');
    }
    externalTransferEnabled = enabled;

    final reason = value['disabled_reason'];
    if (reason != null && (reason is! String || reason.trim().isEmpty)) {
      throw const FormatException('Invalid disabled_reason');
    }
    disabledReason = reason as String?;

    final rawSources = value['sources'];
    if (rawSources is! List) {
      throw const FormatException('Invalid transfer sources');
    }
    sources = List<NajmBaharTransferSource>.unmodifiable(
      rawSources.map(NajmBaharTransferSource.fromJson),
    );

    if (externalTransferEnabled && disabledReason != null) {
      throw const FormatException('Enabled transfer cannot have disabled reason');
    }
    if (!externalTransferEnabled && disabledReason == null) {
      throw const FormatException('Disabled transfer requires a reason');
    }
  }

  late final int contractVersion;
  late final bool externalTransferEnabled;
  late final String? disabledReason;
  late final List<NajmBaharTransferSource> sources;
}

class NajmBaharTransferDestination {
  NajmBaharTransferDestination.fromJson(Object? raw) {
    final value = _transferObject(raw);
    accountNumber = _transferString(value, 'account_number');
    name = _transferString(value, 'name');
    ownerType = _transferString(value, 'owner_type');
    if (ownerType != 'user' && ownerType != 'legal_entity') {
      throw const FormatException('Unsupported transfer destination owner');
    }
    ownerDisplayName = _transferString(value, 'owner_display_name');
    kind = _transferString(value, 'kind');
    if (kind != 'subaccount') {
      throw const FormatException('Transfer destination must be a subaccount');
    }
    status = _transferInt(value, 'status');
    if (status != 1) {
      throw const FormatException('Transfer destination is not active');
    }
    token = _transferString(value, 'destination_token');
  }

  late final String accountNumber;
  late final String name;
  late final String ownerType;
  late final String ownerDisplayName;
  late final String kind;
  late final String token;
  late final int status;
}

class NajmBaharTransferIntent {
  NajmBaharTransferIntent({
    required this.contractVersion,
    required this.source,
    required this.destination,
    required this.amountGol,
    required String? description,
    required this.key,
  }) : description = _normalizeDescription(description) {
    if (contractVersion != 1) {
      throw ArgumentError.value(contractVersion, 'contractVersion');
    }
    if (!source.canTransferActive || source.status != 1) {
      throw ArgumentError('Selected source cannot transfer Active Bahar');
    }
    if (destination.status != 1 || destination.kind != 'subaccount') {
      throw ArgumentError('Selected destination is unavailable');
    }
    if (amountGol <= 0 || amountGol > source.activeAvailableGol) {
      throw ArgumentError.value(amountGol, 'amountGol');
    }
    if (key.trim().isEmpty) {
      throw ArgumentError.value(key, 'key');
    }
  }

  final int contractVersion;
  final NajmBaharTransferSource source;
  final NajmBaharTransferDestination destination;
  final int amountGol;
  final String? description;
  final String key;

  Map<String, Object?> toJson() => {
        'source_account_id': source.accountId,
        'destination_account_number': destination.accountNumber,
        'amount_gol': amountGol,
        'balance_bucket': 'active',
        'description': description,
        'expected': {
          'transfer_contract_version': contractVersion,
          'source_account_number': source.accountNumber,
          'source_active_available_gol': source.activeAvailableGol,
          'destination_token': destination.token,
        },
      };

  static String? _normalizeDescription(String? raw) {
    if (raw == null) return null;
    final value = raw.trim();
    if (value.isEmpty) return null;
    if (value.length > 500) {
      throw ArgumentError.value(raw, 'description');
    }
    return value;
  }
}

class NajmBaharTransferReceipt {
  NajmBaharTransferReceipt._({
    required this.transaction,
    required this.sourceLocal,
    required this.sourceAggregate,
  });

  factory NajmBaharTransferReceipt.fromMutationJson(Object? raw) {
    final value = _transferObject(raw);
    final transaction = NajmBaharTransaction.fromJson(value['transaction']);

    final sourceBalanceRaw = value['source_balance'];
    if (sourceBalanceRaw is! Map) {
      throw const FormatException('Missing transfer source balance');
    }
    final sourceBalance = Map<String, Object?>.from(sourceBalanceRaw);

    return NajmBaharTransferReceipt._(
      transaction: transaction,
      sourceLocal: NajmBaharBalance.fromJson(sourceBalance['local']),
      sourceAggregate: NajmBaharBalance.fromJson(sourceBalance['aggregate']),
    );
  }

  factory NajmBaharTransferReceipt.fromReconciliationJson(Object? raw) {
    final value = _transferObject(raw);
    return NajmBaharTransferReceipt._(
      transaction: NajmBaharTransaction.fromJson(value['transaction']),
      sourceLocal: null,
      sourceAggregate: null,
    );
  }

  final NajmBaharTransaction transaction;
  final NajmBaharBalance? sourceLocal;
  final NajmBaharBalance? sourceAggregate;

  bool matches(NajmBaharTransferIntent intent) =>
      transaction.status == 'completed' &&
      transaction.amountGol == intent.amountGol &&
      transaction.balanceBucket == 'active' &&
      transaction.direction == 'outgoing' &&
      transaction.counterparty?.accountNumber ==
          intent.destination.accountNumber;
}
