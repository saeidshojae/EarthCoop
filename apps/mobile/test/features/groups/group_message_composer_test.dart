import 'package:earthcoop_mobile/features/groups/group_detail_screen.dart';
import 'package:earthcoop_mobile/features/groups/group_message_composer.dart';
import 'package:earthcoop_mobile/features/groups/group_message_composer_controller.dart';
import 'package:earthcoop_mobile/features/groups/groups_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'group_detail_screen_test.dart' as detail;
import 'group_message_send_test.dart' as send;

void main() {
  testWidgets(
      'visible composer preserves failed text and refreshes only after acknowledgement',
      (tester) async {
    final sender = send.FakeSender();
    final controller =
        GroupMessageComposerController(groupId: 42, sender: sender);
    var refreshed = 0;
    await tester.pumpWidget(MaterialApp(
        home: GroupDetailScreen(
      state: GroupDetailState.ready(detail.sampleGroup()),
      composer: controller,
      onRetry: () => refreshed++,
    )));
    await tester.enterText(
        find.byKey(const Key('group-message-draft')), 'سلام');
    await tester.tap(find.byKey(const Key('group-message-send')));
    await tester.pumpAndSettle();
    expect(refreshed, 0);
    expect(find.byKey(const Key('group-message-error')), findsOneWidget);
    expect(
        tester
            .widget<TextField>(find.byKey(const Key('group-message-draft')))
            .controller!
            .text,
        'سلام');
    await tester.tap(find.byKey(const Key('group-message-send')));
    await tester.pumpAndSettle();
    expect(refreshed, 1);
    expect(sender.keys.toSet(), hasLength(1));
    expect(
        tester
            .widget<TextField>(find.byKey(const Key('group-message-draft')))
            .controller!
            .text,
        isEmpty);
    await tester.pumpWidget(const SizedBox());
    controller.dispose();
  });

  testWidgets('cached group never exposes a write composer', (tester) async {
    final controller =
        GroupMessageComposerController(groupId: 42, sender: send.FakeSender());
    await tester.pumpWidget(MaterialApp(
        home: GroupDetailScreen(
      state: GroupDetailState.ready(detail.sampleGroup(), isStale: true),
      composer: controller,
    )));
    expect(find.byType(GroupMessageComposer), findsNothing);
    await tester.pumpWidget(const SizedBox());
    controller.dispose();
  });
}
