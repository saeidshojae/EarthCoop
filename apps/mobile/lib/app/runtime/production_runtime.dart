import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../core/api/api_client.dart';
import '../../core/api/app_environment.dart';
import '../../core/auth/secure_session_store.dart';
import '../../core/auth/session_controller.dart';
import '../../core/auth/session_repository.dart';
import '../../core/deep_links/semantic_link.dart';
import '../../core/device/device_context.dart';
import '../../core/device/device_timezone.dart';
import '../../core/local/app_database.dart';
import '../../features/auth/login_controller.dart';
import '../../features/groups/group_cache.dart';
import '../../features/groups/group_detail_screen.dart';
import '../../features/groups/group_repository.dart';
import '../../features/groups/groups_controller.dart';
import '../../features/groups/groups_screen.dart';
import '../../features/notifications/notification_repository.dart';
import '../../features/notifications/notification_sync_service.dart';
import '../../features/notifications/notifications_controller.dart';
import '../../features/notifications/notifications_screen.dart';
import '../bootstrap/app_bootstrap_service.dart';
import '../bootstrap/bootstrap_snapshot_store.dart';
import 'mobile_app_runtime.dart';

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
  final sessionController = SessionController(repository: sessionRepository);
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
  if (sessionController.state.phase == SessionPhase.authenticated) {
    await bootstrapSnapshotStore.markAuthenticatedSessionObserved();
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
  final notificationSyncService = NotificationSyncService(
    source: NotificationRepository(apiClient: apiClient),
    store: DriftNotificationProjectionStore(database),
  );

  return MobileAppRuntime(
    bootstrap: bootstrap,
    sessionController: sessionController,
    loginController: loginController,
    groupsBuilder: (context, openGroup) => _GroupsRuntimeView(
      repository: groupRepository,
      onOpenGroup: openGroup,
    ),
    groupDetailBuilder: (context, groupId) => _GroupDetailRuntimeView(
      repository: groupRepository,
      groupId: groupId,
    ),
    notificationsBuilder: (context, openLink) => _NotificationsRuntimeView(
      controller: NotificationsController(notificationSyncService),
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
  });

  final GroupRepository repository;
  final int groupId;

  @override
  State<_GroupDetailRuntimeView> createState() =>
      _GroupDetailRuntimeViewState();
}

class _GroupDetailRuntimeViewState extends State<_GroupDetailRuntimeView> {
  late final GroupDetailController _controller =
      GroupDetailController(widget.repository, widget.groupId);

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
  Widget build(BuildContext context) => GroupDetailScreen(
        state: _controller.state,
        onRetry: _controller.load,
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

class _NotificationsRuntimeViewState extends State<_NotificationsRuntimeView> {
  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_refresh);
    unawaited(widget.controller.load());
  }

  @override
  void dispose() {
    widget.controller.removeListener(_refresh);
    widget.controller.dispose();
    super.dispose();
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) => NotificationsScreen(
        state: widget.controller.state,
        onOpenLink: widget.onOpenLink,
        onRetry: widget.controller.load,
      );
}
