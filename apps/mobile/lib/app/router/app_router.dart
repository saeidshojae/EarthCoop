import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/session_controller.dart';
import '../../core/deep_links/deep_link_registry.dart';
import '../../core/deep_links/semantic_link.dart';
import '../../features/home/home_screen.dart';
import '../bootstrap/bootstrap_state.dart';

typedef LoginRouteBuilder = Widget Function(BuildContext context);
typedef GroupsRouteBuilder = Widget Function(
  BuildContext context,
  ValueChanged<int> openGroup,
);
typedef GroupDetailRouteBuilder = Widget Function(
  BuildContext context,
  int groupId,
);
typedef NotificationsRouteBuilder = Widget Function(
  BuildContext context,
  ValueChanged<SemanticLink> openLink,
);

class AppRouter {
  AppRouter({
    required BootstrapState bootstrap,
    required SessionState session,
    SemanticLink? initialLink,
    DeepLinkRegistry registry = const DeepLinkRegistry(),
    LoginRouteBuilder? loginBuilder,
    GroupsRouteBuilder? groupsBuilder,
    GroupDetailRouteBuilder? groupDetailBuilder,
    NotificationsRouteBuilder? notificationsBuilder,
  }) : router = GoRouter(
          initialLocation: _initialLocation(
            bootstrap: bootstrap,
            session: session,
            initialLink: initialLink,
            registry: registry,
          ),
          routes: [
            GoRoute(
              path: '/home',
              builder: (context, state) => HomeScreen(
                onOpenGroups: () => context.push('/groups'),
                onOpenNotifications: () => context.push('/notifications'),
              ),
            ),
            GoRoute(
              path: '/login',
              builder: (context, state) => loginBuilder == null
                  ? const _RouteMessageScreen(
                      key: Key('login-route-screen'),
                      message: 'ورود',
                    )
                  : loginBuilder(context),
            ),
            GoRoute(
              path: '/update-required',
              builder: (context, state) => const _RouteMessageScreen(
                key: Key('required-update-route-screen'),
                message: 'برای ادامه، برنامه را به‌روزرسانی کنید.',
              ),
            ),
            GoRoute(
              path: '/unavailable',
              builder: (context, state) => const _RouteMessageScreen(
                key: Key('unavailable-route-screen'),
                message: 'در حال حاضر امکان ورود به برنامه وجود ندارد.',
              ),
            ),
            GoRoute(
              path: '/groups',
              builder: (context, state) => groupsBuilder == null
                  ? const _RouteMessageScreen(
                      key: Key('groups-unavailable-route-screen'),
                      message: 'گروه‌ها در حال حاضر در دسترس نیستند.',
                    )
                  : groupsBuilder(
                      context,
                      (groupId) => context.push('/groups/$groupId'),
                    ),
            ),
            GoRoute(
              path: '/notifications',
              builder: (context, state) => notificationsBuilder == null
                  ? const _RouteMessageScreen(
                      key: Key('notifications-unavailable-route-screen'),
                      message: 'اعلان‌ها در حال حاضر در دسترس نیستند.',
                    )
                  : notificationsBuilder(
                      context,
                      (link) => _openSemanticLink(
                        context: context,
                        link: link,
                        bootstrap: bootstrap,
                        session: session,
                        registry: registry,
                      ),
                    ),
            ),
            GoRoute(
              path: '/groups/:groupId',
              builder: (context, state) {
                final rawId = state.pathParameters['groupId']!;
                final groupId = int.tryParse(rawId);
                if (groupId == null || groupId <= 0) {
                  return const _RouteMessageScreen(
                    key: Key('invalid-group-route-screen'),
                    message: 'نشانی گروه معتبر نیست.',
                  );
                }
                return groupDetailBuilder == null
                    ? _GroupDetailRouteScreen(groupId: rawId)
                    : groupDetailBuilder(context, groupId);
              },
            ),
          ],
        );

  final GoRouter router;

  static String _initialLocation({
    required BootstrapState bootstrap,
    required SessionState session,
    required SemanticLink? initialLink,
    required DeepLinkRegistry registry,
  }) {
    if (!bootstrap.allowsProductShell) {
      return bootstrap.decision == BootstrapDecision.requiredUpdate
          ? '/update-required'
          : '/unavailable';
    }

    if (session.phase != SessionPhase.authenticated) {
      return '/login';
    }

    if (initialLink == null) return '/home';
    return _resolveSemanticLocation(
      bootstrap: bootstrap,
      session: session,
      link: initialLink,
      registry: registry,
    );
  }

  static String _resolveSemanticLocation({
    required BootstrapState bootstrap,
    required SessionState session,
    required SemanticLink link,
    required DeepLinkRegistry registry,
  }) {
    final resolution = registry.resolve(link);
    if (!resolution.isAllowed) return resolution.fallbackLocation;

    if (resolution.requiresAuthentication &&
        session.phase != SessionPhase.authenticated) {
      return '/login';
    }

    if (resolution.requiresAuthentication &&
        !bootstrap.allowsProtectedNetwork) {
      return resolution.fallbackLocation;
    }

    return resolution.location;
  }

  static void _openSemanticLink({
    required BuildContext context,
    required SemanticLink link,
    required BootstrapState bootstrap,
    required SessionState session,
    required DeepLinkRegistry registry,
  }) {
    context.push(
      _resolveSemanticLocation(
        bootstrap: bootstrap,
        session: session,
        link: link,
        registry: registry,
      ),
    );
  }
}

class _RouteMessageScreen extends StatelessWidget {
  const _RouteMessageScreen({super.key, required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Scaffold(
        body: SafeArea(
          child: Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text(message, textAlign: TextAlign.center),
            ),
          ),
        ),
      );
}

class _GroupDetailRouteScreen extends StatelessWidget {
  const _GroupDetailRouteScreen({required this.groupId});

  final String groupId;

  @override
  Widget build(BuildContext context) => Scaffold(
        key: const Key('group-detail-route-screen'),
        body: SafeArea(
          child: Center(child: Text(groupId)),
        ),
      );
}
