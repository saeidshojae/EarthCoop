import 'package:earthcoop_mobile/features/groups/group_detail_screen.dart';
import 'package:earthcoop_mobile/features/groups/group_dto.dart';
import 'package:earthcoop_mobile/features/groups/group_feed_dto.dart';
import 'package:earthcoop_mobile/features/groups/groups_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('file activity displays its attachment name', (tester) async {
    final event = GroupFeedEvent.fromJson({
      'sequence': 1,
      'payload': {
        'content_type': 'file',
        'content_id': 12,
        'attachment': {
          'file_name': 'guide.pdf',
          'mime_type': 'application/pdf',
          'download_path': '/groups/1/messages/12/attachment'
        }
      }
    });
    await tester.pumpWidget(MaterialApp(
        home: GroupDetailScreen(
      state: GroupDetailState.ready(sampleGroup(), activity: [event]),
    )));
    expect(find.text('guide.pdf'), findsOneWidget);
  });

  testWidgets('group detail exposes loading state', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: GroupDetailScreen(state: GroupDetailState.loading()),
      ),
    );

    expect(find.byType(CircularProgressIndicator), findsOneWidget);
  });

  testWidgets('detail renders user-facing projection without raw identity keys',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: GroupDetailScreen(
          state: GroupDetailState.ready(sampleGroup(), isStale: true),
        ),
      ),
    );

    expect(find.text('مجمع تخصصی علوم پایه در منطقه نمونه'), findsOneWidget);
    expect(find.text('فعال'), findsOneWidget);
    expect(find.text('specialty'), findsNothing);
    expect(find.text('experience_field:1'), findsNothing);
    expect(find.text('نمایش نسخه ذخیره‌شده'), findsOneWidget);
  });

  testWidgets('detail renders unread count and native group activity',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: GroupDetailScreen(
          state: GroupDetailState.ready(
            sampleGroup(),
            unreadCount: 3,
            activity: [
              const GroupFeedEvent.message(
                sequence: 8,
                sender: 'سعید شجاعی',
                message: 'سلام به اعضای گروه',
              ),
              const GroupFeedEvent.post(
                sequence: 9,
                title: 'گزارش فعالیت',
                content: 'خلاصه گزارش گروه',
              ),
              const GroupFeedEvent.poll(
                sequence: 10,
                question: 'جلسه بعدی چه روزی باشد؟',
                options: ['شنبه', 'یکشنبه'],
              ),
            ],
          ),
        ),
      ),
    );

    expect(find.text('۳ خوانده‌نشده'), findsOneWidget);
    expect(find.text('فعالیت‌های گروه'), findsOneWidget);
    expect(find.text('سعید شجاعی'), findsOneWidget);
    expect(find.text('سلام به اعضای گروه'), findsOneWidget);
    expect(find.text('گزارش فعالیت'), findsOneWidget);
    expect(find.text('خلاصه گزارش گروه'), findsOneWidget);
    expect(find.text('جلسه بعدی چه روزی باشد؟'), findsOneWidget);
    expect(find.text('۲ گزینه'), findsOneWidget);
  });

  testWidgets('activity failure does not hide group identity', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: GroupDetailScreen(
          state: GroupDetailState.ready(
            sampleGroup(),
            activityFailure: 'فعالیت‌های گروه فعلاً دریافت نشد.',
          ),
        ),
      ),
    );

    expect(find.text('مجمع تخصصی علوم پایه در منطقه نمونه'), findsOneWidget);
    expect(find.text('فعالیت‌های گروه فعلاً دریافت نشد.'), findsOneWidget);
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
      name: 'مجمع تخصصی علوم پایه در منطقه نمونه',
      identity: const GroupIdentity(
        governanceAreaId: 7,
        dimensionKey: 'specialty',
        dimensionValueKey: 'experience_field:1',
      ),
      membership: const GroupMembership(
        role: 1,
        roleLabel: 'فعال',
        status: 1,
      ),
      membersCount: 12,
      lastActivityAt: DateTime.utc(2026, 9, 29),
    );
