import 'package:earthcoop_mobile/core/device/device_timezone.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('returns IANA timezone identifier from the native loader', () async {
    final resolver = DeviceTimezoneResolver(
      loadIdentifier: () async => 'Asia/Tehran',
    );

    expect(await resolver.resolve(), 'Asia/Tehran');
  });

  test('returns null when native timezone lookup fails', () async {
    final resolver = DeviceTimezoneResolver(
      loadIdentifier: () async => throw Exception('timezone unavailable'),
    );

    expect(await resolver.resolve(), isNull);
  });

  test('returns null for an empty native timezone identifier', () async {
    final resolver = DeviceTimezoneResolver(
      loadIdentifier: () async => '   ',
    );

    expect(await resolver.resolve(), isNull);
  });
}
