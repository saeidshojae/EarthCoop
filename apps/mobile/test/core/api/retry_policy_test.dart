import 'package:earthcoop_mobile/core/api/retry_policy.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('RetryPolicy', () {
    const policy = RetryPolicy(maxAttempts: 2);

    test('allows bounded transient read retries', () {
      expect(
        policy.shouldRetry(
          method: 'GET',
          attempt: 1,
          statusCode: 503,
          retryable: true,
          hasIdempotencyKey: false,
        ),
        isTrue,
      );
      expect(
        policy.shouldRetry(
          method: 'GET',
          attempt: 2,
          statusCode: 503,
          retryable: true,
          hasIdempotencyKey: false,
        ),
        isFalse,
      );
    });

    test('mutation retry requires an existing idempotency key', () {
      expect(
        policy.shouldRetry(
          method: 'POST',
          attempt: 1,
          statusCode: 503,
          retryable: true,
          hasIdempotencyKey: false,
        ),
        isFalse,
      );
      expect(
        policy.shouldRetry(
          method: 'POST',
          attempt: 1,
          statusCode: 503,
          retryable: true,
          hasIdempotencyKey: true,
        ),
        isTrue,
      );
    });

    test('server retryable false is authoritative', () {
      expect(
        policy.shouldRetry(
          method: 'GET',
          attempt: 1,
          statusCode: 503,
          retryable: false,
          hasIdempotencyKey: false,
        ),
        isFalse,
      );
    });

    test('validation auth and conflict responses do not use generic retry', () {
      for (final status in [401, 403, 409, 422]) {
        expect(
          policy.shouldRetry(
            method: 'GET',
            attempt: 1,
            statusCode: status,
            retryable: true,
            hasIdempotencyKey: false,
          ),
          isFalse,
          reason: 'status $status must not enter generic retry',
        );
      }
    });
  });
}
