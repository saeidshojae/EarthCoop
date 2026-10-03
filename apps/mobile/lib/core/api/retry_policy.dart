class RetryPolicy {
  const RetryPolicy({this.maxAttempts = 2});

  final int maxAttempts;

  bool shouldRetry({
    required String method,
    required int attempt,
    required int? statusCode,
    required bool retryable,
    required bool hasIdempotencyKey,
  }) {
    if (!retryable || attempt >= maxAttempts) return false;

    final normalizedMethod = method.toUpperCase();
    final isMutation = normalizedMethod != 'GET' && normalizedMethod != 'HEAD';
    if (isMutation && !hasIdempotencyKey) return false;

    if (statusCode == null) return true;
    if (statusCode == 408 || statusCode == 429 || statusCode >= 500) {
      return true;
    }

    return false;
  }
}
