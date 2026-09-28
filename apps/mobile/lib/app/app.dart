import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

class EarthCoopApp extends StatelessWidget {
  const EarthCoopApp({super.key});

  @override
  Widget build(BuildContext context) {
    return const MaterialApp(
      key: Key('earthcoop-app-root'),
      debugShowCheckedModeBanner: false,
      locale: Locale('fa'),
      supportedLocales: [
        Locale('fa'),
        Locale('en'),
      ],
      localizationsDelegates: [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: Scaffold(
        body: Center(child: Text('EarthCoop')),
      ),
    );
  }
}
