import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_activation_dto.dart';

void main() {
  Map<String, Object?> eligible() => {
        'activation_contract_version': 1,
        'enabled': true,
        'source': 'participation',
        'remaining_convertible_points': 350,
        'conversion_ratio_points_per_gol': 100,
        'max_convertible_points': 300,
        'max_activation_points': 300,
        'max_activation_gol': 3,
        'dim_available_gol': 10,
        'active_gol': 5,
        'policy_version_id': 12,
        'policy_version': 1,
        'policy_source': 'versioned_policy',
      };

  test('consent freezes exact server values and rejects partial ratios', () {
    final terms = NajmBaharActivationTerms.fromJson(eligible());
    expect(
        () => NajmBaharActivationIntent(
            terms: terms, points: 250, key: 'activation-key-123'),
        throwsArgumentError);
    final intent = NajmBaharActivationIntent(
        terms: terms, points: 200, key: 'activation-key-123');
    expect(intent.amountGol, 2);
    expect(intent.toJson()['points'], 200);
    final expected = intent.toJson()['expected'] as Map<String, Object?>;
    expect(expected['policy_version_id'], 12);
    expect(expected['remaining_convertible_points'], 350);
    expect(expected['dim_available_gol'], 10);
  });

  test('rejects inconsistent eligibility and unsafe policy types', () {
    expect(
        () => NajmBaharActivationTerms.fromJson(
            {...eligible(), 'max_activation_points': 301}),
        throwsFormatException);
    expect(
        () => NajmBaharActivationTerms.fromJson(
            {...eligible(), 'remaining_convertible_points': '350'}),
        throwsFormatException);
    expect(
        () => NajmBaharActivationTerms.fromJson(
            {...eligible(), 'policy_version_id': null}),
        throwsFormatException);
  });
}
