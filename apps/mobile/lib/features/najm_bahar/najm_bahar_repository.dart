import '../../core/api/api_client.dart';
import '../../core/api/api_error.dart';
import '../../core/api/api_envelope.dart';
import 'najm_bahar_dto.dart';
import 'najm_bahar_policy_dto.dart';

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


  Future<T> _policy<T>(String path,T Function(Object?) decode) async {
    _guard();
    try {
      final response=await _api.get<T>(path,decodeData:decode);
      _guard(); return response.data;
    } catch (_) { _guardSession(); rethrow; }
  }
  Future<NajmBaharActivationEligibility> activationEligibility() => _policy('/najm-bahar/activation/eligibility',NajmBaharActivationEligibility.fromJson);
  Future<NajmBaharMembershipFee> membershipFee() => _policy('/najm-bahar/membership-fee',NajmBaharMembershipFee.fromJson);
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
