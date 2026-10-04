import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_error.dart';
import '../../core/offline/offline_queue_repository.dart';
import '../../core/offline/offline_operation_registry.dart';
import '../../core/offline/offline_replay_engine.dart';
import '../../core/api/app_environment.dart';
import '../../core/auth/secure_session_store.dart';
import '../../core/auth/session_controller.dart';
import '../../core/auth/session_repository.dart';
import '../../core/deep_links/semantic_link.dart';
import '../../core/device/device_context.dart';
import '../../core/push/push_provider_selector.dart';
import '../../core/push/session_push_binding.dart';
import '../../core/push/push_token_source.dart';
import '../../core/push/fcm_push_token_source.dart';
import '../../core/push/hms_push_token_source.dart';
import '../../core/device/device_timezone.dart';
import '../../core/local/app_database.dart';
import '../../features/auth/login_controller.dart';
import '../../features/groups/group_cache.dart';
import '../../features/groups/group_detail_screen.dart';
import '../../features/groups/group_repository.dart';
import '../../features/groups/group_message_repository.dart';
import '../../features/groups/group_message_composer_controller.dart';
import '../../features/groups/groups_controller.dart';
import '../../features/groups/groups_screen.dart';
import '../../features/notifications/notification_repository.dart';
import '../../features/notifications/notification_sync_service.dart';
import '../../features/notifications/notifications_controller.dart';
import '../../features/notifications/notifications_screen.dart';
import '../bootstrap/app_bootstrap_service.dart';
import '../bootstrap/bootstrap_state.dart';
import '../bootstrap/bootstrap_snapshot_store.dart';
import 'mobile_app_runtime.dart';
import 'notification_account_storage.dart';

const _apiBaseUrl = String.fromEnvironment(
  'EARTHCOOP_API_BASE_URL',
  defaultValue: 'https://earthcoop.ir/api/v1',
);

