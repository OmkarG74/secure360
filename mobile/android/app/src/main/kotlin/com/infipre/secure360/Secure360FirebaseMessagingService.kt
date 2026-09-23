package com.infipre.secure360

import android.util.Log
import com.google.firebase.messaging.RemoteMessage
import io.flutter.plugins.firebase.messaging.FlutterFirebaseMessagingService

class Secure360FirebaseMessagingService : FlutterFirebaseMessagingService() {
    private val TAG = "WakeUp"

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        val data = remoteMessage.data
        val type = (data["type"] ?: "").lowercase()
        val screen = (data["screen"] ?: "").lowercase()
        val isWakeUp = type == "wake_up" || type == "wake_up_call" || screen == "wake_up"

        if (isWakeUp) {
            val notifId = (data["notification_id"] ?: data["entity_id"] ?: data["id"] ?: "0").toIntOrNull() ?: 0
            if (notifId > 0) {
                val isAck = WakeUpAlarmService.isAcknowledged(applicationContext, notifId)
                if (!isAck) {
                    Log.d(TAG, "[WakeUp] START_REQUEST notification_id=$notifId source=background")
                    val active = WakeUpAlarmService.isAlarmActive(notifId)
                    Log.d(TAG, "[WakeUp] NATIVE_STATE notification_id=$notifId active=$active")
                    if (!active) {
                        WakeUpAlarmService.start(applicationContext, notifId, "background")
                    } else {
                        Log.d(TAG, "[WakeUp] START_RESULT notification_id=$notifId result=SKIPPED_ALREADY_ACTIVE")
                    }
                } else {
                    Log.d(TAG, "[WakeUp] START_RESULT notification_id=$notifId result=SKIPPED_ALREADY_ACKNOWLEDGED")
                }
            }
        }

        super.onMessageReceived(remoteMessage)
    }
}
