import 'dart:async';
import 'dart:collection';

import 'package:firebase_messaging/firebase_messaging.dart';

class FcmMessageEvent {
  FcmMessageEvent(this.messageId, Map<String, Object?> data)
      : data = Map.unmodifiable(data);
  final String? messageId;
  final Map<String, Object?> data;
}

abstract interface class FcmMessageRuntime {
  Future<FcmMessageEvent?> initialMessage();
  Stream<FcmMessageEvent> get opened;
  Stream<FcmMessageEvent> get foreground;
}

class FirebaseFcmMessageRuntime implements FcmMessageRuntime {
  const FirebaseFcmMessageRuntime();

  static FcmMessageEvent _event(RemoteMessage message) =>
      FcmMessageEvent(message.messageId, message.data);

  @override
  Future<FcmMessageEvent?> initialMessage() async {
    final message = await FirebaseMessaging.instance
        .getInitialMessage()
        .timeout(const Duration(seconds: 10));
    return message == null ? null : _event(message);
  }

  @override
  Stream<FcmMessageEvent> get opened =>
      FirebaseMessaging.onMessageOpenedApp.map(_event);

  @override
  Stream<FcmMessageEvent> get foreground =>
      FirebaseMessaging.onMessage.map(_event);
}

class FcmMessageBinding {
  FcmMessageBinding({
    required this.runtime,
    required this.onOpen,
    required this.onForeground,
  });

  final FcmMessageRuntime runtime;
  final Future<bool> Function(Map<String, Object?> data) onOpen;
  final Future<void> Function(Map<String, Object?> data) onForeground;
  final _seen = LinkedHashSet<String>();
  final _buffer = <(FcmMessageEvent, bool)>[];
  StreamSubscription<FcmMessageEvent>? _opens;
  StreamSubscription<FcmMessageEvent>? _foregrounds;
  Future<void>? _starting;
  Future<void> _tail = Future.value();
  bool _ready = false;
  bool _disposed = false;
  int _queued = 0;

  Future<void> start() => _starting ??= _start();

  Future<void> _start() async {
    if (_disposed) return;
    try {
      _opens = runtime.opened.listen((event) => _receive(event, true),
          onError: (Object _) {});
      _foregrounds = runtime.foreground.listen((event) => _receive(event, false),
          onError: (Object _) {});
      FcmMessageEvent? initial;
      try {
        initial = await runtime.initialMessage();
      } catch (_) {}
      if (_disposed) return;
      _ready = true;
      if (initial != null) _enqueue(initial, true);
      for (final (event, open) in _buffer) {
        _enqueue(event, open);
      }
      _buffer.clear();
      await _tail;
    } catch (_) {
      await _opens?.cancel();
      await _foregrounds?.cancel();
      _buffer.clear();
      rethrow;
    }
  }

  void _receive(FcmMessageEvent event, bool open) {
    if (_disposed) return;
    if (!_ready) {
      if (_buffer.length == 128) _buffer.removeAt(0);
      _buffer.add((event, open));
    } else {
      _enqueue(event, open);
    }
  }

  void _enqueue(FcmMessageEvent event, bool open) {
    if (_queued == 128) return;
    _queued++;
    _tail = _tail.then((_) async {
      try {
        if (_disposed) return;
        final id = event.messageId;
        final key = id == null || id.isEmpty
            ? null
            : '${open ? 'open' : 'foreground'}:$id';
        if (key != null && _seen.contains(key)) return;
        final bool handled;
        if (open) {
          handled = await onOpen(event.data);
        } else {
          await onForeground(event.data);
          handled = true;
        }
        if (!_disposed && handled && key != null) {
          _seen.add(key);
          if (_seen.length > 128) _seen.remove(_seen.first);
        }
      } catch (_) {
        // A failed message must not poison later delivery events.
      } finally {
        _queued--;
      }
    });
  }

  Future<void> dispose() async {
    _disposed = true;
    _buffer.clear();
    _seen.clear();
    await _opens?.cancel();
    await _foregrounds?.cancel();
  }
}
