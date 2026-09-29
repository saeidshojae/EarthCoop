import 'dart:convert';

import 'package:dio/dio.dart';

import '../logging/diagnostics.dart';
import 'api_envelope.dart';
import 'api_error.dart';
import 'request_context.dart';
import 'retry_policy.dart';

typedef BearerTokenProvider = Future<String?> Function();
typedef RequestIdFactory = String Function();
typedef RetryDelay = Future<void> Function(Duration delay);

class ApiClient {
  ApiClient({
    required Dio dio,
    required BearerTokenProvider bearerTokenProvider,
    required RequestIdFactory requestIdFactory,
    required RetryDelay retryDelay,
    RetryPolicy retryPolicy = const RetryPolicy(),
    DiagnosticsSink diagnostics = const NoopDiagnosticsSink(),
  })  : _dio = dio,
        _bearerTokenProvider = bearerTokenProvider,
        _requestIdFactory = requestIdFactory,
        _retryDelay = retryDelay,
        _retryPolicy = retryPolicy,
        _diagnostics = diagnostics;

  final Dio _dio;
  final BearerTokenProvider _bearerTokenProvider;
  final RequestIdFactory _requestIdFactory;
  final RetryDelay _retryDelay;
  final RetryPolicy _retryPolicy;
  final DiagnosticsSink _diagnostics;

