import 'package:flutter/material.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    this.onOpenGroups,
    this.onOpenNajmBahar,
    this.onOpenNotifications,
    this.onLogout,
  });

  final VoidCallback? onOpenGroups;
  final VoidCallback? onOpenNajmBahar;
  final VoidCallback? onOpenNotifications;
  final Future<void> Function()? onLogout;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _leaving = false;
  bool _busy = false;
  bool _failed = false;

  Future<void> _logout() async {
    if (_busy) return;
    setState(() {
      _leaving = true;
      _busy = true;
      _failed = false;
    });
    try {
      await widget.onLogout?.call();
    } catch (_) {
      if (mounted) {
        setState(() {
          _failed = true;
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

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
                    onPressed: _leaving ? null : widget.onOpenGroups,
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
                    onPressed: _leaving ? null : widget.onOpenNotifications,
                    icon: const Icon(Icons.notifications_outlined),
                    label: const Text('اعلان‌ها'),
                  ),
                ),
                if (widget.onOpenNajmBahar != null) ...[
                  const SizedBox(height: 12),
                  FilledButton.tonalIcon(
                    key: const Key('home-najm-bahar-action'),
                    onPressed: _leaving ? null : widget.onOpenNajmBahar,
                    icon: const Icon(Icons.account_balance_wallet_outlined),
                    label: const Text('نجم بهار'),
                  ),
                ],
                if (widget.onLogout != null) ...[
                  const SizedBox(height: 24),
                  OutlinedButton.icon(
                    key: const Key('home-logout-action'),
                    onPressed: _busy ? null : _logout,
                    icon: _busy
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.logout),
                    label: Text(_busy
                        ? 'در حال خروج…'
                        : _failed
                            ? 'تلاش دوباره برای خروج'
                            : 'خروج از حساب'),
                  ),
                  if (_failed)
                    const Padding(
                      padding: EdgeInsets.only(top: 12),
                      child: Text('خروج کامل نشد. دوباره تلاش کنید.',
                          key: Key('home-logout-error')),
                    ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
