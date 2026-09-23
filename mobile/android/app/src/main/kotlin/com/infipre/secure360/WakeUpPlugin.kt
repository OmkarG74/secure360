package com.infipre.secure360

import android.content.Context
import androidx.annotation.NonNull
import io.flutter.embedding.engine.plugins.FlutterPlugin
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

class WakeUpPlugin : FlutterPlugin, MethodChannel.MethodCallHandler {
    private var channel: MethodChannel? = null
    private var context: Context? = null

    override fun onAttachedToEngine(@NonNull binding: FlutterPlugin.FlutterPluginBinding) {
        context = binding.applicationContext
        channel = MethodChannel(binding.binaryMessenger, "com.infipre.secure360/wake_up")
        channel?.setMethodCallHandler(this)
    }

    override fun onDetachedFromEngine(@NonNull binding: FlutterPlugin.FlutterPluginBinding) {
        channel?.setMethodCallHandler(null)
        channel = null
        context = null
    }

    override fun onMethodCall(@NonNull call: MethodCall, @NonNull result: MethodChannel.Result) {
        val ctx = context ?: return result.error("NO_CONTEXT", "Application context is null", null)
        when (call.method) {
            "startAlarm" -> {
                val notifId = call.argument<Int>("notification_id") ?: 0
                val source = call.argument<String>("source") ?: "unknown"
                val res = WakeUpAlarmService.start(ctx, notifId, source)
                result.success(res)
            }
            "stopAlarm" -> {
                val notifId = call.argument<Int>("notification_id")
                val source = call.argument<String>("source") ?: "unknown"
                val res = WakeUpAlarmService.stop(ctx, notifId, source)
                result.success(res)
            }
            "isAlarmActive" -> {
                val notifId = call.argument<Int>("notification_id")
                result.success(WakeUpAlarmService.isAlarmActive(notifId))
            }
            "isAcknowledged" -> {
                val notifId = call.argument<Int>("notification_id") ?: 0
                result.success(WakeUpAlarmService.isAcknowledged(ctx, notifId))
            }
            "getActiveNotificationId" -> {
                result.success(WakeUpAlarmService.getActiveNotificationId())
            }
            else -> result.notImplemented()
        }
    }
}
