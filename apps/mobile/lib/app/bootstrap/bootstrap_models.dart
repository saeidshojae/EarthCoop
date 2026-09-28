class BootstrapClientPolicy {
  const BootstrapClientPolicy({
    required this.platform,
    required this.minimumVersion,
    required this.latestVersion,
    required this.updateRequired,
    required this.updateRecommended,
  });

  final String platform;
  final String minimumVersion;
  final String latestVersion;
  final bool updateRequired;
  final bool updateRecommended;

  factory BootstrapClientPolicy.fromJson(Object? json) {
    final map = _stringKeyedMap(json, 'client');
    return BootstrapClientPolicy(
      platform: _string(map, 'platform'),
      minimumVersion: _string(map, 'minimum_version'),
      latestVersion: _string(map, 'latest_version'),
      updateRequired: _boolean(map, 'update_required'),
      updateRecommended: _boolean(map, 'update_recommended'),
    );
  }

  Map<String, Object?> toJson() => {
        'platform': platform,
        'minimum_version': minimumVersion,
        'latest_version': latestVersion,
        'update_required': updateRequired,
        'update_recommended': updateRecommended,
      };
}

class BootstrapPayload {
  const BootstrapPayload({
    required this.apiVersion,
    required this.client,
  });

  final String apiVersion;
  final BootstrapClientPolicy client;

  factory BootstrapPayload.fromJson(Object? json) {
    final map = _stringKeyedMap(json, 'bootstrap');
    final api = _stringKeyedMap(map['api'], 'api');
    final version = _string(api, 'version');
    if (version != 'v1') {
      throw FormatException('Unsupported API bootstrap version: $version');
    }

    return BootstrapPayload(
      apiVersion: version,
      client: BootstrapClientPolicy.fromJson(map['client']),
    );
  }

  Map<String, Object?> toJson() => {
        'api': {'version': apiVersion},
        'client': client.toJson(),
      };
}

class BootstrapSnapshot {
  const BootstrapSnapshot({
    required this.payload,
    required this.fetchedAt,
    required this.hadAuthenticatedSession,
  });

  final BootstrapPayload payload;
  final DateTime fetchedAt;
  final bool hadAuthenticatedSession;

  BootstrapSnapshot copyWith({
    BootstrapPayload? payload,
    DateTime? fetchedAt,
    bool? hadAuthenticatedSession,
  }) =>
      BootstrapSnapshot(
        payload: payload ?? this.payload,
        fetchedAt: fetchedAt ?? this.fetchedAt,
        hadAuthenticatedSession:
            hadAuthenticatedSession ?? this.hadAuthenticatedSession,
      );
}

Map<String, Object?> _stringKeyedMap(Object? value, String field) {
  if (value is! Map) {
    throw FormatException('$field must be an object.');
  }
  try {
    return Map<String, Object?>.from(value);
  } on TypeError {
    throw FormatException('$field must use string keys.');
  }
}

String _string(Map<String, Object?> map, String key) {
  final value = map[key];
  if (value is! String || value.isEmpty) {
    throw FormatException('$key must be a non-empty string.');
  }
  return value;
}

bool _boolean(Map<String, Object?> map, String key) {
  final value = map[key];
  if (value is! bool) {
    throw FormatException('$key must be a boolean.');
  }
  return value;
}
