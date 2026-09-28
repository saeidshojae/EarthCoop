import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

class EarthCoopApp extends StatelessWidget {
  const EarthCoopApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      key: const Key('earthcoop-app-root'),
      debugShowCheckedModeBanner: false,
      locale: const Locale('fa'),
      supportedLocales: const [
        Locale('fa'),
        Locale('en'),
      ],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: const Scaffold(
        body: Center(child: Text('EarthCoop')),
      ),
    );
  }
}