  Future<ApiSuccess<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    RequestContext? context,
    required T Function(Object? json) decodeData,
  }) =>
      _request<T>(
        'GET',
        path,
        queryParameters: queryParameters,
        context: context,
        decodeData: decodeData,
      );

  Future<ApiSuccess<T>> post<T>(
    String path, {
    Object? data,
    RequestContext? context,
    required T Function(Object? json) decodeData,
  }) =>
      _request<T>(
        'POST',
        path,
        data: data,
        context: context,
        decodeData: decodeData,
      );

  Future<ApiSuccess<T>> postMultipart<T>(
    String path, {
    required FormData data,
    RequestContext? context,
    ProgressCallback? onSendProgress,
    CancelToken? cancelToken,
    required T Function(Object? json) decodeData,
  }) =>
      _requestMultipart<T>(
        path,
        data: data,
        context: context,
        onSendProgress: onSendProgress,
        cancelToken: cancelToken,
        decodeData: decodeData,
      );

  Future<void> delete(
    String path, {
    RequestContext? context,
  }) =>
      _requestEmpty('DELETE', path, context: context);

  Future<ApiSuccess<T>> _request<T>(
    String method,
    String path, {
    Object? data,
    Map<String, dynamic>? queryParameters,
    RequestContext? context,
    required T Function(Object? json) decodeData,
  }) async {
    final request = await _requestMetadata(context);
    var attempt = 1;
    while (true) {
      try {
        _recordRequest(method, path, request, attempt);
        final response = await _dio.request<Object?>(
          path,
          data: data,
          queryParameters: queryParameters,
          options: Options(
            method: method,
            headers: request.headers,
            validateStatus: (_) => true,
            responseType: ResponseType.json,
          ),
        );
        final raw = _normalizeBody(response.data);
        final retryAfter =
            _parseRetryAfter(response.headers.value('retry-after'));

        if (_isSuccess(response.statusCode)) {
          return decodeSuccessEnvelope<T>(
            raw,
            decodeData: decodeData,
            httpStatus: response.statusCode,
          );
        }

        final failure = decodeErrorEnvelope(
          raw,
          httpStatus: response.statusCode,
          retryAfter: retryAfter,
        );
        if (!_shouldRetry(
          method: method,
          attempt: attempt,
          statusCode: response.statusCode,
          retryable: failure.retryable,
          idempotencyKey: request.idempotencyKey,
        )) {
          throw failure;
        }
        await _retryDelay(retryAfter ?? _defaultRetryDelay(attempt));
        attempt += 1;
      } on ApiFailure {
        rethrow;
      } on DioException catch (error) {
        if (!_shouldRetry(
          method: method,
          attempt: attempt,
          statusCode: error.response?.statusCode,
          retryable: true,
          idempotencyKey: request.idempotencyKey,
        )) {
          throw ApiFailure(
            code: 'network_error',
            message: 'The request could not be completed.',
            retryable: true,
            requestId: request.requestId,
            httpStatus: error.response?.statusCode,
          );
        }
        await _retryDelay(_defaultRetryDelay(attempt));
        attempt += 1;
      }
    }
  }

  Future<ApiSuccess<T>> _requestMultipart<T>(
    String path, {
    required FormData data,
    RequestContext? context,
    ProgressCallback? onSendProgress,
    CancelToken? cancelToken,
    required T Function(Object? json) decodeData,
  }) async {
    final request = await _requestMetadata(context);
    try {
      _recordRequest('POST', path, request, 1);
      final response = await _dio.request<Object?>(
        path,
        data: data,
        options: Options(
          method: 'POST',
          headers: request.headers,
          validateStatus: (_) => true,
          responseType: ResponseType.json,
        ),
        onSendProgress: onSendProgress,
        cancelToken: cancelToken,
      );
      final raw = _normalizeBody(response.data);
      if (_isSuccess(response.statusCode)) {
        return decodeSuccessEnvelope<T>(
          raw,
          decodeData: decodeData,
          httpStatus: response.statusCode,
        );
      }
      throw decodeErrorEnvelope(
        raw,
        httpStatus: response.statusCode,
        retryAfter: _parseRetryAfter(response.headers.value('retry-after')),
      );
    } on ApiFailure {
      rethrow;
    } on DioException catch (error) {
      final cancelled = error.type == DioExceptionType.cancel;
      throw ApiFailure(
        code: cancelled ? 'request_cancelled' : 'network_error',
        message: cancelled
            ? 'The request was cancelled.'
            : 'The request could not be completed.',
        retryable: !cancelled,
        requestId: request.requestId,
        httpStatus: error.response?.statusCode,
      );
    }
  }

  Future<void> _requestEmpty(
    String method,
    String path, {
    RequestContext? context,
  }) async {
    final request = await _requestMetadata(context);
    var attempt = 1;
    while (true) {
      try {
        _recordRequest(method, path, request, attempt);
        final response = await _dio.request<Object?>(
          path,
          options: Options(
            method: method,
            headers: request.headers,
            validateStatus: (_) => true,
            responseType: ResponseType.json,
          ),
        );
        final retryAfter =
            _parseRetryAfter(response.headers.value('retry-after'));
        if (_isSuccess(response.statusCode)) return;

        final failure = decodeErrorEnvelope(
          _normalizeBody(response.data),
          httpStatus: response.statusCode,
          retryAfter: retryAfter,
        );
        if (!_shouldRetry(
          method: method,
          attempt: attempt,
          statusCode: response.statusCode,
          retryable: failure.retryable,
          idempotencyKey: request.idempotencyKey,
        )) {
          throw failure;
        }
        await _retryDelay(retryAfter ?? _defaultRetryDelay(attempt));
        attempt += 1;
      } on ApiFailure {
        rethrow;
      } on DioException catch (error) {
        if (!_shouldRetry(
          method: method,
          attempt: attempt,
          statusCode: error.response?.statusCode,
          retryable: true,
          idempotencyKey: request.idempotencyKey,
        )) {
          throw ApiFailure(
            code: 'network_error',
            message: 'The request could not be completed.',
            retryable: true,
            requestId: request.requestId,
            httpStatus: error.response?.statusCode,
          );
        }
        await _retryDelay(_defaultRetryDelay(attempt));
        attempt += 1;
      }
    }
  }

  Future<_RequestMetadata> _requestMetadata(RequestContext? context) async {
    final requestId = context?.requestId ?? _requestIdFactory();
    final idempotencyKey = context?.idempotencyKey;
    final bearer = await _bearerTokenProvider();
    final headers = <String, Object?>{'X-Request-ID': requestId};
    if (bearer != null && bearer.isNotEmpty) {
      headers['Authorization'] = 'Bearer $bearer';
    }
    if (idempotencyKey != null && idempotencyKey.isNotEmpty) {
      headers['Idempotency-Key'] = idempotencyKey;
    }
    final deviceId = context?.deviceId;
    if (deviceId != null && deviceId.isNotEmpty) {
      headers['X-Device-ID'] = deviceId;
    }
    return _RequestMetadata(
      requestId: requestId,
      idempotencyKey: idempotencyKey,
      headers: headers,
    );
  }

  void _recordRequest(
    String method,
    String path,
    _RequestMetadata request,
    int attempt,
  ) {
    _diagnostics.record(
      'api.request',
      data: {
        'method': method,
        'path': path,
        'request_id': request.requestId,
        'attempt': attempt,
        ...request.headers,
      },
    );
  }

  bool _shouldRetry({
    required String method,
    required int attempt,
    required int? statusCode,
    required bool retryable,
    required String? idempotencyKey,
  }) =>
      _retryPolicy.shouldRetry(
        method: method,
        attempt: attempt,
        statusCode: statusCode,
        retryable: retryable,
        hasIdempotencyKey: idempotencyKey != null && idempotencyKey.isNotEmpty,
      );
}

class _RequestMetadata {
  const _RequestMetadata({
    required this.requestId,
    required this.idempotencyKey,
    required this.headers,
  });

  final String requestId;
  final String? idempotencyKey;
  final Map<String, Object?> headers;
}

bool _isSuccess(int? statusCode) =>
    statusCode != null && statusCode >= 200 && statusCode < 300;

Object? _normalizeBody(Object? raw) {
  if (raw is String) {
    try {
      return jsonDecode(raw);
    } catch (_) {
      return raw;
    }
  }
  return raw;
}

Duration? _parseRetryAfter(String? raw) {
  if (raw == null) return null;
  final seconds = int.tryParse(raw.trim());
  if (seconds == null || seconds < 0) return null;
  return Duration(seconds: seconds);
}

Duration _defaultRetryDelay(int attempt) =>
    Duration(milliseconds: 250 * attempt);
