import 'package:earthcoop_mobile/core/deep_links/deep_link_registry.dart';
import 'package:earthcoop_mobile/core/deep_links/semantic_link.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('DeepLinkRegistry', () {
    const registry = DeepLinkRegistry();

    test('allowlisted group.detail resolves typed integer id', () {
      final link = SemanticLink.fromJson({
        'version': 1,
        'route': 'group.detail',
        'params': {'group_id': 42},
        'fallback_url': 'https://earthcoop.ir/groups/42',
      });

      final result = registry.resolve(link);

      expect(result.isAllowed, isTrue);
      expect(result.location, '/groups/42');
      expect(result.requiresAuthentication, isTrue);
      expect(result.fallbackLocation, '/home');
    });

    test('missing or invalid group id fails closed to safe in-app fallback',
        () {
      for (final params in [
        <String, Object?>{},
        <String, Object?>{'group_id': '42'},
        <String, Object?>{'group_id': 0},
      ]) {
        final result = registry.resolve(
          SemanticLink(
            version: 1,
            route: 'group.detail',
            params: params,
          ),
        );

        expect(result.isAllowed, isFalse);
        expect(result.location, '/home');
      }
    });

    test('unknown or URL-shaped routes are never executable', () {
      for (final route in ['unknown.route', 'https://evil.example/path']) {
        final result = registry.resolve(
          SemanticLink(version: 1, route: route, params: const {}),
        );

        expect(result.isAllowed, isFalse);
        expect(result.location, '/home');
      }
    });

    test('unsupported link version fails closed', () {
      final result = registry.resolve(
        const SemanticLink(
          version: 2,
          route: 'group.detail',
          params: {'group_id': 42},
        ),
      );

      expect(result.isAllowed, isFalse);
      expect(result.location, '/home');
    });

    test('fallback_url remains data only and cannot replace native fallback',
        () {
      final link = SemanticLink.fromJson({
        'version': 1,
        'route': 'unknown.route',
        'params': <String, Object?>{},
        'fallback_url': 'https://evil.example/phish',
      });

      final result = registry.resolve(link);

      expect(link.fallbackUrl, 'https://evil.example/phish');
      expect(result.isAllowed, isFalse);
      expect(result.location, '/home');
      expect(result.location, isNot(contains('evil.example')));
    });
  });
}
