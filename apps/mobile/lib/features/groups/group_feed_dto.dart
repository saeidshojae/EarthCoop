enum GroupFeedKind { message, file, voice, post, poll, comment, unknown }

class GroupFeedEvent {
  const GroupFeedEvent({
    required this.sequence,
    required this.kind,
    this.sender,
    this.message,
    this.title,
    this.content,
    this.question,
    this.options = const <String>[],
  });

  const GroupFeedEvent.message({
    required int sequence,
    String? sender,
    String? message,
  }) : this(
          sequence: sequence,
          kind: GroupFeedKind.message,
          sender: sender,
          message: message,
        );

  const GroupFeedEvent.post({
    required int sequence,
    String? title,
    String? content,
  }) : this(
          sequence: sequence,
          kind: GroupFeedKind.post,
          title: title,
          content: content,
        );

  const GroupFeedEvent.poll({
    required int sequence,
    String? question,
    List<String> options = const <String>[],
  }) : this(
          sequence: sequence,
          kind: GroupFeedKind.poll,
          question: question,
          options: options,
        );

  final int sequence;
  final GroupFeedKind kind;
  final String? sender;
  final String? message;
  final String? title;
  final String? content;
  final String? question;
  final List<String> options;

  factory GroupFeedEvent.fromJson(Object? raw) {
    final map = _stringMap(raw, 'feed event');
    final payload = _stringMap(map['payload'], 'feed payload');
    final sequence = _requiredInt(map, 'sequence');
    final contentType = _nullableString(payload['content_type']) ?? '';

    switch (contentType) {
      case 'message':
        return GroupFeedEvent(
          sequence: sequence,
          kind: GroupFeedKind.message,
          sender: _nullableString(payload['sender']),
          message: _nullableString(payload['message']),
        );
      case 'file':
        return GroupFeedEvent(
          sequence: sequence,
          kind: GroupFeedKind.file,
          sender: _nullableString(payload['sender']),
          message: _nullableString(payload['message']),
        );
      case 'voice':
        return GroupFeedEvent(
          sequence: sequence,
          kind: GroupFeedKind.voice,
          sender: _nullableString(payload['sender']),
          message: _nullableString(payload['message']),
        );
      case 'post':
        return GroupFeedEvent.post(
          sequence: sequence,
          title: _nullableString(payload['title']),
          content: _nullableString(payload['content']),
        );
      case 'poll':
        return GroupFeedEvent.poll(
          sequence: sequence,
          question: _nullableString(payload['question']),
          options: _pollOptions(payload['options']),
        );
      case 'comment':
        return GroupFeedEvent(
          sequence: sequence,
          kind: GroupFeedKind.comment,
          message: _nullableString(payload['message']),
        );
      default:
        return GroupFeedEvent(
          sequence: sequence,
          kind: GroupFeedKind.unknown,
          message: _nullableString(payload['message']),
        );
    }
  }
}

class GroupFeedPage {
  const GroupFeedPage({
    required this.events,
    required this.latestSequence,
    required this.hasMore,
  });

  final List<GroupFeedEvent> events;
  final int latestSequence;
  final bool hasMore;

  factory GroupFeedPage.fromJson(Object? raw) {
    final map = _stringMap(raw, 'group feed');
    final eventsRaw = map['events'];
    if (eventsRaw is! List) {
      throw const FormatException('events must be a list');
    }
    final hasMore = map['has_more'];
    if (hasMore is! bool) {
      throw const FormatException('has_more must be a boolean');
    }
    return GroupFeedPage(
      events: eventsRaw.map(GroupFeedEvent.fromJson).toList(growable: false),
      latestSequence: _requiredInt(map, 'latest_sequence'),
      hasMore: hasMore,
    );
  }
}

class GroupUnreadProjection {
  const GroupUnreadProjection({required this.total});

  final int total;

  factory GroupUnreadProjection.fromJson(Object? raw) {
    final map = _stringMap(raw, 'group unread');
    return GroupUnreadProjection(total: _requiredInt(map, 'total'));
  }
}

List<String> _pollOptions(Object? raw) {
  if (raw == null) return const <String>[];
  if (raw is! List) throw const FormatException('options must be a list');

  final result = <String>[];
  for (final item in raw) {
    if (item is String) {
      if (item.isNotEmpty) result.add(item);
      continue;
    }
    if (item is Map) {
      final map = Map<String, Object?>.from(item);
      final text = _nullableString(map['text']);
      if (text != null && text.isNotEmpty) result.add(text);
      continue;
    }
    throw const FormatException('poll option must be text or an object');
  }
  return List<String>.unmodifiable(result);
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

String? _nullableString(Object? value) {
  if (value == null) return null;
  if (value is! String) {
    throw const FormatException('value must be text or null');
  }
  return value;
}
