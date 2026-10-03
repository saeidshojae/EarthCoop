import 'dart:convert';

import '../deep_links/semantic_link.dart';

typedef RecoverNotifications = Future<void> Function();
typedef OpenSemanticLink = Future<void> Function(SemanticLink link);

class PushOpenIntake {
  const PushOpenIntake({
    required RecoverNotifications recoverNotifications,
    required OpenSemanticLink openSemanticLink,
  })  : _recoverNotifications = recoverNotifications,
        _openSemanticLink = openSemanticLink;

  final RecoverNotifications _recoverNotifications;
  final OpenSemanticLink _openSemanticLink;

  Future<bool> handle(Map<String, Object?> payload) async {
    await _recoverNotifications();

    final rawLink = payload['link'];
    if (rawLink == null) return false;

    Object? decoded = rawLink;
    if (rawLink is String) {
      try {
        decoded = jsonDecode(rawLink);
      } catch (_) {
        return false;
      }
    }

    try {
      final link = SemanticLink.fromJson(decoded);
      await _openSemanticLink(link);
      return true;
    } on FormatException {
      return false;
    }
  }
}
