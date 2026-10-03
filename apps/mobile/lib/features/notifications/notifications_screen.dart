import 'package:flutter/material.dart';

import '../../core/deep_links/semantic_link.dart';
import 'notification_dto.dart';
import 'notification_presentation.dart';
import 'notifications_controller.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({
    super.key,
    required this.state,
    this.onOpenLink,
    this.onMarkRead,
    this.onRetry,
  });

  final NotificationsState state;
  final ValueChanged<SemanticLink>? onOpenLink;
  final ValueChanged<NotificationDto>? onMarkRead;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      key: const Key('notifications-route-screen'),
      appBar: AppBar(title: const Text('اعلان‌ها')),
      body: SafeArea(child: _body(context)),
    );
  }

  Widget _body(BuildContext context) {
    switch (state.phase) {
      case NotificationsPhase.loading:
        return const Center(child: CircularProgressIndicator());
      case NotificationsPhase.empty:
        return const Center(child: Text('اعلانی برای نمایش وجود ندارد.'));
      case NotificationsPhase.failure:
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
                    key: const Key('notifications-retry'),
                    onPressed: onRetry,
                    child: const Text('تلاش دوباره'),
                  ),
                ],
              ],
            ),
          ),
        );
      case NotificationsPhase.ready:
        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: state.items.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (context, index) => _NotificationCard(
            notification: state.items[index],
            onOpenLink: onOpenLink,
            onMarkRead: onMarkRead,
          ),
        );
    }
  }
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({
    required this.notification,
    required this.onOpenLink,
    required this.onMarkRead,
  });

  final NotificationDto notification;
  final ValueChanged<SemanticLink>? onOpenLink;
  final ValueChanged<NotificationDto>? onMarkRead;

  @override
  Widget build(BuildContext context) {
    final link = _navigationLink(notification);
    return Card(
      key: Key('notification-card-${notification.id}'),
      child: InkWell(
        onTap: link == null || onOpenLink == null
            ? null
            : () {
                onMarkRead?.call(notification);
                onOpenLink!(link);
              },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (!notification.read)
                Container(
                  key: Key('notification-unread-${notification.id}'),
                  width: 10,
                  height: 10,
                  margin: const EdgeInsetsDirectional.only(top: 6, end: 12),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.primary,
                    shape: BoxShape.circle,
                  ),
                ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (notification.title != null)
                      Text(
                        notification.title!,
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                    if (notification.message != null) ...[
                      const SizedBox(height: 6),
                      Text(
                        NotificationPresentation.localizeMessage(
                          notification.message!,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

SemanticLink? _navigationLink(NotificationDto notification) {
  if (notification.link != null) return notification.link;

  final raw = notification.legacyUrl;
  if (raw == null || raw.isEmpty) return null;

  final uri = Uri.tryParse(raw);
  if (uri == null ||
      uri.hasScheme ||
      uri.hasAuthority ||
      uri.query.isNotEmpty ||
      uri.fragment.isNotEmpty) {
    return null;
  }

  final segments = uri.pathSegments;
  if (segments.length != 2 || segments.first != 'groups') return null;

  final groupId = int.tryParse(segments[1]);
  if (groupId == null || groupId <= 0) return null;

  return SemanticLink(
    version: 1,
    route: 'group.detail',
    params: {'group_id': groupId},
  );
}
