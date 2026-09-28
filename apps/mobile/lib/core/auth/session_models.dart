class SessionUser {
  const SessionUser({
    required this.id,
    required this.firstName,
    required this.lastName,
    this.email,
    this.status,
    this.emailVerifiedAt,
  });

  final int id;
  final String firstName;
  final String lastName;
  final String? email;
  final String? status;
  final DateTime? emailVerifiedAt;

  factory SessionUser.fromJson(Object? raw) {
    final json = Map<String, Object?>.from(raw! as Map);
    return SessionUser(
      id: json['id']! as int,
      email: json['email'] as String?,
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
      status: json['status'] as String?,
      emailVerifiedAt: _dateOrNull(json['email_verified_at']),
    );
  }
}

class SessionDevice {
  const SessionDevice({
    required this.id,
    required this.platform,
    required this.appVersion,
    required this.locale,
    required this.timezone,
    required this.pushCapable,
    this.lastSeenAt,
  });

  final String id;
  final String platform;
  final String appVersion;
  final String locale;
  final String? timezone;
  final bool pushCapable;
  final DateTime? lastSeenAt;

  factory SessionDevice.fromJson(Object? raw) {
    final json = Map<String, Object?>.from(raw! as Map);
    return SessionDevice(
      id: json['id']! as String,
      platform: json['platform']! as String,
      appVersion: json['app_version']! as String,
      locale: json['locale']! as String,
      timezone: json['timezone'] as String?,
      pushCapable: json['push_capable']! as bool,
      lastSeenAt: _dateOrNull(json['last_seen_at']),
    );
  }
}

class NativeSession {
  const NativeSession({
    required this.token,
    required this.expiresAt,
    required this.user,
    required this.device,
  });

  final String token;
  final DateTime? expiresAt;
  final SessionUser user;
  final SessionDevice device;

  factory NativeSession.fromJson(
    Object? raw, {
    String? fallbackToken,
    bool requireToken = false,
  }) {
    final json = Map<String, Object?>.from(raw! as Map);
    final responseToken = json['token'] as String?;
    final token = responseToken ?? fallbackToken;
    if (token == null ||
        token.isEmpty ||
        (requireToken && responseToken == null)) {
      throw const FormatException('Missing native session token.');
    }
    if (json['token_type'] != 'Bearer') {
      throw const FormatException('Unsupported native session token type.');
    }
    return NativeSession(
      token: token,
      expiresAt: _dateOrNull(json['expires_at']),
      user: SessionUser.fromJson(json['user']),
      device: SessionDevice.fromJson(json['device']),
    );
  }
}

DateTime? _dateOrNull(Object? raw) {
  if (raw == null) return null;
  final parsed = DateTime.tryParse(raw as String);
  if (parsed == null) throw const FormatException('Invalid session timestamp.');
  return parsed.toUtc();
}
