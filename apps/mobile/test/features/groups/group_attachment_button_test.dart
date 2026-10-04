import 'dart:async';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/features/groups/group_attachment.dart';
import 'package:earthcoop_mobile/features/groups/group_attachment_button.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

const attachment = GroupAttachment(
    groupId: 1,
    messageId: 2,
    fileName: 'file.pdf',
    mimeType: 'application/pdf');
void main() {
  testWidgets('download shows progress, cancellation and saved receipt',
      (tester) async {
    final pending = Completer<bool>();
    CancelToken? token;
    await tester.pumpWidget(MaterialApp(
        home: Scaffold(
            body: GroupAttachmentButton(
                attachment: attachment,
                download: (_, cancellation, progress) {
                  token = cancellation;
                  progress(0.5);
                  return pending.future;
                }))));
    await tester.tap(find.text('دانلود فایل'));
    await tester.pump();
    expect(find.text('دانلود فایل'), findsNothing);
    expect(
        tester
            .widget<LinearProgressIndicator>(
                find.byType(LinearProgressIndicator))
            .value,
        0.5);
    expect(find.text('لغو دانلود'), findsOneWidget);
    pending.complete(true);
    await tester.pumpAndSettle();
    expect(find.text('فایل ذخیره شد.'), findsOneWidget);
    expect(token!.isCancelled, isFalse);
  });
  testWidgets('disposing the activity cancels its active download',
      (tester) async {
    final pending = Completer<bool>();
    CancelToken? token;
    await tester.pumpWidget(MaterialApp(
        home: GroupAttachmentButton(
            attachment: attachment,
            download: (_, cancellation, progress) {
              token = cancellation;
              return pending.future;
            })));
    await tester.tap(find.text('دانلود فایل'));
    await tester.pump();
    await tester.pumpWidget(const SizedBox());
    expect(token!.isCancelled, isTrue);
    pending.complete(false);
    await tester.pump();
    expect(tester.takeException(), isNull);
  });
  testWidgets('failure permits an explicit retry', (tester) async {
    var calls = 0;
    await tester.pumpWidget(MaterialApp(
        home: GroupAttachmentButton(
            attachment: attachment,
            download: (_, cancellation, progress) async {
              calls++;
              throw StateError('unavailable');
            })));
    await tester.tap(find.text('دانلود فایل'));
    await tester.pumpAndSettle();
    expect(find.textContaining('دریافت فایل انجام نشد'), findsOneWidget);
    await tester.tap(find.text('دانلود فایل'));
    await tester.pumpAndSettle();
    expect(calls, 2);
  });
}
