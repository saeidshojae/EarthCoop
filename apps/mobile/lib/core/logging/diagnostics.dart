class DiagnosticEvent {
  const DiagnosticEvent(this.name, this.data);

  final String name;
  final Map<String, Object?> data;
}

abstract interface class DiagnosticsSink {
  void record(String name, {Map<String, Object?> data = const {}});
}

class RedactingDiagnosticsSink implements DiagnosticsSink {
  RedactingDiagnosticsSink({required this.onEvent});

  final void Function(DiagnosticEvent event) onEvent;

  @override
  void record(String name, {Map<String, Object?> data = const {}}) {
    onEvent(DiagnosticEvent(name, _redactMap(data)));
  }
}

class NoopDiagnosticsSink implements DiagnosticsSink {
  const NoopDiagnosticsSink();

  @override
  void record(String name, {Map<String, Object?> data = const {}}) {}
}

Map<String, Object?> _redactMap(Map<String, Object?> source) => {
      for (final entry in source.entries)
        entry.key:
            _isSecretKey(entry.key) ? '[REDACTED]' : _redactValue(entry.value),
    };

Object? _redactValue(Object? value) {
  if (value is Map) {
    return _redactMap(Map<String, Object?>.from(value));
  }
  if (value is List) {
    return value.map(_redactValue).toList(growable: false);
  }
  return value;
}

bool _isSecretKey(String key) {
  final normalized = key.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), '');
  return normalized == 'authorization' ||
      normalized == 'password' ||
      normalized == 'pushtoken' ||
      normalized == 'accesstoken' ||
      normalized == 'refreshtoken' ||
      normalized == 'bearertoken' ||
      normalized == 'clientsecret';
}
