import '../../core/api/api_client.dart';
import '../../core/api/api_error.dart';
import '../../core/api/request_context.dart';

class SentGroupMessage {
  const SentGroupMessage({
    required this.id,
    required this.groupId,
    required this.text,
  });
  final int id;
  final int groupId;
  final String text;
}

abstract interface class GroupMessageSender {
  Future<SentGroupMessage> send({
    required int groupId,
    required String text,
    required String idempotencyKey,
  });
}

class GroupMessageRepository implements GroupMessageSender {
  GroupMessageRepository({
    required ApiClient apiClient,
    required bool Function() isCurrentSession,
  }) : _apiClient = apiClient,
       _isCurrentSession = isCurrentSession;
  final ApiClient _apiClient;
  final bool Function() _isCurrentSession;

  void _checkSession() {
    if (!_isCurrentSession()) {
      throw const ApiFailure(
        code: 'session_changed',
        message: '',
        retryable: false,
        httpStatus: 401,
      );
    }
  }

  @override
  Future<SentGroupMessage> send({
    required int groupId,
    required String text,
    required String idempotencyKey,
  }) async {
    _checkSession();
    final result = await _apiClient.post<SentGroupMessage>(
      '/groups/$groupId/messages',
      data: {'message': text},
      context: RequestContext(idempotencyKey: idempotencyKey),
      decodeData: (raw) {
        if (raw is! Map ||
            raw['id'] is! int ||
            raw['group_id'] != groupId ||
            raw['message'] is! String) {
          throw const FormatException('Invalid group message acknowledgement');
        }
        return SentGroupMessage(
          id: raw['id'] as int,
          groupId: groupId,
          text: raw['message'] as String,
        );
      },
    );
    _checkSession();
    return result.data;
  }
}