Future<MobileAppRuntime> createProductionRuntime() async {
  final packageInfo = await PackageInfo.fromPlatform();
  final appVersion = packageInfo.version;
  final deviceTimezone = await const DeviceTimezoneResolver().resolve();
  final apiConfiguration = ApiConfiguration(
    environment: AppEnvironment.production,
    baseUrl: Uri.parse(_apiBaseUrl),
  );
  final secureStore = FlutterSecureSessionStore();
  final database = await AppDatabase.openDefault();
  var requestSequence = 0;

  final dio = Dio(
    BaseOptions(
      baseUrl: apiConfiguration.baseUrl.toString(),
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 30),
      sendTimeout: const Duration(seconds: 30),
      headers: const <String, Object>{'Accept': 'application/json'},
    ),
  );
  final apiClient = ApiClient(
    dio: dio,
    bearerTokenProvider: secureStore.readToken,
    deviceIdProvider: secureStore.readDeviceId,
    requestIdFactory: () {
      requestSequence += 1;
      return 'mobile-${DateTime.now().toUtc().microsecondsSinceEpoch}-$requestSequence';
    },
    retryDelay: Future<void>.delayed,
  );

  final sessionRepository = ApiSessionRepository(
    apiClient: apiClient,
    secureStore: secureStore,
  );
  final notificationStorage = NotificationAccountStorage();
  var notificationEpoch = 0;
  late final SessionController sessionController;
  BootstrapState? pushBootstrap;
  var pushBootstrapEpoch = 0;
  final pushCoordinator = createSessionPushCoordinator(
      dio: dio,
      sessionState: () => sessionController.state,
      canRegister: () => pushBootstrap?.allowsProtectedNetwork == true,
      requestIdFactory: () =>
          'push-${DateTime.now().toUtc().microsecondsSinceEpoch}-${++requestSequence}',
      retryDelay: Future<void>.delayed,
      tokenSourceFactory: () async {
        final provider = await const PushProviderSelector(
                capabilities: PlatformPushRuntimeCapabilities())
            .select();
        final PushTokenSource? source = switch (provider) {
          PushProvider.fcm => const FcmPushTokenSource(),
          PushProvider.hms => const HmsPushTokenSource(),
          null => null,
        };
        return source;
      });
  sessionController = SessionController(
    repository: sessionRepository,
    onAuthenticated: pushCoordinator.activate,
    disablePush: pushCoordinator.disable,
    clearUserScopedLocalState: () async {
      notificationEpoch++;
      final session = sessionController.state.session;
      if (session != null) {
        await notificationStorage.clear(
          userId: session.user.id,
          deviceId: session.device.id,
        );
      }
      await DriftGroupProjectionCache(database).writeAll([]);
    },
  );
  try {
    await sessionController.restore();
  } catch (_) {
    // The login route remains usable when restoring an old session fails.
  }

  final bootstrapSnapshotStore = DriftBootstrapSnapshotStore(database);
  final bootstrapService = AppBootstrapService.fromApiClient(
    apiClient: apiClient,
    platform: 'android',
    appVersion: appVersion,
    snapshotStore: bootstrapSnapshotStore,
    clock: DateTime.now,
  );
  final bootstrap = await bootstrapService.start();
  pushBootstrap = bootstrap;
  if (sessionController.state.phase == SessionPhase.authenticated) {
    await bootstrapSnapshotStore.markAuthenticatedSessionObserved();
    if (bootstrap.allowsProtectedNetwork) {
      unawaited(pushCoordinator.activate(sessionController.state.session!));
    }
  }

  final loginController = LoginController(
    sessionController: sessionController,
    deviceContext: () => DeviceContext(
      platform: 'android',
      appVersion: appVersion,
      locale: 'fa',
      timezone: deviceTimezone,
      pushCapable: true,
      deviceId: sessionController.state.session?.device.id,
    ),
  );

  final groupRepository = GroupRepository(
    apiClient: apiClient,
    cache: DriftGroupProjectionCache(database),
  );
  Future<NotificationsController> createNotificationsController() async {
    final session = sessionController.state.session;
    if (session == null) {
      throw const ApiFailure(
        code: 'unauthenticated',
        message: '',
        retryable: false,
        httpStatus: 401,
      );
    }
    final epoch = notificationEpoch;
    bool isCurrent() =>
        notificationEpoch == epoch &&
        sessionController.state.phase == SessionPhase.authenticated &&
        sessionController.state.session?.token == session.token &&
        sessionController.state.session?.user.id == session.user.id &&
        sessionController.state.session?.device.id == session.device.id;
    void checkSession() {
      if (!isCurrent()) {
        throw const ApiFailure(
          code: 'session_changed',
          message: '',
          retryable: false,
          httpStatus: 401,
        );
      }
    }

    final scope = '${session.user.id}:${session.device.id}';
    final scopedDatabase = await notificationStorage.database(
      userId: session.user.id,
      deviceId: session.device.id,
    );
    checkSession();
    // Capture this session's credentials: an old view can never send as a new account.
    final scopedApi = ApiClient(
      dio: dio,
      bearerTokenProvider: () async => session.token,
      deviceIdProvider: () async => session.device.id,
      requestIdFactory: () =>
          'notification-${DateTime.now().toUtc().microsecondsSinceEpoch}-${++requestSequence}',
      retryDelay: Future<void>.delayed,
    );
    final queue = ScopedOfflineQueueRepository(
      DriftOfflineQueueRepository(scopedDatabase),
      scope: scope,
      isCurrent: isCurrent,
    );
    final repository = NotificationRepository(
      apiClient: scopedApi,
      offlineQueue: queue,
      isCurrentSession: isCurrent,
    );
    var replayBootstrap = bootstrap;
    final replay = OfflineReplayEngine(
      queue: queue,
      registry: OfflineOperationRegistry.notificationReadOnly(
        markNotificationRead: repository.replayMarkRead,
      ),
      bootstrapState: () => replayBootstrap,
      sessionState: () => sessionController.state,
    );
    final sync = NotificationSyncService(
      source: repository,
      store: DriftNotificationProjectionStore(
        scopedDatabase,
        isCurrentSession: isCurrent,
      ),
      beforeSync: () async {
        checkSession();
        replayBootstrap = await bootstrapService.start();
        checkSession();
        if (!replayBootstrap.allowsProtectedNetwork) {
          throw ApiFailure(
            code: 'bootstrap_blocked',
            message: '',
            retryable: replayBootstrap.requiresFreshBootstrap,
          );
        }
        await bootstrapSnapshotStore.markAuthenticatedSessionObserved();
        await replay.replayEligible();
        checkSession();
      },
    );
    return NotificationsController(sync);
  }

  return MobileAppRuntime(
    bootstrap: bootstrap,
    onForeground: () async {
      final epoch = ++pushBootstrapEpoch;
      pushBootstrap = null;
      final refreshed = await bootstrapService.start();
      if (epoch != pushBootstrapEpoch) return;
      pushBootstrap = refreshed;
      final session = sessionController.state.session;
      if (session != null && pushBootstrap!.allowsProtectedNetwork) {
        await pushCoordinator.activate(session);
      } else {
        await pushCoordinator.suspend();
      }
    },
    onDispose: () async {
      pushBootstrapEpoch++;
      pushBootstrap = null;
      await pushCoordinator.dispose();
    },
    sessionController: sessionController,
    loginController: loginController,
    groupsBuilder: (context, openGroup) =>
        _GroupsRuntimeView(repository: groupRepository, onOpenGroup: openGroup),
    groupDetailBuilder: (context, groupId) {
      final session = sessionController.state.session!;
      final epoch = notificationEpoch;
      final sender = GroupMessageRepository(
        apiClient: ApiClient(
          dio: dio,
          bearerTokenProvider: () async => session.token,
          deviceIdProvider: () async => session.device.id,
          requestIdFactory: () =>
              'message-${DateTime.now().toUtc().microsecondsSinceEpoch}-${++requestSequence}',
          retryDelay: Future<void>.delayed,
        ),
        isCurrentSession: () =>
            notificationEpoch == epoch &&
            sessionController.state.phase == SessionPhase.authenticated &&
            sessionController.state.session?.token == session.token &&
            sessionController.state.session?.user.id == session.user.id &&
            sessionController.state.session?.device.id == session.device.id,
      );
      return _GroupDetailRuntimeView(
        repository: groupRepository,
        groupId: groupId,
        sender: sender,
      );
    },
    notificationsBuilder: (context, openLink) => _NotificationsRuntimeLoader(
      createController: createNotificationsController,
      onOpenLink: openLink,
    ),
  );
}

