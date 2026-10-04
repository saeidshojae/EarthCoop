import 'dart:async';
import 'package:earthcoop_mobile/features/groups/group_message_repository.dart';
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
    await tester.pump();
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

  testWidgets(
      'acknowledgement survives replacement of the composer during refresh',
      (tester) async {
    final sender = DelayedSender();
    var refreshed = 0;
    final controller = GroupMessageComposerController(
        groupId: 42, sender: sender, onSent: () => refreshed++);
    final state = ValueNotifier(GroupDetailState.ready(detail.sampleGroup()));
    await tester.pumpWidget(MaterialApp(
        home: ValueListenableBuilder(
      valueListenable: state,
      builder: (context, value, child) => GroupDetailScreen(
          state: value,
          composer: controller,
          onRetry: () => state.value = const GroupDetailState.loading()),
    )));
    await tester.enterText(
        find.byKey(const Key('group-message-draft')), 'سلام');
    await tester.pump();
    await tester.tap(find.byKey(const Key('group-message-send')));
    await tester.pump();
    await tester.tap(find.byTooltip('دریافت فعالیت‌های تازه'));
    await tester.pump();
    state.value = GroupDetailState.ready(detail.sampleGroup());
    await tester.pump();
    sender.result
        .complete(const SentGroupMessage(id: 99, groupId: 42, text: 'سلام'));
    await tester.pumpAndSettle();
    expect(refreshed, 1);
    expect(
        tester
            .widget<TextField>(find.byKey(const Key('group-message-draft')))
            .controller!
            .text,
        isEmpty);
    await tester.pumpWidget(const SizedBox());
    state.dispose();
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

class DelayedSender implements GroupMessageSender {
  final result = Completer<SentGroupMessage>();
  @override
  Future<SentGroupMessage> send(
          {required int groupId,
          required String text,
          required String idempotencyKey}) =>
      result.future;
}
