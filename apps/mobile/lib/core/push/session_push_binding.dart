import 'package:dio/dio.dart';

import '../api/api_client.dart';
import '../auth/session_controller.dart';
import 'push_registration_service.dart';
import 'push_token_source.dart';
import 'session_push_coordinator.dart';

/// The production binding captures credentials rather than reading the next account's store.
SessionPushCoordinator createSessionPushCoordinator({
  required Dio dio,
  required SessionState Function() sessionState,
  required bool Function() canRegister,
  required Future<PushTokenSource?> Function() tokenSourceFactory,
  required RequestIdFactory requestIdFactory,
  required RetryDelay retryDelay,
}) =>
    SessionPushCoordinator(createRegistration: (session) async {
      bool isCurrent() {
        final state = sessionState();
        return canRegister() &&
            state.phase == SessionPhase.authenticated &&
            state.session?.token == session.token &&
            state.session?.user.id == session.user.id &&
            state.session?.device.id == session.device.id;
      }

      if (!isCurrent()) throw StateError('Push session is unavailable');
      final source = await tokenSourceFactory();
      return PushRegistrationService(
          apiClient: ApiClient(
              dio: dio,
              bearerTokenProvider: () async => session.token,
              deviceIdProvider: () async => session.device.id,
              requestIdFactory: requestIdFactory,
              retryDelay: retryDelay),
          deviceId: session.device.id,
          tokenSource: source,
          isCurrent: isCurrent);
    });
