import 'package:flutter/material.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({
    super.key,
    this.onOpenGroups,
    this.onOpenNotifications,
  });

  final VoidCallback? onOpenGroups;
  final VoidCallback? onOpenNotifications;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      key: const Key('home-route-screen'),
      appBar: AppBar(title: const Text('ارث‌کوپ')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 720),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'خانه',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: 24),
                Semantics(
                  button: true,
                  label: 'گروه‌های من',
                  excludeSemantics: true,
                  child: FilledButton.icon(
                    key: const Key('home-groups-action'),
                    onPressed: onOpenGroups,
                    icon: const Icon(Icons.groups_outlined),
                    label: const Text('گروه‌های من'),
                  ),
                ),
                const SizedBox(height: 12),
                Semantics(
                  button: true,
                  label: 'اعلان‌ها',
                  excludeSemantics: true,
                  child: FilledButton.tonalIcon(
                    key: const Key('home-notifications-action'),
                    onPressed: onOpenNotifications,
                    icon: const Icon(Icons.notifications_outlined),
                    label: const Text('اعلان‌ها'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
