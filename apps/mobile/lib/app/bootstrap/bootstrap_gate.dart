import 'package:flutter/material.dart';

import 'bootstrap_state.dart';

class BootstrapGate extends StatelessWidget {
  const BootstrapGate({
    super.key,
    required this.state,
    required this.onRetry,
    required this.readyChild,
    required this.degradedChild,
  });

  final BootstrapState state;
  final VoidCallback onRetry;
  final Widget readyChild;
  final Widget degradedChild;

  @override
  Widget build(BuildContext context) {
    switch (state.decision) {
      case BootstrapDecision.compatible:
        return readyChild;
      case BootstrapDecision.recommendedUpdate:
        return Column(
          children: [
            const MaterialBanner(
              key: Key('bootstrap-update-recommended'),
              content: Text('نسخهٔ تازه‌تری از ارث‌کوپ در دسترس است.'),
              actions: [SizedBox.shrink()],
            ),
            Expanded(child: readyChild),
          ],
        );
      case BootstrapDecision.requiredUpdate:
        return const _BlockingMessage(
          key: Key('bootstrap-required-update'),
          icon: Icons.system_update,
          title: 'به‌روزرسانی برنامه لازم است',
          message: 'برای ادامه، نسخهٔ جدید ارث‌کوپ را نصب کنید.',
        );
      case BootstrapDecision.degradedOffline:
        return Column(
          children: [
            const MaterialBanner(
              key: Key('bootstrap-degraded-offline'),
              content: Text(
                'اتصال برقرار نیست؛ فقط اطلاعات ذخیره‌شدهٔ قبلی نمایش داده می‌شود.',
              ),
              actions: [SizedBox.shrink()],
            ),
            Expanded(child: degradedChild),
          ],
        );
      case BootstrapDecision.unavailable:
        return _BlockingMessage(
          key: const Key('bootstrap-unavailable'),
          icon: Icons.cloud_off,
          title: 'اتصال به ارث‌کوپ برقرار نشد',
          message: 'برای بررسی سازگاری برنامه، دوباره تلاش کنید.',
          action: FilledButton(
            key: const Key('bootstrap-retry'),
            onPressed: onRetry,
            child: const Text('تلاش دوباره'),
          ),
        );
    }
  }
}

class _BlockingMessage extends StatelessWidget {
  const _BlockingMessage({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 48),
              const SizedBox(height: 16),
              Text(title, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 8),
              Text(message, textAlign: TextAlign.center),
              if (action != null) ...[
                const SizedBox(height: 20),
                action!,
              ],
            ],
          ),
        ),
      );
}
