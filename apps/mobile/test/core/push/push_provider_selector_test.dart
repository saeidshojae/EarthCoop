import 'package:earthcoop_mobile/core/push/push_provider_selector.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('PushProviderSelector', () {
    test('selects FCM when Google Mobile Services are available', () async {
      const selector = PushProviderSelector(
        capabilities: FakePushRuntimeCapabilities(
          gmsAvailable: true,
          hmsAvailable: true,
        ),
      );

      expect(await selector.select(), PushProvider.fcm);
    });

    test('selects HMS on non-GMS Huawei runtime', () async {
      const selector = PushProviderSelector(
        capabilities: FakePushRuntimeCapabilities(
          gmsAvailable: false,
          hmsAvailable: true,
        ),
      );

      expect(await selector.select(), PushProvider.hms);
    });

    test('returns unavailable when neither supported runtime exists', () async {
      const selector = PushProviderSelector(
        capabilities: FakePushRuntimeCapabilities(
          gmsAvailable: false,
          hmsAvailable: false,
        ),
      );

      expect(await selector.select(), isNull);
    });
  });
}

class FakePushRuntimeCapabilities implements PushRuntimeCapabilities {
  const FakePushRuntimeCapabilities({
    required this.gmsAvailable,
    required this.hmsAvailable,
  });

  final bool gmsAvailable;
  final bool hmsAvailable;

  @override
  Future<bool> hasGoogleMobileServices() async => gmsAvailable;

  @override
  Future<bool> hasHuaweiMobileServices() async => hmsAvailable;
}
