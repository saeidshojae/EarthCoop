class SemanticLink {
  const SemanticLink({
    required this.version,
    required this.route,
    required this.params,
    this.fallbackUrl,
  });

  final int version;
  final String route;
  final Map<String, Object?> params;
  final String? fallbackUrl;

  factory SemanticLink.fromJson(Object? raw) {
    if (raw is! Map) {
      throw const FormatException('Semantic link must be an object.');
    }
    final json = Map<String, Object?>.from(raw);
    final version = json['version'];
    final route = json['route'];
    final rawParams = json['params'];
    final fallbackUrl = json['fallback_url'];

    if (version is! int || route is! String || route.trim().isEmpty) {
      throw const FormatException('Semantic link metadata is invalid.');
    }
    if (rawParams is! Map) {
      throw const FormatException('Semantic link params must be an object.');
    }
    if (fallbackUrl != null && fallbackUrl is! String) {
      throw const FormatException('Semantic link fallback URL must be text.');
    }

    return SemanticLink(
      version: version,
      route: route,
      params: Map<String, Object?>.from(rawParams),
      fallbackUrl: fallbackUrl as String?,
    );
  }
}