class _GroupsRuntimeView extends StatefulWidget {
  const _GroupsRuntimeView({
    required this.repository,
    required this.onOpenGroup,
  });

  final GroupRepository repository;
  final ValueChanged<int> onOpenGroup;

  @override
  State<_GroupsRuntimeView> createState() => _GroupsRuntimeViewState();
}

class _GroupsRuntimeViewState extends State<_GroupsRuntimeView> {
  late final GroupsController _controller = GroupsController(widget.repository);

  @override
  void initState() {
    super.initState();
    _controller.addListener(_refresh);
    unawaited(_controller.load());
  }

  @override
  void dispose() {
    _controller.removeListener(_refresh);
    _controller.dispose();
    super.dispose();
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) => GroupsScreen(
        state: _controller.state,
        onOpenGroup: widget.onOpenGroup,
        onRetry: _controller.load,
      );
}

class _GroupDetailRuntimeView extends StatefulWidget {
  const _GroupDetailRuntimeView({
    required this.repository,
    required this.groupId,
    required this.sender,
  });

  final GroupRepository repository;
  final int groupId;
  final GroupMessageSender sender;

  @override
  State<_GroupDetailRuntimeView> createState() =>
      _GroupDetailRuntimeViewState();
}

class _GroupDetailRuntimeViewState extends State<_GroupDetailRuntimeView> {
  late final GroupDetailController _controller = GroupDetailController(
    widget.repository,
    widget.groupId,
  );

  late final GroupMessageComposerController _composer =
      GroupMessageComposerController(
    groupId: widget.groupId,
    sender: widget.sender,
    onSent: () => unawaited(_controller.load()),
  );

  @override
  void initState() {
    super.initState();
    _controller.addListener(_refresh);
    unawaited(_controller.load());
  }

  @override
  void dispose() {
    _controller.removeListener(_refresh);
    _controller.dispose();
    _composer.dispose();
    super.dispose();
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) => GroupDetailScreen(
        state: _controller.state,
        onRetry: _controller.load,
        composer: _composer,
      );
}

class _NotificationsRuntimeLoader extends StatefulWidget {
  const _NotificationsRuntimeLoader({
    required this.createController,
    required this.onOpenLink,
  });
  final Future<NotificationsController> Function() createController;
  final ValueChanged<SemanticLink> onOpenLink;
  @override
  State<_NotificationsRuntimeLoader> createState() =>
      _NotificationsRuntimeLoaderState();
}

class _NotificationsRuntimeLoaderState
    extends State<_NotificationsRuntimeLoader> {
  late Future<NotificationsController> _controller = widget.createController();
  @override
  Widget build(BuildContext context) => FutureBuilder<NotificationsController>(
        future: _controller,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Scaffold(
              body: Center(
                child: TextButton(
                  onPressed: () => setState(() {
                    _controller = widget.createController();
                  }),
                  child: const Text('دریافت اعلان‌ها ممکن نشد. تلاش دوباره'),
                ),
              ),
            );
          }
          final controller = snapshot.data;
          if (controller == null) {
            return const Scaffold(
                body: Center(child: CircularProgressIndicator()));
          }
          return _NotificationsRuntimeView(
            controller: controller,
            onOpenLink: widget.onOpenLink,
          );
        },
      );
}

class _NotificationsRuntimeView extends StatefulWidget {
  const _NotificationsRuntimeView({
    required this.controller,
    required this.onOpenLink,
  });

  final NotificationsController controller;
  final ValueChanged<SemanticLink> onOpenLink;

  @override
  State<_NotificationsRuntimeView> createState() =>
      _NotificationsRuntimeViewState();
}

class _NotificationsRuntimeViewState extends State<_NotificationsRuntimeView>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    widget.controller.addListener(_refresh);
    unawaited(widget.controller.load());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    widget.controller.removeListener(_refresh);
    widget.controller.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) unawaited(widget.controller.load());
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) => NotificationsScreen(
        state: widget.controller.state,
        onMarkRead: (notification) {
          unawaited(widget.controller.markRead(notification.id));
        },
        onOpenLink: widget.onOpenLink,
        onRetry: widget.controller.load,
      );
}
