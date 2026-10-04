import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/features/groups/group_attachment.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../core/api/api_client_test.dart' show SequenceHttpAdapter;

const attachment = GroupAttachment(
    groupId: 1,
    messageId: 12,
    fileName: 'guide.pdf',
    mimeType: 'application/pdf');

ApiClient client(SequenceHttpAdapter adapter) {
  final dio = Dio(BaseOptions(baseUrl: 'https://earthcoop.test/api/v1'))
    ..httpClientAdapter = adapter;
  return ApiClient(
      dio: dio,
      bearerTokenProvider: () async => 'captured-token',
      deviceIdProvider: () async => 'device-one',
      requestIdFactory: () => 'download-1',
      retryDelay: (_) async {});
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test('metadata rejects external URLs and traversal and strips file path', () {
    expect(
        GroupAttachment.fromJson({
          'download_path': 'https://evil.test/file',
          'file_name': 'file',
          'mime_type': 'text/plain'
        }),
        isNull);
    expect(
        GroupAttachment.fromJson({
          'download_path': '/groups/1/../file',
          'file_name': 'file',
          'mime_type': 'text/plain'
        }),
        isNull);
    final value = GroupAttachment.fromJson({
      'download_path': attachment.path,
      'file_name': '../guide.pdf',
      'mime_type': 'application/pdf'
    })!;
    expect(value.fileName, 'guide.pdf');
    expect(value.groupId, 1);
  });
  test('binary GET uses captured bearer and device with redirects disabled',
      () async {
    final adapter = SequenceHttpAdapter([
      ResponseBody.fromBytes([1, 2, 3], 200,
          headers: {
            'content-length': ['3']
          })
    ]);
    final bytes = await client(adapter)
        .downloadAttachment(attachment.path, cancelToken: CancelToken());
    expect(bytes, [1, 2, 3]);
    final request = adapter.requests.single;
    expect(request.headers['Authorization'], 'Bearer captured-token');
    expect(request.headers['X-Device-ID'], 'device-one');
    expect(request.followRedirects, isFalse);
    expect(request.uri.toString(),
        'https://earthcoop.test/api/v1${attachment.path}');
  });
  test('external download path is rejected before any network request',
      () async {
    final adapter = SequenceHttpAdapter([]);
    await expectLater(
        client(adapter).downloadAttachment('https://evil.test',
            cancelToken: CancelToken()),
        throwsArgumentError);
    expect(adapter.requests, isEmpty);
  });
  test('oversize stream without length is rejected', () async {
    final adapter = SequenceHttpAdapter([
      ResponseBody.fromBytes([1, 2, 3], 200)
    ]);
    await expectLater(
        client(adapter).downloadAttachment(attachment.path,
            cancelToken: CancelToken(), maxBytes: 2),
        throwsA(isA<ApiFailure>()
            .having((e) => e.code, 'code', 'attachment_too_large')));
  });
  test('truncated stream is never exported', () async {
    final adapter = SequenceHttpAdapter([
      ResponseBody.fromBytes([1], 200,
          headers: {
            'content-length': ['2']
          })
    ]);
    await expectLater(
        client(adapter)
            .downloadAttachment(attachment.path, cancelToken: CancelToken()),
        throwsA(isA<ApiFailure>()
            .having((e) => e.code, 'code', 'attachment_incomplete')));
  });
  test('server rejection and redirect are never treated as a file', () async {
    for (final status in [401, 404, 302]) {
      final adapter = SequenceHttpAdapter([
        ResponseBody.fromBytes([1], status)
      ]);
      await expectLater(
          client(adapter)
              .downloadAttachment(attachment.path, cancelToken: CancelToken()),
          throwsA(isA<ApiFailure>()));
      expect(adapter.requests, hasLength(1));
    }
  });
  test('session change during download prevents export', () async {
    var current = true;
    var exports = 0;
    final adapter = SequenceHttpAdapter([
      ResponseBody.fromBytes([1], 200)
    ]);
    final downloader = GroupAttachmentDownloader(
        api: client(adapter),
        groupId: 1,
        isCurrentSession: () => current,
        export: (_, bytes) async {
          exports++;
          return true;
        });
    await expectLater(
        downloader.download(attachment, CancelToken(), (_) => current = false),
        throwsStateError);
    expect(exports, 0);
  });
  test('wrong group is rejected before network', () async {
    final adapter = SequenceHttpAdapter([]);
    final downloader = GroupAttachmentDownloader(
        api: client(adapter), groupId: 2, isCurrentSession: () => true);
    await expectLater(downloader.download(attachment, CancelToken(), (_) {}),
        throwsStateError);
    expect(adapter.requests, isEmpty);
  });
  test('export cancellation returns false and forwards bytes through channel',
      () async {
    MethodCall? call;
    const channel = MethodChannel('earthcoop/attachments');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (value) async {
      call = value;
      return false;
    });
    expect(
        await exportAndroidAttachment(attachment, Uint8List.fromList([1, 2])),
        isFalse);
    expect(call!.method, 'save');
    final arguments = call!.arguments as Map;
    expect(arguments['bytes'], [1, 2]);
    expect(arguments['name'], 'guide.pdf');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });
}
