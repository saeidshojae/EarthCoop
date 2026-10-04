package coop.earthcoop.earthcoop_mobile

import android.content.pm.PackageManager
import android.app.Activity
import android.content.Intent
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private var saveResult: MethodChannel.Result? = null
    private var saveBytes: ByteArray? = null
    private val saveRequestCode = 7312

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "earthcoop/attachments")
            .setMethodCallHandler { call, result ->
                if (call.method != "save") {
                    result.notImplemented()
                } else if (saveResult != null) {
                    result.error("save_busy", "A file chooser is already open", null)
                } else {
                    val bytes = call.argument<ByteArray>("bytes")
                    val name = call.argument<String>("name")
                    val mime = call.argument<String>("mime")
                    if (bytes == null || bytes.size > 20 * 1024 * 1024 || name.isNullOrBlank()) {
                        result.error("invalid_file", "Invalid attachment", null)
                    } else {
                        saveResult = result
                        saveBytes = bytes
                        try {
                            val intent = Intent(Intent.ACTION_CREATE_DOCUMENT).apply {
                                addCategory(Intent.CATEGORY_OPENABLE)
                                type = mime?.takeIf { it.matches(Regex("[a-zA-Z0-9.+-]+/[a-zA-Z0-9.+-]+")) }
                                    ?: "application/octet-stream"
                                putExtra(Intent.EXTRA_TITLE, name.substringAfterLast('/').substringAfterLast('\\'))
                            }
                            startActivityForResult(intent, saveRequestCode)
                        } catch (_: Exception) {
                            saveResult = null
                            saveBytes = null
                            result.error("save_unavailable", "File chooser unavailable", null)
                        }
                    }
                }
            }
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

    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode != saveRequestCode) return
        val result = saveResult ?: return
        val bytes = saveBytes
        saveResult = null
        saveBytes = null
        val uri = data?.data
        if (resultCode != Activity.RESULT_OK || uri == null || bytes == null) {
            result.success(false)
            return
        }
        Thread {
            try {
                val stream = contentResolver.openOutputStream(uri, "w")
                    ?: throw IllegalStateException("No output stream")
                stream.use { it.write(bytes) }
                runOnUiThread { result.success(true) }
            } catch (_: Exception) {
                runOnUiThread { result.error("save_failed", "File could not be saved", null) }
            }
        }.start()
    }

    override fun onDestroy() {
        saveResult?.error("save_cancelled", "Activity closed", null)
        saveResult = null
        saveBytes = null
        super.onDestroy()
    }

    @Suppress("DEPRECATION")
    private fun isPackageEnabled(packageName: String): Boolean =
        try {
            packageManager.getApplicationInfo(packageName, 0).enabled
        } catch (_: PackageManager.NameNotFoundException) {
            false
        }
}
