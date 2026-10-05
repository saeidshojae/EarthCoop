import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

class EarthCoopLocalization {
  const EarthCoopLocalization._();

  static const supportedLocales = <Locale>[
    Locale('fa'),
    Locale('en'),
  ];

  static const delegates = <LocalizationsDelegate<dynamic>>[
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ];
}
