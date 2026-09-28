import 'semantic_link.dart';

class DeepLinkResolution {
  const DeepLinkResolution({
    required this.isAllowed,
    required this.location,
    required this.requiresAuthentication,
    this.fallbackLocation = '/home',
  });

  final bool isAllowed;
  final String location;
  final bool requiresAuthentication;
  final String fallbackLocation;
}

class DeepLinkRegistry {
  const DeepLinkRegistry();

  DeepLinkResolution resolve(SemanticLink link) {
    if (link.version != 1) return _rejected;
    if (_looksExecutable(link.route)) return _rejected;

    return switch (link.route) {
      'group.detail' => _groupDetail(link),
      _ => _rejected,
    };
  }

  DeepLinkResolution _groupDetail(SemanticLink link) {
    final id = link.params['group_id'];
    if (id is! int || id <= 0) return _rejected;

    return DeepLinkResolution(
      isAllowed: true,
      location: '/groups/$id',
      requiresAuthentication: true,
    );
  }

  bool _looksExecutable(String route) {
    final normalized = route.trim().toLowerCase();
    return normalized.contains('://') ||
        normalized.startsWith('/') ||
        normalized.startsWith('javascript:');
  }

  static const _rejected = DeepLinkResolution(
    isAllowed: false,
    location: '/home',
    requiresAuthentication: false,
  );
}
