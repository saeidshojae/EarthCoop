import '../../core/api/api_client.dart';
import '../../core/api/api_error.dart';
import '../../core/api/api_envelope.dart';
import 'najm_bahar_dto.dart';

class NajmBaharRepository {
  NajmBaharRepository(
      {required ApiClient apiClient, required bool Function() isCurrentSession})
      : _api = apiClient,
        _current = isCurrentSession;
  final ApiClient _api;
  final bool Function() _current;
  bool get isCurrentSession => _current();
  void _guard() {
    if (!_current()) {
      throw const ApiFailure(
          code: 'session_changed',
          message: '',
          retryable: false,
          httpStatus: 401);
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
      _guard();
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
      _guard();
      rethrow;
    }
  }
}
