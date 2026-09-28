import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/session_controller.dart';
import '../../core/deep_links/deep_link_registry.dart';
import '../../core/deep_links/semantic_link.dart';
import '../../features/home/home_screen.dart';
import '../bootstrap/bootstrap_state.dart';

class AppRouter {
  AppRouter({
    required BootstrapState bootstrap,
    required SessionState session,
    SemanticLink? initialLink,
    DeepLinkRegistry registry = const DeepLinkRegistry(),
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
              builder: (context, state) => const HomeScreen(),
            ),
            GoRoute(
              path: '/login',
              builder: (context, state) => const _RouteMessageScreen(
                key: Key('login-route-screen'),
                message: 'ورود',
              ),
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
              path: '/groups/:groupId',
              builder: (context, state) => _GroupDetailRouteScreen(
                groupId: state.pathParameters['groupId']!,
              ),
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

    if (initialLink == null) return '/home';
    final resolution = registry.resolve(initialLink);
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
