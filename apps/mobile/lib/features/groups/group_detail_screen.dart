import 'package:flutter/material.dart';

import 'group_feed_dto.dart';
import 'group_message_composer.dart';
import 'group_message_composer_controller.dart';
import 'groups_controller.dart';

class GroupDetailScreen extends StatelessWidget {
  const GroupDetailScreen({
    super.key,
    required this.state,
    this.onRetry,
    this.composer,
  });

  final GroupDetailState state;
  final VoidCallback? onRetry;
  final GroupMessageComposerController? composer;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      key: const Key('group-detail-route-screen'),
      appBar: AppBar(
        title: const Text('گروه'),
        actions: [
          if (state.phase == GroupDetailPhase.ready && onRetry != null)
            IconButton(
              tooltip: 'دریافت فعالیت‌های تازه',
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
            ),
        ],
      ),
      bottomNavigationBar: state.phase == GroupDetailPhase.ready &&
              !state.isStale &&
              state.group!.membership.role != 0 &&
              state.group!.membership.status == 1 &&
              composer != null
          ? SafeArea(
              child: GroupMessageComposer(
                controller: composer!,
              ),
            )
          : null,
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
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (state.isStale) ...[
              const Text('نمایش نسخه ذخیره‌شده'),
              const SizedBox(height: 12),
            ],
            Text(group.name, style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 12),
            Wrap(
              spacing: 12,
              runSpacing: 8,
              children: [
                _InfoChip(label: group.membership.roleLabel),
                _InfoChip(label: '${_persianDigits(group.membersCount)} عضو'),
                if (state.unreadCount > 0)
                  _InfoChip(
                    label: '${_persianDigits(state.unreadCount)} خوانده‌نشده',
                  ),
              ],
            ),
            const SizedBox(height: 28),
            Text(
              'فعالیت‌های گروه',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 12),
            if (state.activityFailure != null) ...[
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Text(state.activityFailure!),
                ),
              ),
              const SizedBox(height: 8),
            ],
            if (state.activity.isEmpty && state.activityFailure == null)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Text('هنوز فعالیتی برای نمایش وجود ندارد.'),
                ),
              )
            else
              ...state.activity.map(
                (event) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _ActivityCard(event: event),
                ),
              ),
          ],
        );
    }
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Chip(label: Text(label));
}

class _ActivityCard extends StatelessWidget {
  const _ActivityCard({required this.event});

  final GroupFeedEvent event;

  @override
  Widget build(BuildContext context) {
    final children = <Widget>[];
    switch (event.kind) {
      case GroupFeedKind.message:
        _appendText(
          children,
          event.sender,
          style: Theme.of(context).textTheme.labelLarge,
        );
        _appendText(children, event.message);
        break;
      case GroupFeedKind.file:
        _appendText(
          children,
          event.sender,
          style: Theme.of(context).textTheme.labelLarge,
        );
        _appendText(children, event.message ?? 'فایل ارسال شد.');
        break;
      case GroupFeedKind.voice:
        _appendText(
          children,
          event.sender,
          style: Theme.of(context).textTheme.labelLarge,
        );
        _appendText(children, event.message ?? 'پیام صوتی ارسال شد.');
        break;
      case GroupFeedKind.post:
        _appendText(
          children,
          event.title,
          style: Theme.of(context).textTheme.titleMedium,
        );
        _appendText(children, event.content);
        break;
      case GroupFeedKind.poll:
        _appendText(
          children,
          event.question,
          style: Theme.of(context).textTheme.titleMedium,
        );
        children.add(Text('${_persianDigits(event.options.length)} گزینه'));
        break;
      case GroupFeedKind.comment:
        children.add(
          Text('نظر', style: Theme.of(context).textTheme.labelLarge),
        );
        _appendText(children, event.message);
        break;
      case GroupFeedKind.unknown:
        children.add(const Text('فعالیت گروه'));
        _appendText(children, event.message);
        break;
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: _spaced(children),
        ),
      ),
    );
  }

  void _appendText(List<Widget> children, String? value, {TextStyle? style}) {
    if (value == null || value.trim().isEmpty) return;
    children.add(Text(value, style: style));
  }

  List<Widget> _spaced(List<Widget> children) {
    if (children.length < 2) return children;
    final result = <Widget>[];
    for (var index = 0; index < children.length; index += 1) {
      if (index > 0) result.add(const SizedBox(height: 6));
      result.add(children[index]);
    }
    return result;
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
