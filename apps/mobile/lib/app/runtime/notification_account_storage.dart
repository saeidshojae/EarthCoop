import '../../core/local/app_database.dart';
import '../../core/offline/offline_queue_repository.dart';
import '../../features/notifications/notification_sync_service.dart';

typedef NotificationDatabaseOpener = Future<AppDatabase> Function(
    int userId, String deviceId);

class NotificationAccountStorage {
  NotificationAccountStorage({NotificationDatabaseOpener? open})
      : _open = open ??
            ((userId, deviceId) =>
                AppDatabase.openForAccount(userId: userId, deviceId: deviceId));
  final NotificationDatabaseOpener _open;
  final _databases = <String, Future<AppDatabase>>{};

  Future<AppDatabase> database(
          {required int userId, required String deviceId}) =>
      _databases.putIfAbsent(
          '$userId:$deviceId', () => _open(userId, deviceId));

  Future<void> clear({required int userId, required String deviceId}) async {
    // Always open the outgoing scope, including logout immediately after restart.
    final db = await database(userId: userId, deviceId: deviceId);
    await DriftOfflineQueueRepository(db).clearAll();
    final projection = DriftNotificationProjectionStore(db);
    await projection.replaceAll([]);
    await projection.writeNextCursor(null);
  }
}
