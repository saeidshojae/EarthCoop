import 'api_error.dart';

class ApiSuccess<T> {
  const ApiSuccess({
    required this.data,
    required this.requestId,
    required this.meta,
  });

  final T data;
  final String requestId;
  final Map<String, Object?> meta;
}

ApiSuccess<T> decodeSuccessEnvelope<T>(
  Object? raw, {
  required T Function(Object? json) decodeData,
  int? httpStatus,
}) {
  final envelope = _asMap(raw);
  if (envelope == null ||
      envelope['status'] != 'success' ||
      envelope['error'] != null ||
      envelope['request_id'] is! String ||
      (envelope['request_id'] as String).isEmpty ||
      envelope['meta'] is! Map) {
    throw malformedResponse(httpStatus: httpStatus);
  }

  final meta = Map<String, Object?>.from(envelope['meta'] as Map);
  if (meta['api_version'] != 'v1') {
    throw malformedResponse(httpStatus: httpStatus);
  }

  try {
    return ApiSuccess<T>(
      data: decodeData(envelope['data']),
      requestId: envelope['request_id'] as String,
      meta: meta,
    );
  } catch (_) {
    throw malformedResponse(httpStatus: httpStatus);
  }
}

ApiFailure decodeErrorEnvelope(
  Object? raw, {
  required int? httpStatus,
  Duration? retryAfter,
}) {
  final envelope = _asMap(raw);
  if (envelope == null ||
      envelope['status'] != 'error' ||
      envelope['data'] != null ||
      envelope['error'] is! Map ||
      envelope['request_id'] is! String ||
      (envelope['request_id'] as String).isEmpty ||
      envelope['meta'] is! Map) {
    return malformedResponse(httpStatus: httpStatus);
  }

  final meta = Map<String, Object?>.from(envelope['meta'] as Map);
  final error = Map<String, Object?>.from(envelope['error'] as Map);
  if (meta['api_version'] != 'v1' ||
      error['code'] is! String ||
      (error['code'] as String).isEmpty ||
      error['message'] is! String ||
      error['retryable'] is! bool) {
    return malformedResponse(httpStatus: httpStatus);
  }

  final rawDetails = error['details'];
  final details = rawDetails is Map
      ? Map<String, Object?>.from(rawDetails)
      : const <String, Object?>{};

  return ApiFailure(
    code: error['code'] as String,
    message: error['message'] as String,
    details: details,
    retryable: error['retryable'] as bool,
    requestId: envelope['request_id'] as String,
    httpStatus: httpStatus,
    retryAfter: retryAfter,
  );
}

ApiFailure malformedResponse({int? httpStatus}) => ApiFailure(
      code: 'malformed_response',
      message: 'The server returned an invalid API v1 response.',
      retryable: false,
      httpStatus: httpStatus,
    );

Map<String, Object?>? _asMap(Object? raw) {
  if (raw is! Map) return null;
  try {
    return Map<String, Object?>.from(raw);
  } catch (_) {
    return null;
  }
}
