import 'package:earthcoop_mobile/app/app.dart';
import 'package:earthcoop_mobile/app/runtime/production_runtime.dart';
import 'package:flutter/widgets.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const EarthCoopApp(runtimeFactory: createProductionRuntime));
}
