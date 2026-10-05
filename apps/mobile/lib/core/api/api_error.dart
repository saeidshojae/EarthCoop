class ApiFailure implements Exception {
  const ApiFailure({
    required this.code,
    required this.message,
    required this.retryable,
    this.details = const <String, Object?>{},
    this.requestId,
    this.httpStatus,
    this.retryAfter,
  });

  final String code;
  final String message;
  final bool retryable;
  final Map<String, Object?> details;
  final String? requestId;
  final int? httpStatus;
  final Duration? retryAfter;

  @override
  String toString() => 'ApiFailure($code, status: $httpStatus)';
}
