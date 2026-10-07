import 'najm_bahar_dto.dart';

Map<String, Object?> _internalObject(Object? raw) {
  if (raw is! Map) {
    throw const FormatException('Expected internal transfer object');
  }
  return Map<String, Object?>.from(raw);
}

int _internalInt(
  Map<String, Object?> raw,
  String key, {
  bool positive = false,
}) {
  final value = raw[key];
  if (value is! int || (positive ? value <= 0 : value < 0)) {
    throw FormatException('Invalid $key');
  }
  return value;
}

String _internalString(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('Invalid $key');
  }
  return value;
}

abstract interface class NajmBaharInternalAccountRef {
  int get accountId;
  int? get subAccountId;
  String get accountNumber;
  String get name;
  int get activeAvailableGol;
  int get dimAvailableGol;
}

class NajmBaharInternalMainAccount implements NajmBaharInternalAccountRef {
  NajmBaharInternalMainAccount.fromJson(Object? raw) {
    final value = _internalObject(raw);
    accountId = _internalInt(value, 'account_id', positive: true);
    accountNumber = _internalString(value, 'account_number');
    name = _internalString(value, 'name');
    activeGol = _internalInt(value, 'active_gol');
    activeAvailableGol = _internalInt(value, 'active_available_gol');
    dimAvailableGol = _internalInt(value, 'dim_available_gol');
    dimCommittedGol = _internalInt(value, 'dim_committed_gol');

    if (activeAvailableGol > activeGol) {
      throw const FormatException('Active availability exceeds main balance');
    }
  }

  @override
  late final int accountId;
  @override
  int? get subAccountId => null;
  @override
  late final String accountNumber;
  @override
  late final String name;
  late final int activeGol;
  @override
  late final int activeAvailableGol;
  @override
  late final int dimAvailableGol;
  late final int dimCommittedGol;
}

class NajmBaharSubAccount implements NajmBaharInternalAccountRef {
  NajmBaharSubAccount.fromJson(Object? raw) {
    final value = _internalObject(raw);
    subAccountId = _internalInt(value, 'sub_account_id', positive: true);
    accountId = _internalInt(value, 'account_id', positive: true);
    accountNumber = _internalString(value, 'account_number');
    name = _internalString(value, 'name');
    status = _internalInt(value, 'status');
    if (status != 1) {
      throw const FormatException('Sub-account is not active');
    }
    activeGol = _internalInt(value, 'active_gol');
    activeAvailableGol = _internalInt(value, 'active_available_gol');
    dimAvailableGol = _internalInt(value, 'dim_available_gol');

    if (activeAvailableGol > activeGol) {
      throw const FormatException('Active availability exceeds sub-account balance');
    }
  }

  @override
  late final int accountId;
  @override
  late final int subAccountId;
  @override
  late final String accountNumber;
  @override
  late final String name;
  late final int status;
  late final int activeGol;
  @override
  late final int activeAvailableGol;
  @override
  late final int dimAvailableGol;
}

class NajmBaharSubAccountSnapshot {
  NajmBaharSubAccountSnapshot.fromJson(Object? raw) {
    final value = _internalObject(raw);
    contractVersion = _internalInt(
      value,
      'internal_transfer_contract_version',
      positive: true,
    );
    if (contractVersion != 1) {
      throw const FormatException('Unsupported internal transfer contract');
    }

    main = NajmBaharInternalMainAccount.fromJson(value['main']);

    final rawSubaccounts = value['subaccounts'];
    if (rawSubaccounts is! List) {
      throw const FormatException('Invalid sub-account list');
    }
    subaccounts = List<NajmBaharSubAccount>.unmodifiable(
      rawSubaccounts.map(NajmBaharSubAccount.fromJson),
    );
  }

  late final int contractVersion;
  late final NajmBaharInternalMainAccount main;
  late final List<NajmBaharSubAccount> subaccounts;
}

class NajmBaharInternalTransferIntent {
  NajmBaharInternalTransferIntent({
    required this.contractVersion,
    required this.direction,
    required this.source,
    required this.destination,
    required this.balanceBucket,
    required this.amountGol,
    required String? description,
    required this.key,
  }) : description = _normalizeDescription(description) {
    if (contractVersion != 1) {
      throw ArgumentError.value(contractVersion, 'contractVersion');
    }
    if (!const {'main_to_sub', 'sub_to_main', 'sub_to_sub'}
        .contains(direction)) {
      throw ArgumentError.value(direction, 'direction');
    }
    if (!const {'active', 'dim'}.contains(balanceBucket)) {
      throw ArgumentError.value(balanceBucket, 'balanceBucket');
    }
    if (key.trim().isEmpty) {
      throw ArgumentError.value(key, 'key');
    }

    final sourceSubId = source.subAccountId;
    final destinationSubId = destination.subAccountId;
    final directionValid = switch (direction) {
      'main_to_sub' => sourceSubId == null && destinationSubId != null,
      'sub_to_main' => sourceSubId != null && destinationSubId == null,
      'sub_to_sub' =>
        sourceSubId != null &&
            destinationSubId != null &&
            sourceSubId != destinationSubId,
      _ => false,
    };
    if (!directionValid) {
      throw ArgumentError('Internal transfer direction/account mismatch');
    }

    final available = balanceBucket == 'active'
        ? source.activeAvailableGol
        : source.dimAvailableGol;
    if (amountGol <= 0 || amountGol > available) {
      throw ArgumentError.value(amountGol, 'amountGol');
    }
  }

  final int contractVersion;
  final String direction;
  final NajmBaharInternalAccountRef source;
  final NajmBaharInternalAccountRef destination;
  final String balanceBucket;
  final int amountGol;
  final String? description;
  final String key;

  int get frozenSourceAvailableGol => balanceBucket == 'active'
      ? source.activeAvailableGol
      : source.dimAvailableGol;

  Map<String, Object?> toJson() => {
        'direction': direction,
        'source_sub_account_id': source.subAccountId,
        'destination_sub_account_id': destination.subAccountId,
        'amount_gol': amountGol,
        'balance_bucket': balanceBucket,
        'description': description,
        'expected': {
          'internal_transfer_contract_version': contractVersion,
          'source_account_number': source.accountNumber,
          'source_available_gol': frozenSourceAvailableGol,
          'destination_account_number': destination.accountNumber,
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

class NajmBaharInternalTransferReceipt {
  NajmBaharInternalTransferReceipt.fromJson(Object? raw) {
    final value = _internalObject(raw);
    transaction = NajmBaharTransaction.fromJson(value['transaction']);
    source = _accountFromJson(value['source']);
    destination = _accountFromJson(value['destination']);
  }

  late final NajmBaharTransaction transaction;
  late final NajmBaharInternalAccountRef source;
  late final NajmBaharInternalAccountRef destination;

  bool matches(NajmBaharInternalTransferIntent intent) =>
      transaction.status == 'completed' &&
      transaction.direction == 'internal' &&
      transaction.amountGol == intent.amountGol &&
      transaction.balanceBucket == intent.balanceBucket &&
      source.accountNumber == intent.source.accountNumber &&
      destination.accountNumber == intent.destination.accountNumber;

  static NajmBaharInternalAccountRef _accountFromJson(Object? raw) {
    final value = _internalObject(raw);
    if (value.containsKey('sub_account_id')) {
      return NajmBaharSubAccount.fromJson(value);
    }
    return NajmBaharInternalMainAccount.fromJson(value);
  }
}
