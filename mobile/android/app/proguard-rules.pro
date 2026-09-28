# Secure360 Android Proguard / R8 Configuration

# Preserve custom native Kotlin classes, plugins, and services
-keep class com.infipre.secure360.** { *; }

# Preserve Flutter engine, embedding, and plugin registrant classes
-keep class io.flutter.app.** { *; }
-keep class io.flutter.plugin.** { *; }
-keep class io.flutter.util.** { *; }
-keep class io.flutter.view.** { *; }
-keep class io.flutter.embedding.** { *; }
-keep class io.flutter.plugins.** { *; }

# Flutter Local Notifications (receivers, scheduled notifications)
-keep class com.dexterous.flutterlocalnotifications.** { *; }

# Suppress harmless warnings from dependencies during R8 optimization
-dontwarn io.flutter.**
-dontwarn com.google.firebase.**
