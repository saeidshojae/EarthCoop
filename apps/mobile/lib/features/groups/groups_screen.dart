import 'package:flutter/material.dart';

import 'groups_controller.dart';

class GroupsScreen extends StatelessWidget {
  const GroupsScreen({
    super.key,
    required this.state,
    this.onOpenGroup,
    this.onRetry,
  });

  final GroupsState state;
  final ValueChanged<int>? onOpenGroup;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      key: const Key('groups-route-screen'),
      appBar: AppBar(title: const Text('گروه‌های من')),
      body: SafeArea(child: _body(context)),
    );
  }

  Widget _body(BuildContext context) {
    switch (state.phase) {
      case GroupsPhase.loading:
        return const Center(child: CircularProgressIndicator());
      case GroupsPhase.empty:
        return const Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text('هنوز گروهی برای نمایش وجود ندارد.'),
          ),
        );
      case GroupsPhase.failure:
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
                    key: const Key('groups-retry'),
                    onPressed: onRetry,
                    child: const Text('تلاش دوباره'),
                  ),
                ],
              ],
            ),
          ),
        );
      case GroupsPhase.ready:
        return Column(
          children: [
            if (state.isStale)
              const Padding(
                padding: EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: Text('نمایش نسخه ذخیره‌شده'),
              ),
            Expanded(
              child: ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: state.items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 12),
                itemBuilder: (context, index) {
                  final group = state.items[index];
                  return Card(
                    child: InkWell(
                      key: Key('group-card-${group.id}'),
                      onTap: onOpenGroup == null
                          ? null
                          : () => onOpenGroup!(group.id),
                      borderRadius: BorderRadius.circular(12),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              group.name,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            const SizedBox(height: 8),
                            Text(group.membership.roleLabel),
                            const SizedBox(height: 4),
                            Text('${_persianDigits(group.membersCount)} عضو'),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        );
    }
  }
}

String _persianDigits(int value) {
  const western = '0123456789';
  const persian = '۰۱۲۳۴۵۶۷۸۹';
  return value.toString().split('').map((digit) {
    final index = western.indexOf(digit);
    return index < 0 ? digit : persian[index];
  }).join();
}
