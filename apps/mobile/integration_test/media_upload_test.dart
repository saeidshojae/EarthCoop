import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/core/api/api_client.dart';
import 'package:earthcoop_mobile/core/media/media_client.dart';
import 'package:earthcoop_mobile/core/media/media_picker.dart';
import 'package:flutter_test/flutter_test.dart';

const _baseUrl = String.fromEnvironment('EARTHCOOP_MEDIA_BASE_URL');
const _token = String.fromEnvironment('EARTHCOOP_MEDIA_TOKEN');
const _deviceId = String.fromEnvironment('EARTHCOOP_MEDIA_DEVICE_ID');

void main() {
  final configured =
      _baseUrl.isNotEmpty && _token.isNotEmpty && _deviceId.isNotEmpty;

  test(
    'controlled backend accepts an authenticated idempotent PNG upload',
    () async {
      final dio = Dio(BaseOptions(baseUrl: _baseUrl));
      final api = ApiClient(
        dio: dio,
        bearerTokenProvider: () async => _token,
        deviceIdProvider: () async => _deviceId,
        requestIdFactory: () =>
            'media-uat-${DateTime.now().microsecondsSinceEpoch}',
        retryDelay: (_) async {},
      );
      final client = MediaClient(apiClient: api);
      final media = SelectedMedia(
        bytes: Uint8List.fromList(_onePixelPng),
        fileName: 'earthcoop-m6-media.png',
        mimeType: 'image/png',
      );
      final idempotencyKey =
          'media-uat-${DateTime.now().microsecondsSinceEpoch}';

      final resource = await client.upload(
        purpose: 'chat_attachment',
        file: media,
        idempotencyKey: idempotencyKey,
      );

      expect(resource.id, isNotEmpty);
      expect(resource.mimeType, 'image/png');
      expect(resource.size, greaterThan(0));
      expect(resource.purpose, 'chat_attachment');
    },
    skip: configured
        ? false
        : 'Requires EARTHCOOP_MEDIA_BASE_URL, EARTHCOOP_MEDIA_TOKEN, '
            'and EARTHCOOP_MEDIA_DEVICE_ID dart-defines.',
  );
}

const _onePixelPng = <int>[
  0x89,
  0x50,
  0x4e,
  0x47,
  0x0d,
  0x0a,
  0x1a,
  0x0a,
  0x00,
  0x00,
  0x00,
  0x0d,
  0x49,
  0x48,
  0x44,
  0x52,
  0x00,
  0x00,
  0x00,
  0x01,
  0x00,
  0x00,
  0x00,
  0x01,
  0x08,
  0x06,
  0x00,
  0x00,
  0x00,
  0x1f,
  0x15,
  0xc4,
  0x89,
  0x00,
  0x00,
  0x00,
  0x0d,
  0x49,
  0x44,
  0x41,
  0x54,
  0x08,
  0xd7,
  0x63,
  0xf8,
  0xcf,
  0xc0,
  0xf0,
  0x1f,
  0x00,
  0x05,
  0x00,
  0x01,
  0xff,
  0x89,
  0x99,
  0x3d,
  0x1d,
  0x00,
  0x00,
  0x00,
  0x00,
  0x49,
  0x45,
  0x4e,
  0x44,
  0xae,
  0x42,
  0x60,
  0x82,
];
