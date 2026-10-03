import 'package:flutter/material.dart';

import 'login_controller.dart';

export 'login_controller.dart' show LoginFailure;

typedef LoginSubmit = Future<void> Function({
  required String email,
  required String password,
});

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.onSubmit});

  final LoginSubmit onSubmit;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_submitting) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await widget.onSubmit(
        email: _emailController.text.trim(),
        password: _passwordController.text,
      );
    } on LoginFailure catch (failure) {
      if (!mounted) return;
      setState(() {
        _error = failure.kind == 'invalid_credentials'
            ? 'ایمیل یا گذرواژه نادرست است.'
            : 'در حال حاضر امکان ورود وجود ندارد. دوباره تلاش کنید.';
      });
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 480),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'ورود به ارث‌کوپ',
                      style: Theme.of(context).textTheme.headlineSmall,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 24),
                    TextField(
                      key: const Key('login-email'),
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const [AutofillHints.email],
                      decoration: const InputDecoration(labelText: 'ایمیل'),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      key: const Key('login-password'),
                      controller: _passwordController,
                      obscureText: true,
                      autofillHints: const [AutofillHints.password],
                      onSubmitted: (_) => _submit(),
                      decoration: const InputDecoration(labelText: 'گذرواژه'),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Semantics(
                        liveRegion: true,
                        child: Text(_error!, textAlign: TextAlign.center),
                      ),
                    ],
                    const SizedBox(height: 24),
                    FilledButton(
                      key: const Key('login-submit'),
                      onPressed: _submitting ? null : _submit,
                      child: Text(_submitting ? 'در حال ورود…' : 'ورود'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
