import 'package:earthcoop_mobile/core/api/api_error.dart';
import 'package:earthcoop_mobile/features/groups/group_message_repository.dart';
import 'package:earthcoop_mobile/features/groups/group_message_composer_controller.dart';
import 'package:flutter_test/flutter_test.dart';

import 'group_repository_test.dart' as fixtures;

void main() {
  test('send uses native path and preserves exact idempotency key', () async {
    final adapter = fixtures.RecordingAdapter([
      fixtures.jsonResponse(
        201,
        fixtures.successEnvelope({'id': 19, 'group_id': 42, 'message': 'سلام'}),
      ),
    ]);
    final repository = GroupMessageRepository(
      apiClient: fixtures.buildClient(adapter),
      isCurrentSession: () => true,
    );
    final result = await repository.send(
      groupId: 42,
      text: 'سلام',
      idempotencyKey: 'same-key-123',
    );
    expect(result.id, 19);
    expect(adapter.requests.single.path, '/groups/42/messages');
    expect(adapter.requests.single.headers['Idempotency-Key'], 'same-key-123');
    expect(adapter.requests.single.data, {'message': 'سلام'});
  });

  test(
      'failed explicit send retains draft and key for retry, then clears on success',
      () async {
    final sender = FakeSender();
    var keys = 0;
    final composer = GroupMessageComposerController(
      groupId: 42,
      sender: sender,
      keyFactory: () => 'key-${++keys}',
    );
    composer.updateDraft('سلام');
    await composer.send();
    expect(composer.draft, 'سلام');
    expect(composer.error, isNotNull);
    await composer.send();
    expect(sender.keys, ['key-1', 'key-1']);
    expect(composer.draft, isEmpty);
    expect(composer.lastSent?.id, 19);
    composer.dispose();
  });

  test('editing a failed draft creates a new intent key', () async {
    final sender = FakeSender();
    var keys = 0;
    final composer = GroupMessageComposerController(
      groupId: 42,
      sender: sender,
      keyFactory: () => 'key-${++keys}',
    );
    composer.updateDraft('اول');
    await composer.send();
    composer.updateDraft('دوم');
    await composer.send();
    expect(sender.keys, ['key-1', 'key-2']);
    composer.dispose();
  });

  test('expired session never starts a mutation', () async {
    final adapter = fixtures.RecordingAdapter([]);
    final repository = GroupMessageRepository(
      apiClient: fixtures.buildClient(adapter),
      isCurrentSession: () => false,
    );
    await expectLater(
      repository.send(groupId: 42, text: 'hello', idempotencyKey: 'key-123456'),
      throwsA(isA<ApiFailure>()),
    );
    expect(adapter.requests, isEmpty);
  });

  test('blank text never reaches the server', () async {
    final sender = FakeSender();
    final composer = GroupMessageComposerController(
      groupId: 42,
      sender: sender,
    );
    composer.updateDraft('   ');
    await composer.send();
    expect(sender.keys, isEmpty);
    composer.dispose();
  });
}

class FakeSender implements GroupMessageSender {
  final keys = <String>[];
  @override
  Future<SentGroupMessage> send({
    required int groupId,
    required String text,
    required String idempotencyKey,
  }) async {
    keys.add(idempotencyKey);
    if (keys.length == 1) {
      throw const ApiFailure(
        code: 'network_error',
        message: '',
        retryable: true,
      );
    }
    return SentGroupMessage(id: 19, groupId: groupId, text: text);
  }
}
