class MediaResource {
  const MediaResource({
    required this.id,
    required this.purpose,
    required this.mimeType,
    required this.size,
    required this.sha256,
    required this.status,
    required this.width,
    required this.height,
    required this.privacyStatus,
    required this.scanStatus,
    required this.createdAt,
  });

  factory MediaResource.fromJson(Object? raw) {
    if (raw is! Map) {
      throw const FormatException('media resource must be an object');
    }
    final json = Map<String, Object?>.from(raw);
    return MediaResource(
      id: _requiredString(json, 'id'),
      purpose: _requiredString(json, 'purpose'),
      mimeType: _requiredString(json, 'mime_type'),
      size: _requiredInt(json, 'size'),
      sha256: _requiredString(json, 'sha256'),
      status: _requiredString(json, 'status'),
      width: _nullableInt(json, 'width'),
      height: _nullableInt(json, 'height'),
      privacyStatus: _requiredString(json, 'privacy_status'),
      scanStatus: _requiredString(json, 'scan_status'),
      createdAt: _nullableDateTime(json, 'created_at'),
    );
  }

  final String id;
  final String purpose;
  final String mimeType;
  final int size;
  final String sha256;
  final String status;
  final int? width;
  final int? height;
  final String privacyStatus;
  final String scanStatus;
  final DateTime? createdAt;
}

String _requiredString(Map<String, Object?> json, String key) {
  final value = json[key];
  if (value is! String || value.isEmpty) {
    throw FormatException('media.$key must be a non-empty string');
  }
  return value;
}

int _requiredInt(Map<String, Object?> json, String key) {
  final value = json[key];
  if (value is! int) throw FormatException('media.$key must be an integer');
  return value;
}

int? _nullableInt(Map<String, Object?> json, String key) {
  final value = json[key];
  if (value == null) return null;
  if (value is! int) throw FormatException('media.$key must be an integer');
  return value;
}

DateTime? _nullableDateTime(Map<String, Object?> json, String key) {
  final value = json[key];
  if (value == null) return null;
  if (value is! String) {
    throw FormatException('media.$key must be an ISO-8601 string');
  }
  final parsed = DateTime.tryParse(value);
  if (parsed == null) {
    throw FormatException('media.$key must be an ISO-8601 string');
  }
  return parsed;
}
