class DeviceContext {
  const DeviceContext({
    required this.platform,
    required this.appVersion,
    required this.locale,
    required this.timezone,
    required this.pushCapable,
    this.deviceId,
  });

  final String platform;
  final String appVersion;
  final String locale;
  final String? timezone;
  final bool pushCapable;
  final String? deviceId;

  Map<String, Object?> toJson() => {
        'platform': platform,
        'app_version': appVersion,
        'locale': locale,
        'timezone': timezone,
        'push_capable': pushCapable,
        if (deviceId != null && deviceId!.isNotEmpty) 'device_id': deviceId,
      };
}
