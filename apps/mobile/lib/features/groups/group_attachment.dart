import 'package:dio/dio.dart';
import 'package:flutter/services.dart';

import '../../core/api/api_client.dart';

class GroupAttachment {
  const GroupAttachment(
      {required this.groupId,
      required this.messageId,
      required this.fileName,
      required this.mimeType});
  final int groupId;
  final int messageId;
  final String fileName;
  final String mimeType;
  String get path => '/groups/$groupId/messages/$messageId/attachment';

  static GroupAttachment? fromJson(Object? raw) {
    if (raw is! Map) return null;
    final path = raw['download_path'];
    if (path is! String) return null;
    final match =
        RegExp(r'^/groups/([1-9][0-9]*)/messages/([1-9][0-9]*)/attachment$')
            .firstMatch(path);
    if (match == null ||
        raw['file_name'] is! String ||
        raw['mime_type'] is! String) {
      return null;
    }
    final group = int.tryParse(match[1]!);
    final message = int.tryParse(match[2]!);
    if (group == null || message == null) return null;
    final name = (raw['file_name'] as String)
        .split(RegExp(r'[/\\]'))
        .last
        .replaceAll(RegExp(r'[\x00-\x1f\x7f]'), '')
        .trim();
    return GroupAttachment(
        groupId: group,
        messageId: message,
        fileName: name.isEmpty ? 'attachment' : name,
        mimeType: raw['mime_type'] as String);
  }
}

typedef AttachmentExport = Future<bool> Function(
    GroupAttachment attachment, Uint8List bytes);
typedef AttachmentDownload = Future<bool> Function(GroupAttachment attachment,
    CancelToken cancellation, void Function(double?) progress);

Future<bool> exportAndroidAttachment(
        GroupAttachment attachment, Uint8List bytes) async =>
    await const MethodChannel('earthcoop/attachments')
        .invokeMethod<bool>('save', {
      'name': attachment.fileName,
      'mime': attachment.mimeType,
      'bytes': bytes,
    }) ??
    false;

class GroupAttachmentDownloader {
  GroupAttachmentDownloader(
      {required this.api,
      required this.groupId,
      required this.isCurrentSession,
      this.export = exportAndroidAttachment});
  final ApiClient api;
  final int groupId;
  final bool Function() isCurrentSession;
  final AttachmentExport export;

  Future<bool> download(GroupAttachment attachment, CancelToken cancellation,
      void Function(double?) progress) async {
    if (!isCurrentSession() || attachment.groupId != groupId) {
      throw StateError('session or group changed');
    }
    final bytes = await api.downloadAttachment(attachment.path,
        cancelToken: cancellation, onProgress: progress);
    if (!isCurrentSession() || cancellation.isCancelled) {
      throw StateError('session changed');
    }
    return export(attachment, bytes);
  }
}
