import 'dart:convert';

import '../deep_links/semantic_link.dart';
import '../../features/notifications/notification_dto.dart';

class AuthorizedPushOpen {
  const AuthorizedPushOpen({
    required this.activeScope,
    required this.readNotification,
    required this.openSemanticLink,
  });

  final Object? Function() activeScope;
  final Future<NotificationDto?> Function(String id) readNotification;
  final bool Function(SemanticLink link) openSemanticLink;

  Future<bool> handle(Map<String, Object?> payload) async {
    final scope = activeScope();
    final id = _notificationId(payload['context']);
    if (scope == null || id == null) return false;
    try {
      final notification = await readNotification(id);
      if (activeScope() != scope || notification?.id != id) return false;
      final link = notification?.link;
      return link != null && openSemanticLink(link);
    } catch (_) {
      return false;
    }
  }

  String? _notificationId(Object? raw) {
    try {
      if (raw is String) {
        if (raw.length > 8192) return null;
        raw = jsonDecode(raw);
      }
      if (raw is! Map) return null;
      final id = raw['notification_id'];
      return id is String && RegExp(r'^[A-Za-z0-9_-]{1,128}$').hasMatch(id)
          ? id
          : null;
    } catch (_) {
      return null;
    }
  }
}
