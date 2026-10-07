import 'najm_bahar_dto.dart';

Map<String, Object?> _activationObject(Object? raw) {
  if (raw is! Map) {
    throw const FormatException('Expected activation object');
  }
  return Map<String, Object?>.from(raw);
}

int _activationInt(
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

bool _activationBool(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value is! bool) {
    throw FormatException('Invalid $key');
  }
  return value;
}

String _activationString(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('Invalid $key');
  }
  return value;
}

int? _activationNullablePositiveInt(
  Map<String, Object?> raw,
  String key,
) {
  final value = raw[key];
  if (value == null) return null;
  if (value is! int || value <= 0) {
    throw FormatException('Invalid $key');
  }
  return value;
}

class NajmBaharActivationEligibilityV1 {
  NajmBaharActivationEligibilityV1.fromJson(Object? raw) {
    final value = _activationObject(raw);

    contractVersion =
        _activationInt(value, 'activation_contract_version', positive: true);
    if (contractVersion != 1) {
      throw const FormatException('Unsupported activation contract');
    }

    enabled = _activationBool(value, 'enabled');
    source = _activationString(value, 'source');
    if (source != 'participation') {
      throw const FormatException('Unsupported activation source');
    }

    remainingPoints = _activationInt(value, 'remaining_convertible_points');
    pointsPerGol = _activationInt(
      value,
      'conversion_ratio_points_per_gol',
      positive: true,
    );
    maxConvertiblePoints = _activationInt(value, 'max_convertible_points');
    maxActivationPoints = _activationInt(value, 'max_activation_points');
    maxActivationGol = _activationInt(value, 'max_activation_gol');
    dimAvailableGol = _activationInt(value, 'dim_available_gol');
    activeGol = _activationInt(value, 'active_gol');

    policyVersionId =
        _activationNullablePositiveInt(value, 'policy_version_id');
    final rawPolicyVersion = value['policy_version'];
    if (rawPolicyVersion != null &&
        (rawPolicyVersion is! int || rawPolicyVersion <= 0)) {
      throw const FormatException('Invalid policy_version');
    }
    policyVersion = rawPolicyVersion as int?;

    policySource = _activationString(value, 'policy_source');
    if (policySource != 'versioned_policy' &&
        policySource != 'legacy_settings') {
      throw const FormatException('Unsupported activation policy source');
    }

    if (maxConvertiblePoints > remainingPoints ||
        maxConvertiblePoints % pointsPerGol != 0 ||
        maxActivationPoints > maxConvertiblePoints ||
        maxActivationPoints % pointsPerGol != 0 ||
        maxActivationGol != maxActivationPoints ~/ pointsPerGol ||
        maxActivationGol > dimAvailableGol) {
      throw const FormatException('Inconsistent activation limits');
    }

    if (policySource == 'versioned_policy' &&
        (policyVersionId == null || policyVersion == null)) {
      throw const FormatException('Versioned activation policy is incomplete');
    }
    if (policySource == 'legacy_settings' &&
        (policyVersionId != null || policyVersion != null)) {
      throw const FormatException(
          'Legacy activation policy must be unversioned');
    }
  }

  late final int contractVersion;
  late final bool enabled;
  late final String source;
  late final int remainingPoints;
  late final int pointsPerGol;
  late final int maxConvertiblePoints;
  late final int maxActivationPoints;
  late final int maxActivationGol;
  late final int dimAvailableGol;
  late final int activeGol;
  late final int? policyVersionId;
  late final int? policyVersion;
  late final String policySource;

  Map<String, Object?> expectedJson() => {
        'activation_contract_version': contractVersion,
        'remaining_convertible_points': remainingPoints,
        'conversion_ratio_points_per_gol': pointsPerGol,
        'max_convertible_points': maxConvertiblePoints,
        'max_activation_points': maxActivationPoints,
        'max_activation_gol': maxActivationGol,
        'dim_available_gol': dimAvailableGol,
        'policy_version_id': policyVersionId,
        'policy_version': policyVersion,
        'policy_source': policySource,
      };
}

class NajmBaharActivationIntent {
  NajmBaharActivationIntent({
    required this.eligibility,
    required this.points,
    required this.key,
  }) {
    if (!eligibility.enabled) {
      throw ArgumentError('Activation is not enabled');
    }
    if (points <= 0 ||
        points % eligibility.pointsPerGol != 0 ||
        points > eligibility.maxActivationPoints) {
      throw ArgumentError.value(points, 'points');
    }
    if (key.trim().isEmpty) {
      throw ArgumentError.value(key, 'key');
    }
  }

  final NajmBaharActivationEligibilityV1 eligibility;
  final int points;
  final String key;

  int get activatedGol => points ~/ eligibility.pointsPerGol;

  Map<String, Object?> toJson() => {
        'source': 'participation',
        'points': points,
        'expected': eligibility.expectedJson(),
      };
}

class NajmBaharActivationReceipt {
  NajmBaharActivationReceipt.fromJson(Object? raw) {
    final value = _activationObject(raw);

    source = _activationString(value, 'source');
    if (source != 'participation') {
      throw const FormatException('Unsupported activation receipt source');
    }

    requestedPoints = _activationInt(value, 'requested_points', positive: true);
    consumedPoints = _activationInt(value, 'consumed_points', positive: true);
    activatedGol = _activationInt(value, 'activated_gol', positive: true);
    transaction = NajmBaharTransaction.fromJson(value['transaction']);

    final rawBalance = value['balance'];
    if (rawBalance is! Map) {
      throw const FormatException('Missing activation balance');
    }
    final balance = Map<String, Object?>.from(rawBalance);
    local = NajmBaharBalance.fromJson(balance['local']);
    aggregate = NajmBaharBalance.fromJson(balance['aggregate']);
  }

  late final String source;
  late final int requestedPoints;
  late final int consumedPoints;
  late final int activatedGol;
  late final NajmBaharTransaction transaction;
  late final NajmBaharBalance local;
  late final NajmBaharBalance aggregate;

  bool matches(NajmBaharActivationIntent intent) =>
      source == 'participation' &&
      requestedPoints == intent.points &&
      consumedPoints == intent.points &&
      activatedGol == intent.activatedGol &&
      transaction.status == 'completed' &&
      transaction.direction == 'internal' &&
      transaction.amountGol == intent.activatedGol;
}
