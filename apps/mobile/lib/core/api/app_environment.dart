enum AppEnvironment {
  development,
  test,
  production,
}

class ApiConfiguration {
  ApiConfiguration({
    required this.environment,
    required Uri baseUrl,
  }) : baseUrl = _validateBaseUrl(environment, baseUrl);

  final AppEnvironment environment;
  final Uri baseUrl;

  static Uri _validateBaseUrl(AppEnvironment environment, Uri baseUrl) {
    if (!baseUrl.isAbsolute || baseUrl.host.isEmpty) {
      throw ArgumentError.value(baseUrl, 'baseUrl', 'Must be an absolute URL.');
    }

    if (baseUrl.scheme != 'http' && baseUrl.scheme != 'https') {
      throw ArgumentError.value(
        baseUrl,
        'baseUrl',
        'Only HTTP(S) API endpoints are supported.',
      );
    }

    final normalizedPath = baseUrl.path.endsWith('/')
        ? baseUrl.path.substring(0, baseUrl.path.length - 1)
        : baseUrl.path;
    if (normalizedPath != '/api/v1') {
      throw ArgumentError.value(
        baseUrl,
        'baseUrl',
        'EarthCoop Native must target the explicit /api/v1 endpoint.',
      );
    }

    if (environment == AppEnvironment.production) {
      if (baseUrl.scheme != 'https') {
        throw ArgumentError.value(
          baseUrl,
          'baseUrl',
          'Production API endpoints must use HTTPS.',
        );
      }

      final host = baseUrl.host.toLowerCase();
      const forbiddenProductionHosts = {
        'localhost',
        '127.0.0.1',
        '::1',
        '10.0.2.2',
      };
      if (forbiddenProductionHosts.contains(host)) {
        throw ArgumentError.value(
          baseUrl,
          'baseUrl',
          'Production cannot use a local or emulator loopback endpoint.',
        );
      }
    }

    return baseUrl;
  }
}
