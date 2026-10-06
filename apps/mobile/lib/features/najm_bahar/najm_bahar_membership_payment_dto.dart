import 'najm_bahar_membership_source.dart';
import 'najm_bahar_policy_dto.dart';

export 'najm_bahar_membership_source.dart';

class MembershipPaymentIntent {
  MembershipPaymentIntent({
    required this.terms,
    required this.source,
    required this.bucket,
    required this.key,
  }) {
    if (terms.paymentContractVersion != 1) {
      throw ArgumentError('Membership payment contract is unavailable');
    }
    if (bucket != 'dim' && bucket != 'active') {
      throw ArgumentError.value(bucket, 'bucket');
    }
    if (key.trim().isEmpty) {
      throw ArgumentError.value(key, 'key');
    }

    final selected = terms.paymentSources.any((candidate) =>
        candidate.kind == source.kind &&
        candidate.subAccountId == source.subAccountId &&
        candidate.accountNumber == source.accountNumber);
    if (!selected) throw ArgumentError('Payment source is not in current terms');

    if (bucket == 'dim') {
      if (source.kind != 'main' ||
          source.subAccountId != null ||
          !source.canPayDim) {
        throw ArgumentError('Selected source cannot pay from Dim');
      }
    } else if (!source.canPayActive) {
      throw ArgumentError('Selected source cannot pay from Active');
    }
  }

  final NajmBaharMembershipFee terms;
  final MembershipSource source;
  final String bucket;
  final String key;

  Map<String, Object?> toJson() => {
        'payment_source': bucket,
        'sub_account_id': source.subAccountId,
        'expected': {
          'payment_year': terms.paymentYear,
          'fee_gol': terms.feeGol,
          'breakdown': {
            'operations_salary_gol': terms.operationsGol,
            'central_insurance_gol': terms.insuranceGol,
            'money_destruction_gol': terms.destructionGol,
          },
          'policy_version_id': terms.policyVersionId,
          'account_number': source.accountNumber,
        },
      };
}

class MembershipPaymentReceipt {
  MembershipPaymentReceipt.fromJson(Object? raw) {
    if (raw is! Map) throw const FormatException('Expected payment receipt');
    final v = Map<String, Object?>.from(raw);
    if (v['has_paid'] is! bool || v['has_paid'] != true) {
      throw const FormatException('Payment is not confirmed');
    }
    hasPaid = true;
    paymentYear = _positiveInt(v, 'payment_year');
    feeGol = _positiveInt(v, 'fee_gol');

    final breakdownRaw = v['breakdown'];
    if (breakdownRaw is! Map) throw const FormatException('Invalid breakdown');
    final breakdown = Map<String, Object?>.from(breakdownRaw);
    operationsGol = _nonNegativeInt(breakdown, 'operations_salary_gol');
    insuranceGol = _nonNegativeInt(breakdown, 'central_insurance_gol');
    destructionGol = _nonNegativeInt(breakdown, 'money_destruction_gol');
    if (BigInt.from(operationsGol) +
            BigInt.from(insuranceGol) +
            BigInt.from(destructionGol) !=
        BigInt.from(feeGol)) {
      throw const FormatException('Inconsistent receipt breakdown');
    }

    final rawBucket = v['payment_source'];
    if (rawBucket != 'dim' && rawBucket != 'active') {
      throw const FormatException('Invalid payment source');
    }
    paymentSource = rawBucket as String;

    final rawAccount = v['payment_account_number'];
    if (rawAccount is! String || rawAccount.trim().isEmpty) {
      throw const FormatException('Invalid payment account');
    }
    paymentAccountNumber = rawAccount;
  }

  late final bool hasPaid;
  late final int paymentYear;
  late final int feeGol;
  late final int operationsGol;
  late final int insuranceGol;
  late final int destructionGol;
  late final String paymentSource;
  late final String paymentAccountNumber;

  bool matches(MembershipPaymentIntent intent) =>
      paymentYear == intent.terms.paymentYear &&
      feeGol == intent.terms.feeGol &&
      operationsGol == intent.terms.operationsGol &&
      insuranceGol == intent.terms.insuranceGol &&
      destructionGol == intent.terms.destructionGol &&
      paymentSource == intent.bucket &&
      paymentAccountNumber == intent.source.accountNumber;
}

int _positiveInt(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value is! int || value <= 0) throw FormatException('Invalid $key');
  return value;
}

int _nonNegativeInt(Map<String, Object?> raw, String key) {
  final value = raw[key];
  if (value is! int || value < 0) throw FormatException('Invalid $key');
  return value;
}
