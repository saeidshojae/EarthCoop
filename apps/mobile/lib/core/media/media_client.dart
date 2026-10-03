import 'package:dio/dio.dart';

import '../api/api_client.dart';
import '../api/request_context.dart';
import 'media_picker.dart';
import 'media_resource.dart';

typedef MediaUploadProgress = void Function(double fraction);

class MediaUploadCancellation {
  final CancelToken _token = CancelToken();

  bool get isCancelled => _token.isCancelled;

  void cancel([String reason = 'cancelled by user']) {
    if (!_token.isCancelled) _token.cancel(reason);
  }

  CancelToken get token => _token;
}

abstract interface class MediaUploadTransport {
  Future<MediaResource> upload({
    required String purpose,
    required SelectedMedia file,
    required String idempotencyKey,
    MediaUploadCancellation? cancellation,
    MediaUploadProgress? onProgress,
  });
}

class MediaClient implements MediaUploadTransport {
  MediaClient({ApiClient? apiClient, MediaUploadTransport? transport})
      : _transport = transport ??
            (apiClient == null
                ? (throw ArgumentError(
                    'Either apiClient or transport must be provided.',
                  ))
                : ApiMediaUploadTransport(apiClient));

  final MediaUploadTransport _transport;

  @override
  Future<MediaResource> upload({
    required String purpose,
    required SelectedMedia file,
    required String idempotencyKey,
    MediaUploadCancellation? cancellation,
    MediaUploadProgress? onProgress,
  }) {
    if (purpose.trim().isEmpty) {
      throw ArgumentError.value(purpose, 'purpose', 'must not be empty');
    }
    if (purpose.length > 80) {
      throw ArgumentError.value(purpose, 'purpose', 'must be at most 80 chars');
    }
    if (idempotencyKey.trim().isEmpty) {
      throw ArgumentError.value(
        idempotencyKey,
        'idempotencyKey',
        'must not be empty',
      );
    }
    return _transport.upload(
      purpose: purpose,
      file: file,
      idempotencyKey: idempotencyKey,
      cancellation: cancellation,
      onProgress: onProgress,
    );
  }
}

class ApiMediaUploadTransport implements MediaUploadTransport {
  ApiMediaUploadTransport(this._apiClient);

  final ApiClient _apiClient;

  @override
  Future<MediaResource> upload({
    required String purpose,
    required SelectedMedia file,
    required String idempotencyKey,
    MediaUploadCancellation? cancellation,
    MediaUploadProgress? onProgress,
  }) async {
    final formData = FormData.fromMap({
      'purpose': purpose,
      'file': MultipartFile.fromBytes(
        file.bytes,
        filename: file.fileName,
      ),
    });

    final response = await _apiClient.postMultipart<MediaResource>(
      '/media',
      data: formData,
      context: RequestContext(idempotencyKey: idempotencyKey),
      cancelToken: cancellation?.token,
      onSendProgress: onProgress == null
          ? null
          : (sent, total) {
              if (total > 0) onProgress(sent / total);
            },
      decodeData: MediaResource.fromJson,
    );
    return response.data;
  }
}
