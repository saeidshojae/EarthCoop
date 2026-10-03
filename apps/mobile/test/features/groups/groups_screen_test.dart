import 'package:earthcoop_mobile/features/groups/group_dto.dart';
import 'package:earthcoop_mobile/features/groups/groups_controller.dart';
import 'package:earthcoop_mobile/features/groups/groups_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('groups list exposes loading and empty states', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: GroupsScreen(state: GroupsState.loading())),
    );
    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    await tester.pumpWidget(
      const MaterialApp(home: GroupsScreen(state: GroupsState.empty())),
    );
    expect(find.text('هنوز گروهی برای نمایش وجود ندارد.'), findsOneWidget);
  });

  testWidgets('ready list renders authoritative role label and stale marker',
      (tester) async {
    int? opened;
    await tester.pumpWidget(
      MaterialApp(
        home: GroupsScreen(
          state: GroupsState.ready([sampleGroup()], isStale: true),
          onOpenGroup: (id) => opened = id,
        ),
      ),
    );

    expect(find.text('مجمع عمومی محله نمونه'), findsOneWidget);
    expect(find.text('فعال'), findsOneWidget);
    expect(find.textContaining('۱۲'), findsOneWidget);
    expect(find.text('نمایش نسخه ذخیره‌شده'), findsOneWidget);

    await tester.tap(find.byKey(const Key('group-card-42')));
    expect(opened, 42);
  });

  testWidgets('pending shell is visible and cannot open group detail',
      (tester) async {
    int? opened;
    final pending = GroupDto.fromJson({
      'id': null,
      'name': 'مجمع عمومی محله در انتظار',
      'identity': {
        'governance_area_id': null,
        'dimension_key': 'public',
        'dimension_value_key': 'public',
      },
      'membership': {
        'role': 1,
        'role_label': 'فعال',
        'status': 1,
      },
      'members_count': 0,
      'last_activity_at': null,
      'pending': true,
      'pending_request_id': 901,
      'can_open': false,
    });

    await tester.pumpWidget(
      MaterialApp(
        home: GroupsScreen(
          state: GroupsState.ready([pending]),
          onOpenGroup: (id) => opened = id,
        ),
      ),
    );

    expect(find.text('مجمع عمومی محله در انتظار'), findsOneWidget);
    expect(find.text('در انتظار تأیید'), findsOneWidget);
    expect(find.textContaining('عضو'), findsNothing);

    await tester.tap(find.byKey(const Key('group-pending-901')));
    expect(opened, isNull);
  });

  testWidgets('retryable failure offers retry', (tester) async {
    var retried = false;
    await tester.pumpWidget(
      MaterialApp(
        home: GroupsScreen(
          state: const GroupsState.failure(
            GroupViewFailure.retryable('ارتباط برقرار نشد.'),
          ),
          onRetry: () => retried = true,
        ),
      ),
    );

    expect(find.byKey(const Key('groups-retry')), findsOneWidget);
    await tester.tap(find.byKey(const Key('groups-retry')));
    expect(retried, isTrue);
  });

  testWidgets('forbidden/non-retryable failure does not offer retry',
      (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: GroupsScreen(
          state: GroupsState.failure(
            GroupViewFailure.forbidden('دسترسی به گروه‌ها مجاز نیست.'),
          ),
        ),
      ),
    );

    expect(find.text('دسترسی به گروه‌ها مجاز نیست.'), findsOneWidget);
    expect(find.byKey(const Key('groups-retry')), findsNothing);
  });
}

GroupDto sampleGroup() => GroupDto(
      id: 42,
      name: 'مجمع عمومی محله نمونه',
      identity: const GroupIdentity(
        governanceAreaId: 7,
        dimensionKey: 'public',
        dimensionValueKey: 'assembly',
      ),
      membership: const GroupMembership(
        role: 1,
        roleLabel: 'فعال',
        status: 1,
      ),
      membersCount: 12,
      lastActivityAt: DateTime.utc(2026, 9, 29),
    );
