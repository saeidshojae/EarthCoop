import 'najm_bahar_dto.dart';

Map<String, Object?> _map(Object? raw) {
  if (raw is! Map) {
    throw const FormatException('Expected object');
  }
  return Map<String, Object?>.from(raw);
}

int _number(Map<String, Object?> raw, String key, {int minimum = 0}) {
  final v = raw[key];
  if (v is! int || v < minimum) {
    throw FormatException('Invalid $key');
  }
  return v;
}

bool _bool(Map<String, Object?> raw, String key) {
  final v = raw[key];
  if (v is! bool) {
    throw FormatException('Invalid $key');
  }
  return v;
}

void _nullableInteger(Map<String, Object?> raw, String key) {
  if (raw[key] != null && raw[key] is! int) {
    throw FormatException('Invalid $key');
  }
}

class NajmBaharActivationEligibility {
  NajmBaharActivationEligibility.fromJson(Object? raw) {
    final v = _map(raw);
    enabled = _bool(v, 'enabled');
    if (v['source'] != 'participation' || v['policy_source'] is! String) {
      throw const FormatException('Invalid policy');
    }
    _nullableInteger(v, 'policy_version');
    remainingPoints = _number(v, 'remaining_convertible_points');
    pointsPerGol = _number(v, 'conversion_ratio_points_per_gol', minimum: 1);
    maxConvertiblePoints = _number(v, 'max_convertible_points');
    maxActivationGol = _number(v, 'max_activation_gol');
    dimAvailableGol = _number(v, 'dim_available_gol');
    activeGol = _number(v, 'active_gol');
    if (maxConvertiblePoints > remainingPoints ||
        maxConvertiblePoints % pointsPerGol != 0 ||
        maxActivationGol > maxConvertiblePoints ~/ pointsPerGol ||
        maxActivationGol > dimAvailableGol) {
      throw const FormatException('Inconsistent activation limits');
    }
  }
  late final bool enabled;
  late final int remainingPoints,
      pointsPerGol,
      maxConvertiblePoints,
      maxActivationGol,
      dimAvailableGol,
      activeGol;
}

class NajmBaharMembershipFee {
  NajmBaharMembershipFee.fromJson(Object? raw) {
    final v = _map(raw);
    hasPaid = _bool(v, 'has_paid');
    paymentYear = _number(v, 'payment_year', minimum: 1);
    feeGol = _number(v, 'fee_gol', minimum: 1);
    canPayDim = _bool(v, 'can_pay_from_dim');
    canPayActive = _bool(v, 'can_pay_from_active');
    if (v['default_payment_source'] != 'active' &&
        v['default_payment_source'] != 'dim') {
      throw const FormatException('Invalid payment source');
    }
    _nullableInteger(v, 'policy_version_id');
    final b = _map(v['breakdown']);
    operationsGol = _number(b, 'operations_salary_gol');
    insuranceGol = _number(b, 'central_insurance_gol');
    destructionGol = _number(b, 'money_destruction_gol');
    if (BigInt.from(operationsGol) +
            BigInt.from(insuranceGol) +
            BigInt.from(destructionGol) !=
        BigInt.from(feeGol)) {
      throw const FormatException('Inconsistent fee breakdown');
    }
    final balances = _map(v['balance']);
    local = NajmBaharBalance.fromJson(balances['local']);
    aggregate = NajmBaharBalance.fromJson(balances['aggregate']);
  }
  late final bool hasPaid, canPayDim, canPayActive;
  late final int paymentYear,
      feeGol,
      operationsGol,
      insuranceGol,
      destructionGol;
  late final NajmBaharBalance local, aggregate;
}
