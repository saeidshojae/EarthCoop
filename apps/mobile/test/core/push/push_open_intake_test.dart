import 'package:earthcoop_mobile/core/deep_links/semantic_link.dart';
import 'package:earthcoop_mobile/core/push/push_open_intake.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
      'push open always triggers authoritative recovery before typed-link intake',
      () async {
    final events = <String>[];
    SemanticLink? opened;
    final intake = PushOpenIntake(
      recoverNotifications: () async => events.add('recover'),
      openSemanticLink: (link) async {
        events.add('open');
        opened = link;
      },
    );

    final handled = await intake.handle({
      'type': 'group.notice',
      'link':
          '{"version":1,"route":"group.detail","params":{"group_id":42},"fallback_url":"https://earthcoop.ir/groups/42"}',
    });

    expect(handled, isTrue);
    expect(events, ['recover', 'open']);
    expect(opened?.route, 'group.detail');
    expect(opened?.params['group_id'], 42);
  });

  test('malformed or missing link never becomes arbitrary navigation',
      () async {
    var recoveryCount = 0;
    var openCount = 0;
    final intake = PushOpenIntake(
      recoverNotifications: () async => recoveryCount += 1,
      openSemanticLink: (_) async => openCount += 1,
    );

    expect(await intake.handle({'link': 'https://evil.example/path'}), isFalse);
    expect(await intake.handle({'type': 'notice'}), isFalse);
    expect(recoveryCount, 2);
    expect(openCount, 0);
  });
}
