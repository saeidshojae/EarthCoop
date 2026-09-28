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
    RequestContext? context,
    required T Function(Object? json) decodeData,
  }) =>
      _request<T>(
        'GET',
        path,
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

  Future<ApiSuccess<T>> _request<T>(
    String method,
    String path, {
    Object? data,
    RequestContext? context,
    required T Function(Object? json) decodeData,
  }) async {
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

    var attempt = 1;
    while (true) {
      try {
        _diagnostics.record(
          'api.request',
          data: {
            'method': method,
            'path': path,
            'request_id': requestId,
            'attempt': attempt,
            ...headers,
          },
        );
        final response = await _dio.request<Object?>(
          path,
          data: data,
          options: Options(
            method: method,
            headers: headers,
            validateStatus: (_) => true,
            responseType: ResponseType.json,
          ),
        );
        final raw = _normalizeBody(response.data);
        final retryAfter = _parseRetryAfter(response.headers.value('retry-after'));

        if (response.statusCode != null &&
            response.statusCode! >= 200 &&
            response.statusCode! < 300) {
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
        if (!_retryPolicy.shouldRetry(
          method: method,
          attempt: attempt,
          statusCode: response.statusCode,
          retryable: failure.retryable,
          hasIdempotencyKey: idempotencyKey != null && idempotencyKey.isNotEmpty,
        )) {
          throw failure;
        }
        await _retryDelay(retryAfter ?? _defaultRetryDelay(attempt));
        attempt += 1;
      } on ApiFailure {
        rethrow;
      } on DioException catch (error) {
        final statusCode = error.response?.statusCode;
        if (!_retryPolicy.shouldRetry(
          method: method,
          attempt: attempt,
          statusCode: statusCode,
          retryable: true,
          hasIdempotencyKey: idempotencyKey != null && idempotencyKey.isNotEmpty,
        )) {
          throw ApiFailure(
            code: 'network_error',
            message: 'The request could not be completed.',
            retryable: true,
            requestId: requestId,
            httpStatus: statusCode,
          );
        }
        await _retryDelay(_defaultRetryDelay(attempt));
        attempt += 1;
      }
    }
  }
}

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

Duration _defaultRetryDelay(int attempt) => Duration(milliseconds: 250 * attempt);
