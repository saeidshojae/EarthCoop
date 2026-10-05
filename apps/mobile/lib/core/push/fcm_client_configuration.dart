import 'package:firebase_core/firebase_core.dart';

FirebaseOptions? fcmOptionsFromEnvironment() => fcmOptionsFromValues(
      apiKey: const String.fromEnvironment('EARTHCOOP_FCM_API_KEY'),
      appId: const String.fromEnvironment('EARTHCOOP_FCM_APP_ID'),
      senderId: const String.fromEnvironment('EARTHCOOP_FCM_SENDER_ID'),
      projectId: const String.fromEnvironment('EARTHCOOP_FCM_PROJECT_ID'),
    );

FirebaseOptions? fcmOptionsFromValues(
    {required String apiKey,
    required String appId,
    required String senderId,
    required String projectId}) {
  final values = [apiKey, appId, senderId, projectId];
  if (values.every((value) => value.isEmpty)) return null;
  if (values.any((value) =>
      value.isEmpty ||
      value.trim() != value ||
      value.contains(RegExp(r'\s')))) {
    throw const FormatException(
        'Incomplete or invalid Firebase client configuration');
  }
  if (!RegExp(r'^\d+$').hasMatch(senderId) ||
      !appId.startsWith('1:$senderId:android:')) {
    throw const FormatException('Firebase Android app and sender do not match');
  }
  return FirebaseOptions(
      apiKey: apiKey,
      appId: appId,
      messagingSenderId: senderId,
      projectId: projectId);
}
