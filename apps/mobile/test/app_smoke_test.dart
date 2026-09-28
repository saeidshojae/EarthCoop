import 'package:earthcoop_mobile/app/app.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('EarthCoopApp exposes the app root and Persian locale', (tester) async {
    await tester.pumpWidget(const EarthCoopApp());

    expect(find.byKey(const Key('earthcoop-app-root')), findsOneWidget);

    final app = tester.widget<WidgetsApp>(find.byType(WidgetsApp));
    expect(app.supportedLocales, contains(const Locale('fa')));
  });
}
