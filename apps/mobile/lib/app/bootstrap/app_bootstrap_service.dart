import '../../core/api/api_client.dart';
import '../../core/api/api_error.dart';
import 'bootstrap_models.dart';
import 'bootstrap_snapshot_store.dart';
import 'bootstrap_state.dart';

typedef BootstrapFetcher = Future<BootstrapPayload> Function();
typedef BootstrapClock = DateTime Function();

class AppBootstrapService {
  AppBootstrapService({
    required BootstrapFetcher fetchRemote,
    required BootstrapSnapshotStore snapshotStore,
    required BootstrapClock clock,
  })  : _fetchRemote = fetchRemote,
        _snapshotStore = snapshotStore,
        _clock = clock;

  factory AppBootstrapService.fromApiClient({
    required ApiClient apiClient,
    required String platform,
    required String appVersion,
    required BootstrapSnapshotStore snapshotStore,
    required BootstrapClock clock,
  }) {
    final query = Uri(
      path: '/bootstrap',
      queryParameters: {
        'platform': platform,
        'version': appVersion,
      },
    ).toString();

    return AppBootstrapService(
      fetchRemote: () async {
        final result = await apiClient.get<BootstrapPayload>(
          query,
          decodeData: BootstrapPayload.fromJson,
        );
        return result.data;
      },
      snapshotStore: snapshotStore,
      clock: clock,
    );
  }

  final BootstrapFetcher _fetchRemote;
  final BootstrapSnapshotStore _snapshotStore;
  final BootstrapClock _clock;

  Future<BootstrapState> start() async {
    try {
      final payload = await _fetchRemote();
      if (payload.apiVersion != 'v1') {
        return const BootstrapState.unavailable();
      }

      final previous = await _safeReadSnapshot();
      await _snapshotStore.write(
        BootstrapSnapshot(
          payload: payload,
          fetchedAt: _clock().toUtc(),
          hadAuthenticatedSession:
              previous?.hadAuthenticatedSession ?? false,
        ),
      );

      if (payload.client.updateRequired) {
        return const BootstrapState.requiredUpdate();
      }
      if (payload.client.updateRecommended) {
        return const BootstrapState.recommendedUpdate();
      }
      return const BootstrapState.compatible();
    } catch (error) {
      if (!_isTransient(error)) {
        return const BootstrapState.unavailable();
      }

      final snapshot = await _safeReadSnapshot();
      if (_canUseDegradedSnapshot(snapshot)) {
        return const BootstrapState.degradedOffline();
      }
      return const BootstrapState.unavailable();
    }
  }

  Future<BootstrapSnapshot?> _safeReadSnapshot() async {
    try {
      return await _snapshotStore.read();
    } catch (_) {
      return null;
    }
  }

  bool _canUseDegradedSnapshot(BootstrapSnapshot? snapshot) {
    if (snapshot == null) return false;
    return snapshot.payload.apiVersion == 'v1' &&
        !snapshot.payload.client.updateRequired &&
        snapshot.hadAuthenticatedSession;
  }

  bool _isTransient(Object error) =>
      error is ApiFailure && error.retryable;
}
