package coop.earthcoop.earthcoop_mobile

import android.content.pm.PackageManager
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            "earthcoop/push_runtime",
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "hasGms" -> result.success(isPackageEnabled("com.google.android.gms"))
                "hasHms" -> result.success(isPackageEnabled("com.huawei.hwid"))
                else -> result.notImplemented()
            }
        }
    }

    @Suppress("DEPRECATION")
    private fun isPackageEnabled(packageName: String): Boolean =
        try {
            packageManager.getApplicationInfo(packageName, 0).enabled
        } catch (_: PackageManager.NameNotFoundException) {
            false
        }
}
