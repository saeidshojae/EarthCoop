import 'package:flutter/material.dart';

import 'groups_controller.dart';

class GroupDetailScreen extends StatelessWidget {
  const GroupDetailScreen({
    super.key,
    required this.state,
    this.onRetry,
  });

  final GroupDetailState state;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      key: const Key('group-detail-route-screen'),
      appBar: AppBar(title: const Text('گروه')),
      body: SafeArea(child: _body(context)),
    );
  }

  Widget _body(BuildContext context) {
    switch (state.phase) {
      case GroupDetailPhase.loading:
        return const Center(child: CircularProgressIndicator());
      case GroupDetailPhase.failure:
        final failure = state.failure!;
        return Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(failure.message, textAlign: TextAlign.center),
                if (failure.canRetry && onRetry != null) ...[
                  const SizedBox(height: 16),
                  FilledButton(
                    key: const Key('group-detail-retry'),
                    onPressed: onRetry,
                    child: const Text('تلاش دوباره'),
                  ),
                ],
              ],
            ),
          ),
        );
      case GroupDetailPhase.ready:
        final group = state.group!;
        return SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (state.isStale) ...[
                const Text('نمایش نسخه ذخیره‌شده'),
                const SizedBox(height: 16),
              ],
              Text(
                group.name,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 16),
              Text(group.membership.roleLabel),
              const SizedBox(height: 12),
              Text(group.identity.dimensionKey),
              const SizedBox(height: 8),
              Text(group.identity.dimensionValueKey),
              const SizedBox(height: 12),
              Text('${group.membersCount} عضو'),
            ],
          ),
        );
    }
  }
}
