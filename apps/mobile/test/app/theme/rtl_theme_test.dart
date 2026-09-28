import 'package:earthcoop_mobile/app/localization/earthcoop_localization.dart';
import 'package:earthcoop_mobile/app/theme/earthcoop_theme.dart';
import 'package:earthcoop_mobile/features/home/home_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('Persian locale renders RTL with Material 3 light theme',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('fa'),
        supportedLocales: EarthCoopLocalization.supportedLocales,
        localizationsDelegates: EarthCoopLocalization.delegates,
        theme: EarthCoopTheme.light(),
        home: const HomeScreen(),
      ),
    );

    expect(Directionality.of(tester.element(find.byType(HomeScreen))),
        TextDirection.rtl);
    expect(Theme.of(tester.element(find.byType(HomeScreen))).brightness,
        Brightness.light);
    expect(
        Theme.of(tester.element(find.byType(HomeScreen))).useMaterial3, isTrue);
  });

  testWidgets('dark theme remains Material 3', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: EarthCoopTheme.light(),
        darkTheme: EarthCoopTheme.dark(),
        themeMode: ThemeMode.dark,
        home: const HomeScreen(),
      ),
    );

    final theme = Theme.of(tester.element(find.byType(HomeScreen)));
    expect(theme.brightness, Brightness.dark);
    expect(theme.useMaterial3, isTrue);
  });

  testWidgets('large text keeps primary controls usable and semantic',
      (tester) async {
    await tester.pumpWidget(
      MediaQuery(
        data: const MediaQueryData(
          textScaler: TextScaler.linear(2),
          size: Size(390, 844),
        ),
        child: MaterialApp(
          locale: const Locale('fa'),
          supportedLocales: EarthCoopLocalization.supportedLocales,
          localizationsDelegates: EarthCoopLocalization.delegates,
          theme: EarthCoopTheme.light(),
          home: const HomeScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.bySemanticsLabel('گروه‌های من'), findsOneWidget);
    expect(find.byKey(const Key('home-groups-action')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
