import '../../core/api/api_client.dart';
import '../../core/api/api_error.dart';
import '../../core/api/api_envelope.dart';
import '../../core/api/request_context.dart';
import 'najm_bahar_dto.dart';
import 'najm_bahar_policy_dto.dart';
import 'najm_bahar_membership_payment_dto.dart';
import 'najm_bahar_transfer_dto.dart';
import 'najm_bahar_activation_dto.dart';

class NajmBaharRepository {
  NajmBaharRepository(
      {required ApiClient apiClient,
      required bool Function() isCurrentSession,
      bool Function()? isNetworkAllowed})
      : _api = apiClient,
        _current = isCurrentSession,
        _networkAllowed = isNetworkAllowed ?? (() => true);
  final ApiClient _api;
  final bool Function() _current;
  final bool Function() _networkAllowed;
  bool get isCurrentSession => _current();
  void _guardSession() {
    if (!_current()) {
      throw const ApiFailure(
          code: 'session_changed',
          message: '',
          retryable: false,
          httpStatus: 401);
    }
  }

  void _guard() {
    _guardSession();
    if (!_networkAllowed()) {
      throw const ApiFailure(
          code: 'bootstrap_unavailable', message: '', retryable: true);
    }
  }

  Future<T> _policy<T>(String path, T Function(Object?) decode) async {
    _guard();
    try {
      final response = await _api.get<T>(path, decodeData: decode);
      _guard();
      return response.data;
    } catch (_) {
      _guardSession();
      rethrow;
    }
  }

  Future<NajmBaharActivationEligibility> activationEligibility() => _policy(
      '/najm-bahar/activation/eligibility',
      NajmBaharActivationEligibility.fromJson);

  Future<NajmBaharActivationEligibilityV1> activationEligibilityV1() => _policy(
      '/najm-bahar/activation/eligibility',
      NajmBaharActivationEligibilityV1.fromJson);

  Future<NajmBaharActivationReceipt> activateParticipation(
      NajmBaharActivationIntent intent) async {
    _guard();
    try {
      final response = await _api.post<NajmBaharActivationReceipt>(
        '/najm-bahar/activation',
        data: intent.toJson(),
        context: RequestContext(
          idempotencyKey: intent.key,
          allowAutomaticRetry: false,
        ),
        decodeData: NajmBaharActivationReceipt.fromJson,
      );
      _guardSession();
      if (!response.data.matches(intent)) throw malformedResponse();
      return response.data;
    } catch (_) {
      _guardSession();
      rethrow;
    }
  }

  Future<NajmBaharActivationReceipt> reconcileActivation(
      NajmBaharActivationIntent intent) async {
    _guard();
    try {
      final encodedKey = Uri.encodeComponent(intent.key);
      final response = await _api.get<NajmBaharActivationReceipt>(
        '/najm-bahar/activation/by-idempotency/$encodedKey',
        decodeData: NajmBaharActivationReceipt.fromJson,
      );
      _guard();
      if (!response.data.matches(intent)) throw malformedResponse();
      return response.data;
    } catch (_) {
      _guardSession();
      rethrow;
    }
  }
  Future<NajmBaharMembershipFee> membershipFee() =>
      _policy('/najm-bahar/membership-fee', NajmBaharMembershipFee.fromJson);

  Future<MembershipPaymentReceipt> payMembership(
      MembershipPaymentIntent intent) async {
    _guard();
    try {
      final response = await _api.post<MembershipPaymentReceipt>(
        '/najm-bahar/membership-fee/pay',
        data: intent.toJson(),
        context: RequestContext(
          idempotencyKey: intent.key,
          allowAutomaticRetry: false,
        ),
        decodeData: MembershipPaymentReceipt.fromJson,
      );
      _guardSession();
      if (!response.data.matches(intent)) throw malformedResponse();
      return response.data;
    } catch (_) {
      _guardSession();
      rethrow;
    }
  }

  Future<NajmBaharTransferCapability> transferCapability() => _policy(
      '/najm-bahar/transfers/capability', NajmBaharTransferCapability.fromJson);

  Future<NajmBaharTransferDestination> transferDestination(
      String accountNumber) async {
    _guard();
    try {
      final response = await _api.get<NajmBaharTransferDestination>(
        '/najm-bahar/transfers/destination',
        queryParameters: {'account_number': accountNumber},
        decodeData: NajmBaharTransferDestination.fromJson,
      );
      _guard();
      return response.data;
    } catch (_) {
      _guardSession();
      rethrow;
    }
  }

  Future<NajmBaharTransferReceipt> sendTransfer(
      NajmBaharTransferIntent intent) async {
    _guard();
    try {
      final response = await _api.post<NajmBaharTransferReceipt>(
        '/najm-bahar/transfers',
        data: intent.toJson(),
        context: RequestContext(
          idempotencyKey: intent.key,
          allowAutomaticRetry: false,
        ),
        decodeData: NajmBaharTransferReceipt.fromMutationJson,
      );
      _guardSession();
      if (!response.data.matches(intent)) throw malformedResponse();
      return response.data;
    } catch (_) {
      _guardSession();
      rethrow;
    }
  }

  Future<NajmBaharTransferReceipt> reconcileTransfer(
      NajmBaharTransferIntent intent) async {
    _guard();
    try {
      final encodedKey = Uri.encodeComponent(intent.key);
      final response = await _api.get<NajmBaharTransferReceipt>(
        '/najm-bahar/transfers/by-idempotency/$encodedKey',
        decodeData: NajmBaharTransferReceipt.fromReconciliationJson,
      );
      _guard();
      if (!response.data.matches(intent)) throw malformedResponse();
      return response.data;
    } catch (_) {
      _guardSession();
      rethrow;
    }
  }

  Future<NajmBaharAccount> account() async {
    _guard();
    try {
      final result = await _api.get<NajmBaharAccount>('/najm-bahar/account',
          decodeData: NajmBaharAccount.fromJson);
      _guard();
      return result.data;
    } catch (_) {
      _guardSession();
      rethrow;
    }
  }

  Future<NajmBaharHistoryPage> history({String? cursor}) async {
    _guard();
    try {
      final response = await _api.get<List<NajmBaharTransaction>>(
          '/najm-bahar/transactions',
          queryParameters: {
            'page[limit]': 20,
            if (cursor != null) 'page[cursor]': cursor
          }, decodeData: (raw) {
        if (raw is! List) {
          throw const FormatException('Expected transaction list');
        }
        return raw.map(NajmBaharTransaction.fromJson).toList(growable: false);
      });
      _guard();
      final raw = response.meta['pagination'];
      if (raw is! Map ||
          raw['has_more'] is! bool ||
          (raw['next_cursor'] != null && raw['next_cursor'] is! String)) {
        throw malformedResponse();
      }
      final next = raw['next_cursor'] as String?;
      final more = raw['has_more'] as bool;
      if (more && (next == null || next.isEmpty)) throw malformedResponse();
      return NajmBaharHistoryPage(
          items: List.unmodifiable(response.data),
          nextCursor: next,
          hasMore: more);
    } catch (_) {
      _guardSession();
      rethrow;
    }
  }
}
