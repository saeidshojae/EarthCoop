import 'package:earthcoop_mobile/core/deep_links/deep_link_registry.dart';
import 'package:earthcoop_mobile/core/deep_links/semantic_link.dart';
import 'package:earthcoop_mobile/core/push/push_open_intake.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('push open recovers notifications before resolving typed destination',
      () async {
    final events = <String>[];
    String? resolvedLocation;
    const registry = DeepLinkRegistry();
    final intake = PushOpenIntake(
      recoverNotifications: () async => events.add('recover'),
      openSemanticLink: (SemanticLink link) async {
        events.add('open');
        final resolution = registry.resolve(link);
        resolvedLocation = resolution.isAllowed
            ? resolution.location
            : resolution.fallbackLocation;
      },
    );

    final handled = await intake.handle({
      'type': 'group.notice',
      'link':
          '{"version":1,"route":"group.detail","params":{"group_id":42},"fallback_url":"https://earthcoop.ir/groups/42"}',
    });

    expect(handled, isTrue);
    expect(events, ['recover', 'open']);
    expect(resolvedLocation, '/groups/42');
  });

  test('malformed push link recovers but never becomes arbitrary navigation',
      () async {
    var recoveryCount = 0;
    var openCount = 0;
    final intake = PushOpenIntake(
      recoverNotifications: () async => recoveryCount += 1,
      openSemanticLink: (_) async => openCount += 1,
    );

    final handled = await intake.handle({
      'type': 'group.notice',
      'link': 'https://evil.example/path',
    });

    expect(handled, isFalse);
    expect(recoveryCount, 1);
    expect(openCount, 0);
  });
}
