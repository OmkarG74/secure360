package com.infipre.secure360

import android.app.NotificationManager
import android.content.Context
import android.content.SharedPreferences
import android.media.AudioAttributes
import android.media.MediaPlayer
import android.os.PowerManager
import android.util.Log

object WakeUpAlarmService {
    private const val TAG = "WakeUp"
    private const val PREFS_NAME = "secure360_wakeup_prefs"
    private const val KEY_ACKNOWLEDGED_IDS = "acknowledged_notification_ids"

    private var mediaPlayer: MediaPlayer? = null
    private var wakeLock: PowerManager.WakeLock? = null
    private var activeNotificationId: Int = 0

    private fun getPrefs(context: Context): SharedPreferences {
        return context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
    }

    fun isAcknowledged(context: Context, notificationId: Int): Boolean {
        if (notificationId <= 0) return false
        val set = getPrefs(context).getStringSet(KEY_ACKNOWLEDGED_IDS, emptySet()) ?: emptySet()
        return set.contains(notificationId.toString())
    }

    private fun markAcknowledgedInPrefs(context: Context, notificationId: Int) {
        if (notificationId <= 0) return
        val prefs = getPrefs(context)
        val existing = prefs.getStringSet(KEY_ACKNOWLEDGED_IDS, emptySet()) ?: emptySet()
        val updated = existing.toMutableSet()
        updated.add(notificationId.toString())
        prefs.edit().putStringSet(KEY_ACKNOWLEDGED_IDS, updated).apply()
    }

    fun isAlarmActive(notificationId: Int? = null): Boolean {
        val playing = mediaPlayer?.isPlaying == true
        if (!playing) return false
        if (notificationId != null && notificationId > 0) {
            return activeNotificationId == notificationId
        }
        return true
    }

    fun getActiveNotificationId(): Int {
        return if (mediaPlayer?.isPlaying == true) activeNotificationId else 0
    }

    @Synchronized
    fun start(context: Context, notificationId: Int, source: String = "unknown"): Map<String, Any> {
        // 1. Guard check: Already acknowledged?
        if (isAcknowledged(context, notificationId)) {
            Log.d(TAG, "[WakeUp] START_RESULT notification_id=$notificationId result=SKIPPED_ALREADY_ACKNOWLEDGED")
            return mapOf(
                "action" to "skip_duplicate",
                "is_alarm_active" to isAlarmActive(),
                "reason" to "already_acknowledged",
                "result" to "SKIPPED_ALREADY_ACKNOWLEDGED"
            )
        }

        // 2. Guard check: Is an alarm ALREADY active for this notification ID?
        if (mediaPlayer != null && mediaPlayer?.isPlaying == true) {
            if (activeNotificationId == notificationId) {
                Log.d(TAG, "[WakeUp] START_RESULT notification_id=$notificationId result=SKIPPED_ALREADY_ACTIVE")
                return mapOf(
                    "action" to "skip_duplicate",
                    "is_alarm_active" to true,
                    "reason" to "already_running",
                    "result" to "SKIPPED_ALREADY_ACTIVE"
                )
            } else {
                // Another alarm was playing; silence it first
                Log.d(TAG, "[WakeUp] STOP notification_id=$activeNotificationId source=superseded_by_$notificationId")
                stopInternal(context, activeNotificationId)
            }
        }

        // 3. Start Alarm
        activeNotificationId = notificationId

        try {
            val pm = context.getSystemService(Context.POWER_SERVICE) as? PowerManager
            wakeLock = pm?.newWakeLock(
                PowerManager.PARTIAL_WAKE_LOCK or PowerManager.ACQUIRE_CAUSES_WAKEUP,
                "Secure360:WakeUpAlarmLock"
            )?.apply {
                acquire(10 * 60 * 1000L) // 10 minutes safety timeout
            }

            mediaPlayer = MediaPlayer().apply {
                val resId = context.resources.getIdentifier("wake_up_alarm", "raw", context.packageName)
                if (resId != 0) {
                    val afd = context.resources.openRawResourceFd(resId)
                    setDataSource(afd.fileDescriptor, afd.startOffset, afd.length)
                    afd.close()
                }

                setAudioAttributes(
                    AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_ALARM)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                        .build()
                )
                isLooping = true
                setVolume(1.0f, 1.0f)
                prepare()
                start()
            }

            Log.d(TAG, "[WakeUp] START_RESULT notification_id=$notificationId result=STARTED")
            return mapOf(
                "action" to "start",
                "is_alarm_active" to true,
                "result" to "STARTED"
            )
        } catch (e: Exception) {
            Log.e(TAG, "[WakeUp] Failed to play Android wake-up alarm: ${e.message}", e)
            activeNotificationId = 0
            return mapOf(
                "action" to "error",
                "is_alarm_active" to false,
                "error" to (e.message ?: "Unknown error"),
                "result" to "ERROR"
            )
        }
    }

    @Synchronized
    fun stop(context: Context, notificationId: Int? = null, source: String = "unknown"): Map<String, Any> {
        val targetId = if (notificationId != null && notificationId > 0) notificationId else activeNotificationId
        if (targetId > 0) {
            markAcknowledgedInPrefs(context, targetId)
        }

        stopInternal(context, targetId)
        Log.d(TAG, "[WakeUp] STOP notification_id=$targetId source=$source")

        return mapOf(
            "action" to "stop",
            "is_alarm_active" to false,
            "notification_id" to targetId
        )
    }

    private fun stopInternal(context: Context, notificationId: Int) {
        try {
            mediaPlayer?.let {
                try {
                    if (it.isPlaying) {
                        it.stop()
                    }
                } catch (_: Exception) {}
                try {
                    it.reset()
                } catch (_: Exception) {}
                try {
                    it.release()
                } catch (_: Exception) {}
            }
        } catch (e: Exception) {
            Log.e(TAG, "[WakeUp] Error stopping MediaPlayer: ${e.message}", e)
        } finally {
            mediaPlayer = null
            activeNotificationId = 0
        }

        try {
            wakeLock?.let {
                if (it.isHeld) {
                    it.release()
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "[WakeUp] Error releasing wakeLock: ${e.message}", e)
        } finally {
            wakeLock = null
        }

        try {
            val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
            if (notificationId > 0) {
                nm?.cancel(notificationId)
            }
        } catch (e: Exception) {
            Log.e(TAG, "[WakeUp] Error cancelling notification: ${e.message}", e)
        }
    }
}
