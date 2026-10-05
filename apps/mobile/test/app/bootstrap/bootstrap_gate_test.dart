import 'package:earthcoop_mobile/app/bootstrap/bootstrap_gate.dart';
import 'package:earthcoop_mobile/app/bootstrap/bootstrap_state.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('compatible state enters the ready shell', (tester) async {
    await tester.pumpWidget(
      appWithGate(
        const BootstrapState.compatible(),
      ),
    );

    expect(find.byKey(const Key('ready-shell')), findsOneWidget);
    expect(find.byKey(const Key('bootstrap-required-update')), findsNothing);
  });

  testWidgets('recommended update is non-blocking', (tester) async {
    await tester.pumpWidget(
      appWithGate(
        const BootstrapState.recommendedUpdate(),
      ),
    );

    expect(find.byKey(const Key('ready-shell')), findsOneWidget);
    expect(
        find.byKey(const Key('bootstrap-update-recommended')), findsOneWidget);
  });

  testWidgets('required update blocks the ready shell', (tester) async {
    await tester.pumpWidget(
      appWithGate(
        const BootstrapState.requiredUpdate(),
      ),
    );

    expect(find.byKey(const Key('ready-shell')), findsNothing);
    expect(find.byKey(const Key('bootstrap-required-update')), findsOneWidget);
  });

  testWidgets('degraded offline uses a distinct cached shell and warning',
      (tester) async {
    await tester.pumpWidget(
      appWithGate(
        const BootstrapState.degradedOffline(),
      ),
    );

    expect(find.byKey(const Key('ready-shell')), findsNothing);
    expect(find.byKey(const Key('degraded-shell')), findsOneWidget);
    expect(find.byKey(const Key('bootstrap-degraded-offline')), findsOneWidget);
  });

  testWidgets('unavailable state blocks product shell and offers retry',
      (tester) async {
    var retries = 0;
    await tester.pumpWidget(
      appWithGate(
        const BootstrapState.unavailable(),
        onRetry: () => retries += 1,
      ),
    );

    expect(find.byKey(const Key('ready-shell')), findsNothing);
    expect(find.byKey(const Key('degraded-shell')), findsNothing);
    expect(find.byKey(const Key('bootstrap-unavailable')), findsOneWidget);

    await tester.tap(find.byKey(const Key('bootstrap-retry')));
    expect(retries, 1);
  });
}

Widget appWithGate(
  BootstrapState state, {
  VoidCallback? onRetry,
}) =>
    MaterialApp(
      home: BootstrapGate(
        state: state,
        onRetry: onRetry ?? () {},
        readyChild: const SizedBox(
          key: Key('ready-shell'),
          child: Text('ready'),
        ),
        degradedChild: const SizedBox(
          key: Key('degraded-shell'),
          child: Text('offline'),
        ),
      ),
    );
