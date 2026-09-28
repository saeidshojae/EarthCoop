import 'package:earthcoop_mobile/core/api/app_environment.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('AppEnvironment API base configuration', () {
    test('selects environment explicitly and preserves a typed API v1 base URL', () {
      final development = ApiConfiguration(
        environment: AppEnvironment.development,
        baseUrl: Uri.parse('http://10.0.2.2:8000/api/v1'),
      );
      final test = ApiConfiguration(
        environment: AppEnvironment.test,
        baseUrl: Uri.parse('https://test.example.test/api/v1'),
      );
      final production = ApiConfiguration(
        environment: AppEnvironment.production,
        baseUrl: Uri.parse('https://earthcoop.ir/api/v1'),
      );

      expect(development.environment, AppEnvironment.development);
      expect(test.environment, AppEnvironment.test);
      expect(production.environment, AppEnvironment.production);
      expect(production.baseUrl.path, '/api/v1');
    });

    test('production rejects localhost, emulator loopback and insecure HTTP', () {
      for (final raw in [
        'http://localhost/api/v1',
        'https://127.0.0.1/api/v1',
        'https://10.0.2.2/api/v1',
        'http://earthcoop.ir/api/v1',
      ]) {
        expect(
          () => ApiConfiguration(
            environment: AppEnvironment.production,
            baseUrl: Uri.parse(raw),
          ),
          throwsArgumentError,
          reason: raw,
        );
      }
    });

    test('all environments require an explicit /api/v1 HTTP(S) endpoint', () {
      for (final raw in [
        'https://earthcoop.ir',
        'ftp://earthcoop.ir/api/v1',
        'https:///api/v1',
      ]) {
        expect(
          () => ApiConfiguration(
            environment: AppEnvironment.test,
            baseUrl: Uri.parse(raw),
          ),
          throwsArgumentError,
          reason: raw,
        );
      }
    });
  });
}
