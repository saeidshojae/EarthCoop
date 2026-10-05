import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../../core/api/api_error.dart';
import 'group_attachment.dart';

class GroupAttachmentButton extends StatefulWidget {
  const GroupAttachmentButton(
      {super.key, required this.attachment, required this.download});
  final GroupAttachment attachment;
  final AttachmentDownload download;
  @override
  State<GroupAttachmentButton> createState() => _GroupAttachmentButtonState();
}

class _GroupAttachmentButtonState extends State<GroupAttachmentButton> {
  CancelToken? _cancellation;
  double? _progress;
  String? _status;

  @override
  void dispose() {
    _cancellation?.cancel();
    super.dispose();
  }

  Future<void> _download() async {
    if (_cancellation != null) return;
    final token = CancelToken();
    setState(() {
      _cancellation = token;
      _status = null;
      _progress = null;
    });
    try {
      final saved = await widget.download(widget.attachment, token, (value) {
        if (mounted && !token.isCancelled) setState(() => _progress = value);
      });
      if (mounted) {
        setState(
            () => _status = saved ? 'فایل ذخیره شد.' : 'ذخیره فایل لغو شد.');
      }
    } catch (error) {
      if (mounted) {
        setState(() => _status = token.isCancelled
            ? 'دانلود لغو شد.'
            : error is ApiFailure && error.code == 'attachment_too_large'
                ? 'حجم فایل بیش از ۲۰ مگابایت است.'
                : 'دریافت فایل انجام نشد. دسترسی یا اتصال را بررسی و دوباره تلاش کنید.');
      }
    } finally {
      if (mounted) setState(() => _cancellation = null);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (_cancellation != null) ...[
            LinearProgressIndicator(value: _progress),
            TextButton(
                onPressed: () => _cancellation?.cancel(),
                child: const Text('لغو دانلود')),
          ] else
            OutlinedButton.icon(
                onPressed: _download,
                icon: const Icon(Icons.download),
                label: const Text('دانلود فایل')),
          if (_status != null) Text(_status!),
        ],
      );
}
