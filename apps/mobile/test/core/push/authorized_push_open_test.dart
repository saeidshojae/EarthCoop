import 'dart:async';

import 'package:earthcoop_mobile/core/deep_links/semantic_link.dart';
import 'package:earthcoop_mobile/core/push/authorized_push_open.dart';
import 'package:earthcoop_mobile/features/notifications/notification_dto.dart';
import 'package:flutter_test/flutter_test.dart';

const notificationId = 'notice-123';
const authoritativeLink = SemanticLink(
  version: 1,
  route: 'group.detail',
  params: {'group_id': 42},
);

NotificationDto notice() => NotificationDto(
      id: notificationId,
      type: 'group.notice',
      title: null,
      message: null,
      context: const {},
      read: true,
      createdAt: null,
      link: authoritativeLink,
    );

void main() {
  test('open uses owner-authorized response instead of forged payload link',
      () async {
    SemanticLink? opened;
    final intake = AuthorizedPushOpen(
      activeScope: () => 'account-1',
      readNotification: (id) async {
        expect(id, notificationId);
        return notice();
      },
      openSemanticLink: (link) {
        opened = link;
        return true;
      },
    );
    expect(
      await intake.handle({
        'context': '{"notification_id":"notice-123"}',
        'link': 'https://evil.example/path',
      }),
      isTrue,
    );
    expect(opened?.params['group_id'], 42);
  });

  test('account change while acknowledgment waits prevents navigation',
      () async {
    Object? scope = 'account-1';
    final pending = Completer<NotificationDto?>();
    var opened = 0;
    final intake = AuthorizedPushOpen(
      activeScope: () => scope,
      readNotification: (_) => pending.future,
      openSemanticLink: (_) {
        opened++;
        return true;
      },
    );
    final handling = intake.handle({
      'context': {'notification_id': notificationId},
    });
    scope = 'account-2';
    pending.complete(notice());
    expect(await handling, isFalse);
    expect(opened, 0);
  });

  test('disposed scope and forbidden owner read leave navigation untouched', () async {
    Object? scope = 'account-1';
    var opened = 0;
    final pending = Completer<NotificationDto?>();
    final intake = AuthorizedPushOpen(
      activeScope: () => scope,
      readNotification: (_) => pending.future,
      openSemanticLink: (_) { opened++; return true; },
    );
    final handling = intake.handle({'context': {'notification_id': notificationId}});
    scope = null;
    pending.complete(notice());
    expect(await handling, isFalse);
    final forbidden = AuthorizedPushOpen(
      activeScope: () => 'account-1',
      readNotification: (_) async => throw StateError('forbidden'),
      openSemanticLink: (_) { opened++; return true; },
    );
    expect(await forbidden.handle({'context': {'notification_id': notificationId}}), isFalse);
    expect(opened, 0);
  });

  test('missing session and malformed IDs never call protected read', () async {
    var reads = 0;
    Object? scope;
    final intake = AuthorizedPushOpen(
      activeScope: () => scope,
      readNotification: (_) async {
        reads++;
        return notice();
      },
      openSemanticLink: (_) => true,
    );
    expect(await intake.handle({'context': {'notification_id': notificationId}}),
        isFalse);
    scope = 'account-1';
    for (final raw in [null, 'invalid-json', {'notification_id': '../other'}]) {
      expect(await intake.handle({'context': raw}), isFalse);
    }
    expect(reads, 0);
  });
}
