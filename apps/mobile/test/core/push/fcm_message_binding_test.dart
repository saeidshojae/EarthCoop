import 'dart:async';

import 'package:earthcoop_mobile/core/push/fcm_message_binding.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('cold message and buffered warm duplicate open once', () async {
    final runtime = FakeMessages();
    final opened = <String>[];
    final binding = FcmMessageBinding(
      runtime: runtime,
      onOpen: (data) async {
        opened.add(data['value']! as String);
        return true;
      },
      onForeground: (_) async {},
    );
    final starting = binding.start();
    final same = FcmMessageEvent('same-id', {'value': 'cold'});
    runtime.opens.add(same);
    runtime.initial.complete(same);
    await starting;
    expect(opened, ['cold']);
    await binding.start();
    expect(runtime.initialReads, 1);
    await binding.dispose();
    await runtime.dispose();
  });

  test('foreground recovery does not consume a later tap of that message',
      () async {
    final runtime = FakeMessages()..initial.complete(null);
    final foreground = Completer<void>();
    final opened = Completer<void>();
    final binding = FcmMessageBinding(
      runtime: runtime,
      onOpen: (_) async {
        opened.complete();
        return true;
      },
      onForeground: (_) async => foreground.complete(),
    );
    await binding.start();
    final event = FcmMessageEvent('same-id', {'value': 'same'});
    runtime.foregrounds.add(event);
    await foreground.future;
    runtime.opens.add(event);
    await opened.future;
    await binding.dispose();
    await runtime.dispose();
  });

  test('queued tap captured before session replacement is dropped', () async {
    Object? scope = 'epoch-1';
    final runtime = FakeMessages()..initial.complete(null);
    final entered = Completer<void>();
    final release = Completer<void>();
    final opened = <String>[];
    final binding = FcmMessageBinding(
      runtime: runtime,
      activeScope: () => scope,
      onOpen: (data) async {
        opened.add(data['value'] as String);
        entered.complete();
        await release.future;
        return true;
      },
      onForeground: (_) async {},
    );
    await binding.start();
    runtime.opens.add(FcmMessageEvent('first', {'value': 'first'}));
    await entered.future;
    runtime.opens.add(FcmMessageEvent('second', {'value': 'second'}));
    await Future<void>.delayed(Duration.zero);
    scope = 'epoch-2';
    release.complete();
    await Future<void>.delayed(Duration.zero);
    expect(opened, ['first']);
    await binding.dispose();
    await runtime.dispose();
  });

  test('initial and buffered taps cannot cross a startup session change',
      () async {
    Object? scope = 'epoch-1';
    final runtime = FakeMessages();
    var opens = 0;
    final binding = FcmMessageBinding(
      runtime: runtime,
      activeScope: () => scope,
      onOpen: (_) async {
        opens++;
        return true;
      },
      onForeground: (_) async {},
    );
    final starting = binding.start();
    runtime.opens.add(FcmMessageEvent('buffered', {}));
    await Future<void>.delayed(Duration.zero);
    scope = 'epoch-2';
    runtime.initial.complete(FcmMessageEvent('initial', {}));
    await starting;
    expect(opens, 0);
    await binding.dispose();
    await runtime.dispose();
  });

  test('disposal during initial message read suppresses late navigation',
      () async {
    final runtime = FakeMessages();
    var opened = 0;
    final binding = FcmMessageBinding(
      runtime: runtime,
      onOpen: (_) async {
        opened++;
        return true;
      },
      onForeground: (_) async {},
    );
    final starting = binding.start();
    await binding.dispose();
    runtime.initial.complete(FcmMessageEvent('late', {}));
    await starting;
    expect(opened, 0);
    await runtime.dispose();
  });
}

class FakeMessages implements FcmMessageRuntime {
  final initial = Completer<FcmMessageEvent?>();
  final opens = StreamController<FcmMessageEvent>.broadcast();
  final foregrounds = StreamController<FcmMessageEvent>.broadcast();
  var initialReads = 0;

  @override
  Future<FcmMessageEvent?> initialMessage() {
    initialReads++;
    return initial.future;
  }

  @override
  Stream<FcmMessageEvent> get opened => opens.stream;

  @override
  Stream<FcmMessageEvent> get foreground => foregrounds.stream;

  Future<void> dispose() async {
    await opens.close();
    await foregrounds.close();
  }
}
