import '../../core/deep_links/semantic_link.dart';

class NotificationDto {
  const NotificationDto({
    required this.id,
    required this.type,
    required this.title,
    required this.message,
    required this.context,
    required this.read,
    required this.createdAt,
    this.readAt,
    this.link,
    this.legacyUrl,
  });

  final String id;
  final String? type;
  final String? title;
  final String? message;
  final Map<String, Object?> context;
  final bool read;
  final DateTime? readAt;
  final DateTime? createdAt;
  final SemanticLink? link;
  final String? legacyUrl;

  NotificationDto asRead({DateTime? at}) {
    if (read) return this;
    return NotificationDto(
      id: id,
      type: type,
      title: title,
      message: message,
      context: context,
      read: true,
      readAt: readAt ?? at,
      createdAt: createdAt,
      link: link,
      legacyUrl: legacyUrl,
    );
  }

  factory NotificationDto.fromJson(Object? raw) {
    final map = _stringMap(raw, 'notification');
    final linkRaw = map['link'];

    return NotificationDto(
      id: _requiredString(map, 'id'),
      type: _nullableString(map['type'], 'type'),
      title: _nullableString(map['title'], 'title'),
      message: _nullableString(map['message'], 'message'),
      context: _nullableMap(map['context'], 'context'),
      read: _requiredBool(map, 'read'),
      readAt: _nullableDateTime(map['read_at'], 'read_at'),
      createdAt: _nullableDateTime(map['created_at'], 'created_at'),
      link: linkRaw == null ? null : SemanticLink.fromJson(linkRaw),
      legacyUrl: _nullableString(map['url'], 'url'),
    );
  }

  Map<String, Object?> toJson() => {
        'id': id,
        'type': type,
        'title': title,
        'message': message,
        'context': context,
        'read': read,
        'read_at': readAt?.toUtc().toIso8601String(),
        'created_at': createdAt?.toUtc().toIso8601String(),
        'url': legacyUrl,
        'link': link == null
            ? null
            : {
                'version': link!.version,
                'route': link!.route,
                'params': link!.params,
                'fallback_url': link!.fallbackUrl,
              },
      };
}

Map<String, Object?> _stringMap(Object? raw, String field) {
  if (raw is! Map) throw FormatException('$field must be an object');
  try {
    return Map<String, Object?>.from(raw);
  } catch (_) {
    throw FormatException('$field must use string keys');
  }
}

String _requiredString(Map<String, Object?> map, String key) {
  final value = map[key];
  if (value is! String || value.isEmpty) {
    throw FormatException('$key must be a non-empty string');
  }
  return value;
}

String? _nullableString(Object? value, String key) {
  if (value == null) return null;
  if (value is! String) throw FormatException('$key must be text or null');
  return value;
}

bool _requiredBool(Map<String, Object?> map, String key) {
  final value = map[key];
  if (value is! bool) throw FormatException('$key must be a boolean');
  return value;
}

Map<String, Object?> _nullableMap(Object? value, String key) {
  if (value == null) return const <String, Object?>{};
  if (value is! Map) throw FormatException('$key must be an object');
  try {
    return Map<String, Object?>.from(value);
  } catch (_) {
    throw FormatException('$key must use string keys');
  }
}

DateTime? _nullableDateTime(Object? value, String key) {
  if (value == null) return null;
  if (value is! String) throw FormatException('$key must be text or null');
  final parsed = DateTime.tryParse(value);
  if (parsed == null) throw FormatException('$key is invalid');
  return parsed.toUtc();
}
