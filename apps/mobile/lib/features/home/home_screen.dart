import 'package:flutter/material.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

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
                  child: FilledButton.icon(
                    key: const Key('home-groups-action'),
                    onPressed: () {},
                    icon: const Icon(Icons.groups_outlined),
                    label: const Text('گروه‌های من'),
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
