import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/deep_links/semantic_link.dart';
import 'notifications_controller.dart';
import 'notifications_screen.dart';

class NotificationsRuntimeView extends StatefulWidget {
  const NotificationsRuntimeView({
    required this.controller,
    required this.onOpenLink,
    this.refreshEvents,
  });

  final Stream<void>? refreshEvents;
  final NotificationsController controller;
  final ValueChanged<SemanticLink> onOpenLink;

  @override
  State<NotificationsRuntimeView> createState() =>
      NotificationsRuntimeViewState();
}

class NotificationsRuntimeViewState extends State<NotificationsRuntimeView>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    widget.controller.addListener(_refresh);
    unawaited(widget.controller.load());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    widget.controller.removeListener(_refresh);
    widget.controller.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) unawaited(widget.controller.load());
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) => NotificationsScreen(
        state: widget.controller.state,
        onMarkRead: (notification) {
          unawaited(widget.controller.markRead(notification.id));
        },
        onOpenLink: widget.onOpenLink,
        onRetry: widget.controller.load,
      );
}
