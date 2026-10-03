import 'dart:io';

import 'package:flutter/services.dart';

enum PushProvider { fcm, hms }

extension PushProviderWireName on PushProvider {
  String get wireName => switch (this) {
        PushProvider.fcm => 'fcm',
        PushProvider.hms => 'hms',
      };
}

abstract interface class PushRuntimeCapabilities {
  Future<bool> hasGoogleMobileServices();

  Future<bool> hasHuaweiMobileServices();
}

class PlatformPushRuntimeCapabilities implements PushRuntimeCapabilities {
  const PlatformPushRuntimeCapabilities({
    MethodChannel channel = const MethodChannel('earthcoop/push_runtime'),
  }) : _channel = channel;

  final MethodChannel _channel;

  @override
  Future<bool> hasGoogleMobileServices() async {
    if (Platform.isIOS) return true;
    if (!Platform.isAndroid) return false;
    return await _channel.invokeMethod<bool>('hasGms') ?? false;
  }

  @override
  Future<bool> hasHuaweiMobileServices() async {
    if (!Platform.isAndroid) return false;
    return await _channel.invokeMethod<bool>('hasHms') ?? false;
  }
}

class PushProviderSelector {
  const PushProviderSelector({required PushRuntimeCapabilities capabilities})
      : _capabilities = capabilities;

  final PushRuntimeCapabilities _capabilities;

  Future<PushProvider?> select() async {
    if (await _capabilities.hasGoogleMobileServices()) {
      return PushProvider.fcm;
    }
    if (await _capabilities.hasHuaweiMobileServices()) {
      return PushProvider.hms;
    }
    return null;
  }
}
