import 'package:flutter/material.dart';

import 'localization/earthcoop_localization.dart';
import 'runtime/mobile_app_runtime.dart';
import 'theme/earthcoop_theme.dart';

typedef MobileAppRuntimeFactory = Future<MobileAppRuntime> Function();

class EarthCoopApp extends StatelessWidget {
  const EarthCoopApp({super.key, this.runtimeFactory});

  final MobileAppRuntimeFactory? runtimeFactory;

  @override
  Widget build(BuildContext context) {
    final factory = runtimeFactory;
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
      future: factory(),
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
