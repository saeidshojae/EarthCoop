import 'package:flutter_timezone/flutter_timezone.dart';

typedef TimezoneIdentifierLoader = Future<String> Function();

class DeviceTimezoneResolver {
  const DeviceTimezoneResolver({TimezoneIdentifierLoader? loadIdentifier})
      : _loadIdentifier = loadIdentifier;

  final TimezoneIdentifierLoader? _loadIdentifier;

  Future<String?> resolve() async {
    try {
      final identifier = await (_loadIdentifier ?? _loadPlatformIdentifier)();
      final normalized = identifier.trim();
      return normalized.isEmpty ? null : normalized;
    } catch (_) {
      return null;
    }
  }

  static Future<String> _loadPlatformIdentifier() async {
    final timezone = await FlutterTimezone.getLocalTimezone();
    return timezone.identifier;
  }
}
