import 'package:earthcoop_mobile/core/logging/diagnostics.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('diagnostics redact secrets while preserving safe correlation fields',
      () {
    final events = <DiagnosticEvent>[];
    final sink = RedactingDiagnosticsSink(onEvent: events.add);

    sink.record(
      'api.request',
      data: {
        'Authorization': 'Bearer secret-token',
        'push_token': 'provider-secret',
        'password': 'plain-password',
        'request_id': 'req-42',
        'error_code': 'validation_failed',
      },
    );

    expect(events, hasLength(1));
    expect(events.single.data['Authorization'], '[REDACTED]');
    expect(events.single.data['push_token'], '[REDACTED]');
    expect(events.single.data['password'], '[REDACTED]');
    expect(events.single.data['request_id'], 'req-42');
    expect(events.single.data['error_code'], 'validation_failed');
    expect(
        events.single.data.values.join(' '), isNot(contains('secret-token')));
    expect(events.single.data.values.join(' '),
        isNot(contains('provider-secret')));
    expect(
        events.single.data.values.join(' '), isNot(contains('plain-password')));
  });
}
