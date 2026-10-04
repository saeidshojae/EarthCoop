import 'dart:math';

import 'package:flutter/foundation.dart';

import '../../core/api/api_error.dart';
import 'group_message_repository.dart';

class GroupMessageComposerController extends ChangeNotifier {
  GroupMessageComposerController({
    required this.groupId,
    required GroupMessageSender sender,
    String Function()? keyFactory,
  }) : _sender = sender,
       _keyFactory = keyFactory ?? _newKey;
  final int groupId;
  final GroupMessageSender _sender;
  final String Function() _keyFactory;
  String draft = '';
  String? error;
  bool isSending = false;
  SentGroupMessage? lastSent;
  String? _pendingText;
  String? _pendingKey;
  bool _disposed = false;

  void updateDraft(String value) {
    if (_disposed || isSending) return;
    draft = value;
    error = null;
    notifyListeners();
  }

  Future<void> send() async {
    if (_disposed || isSending) return;
    final text = draft.trim();
    if (text.isEmpty) return;
    if (text.runes.length > 2000) {
      error = 'پیام باید حداکثر ۲۰۰۰ نویسه باشد.';
      notifyListeners();
      return;
    }
    if (_pendingText != text) {
      _pendingText = text;
      _pendingKey = _keyFactory();
    }
    isSending = true;
    error = null;
    notifyListeners();
    try {
      final result = await _sender.send(
        groupId: groupId,
        text: text,
        idempotencyKey: _pendingKey!,
      );
      if (_disposed) return;
      lastSent = result;
      draft = '';
      _pendingKey = null;
      _pendingText = null;
    } on ApiFailure catch (failure) {
      if (_disposed) return;
      if (failure.httpStatus == 401) {
        error = 'برای ارسال پیام دوباره وارد حساب شوید.';
      } else if (failure.httpStatus == 403 &&
          failure.message.trim().isNotEmpty) {
        error = failure.message;
      } else if (failure.httpStatus == 404) {
        error = 'ارسال پیام در این نسخهٔ سرور هنوز در دسترس نیست.';
      } else if (failure.httpStatus == 429) {
        error = 'کمی صبر کنید و دوباره پیام را ارسال کنید.';
      } else {
        error = 'ارسال تأیید نشد. متن حفظ شده؛ دوباره تلاش کنید.';
      }
    } catch (_) {
      if (!_disposed) error = 'ارسال تأیید نشد. متن حفظ شده؛ دوباره تلاش کنید.';
    } finally {
      isSending = false;
      if (!_disposed) notifyListeners();
    }
  }

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }
}

String _newKey() {
  final random = Random.secure();
  return 'group-message-${List.generate(16, (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0')).join()}';
}
