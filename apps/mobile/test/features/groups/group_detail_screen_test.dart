import 'package:earthcoop_mobile/features/groups/group_detail_screen.dart';
import 'package:earthcoop_mobile/features/groups/group_dto.dart';
import 'package:earthcoop_mobile/features/groups/groups_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('group detail exposes loading state', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: GroupDetailScreen(state: GroupDetailState.loading()),
      ),
    );

    expect(find.byType(CircularProgressIndicator), findsOneWidget);
  });

  testWidgets('detail renders server projection and stale warning',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: GroupDetailScreen(
          state: GroupDetailState.ready(sampleGroup(), isStale: true),
        ),
      ),
    );

    expect(find.text('مجمع عمومی محله نمونه'), findsOneWidget);
    expect(find.text('فعال'), findsOneWidget);
    expect(find.text('public'), findsOneWidget);
    expect(find.text('assembly'), findsOneWidget);
    expect(find.text('نمایش نسخه ذخیره‌شده'), findsOneWidget);
  });

  testWidgets('retryable detail failure offers retry', (tester) async {
    var retried = false;
    await tester.pumpWidget(
      MaterialApp(
        home: GroupDetailScreen(
          state: const GroupDetailState.failure(
            GroupViewFailure.retryable('ارتباط برقرار نشد.'),
          ),
          onRetry: () => retried = true,
        ),
      ),
    );

    await tester.tap(find.byKey(const Key('group-detail-retry')));
    expect(retried, isTrue);
  });

  testWidgets('forbidden detail is blocking and cannot be retried',
      (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: GroupDetailScreen(
          state: GroupDetailState.failure(
            GroupViewFailure.forbidden('دسترسی به این گروه مجاز نیست.'),
          ),
        ),
      ),
    );

    expect(find.text('دسترسی به این گروه مجاز نیست.'), findsOneWidget);
    expect(find.byKey(const Key('group-detail-retry')), findsNothing);
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
