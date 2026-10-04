import 'dart:io';
import 'package:earthcoop_mobile/app/runtime/notification_account_storage.dart';
import 'package:earthcoop_mobile/core/local/app_database.dart';
import 'package:earthcoop_mobile/core/offline/offline_operation.dart';
import 'package:earthcoop_mobile/core/offline/offline_queue_repository.dart';
import 'package:earthcoop_mobile/features/notifications/notification_dto.dart';
import 'package:earthcoop_mobile/features/notifications/notification_sync_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'notification_sync_service_test.dart' as fixtures;

void main() {
  test('logout after restart clears an account never opened in the new process',
      () async {
    final directory =
        await Directory.systemTemp.createTemp('notification-restart-');
    final file = File('${directory.path}/account.sqlite');
    final beforeRestart = AppDatabase.file(file);
    await DriftOfflineQueueRepository(beforeRestart).enqueue(
        OfflineOperation.notificationRead(
            notificationId: 'n-1',
            idempotencyKey: 'key',
            createdAt: DateTime.utc(2026)));
    await DriftNotificationProjectionStore(beforeRestart).replaceAll(
        [NotificationDto.fromJson(fixtures.notificationJson('n-1'))]);
    await beforeRestart.close();
    final afterRestart = AppDatabase.file(file);
    final storage = NotificationAccountStorage(
        open: (userId, deviceId) async => afterRestart);
    await storage.clear(userId: 7, deviceId: 'device');
    expect(await DriftOfflineQueueRepository(afterRestart).all(), isEmpty);
    expect(await DriftNotificationProjectionStore(afterRestart).readAll(),
        isEmpty);
    await afterRestart.close();
    await directory.delete(recursive: true);
  });
}
