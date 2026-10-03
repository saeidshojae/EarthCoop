import 'package:earthcoop_mobile/features/auth/login_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('login screen is Persian RTL and submits credentials',
      (tester) async {
    String? submittedEmail;
    String? submittedPassword;

    await tester.pumpWidget(
      MaterialApp(
        home: LoginScreen(
          onSubmit: ({required email, required password}) async {
            submittedEmail = email;
            submittedPassword = password;
          },
        ),
      ),
    );

    expect(find.text('ورود به ارث‌کوپ'), findsOneWidget);
    final directionality =
        tester.widget<Directionality>(find.byType(Directionality).last);
    expect(directionality.textDirection, TextDirection.rtl);

    await tester.enterText(
        find.byKey(const Key('login-email')), 'member@example.test');
    await tester.enterText(
        find.byKey(const Key('login-password')), 'secret-password');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pump();

    expect(submittedEmail, 'member@example.test');
    expect(submittedPassword, 'secret-password');
  });

  testWidgets('login failure is visible without exposing sensitive details',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: LoginScreen(
          onSubmit: ({required email, required password}) async {
            throw const LoginFailure.invalidCredentials();
          },
        ),
      ),
    );

    await tester.enterText(
        find.byKey(const Key('login-email')), 'member@example.test');
    await tester.enterText(
        find.byKey(const Key('login-password')), 'wrong-password');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(find.text('ایمیل یا گذرواژه نادرست است.'), findsOneWidget);
    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is Text &&
            (widget.data?.contains('wrong-password') ?? false),
      ),
      findsNothing,
    );
  });
}
