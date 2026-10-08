import 'najm_bahar_dto.dart';

Map<String, Object?> _object(Object? raw) {
  if (raw is! Map) throw const FormatException('Invalid activation object');
  return Map<String, Object?>.from(raw);
}

int _int(Map<String, Object?> data, String name, {int minimum = 0}) {
  final v = data[name];
  if (v is! int || v < minimum) throw FormatException('Invalid $name');
  return v;
}

int? _nullableInt(Map<String, Object?> data, String name) {
  if (!data.containsKey(name)) throw FormatException('Missing $name');
  final v = data[name];
  if (v == null) return null;
  if (v is! int || v <= 0) throw FormatException('Invalid $name');
  return v;
}

class NajmBaharActivationTerms {
  NajmBaharActivationTerms.fromJson(Object? raw) {
    final d = _object(raw);
    contractVersion = _int(d, 'activation_contract_version', minimum: 1);
    if (contractVersion != 1 || d['source'] != 'participation' ||
        d['enabled'] is! bool || d['enabled'] != true ||
        d['policy_source'] is! String) {
      throw const FormatException('Unsupported activation contract');
    }
    policyVersionId = _nullableInt(d, 'policy_version_id');
    policyVersion = _nullableInt(d, 'policy_version');
    final source = d['policy_source'];
    if (source != 'versioned_policy' && source != 'legacy_settings') {
      throw const FormatException('Unknown activation policy source');
    }
    if (source == 'versioned_policy' &&
        (policyVersionId == null || policyVersion == null)) {
      throw const FormatException('Missing persisted policy identity');
    }
    remainingPoints = _int(d, 'remaining_convertible_points');
    pointsPerGol = _int(d, 'conversion_ratio_points_per_gol', minimum: 1);
    maxConvertiblePoints = _int(d, 'max_convertible_points');
    maxActivationPoints = _int(d, 'max_activation_points');
    maxActivationGol = _int(d, 'max_activation_gol');
    dimAvailableGol = _int(d, 'dim_available_gol');
    activeGol = _int(d, 'active_gol');
    if (maxConvertiblePoints !=
            remainingPoints ~/ pointsPerGol * pointsPerGol ||
        maxActivationGol !=
            (maxConvertiblePoints ~/ pointsPerGol < dimAvailableGol
                ? maxConvertiblePoints ~/ pointsPerGol
                : dimAvailableGol) ||
        BigInt.from(maxActivationPoints) !=
            BigInt.from(maxActivationGol) * BigInt.from(pointsPerGol)) {
      throw const FormatException('Inconsistent activation capacity');
    }
  }

  late final int contractVersion, remainingPoints, pointsPerGol,
      maxConvertiblePoints, maxActivationPoints, maxActivationGol,
      dimAvailableGol, activeGol;
  late final int? policyVersionId, policyVersion;

  Map<String, Object?> expectedJson() => {
    'activation_contract_version': contractVersion,
    'policy_version_id': policyVersionId,
    'policy_version': policyVersion,
    'conversion_ratio_points_per_gol': pointsPerGol,
    'remaining_convertible_points': remainingPoints,
    'dim_available_gol': dimAvailableGol,
    'max_activation_gol': maxActivationGol,
  };
}

class NajmBaharActivationIntent {
  NajmBaharActivationIntent({
    required this.terms,
    required this.points,
    required this.key,
  }) {
    if (points <= 0 || points % terms.pointsPerGol != 0 ||
        points > terms.maxActivationPoints) {
      throw ArgumentError.value(points, 'points');
    }
    if (!RegExp(r'^[A-Za-z0-9._:-]{8,100}$').hasMatch(key)) {
      throw ArgumentError.value(key, 'key');
    }
  }
  final NajmBaharActivationTerms terms;
  final int points;
  final String key;
  int get amountGol => points ~/ terms.pointsPerGol;

  Map<String, Object?> toJson() => {
    'source': 'participation',
    'points': points,
    'expected': terms.expectedJson(),
  };
}

class NajmBaharActivationReceipt {
  NajmBaharActivationReceipt.fromJson(Object? raw) {
    final d = _object(raw);
    if (d['source'] != 'participation') {
      throw const FormatException('Invalid activation source');
    }
    requestedPoints = _int(d, 'requested_points', minimum: 1);
    consumedPoints = _int(d, 'consumed_points', minimum: 1);
    activatedGol = _int(d, 'activated_gol', minimum: 1);
    transaction = NajmBaharTransaction.fromJson(d['transaction']);
    if (d['balance'] != null) {
      final b = _object(d['balance']);
      local = NajmBaharBalance.fromJson(b['local']);
    } else {
      local = null;
    }
  }
  late final int requestedPoints, consumedPoints, activatedGol;
  late final NajmBaharTransaction transaction;
  late final NajmBaharBalance? local;

  bool matches(NajmBaharActivationIntent intent) =>
      requestedPoints == intent.points &&
      consumedPoints == intent.points &&
      activatedGol == intent.amountGol &&
      transaction.status == 'completed' &&
      transaction.amountGol == intent.amountGol;
}
