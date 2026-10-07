class MembershipSource {
  MembershipSource.fromJson(Object? raw) {
    if (raw is! Map) throw const FormatException('Expected payment source');
    final v = Map<String, Object?>.from(raw);
    final rawKind = v['kind'];
    if (rawKind != 'main' && rawKind != 'subaccount') {
      throw const FormatException('Invalid payment source kind');
    }
    kind = rawKind as String;

    final rawSub = v['sub_account_id'];
    if (rawSub != null && (rawSub is! int || rawSub <= 0)) {
      throw const FormatException('Invalid subaccount id');
    }
    subAccountId = rawSub as int?;

    if ((kind == 'main' && subAccountId != null) ||
        (kind == 'subaccount' && subAccountId == null)) {
      throw const FormatException('Inconsistent payment source selector');
    }

    accountNumber = _requiredString(v, 'account_number');
    name = _requiredString(v, 'name');
    activeAvailableGol = _nonNegativeInt(v, 'active_available_gol');
    dimAvailableGol = _nonNegativeInt(v, 'dim_available_gol');
    canPayActive = _requiredBool(v, 'can_pay_active');
    canPayDim = _requiredBool(v, 'can_pay_dim');

    if (kind == 'subaccount' && (canPayDim || dimAvailableGol != 0)) {
      throw const FormatException('Subaccounts cannot provide Dim payment');
    }
  }

  late final String kind;
  late final int? subAccountId;
  late final String accountNumber;
  late final String name;
  late final int activeAvailableGol;
  late final int dimAvailableGol;
  late final bool canPayActive;
  late final bool canPayDim;
}

String _requiredString(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('Invalid $key');
  }
  return value;
}

int _nonNegativeInt(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value is! int || value < 0) throw FormatException('Invalid $key');
  return value;
}

bool _requiredBool(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value is! bool) throw FormatException('Invalid $key');
  return value;
}
