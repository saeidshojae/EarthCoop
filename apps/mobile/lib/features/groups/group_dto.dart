class GroupIdentity {
  const GroupIdentity({
    required this.governanceAreaId,
    required this.dimensionKey,
    required this.dimensionValueKey,
  });

  final int governanceAreaId;
  final String dimensionKey;
  final String dimensionValueKey;

  factory GroupIdentity.fromJson(Object? raw) {
    final map = _stringMap(raw, 'identity');
    return GroupIdentity(
      governanceAreaId: _requiredInt(map, 'governance_area_id'),
      dimensionKey: _requiredString(map, 'dimension_key'),
      dimensionValueKey: _requiredString(map, 'dimension_value_key'),
    );
  }

  Map<String, Object?> toJson() => {
        'governance_area_id': governanceAreaId,
        'dimension_key': dimensionKey,
        'dimension_value_key': dimensionValueKey,
      };
}

class GroupMembership {
  const GroupMembership({
    required this.role,
    required this.roleLabel,
    required this.status,
  });

  final int role;
  final String roleLabel;
  final int status;

  factory GroupMembership.fromJson(Object? raw) {
    final map = _stringMap(raw, 'membership');
    return GroupMembership(
      role: _requiredInt(map, 'role'),
      roleLabel: _requiredString(map, 'role_label'),
      status: _requiredInt(map, 'status'),
    );
  }

  Map<String, Object?> toJson() => {
        'role': role,
        'role_label': roleLabel,
        'status': status,
      };
}

class GroupDto {
  const GroupDto({
    required this.id,
    required this.name,
    required this.identity,
    required this.membership,
    required this.membersCount,
    this.lastActivityAt,
  });

  final int id;
  final String name;
  final GroupIdentity identity;
  final GroupMembership membership;
  final int membersCount;
  final DateTime? lastActivityAt;

  factory GroupDto.fromJson(Object? raw) {
    final map = _stringMap(raw, 'group');
    final lastActivityRaw = map['last_activity_at'];
    DateTime? lastActivityAt;
    if (lastActivityRaw != null) {
      if (lastActivityRaw is! String) {
        throw const FormatException(
            'last_activity_at must be a string or null');
      }
      final parsed = DateTime.tryParse(lastActivityRaw);
      if (parsed == null) {
        throw const FormatException('last_activity_at is invalid');
      }
      lastActivityAt = parsed.toUtc();
    }

    return GroupDto(
      id: _requiredInt(map, 'id'),
      name: _requiredString(map, 'name'),
      identity: GroupIdentity.fromJson(map['identity']),
      membership: GroupMembership.fromJson(map['membership']),
      membersCount: _requiredInt(map, 'members_count'),
      lastActivityAt: lastActivityAt,
    );
  }

  Map<String, Object?> toJson() => {
        'id': id,
        'name': name,
        'identity': identity.toJson(),
        'membership': membership.toJson(),
        'members_count': membersCount,
        'last_activity_at': lastActivityAt?.toUtc().toIso8601String(),
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

int _requiredInt(Map<String, Object?> map, String key) {
  final value = map[key];
  if (value is! int) throw FormatException('$key must be an integer');
  return value;
}

String _requiredString(Map<String, Object?> map, String key) {
  final value = map[key];
  if (value is! String || value.isEmpty) {
    throw FormatException('$key must be a non-empty string');
  }
  return value;
}
