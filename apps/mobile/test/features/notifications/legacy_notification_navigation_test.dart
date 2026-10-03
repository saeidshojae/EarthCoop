import 'package:earthcoop_mobile/core/deep_links/semantic_link.dart';
import 'package:earthcoop_mobile/features/notifications/notification_dto.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('legacy notification url is preserved when semantic link is absent', () {
    final notification = NotificationDto.fromJson({
      'id': 'legacy-1',
      'type': 'info',
      'title': 'اعلان قدیمی',
      'message': 'متن اعلان',
      'context': <String, Object?>{},
      'read': false,
      'read_at': null,
      'created_at': '2026-10-03T00:00:00Z',
      'url': '/groups/42',
      'link': null,
    });

    expect(notification.link, isNull);
    expect(notification.legacyUrl, '/groups/42');
  });

  test('semantic link remains authoritative when both link and url exist', () {
    final notification = NotificationDto.fromJson({
      'id': 'modern-1',
      'type': 'info',
      'title': 'اعلان جدید',
      'message': 'متن اعلان',
      'context': <String, Object?>{},
      'read': false,
      'read_at': null,
      'created_at': '2026-10-03T00:00:00Z',
      'url': '/groups/41',
      'link': {
        'version': 1,
        'route': 'group.detail',
        'params': {'group_id': 42},
        'fallback_url': '/groups/42',
      },
    });

    expect(notification.link, isA<SemanticLink>());
    expect(notification.link!.params['group_id'], 42);
    expect(notification.legacyUrl, '/groups/41');
  });
}
