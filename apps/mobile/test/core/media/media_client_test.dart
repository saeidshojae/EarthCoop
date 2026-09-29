import 'dart:collection';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/api/retry_policy.dart';
import 'package:earthcoop_mobile/core/media/media_client.dart';
import 'package:earthcoop_mobile/core/media/media_picker.dart';
import 'package:earthcoop_mobile/core/media/media_resource.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('MediaResource accepts opaque public id and ignores internal-path fields', () {
    final resource = MediaResource.fromJson({
      'id': 'media-public-opaque',
      'purpose': 'chat_attachment',
      'mime_type': 'image/png',
      'size': 3,
      'sha256': 'abc',
      'status': 'ready',
      'width': 1,
      'height': 1,
      'privacy_status': 'pending',
      'scan_status': 'pending',
      'created_at': '2026-09-29T03:00:00.000Z',
      'storage_key': 'must-not-be-modeled',
      'disk': 'local',
      'path': '/secret/path',
    });

    expect(resource.id, 'media-public-opaque');
    expect(resource.purpose, 'chat_attachment');
    expect(resource.mimeType, 'image/png');
  });

  test('upload sends purpose, file body and original idempotency key', () async {
    final adapter = RecordingAdapter([
      jsonResponse(201, successEnvelope(mediaJson())),
    ]);
    final client = MediaClient(apiClient: buildClient(adapter));
    final file = SelectedMedia(
      bytes: Uint8List.fromList([1, 2, 3]),
      fileName: 'photo.png',
      mimeType: 'image/png',
    );

    final result = await client.upload(
      purpose: 'chat_attachment',
      file: file,
      idempotencyKey: 'idem-media-1',
    );

    expect(result.id, 'media-1');
    expect(adapter.requests, hasLength(1));
    final request = adapter.requests.single;
    expect(request.method, 'POST');
    expect(request.path, '/media');
    expect(request.headers['Idempotency-Key'], 'idem-media-1');
    expect(request.data, isA<FormData>());
    final form = request.data as FormData;
    expect(form.fields, contains(const MapEntry('purpose', 'chat_attachment')));
    expect(form.files.single.key, 'file');
    expect(form.files.single.value.filename, 'photo.png');
  });

  test('retry creates a fresh multipart body while preserving key', () async {
    final adapter = RecordingAdapter([
      jsonResponse(503, errorEnvelope('provider_busy', retryable: true)),
      jsonResponse(201, successEnvelope(mediaJson())),
    ]);
    final client = MediaClient(apiClient: buildClient(adapter, maxAttempts: 1));
    final file = SelectedMedia(
      bytes: Uint8List.fromList([1, 2, 3]),
      fileName: 'photo.png',
      mimeType: 'image/png',
    );

    await expectLater(
      client.upload(
        purpose: 'chat_attachment',
        file: file,
        idempotencyKey: 'idem-media-retry',
      ),
      throwsA(isA<Object>()),
    );
    final result = await client.upload(
      purpose: 'chat_attachment',
      file: file,
      idempotencyKey: 'idem-media-retry',
    );

    expect(result.id, 'media-1');
    expect(adapter.requests, hasLength(2));
    expect(
      adapter.requests.map((r) => r.headers['Idempotency-Key']).toSet(),
      {'idem-media-retry'},
    );
    expect(identical(adapter.requests[0].data, adapter.requests[1].data), isFalse);
  });

  test('upload forwards progress and cancellation handles', () async {
    final transport = FakeMediaUploadTransport();
    final client = MediaClient(transport: transport);
    final cancellation = MediaUploadCancellation();
    final progress = <double>[];
    final file = SelectedMedia(
      bytes: Uint8List.fromList([1, 2, 3]),
      fileName: 'photo.png',
      mimeType: 'image/png',
    );

    await client.upload(
      purpose: 'profile_avatar',
      file: file,
      idempotencyKey: 'idem-progress',
      cancellation: cancellation,
      onProgress: progress.add,
    );

    expect(transport.seenCancellation, same(cancellation));
    expect(transport.seenPurpose, 'profile_avatar');
    expect(transport.seenFile, same(file));
    expect(transport.seenIdempotencyKey, 'idem-progress');
    expect(progress, [0.5, 1.0]);
  });

  test('platform-neutral picker contract returns SelectedMedia', () async {
    final picker = FakeMediaPicker();
    final picked = await picker.pick();

    expect(picked?.fileName, 'picked.jpg');
    expect(picked?.mimeType, 'image/jpeg');
  });
}

ApiClient buildClient(RecordingAdapter adapter, {int maxAttempts = 1}) {
  final dio = Dio(BaseOptions(baseUrl: 'https://api.example.test/api/v1'));
  dio.httpClientAdapter = adapter;
  return ApiClient(
    dio: dio,
    bearerTokenProvider: () async => 'token',
    requestIdFactory: () => 'req-media',
    retryDelay: (_) async {},
    retryPolicy: RetryPolicy(maxAttempts: maxAttempts),
  );
}

Map<String, Object?> mediaJson() => {
      'id': 'media-1',
      'purpose': 'chat_attachment',
      'mime_type': 'image/png',
      'size': 3,
      'sha256': 'abc',
      'status': 'ready',
      'width': 1,
      'height': 1,
      'privacy_status': 'pending',
      'scan_status': 'pending',
      'created_at': '2026-09-29T03:00:00.000Z',
    };

Map<String, Object?> successEnvelope(Object? data) => {
      'status': 'success',
      'data': data,
      'error': null,
      'meta': {'api_version': 'v1'},
      'request_id': 'req-server',
    };

Map<String, Object?> errorEnvelope(String code, {required bool retryable}) => {
      'status': 'error',
      'data': null,
      'error': {
        'code': code,
        'message': code,
        'retryable': retryable,
      },
      'meta': {'api_version': 'v1'},
      'request_id': 'req-server',
    };

ResponseBody jsonResponse(int status, Map<String, Object?> body) =>
    ResponseBody.fromString(
      jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );

class RecordingAdapter implements HttpClientAdapter {
  RecordingAdapter(Iterable<ResponseBody> responses)
      : _responses = Queue<ResponseBody>.of(responses);

  final Queue<ResponseBody> _responses;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    return _responses.removeFirst();
  }

  @override
  void close({bool force = false}) {}
}

class FakeMediaUploadTransport implements MediaUploadTransport {
  MediaUploadCancellation? seenCancellation;
  String? seenPurpose;
  SelectedMedia? seenFile;
  String? seenIdempotencyKey;

  @override
  Future<MediaResource> upload({
    required String purpose,
    required SelectedMedia file,
    required String idempotencyKey,
    MediaUploadCancellation? cancellation,
    MediaUploadProgress? onProgress,
  }) async {
    seenCancellation = cancellation;
    seenPurpose = purpose;
    seenFile = file;
    seenIdempotencyKey = idempotencyKey;
    onProgress?.call(0.5);
    onProgress?.call(1.0);
    return MediaResource.fromJson(mediaJson());
  }
}

class FakeMediaPicker implements MediaPicker {
  @override
  Future<SelectedMedia?> pick() async => SelectedMedia(
        bytes: Uint8List.fromList([1]),
        fileName: 'picked.jpg',
        mimeType: 'image/jpeg',
      );
}
