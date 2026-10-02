class NotificationPresentation {
  const NotificationPresentation._();

  static const _actionLabels = <String, String>{
    'review_auth_service_event': 'بررسی رویداد احراز هویت',
  };

  static const _riskLabels = <String, String>{
    'low': 'کم',
    'medium': 'متوسط',
    'high': 'زیاد',
    'critical': 'بحرانی',
  };

  static String localizeMessage(String message) {
    var localized = message;
    for (final entry in _actionLabels.entries) {
      localized = localized.replaceAll(entry.key, entry.value);
    }
    for (final entry in _riskLabels.entries) {
      localized = localized.replaceAll(
        RegExp('(?<![A-Za-z0-9_])${RegExp.escape(entry.key)}(?![A-Za-z0-9_])'),
        entry.value,
      );
    }
    return localized;
  }
}
