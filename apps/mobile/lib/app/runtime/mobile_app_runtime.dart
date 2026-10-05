import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/session_controller.dart';
import '../../core/deep_links/deep_link_registry.dart';
import '../../core/deep_links/semantic_link.dart';
import '../../features/auth/login_controller.dart';
import '../../features/auth/login_screen.dart';
import '../bootstrap/bootstrap_state.dart';
import '../router/app_router.dart';

class MobileAppRuntime {
  MobileAppRuntime({
    required this.bootstrap,
    required this.sessionController,
    required this.loginController,
    this.initialLink,
    this.registry = const DeepLinkRegistry(),
    this.groupsBuilder,
    this.groupDetailBuilder,
    this.notificationsBuilder,
    this.onForeground,
    this.onDispose,
  });

  final Future<void> Function()? onForeground;
  final Future<void> Function()? onDispose;
  bool _disposed = false;

  void dispose() {
    if (_disposed) return;
    _disposed = true;
    _router?.dispose();
    final cleanup = onDispose;
    if (cleanup != null) unawaited(cleanup().catchError((Object _) {}));
  }

  final BootstrapState bootstrap;
  final SessionController sessionController;
  final LoginController loginController;
  final SemanticLink? initialLink;
  final DeepLinkRegistry registry;
  final GroupsRouteBuilder? groupsBuilder;
  final GroupDetailRouteBuilder? groupDetailBuilder;
  final NotificationsRouteBuilder? notificationsBuilder;

  GoRouter? _router;

  bool openSemanticLink(SemanticLink link,
      {required BootstrapState currentBootstrap}) {
    if (_disposed ||
        sessionController.state.phase != SessionPhase.authenticated ||
        !currentBootstrap.allowsProductShell ||
        !currentBootstrap.allowsProtectedNetwork) {
      return false;
    }
    final resolution = registry.resolve(link);
    if (!resolution.isAllowed) return false;
    router.go(resolution.location);
    return true;
  }

  GoRouter get router => _router ??= AppRouter(
        bootstrap: bootstrap,
        session: sessionController.state,
        initialLink: initialLink,
        registry: registry,
        loginBuilder: _buildLogin,
        groupsBuilder: groupsBuilder,
        groupDetailBuilder: groupDetailBuilder,
        notificationsBuilder: notificationsBuilder,
      ).router;

  Widget _buildLogin(BuildContext context) => LoginScreen(
        onSubmit: ({required email, required password}) async {
          await loginController.submit(email: email, password: password);
          if (sessionController.state.phase == SessionPhase.authenticated &&
              context.mounted) {
            context.go('/home');
          }
        },
      );
}
