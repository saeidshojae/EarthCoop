plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

val uatKeystorePath = System.getenv("EARTHCOOP_UAT_KEYSTORE_PATH")
val uatKeyAlias = System.getenv("EARTHCOOP_UAT_KEY_ALIAS")
val uatStorePassword = System.getenv("EARTHCOOP_UAT_STORE_PASSWORD")
val uatKeyPassword = System.getenv("EARTHCOOP_UAT_KEY_PASSWORD")
val hasUatSigning = listOf(
    uatKeystorePath,
    uatKeyAlias,
    uatStorePassword,
    uatKeyPassword,
).all { !it.isNullOrBlank() }

android {
    namespace = "coop.earthcoop.earthcoop_mobile"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    signingConfigs {
        if (hasUatSigning) {
            create("uat") {
                storeFile = file(uatKeystorePath!!)
                storePassword = uatStorePassword!!
                keyAlias = uatKeyAlias!!
                keyPassword = uatKeyPassword!!
            }
        }
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "coop.earthcoop.earthcoop_mobile"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = 24
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    buildTypes {
        getByName("debug") {
            if (hasUatSigning) {
                signingConfig = signingConfigs.getByName("uat")
            }
        }
        release {
            // Production/store signing is intentionally separate from the UAT certificate.
            // Keep the existing development fallback until the release-signing task is completed.
            signingConfig = signingConfigs.getByName("debug")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
