import 'package:flutter/material.dart';

import 'group_message_composer_controller.dart';

class GroupMessageComposer extends StatefulWidget {
  const GroupMessageComposer({
    super.key,
    required this.controller,
    required this.onSent,
  });
  final GroupMessageComposerController controller;
  final VoidCallback onSent;
  @override
  State<GroupMessageComposer> createState() => _GroupMessageComposerState();
}

class _GroupMessageComposerState extends State<GroupMessageComposer> {
  late final TextEditingController _text = TextEditingController(
    text: widget.controller.draft,
  );
  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final previous = widget.controller.lastSent;
    await widget.controller.send();
    if (!mounted) return;
    if (widget.controller.lastSent != previous) {
      _text.clear();
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('پیام ارسال شد.')));
      widget.onSent();
    }
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
        listenable: widget.controller,
        builder: (context, child) => Padding(
          padding: EdgeInsets.fromLTRB(
            12,
            8,
            12,
            8 + MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (widget.controller.error != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(
                    widget.controller.error!,
                    key: const Key('group-message-error'),
                  ),
                ),
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: TextField(
                      key: const Key('group-message-draft'),
                      controller: _text,
                      enabled: !widget.controller.isSending,
                      minLines: 1,
                      maxLines: 4,
                      maxLength: 2000,
                      onChanged: widget.controller.updateDraft,
                      decoration: const InputDecoration(
                        hintText: 'پیام خود را بنویسید',
                        border: OutlineInputBorder(),
                        counterText: '',
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    key: const Key('group-message-send'),
                    tooltip: 'ارسال پیام',
                    onPressed: widget.controller.isSending ||
                            widget.controller.draft.trim().isEmpty
                        ? null
                        : _send,
                    icon: widget.controller.isSending
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.send),
                  ),
                ],
              ),
            ],
          ),
        ),
      );
}
