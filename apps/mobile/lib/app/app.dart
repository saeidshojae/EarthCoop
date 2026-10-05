import 'dart:async';

import 'package:flutter/material.dart';

import 'localization/earthcoop_localization.dart';
import 'runtime/mobile_app_runtime.dart';
import 'theme/earthcoop_theme.dart';

typedef MobileAppRuntimeFactory = Future<MobileAppRuntime> Function();

class EarthCoopApp extends StatefulWidget {
  const EarthCoopApp({super.key, this.runtimeFactory});

  final MobileAppRuntimeFactory? runtimeFactory;

  @override
  State<EarthCoopApp> createState() => _EarthCoopAppState();
}

class _EarthCoopAppState extends State<EarthCoopApp>
    with WidgetsBindingObserver {
  Future<MobileAppRuntime>? _future;
  MobileAppRuntime? _runtime;
  int _generation = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _start();
  }

  void _start() {
    final generation = ++_generation;
    final factory = widget.runtimeFactory;
    _future = factory?.call().then((runtime) {
      if (!mounted || generation != _generation) {
        runtime.dispose();
      } else {
        _runtime = runtime;
      }
      return runtime;
    });
  }

  @override
  void didUpdateWidget(EarthCoopApp oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.runtimeFactory != widget.runtimeFactory) {
      _runtime?.dispose();
      _runtime = null;
      _start();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final foreground = _runtime?.onForeground;
    if (state == AppLifecycleState.resumed && foreground != null) {
      unawaited(foreground().catchError((Object _) {}));
    }
  }

  @override
  void dispose() {
    _generation++;
    WidgetsBinding.instance.removeObserver(this);
    _runtime?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final factory = widget.runtimeFactory;
    if (factory == null) {
      return MaterialApp(
        key: const Key('earthcoop-app-root'),
        debugShowCheckedModeBanner: false,
        locale: const Locale('fa'),
        supportedLocales: EarthCoopLocalization.supportedLocales,
        localizationsDelegates: EarthCoopLocalization.delegates,
        theme: EarthCoopTheme.light(),
        darkTheme: EarthCoopTheme.dark(),
        home: const Scaffold(
          body: Center(child: Text('EarthCoop')),
        ),
      );
    }

    return FutureBuilder<MobileAppRuntime>(
      key: ValueKey(_generation),
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _shell(
            const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text(
                  'در حال حاضر امکان راه‌اندازی برنامه وجود ندارد.',
                  textAlign: TextAlign.center,
                ),
              ),
            ),
          );
        }
        final runtime = snapshot.data;
        if (runtime == null) {
          return _shell(const Center(child: CircularProgressIndicator()));
        }
        return MaterialApp.router(
          key: const Key('earthcoop-app-root'),
          debugShowCheckedModeBanner: false,
          locale: const Locale('fa'),
          supportedLocales: EarthCoopLocalization.supportedLocales,
          localizationsDelegates: EarthCoopLocalization.delegates,
          theme: EarthCoopTheme.light(),
          darkTheme: EarthCoopTheme.dark(),
          routerConfig: runtime.router,
        );
      },
    );
  }

  Widget _shell(Widget child) => MaterialApp(
        key: const Key('earthcoop-app-root'),
        debugShowCheckedModeBanner: false,
        locale: const Locale('fa'),
        supportedLocales: EarthCoopLocalization.supportedLocales,
        localizationsDelegates: EarthCoopLocalization.delegates,
        theme: EarthCoopTheme.light(),
        darkTheme: EarthCoopTheme.dark(),
        home: Scaffold(body: child),
      );
}
