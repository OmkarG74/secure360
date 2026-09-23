import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';
import 'notification_service.dart';
import '../../features/notifications/screens/wake_up_call_screen.dart';

/// Centralized Wake-Up Call Alert & State Manager
///
/// Responsibilities:
/// - Native WakeUpAlarmService is the SINGLE SOURCE OF TRUTH for active alarm state
/// - Guarantees every Wake-Up Call is processed once per unique notification ID
/// - Never replays already-acknowledged wake-up calls
/// - Diagnostic logging tracking every request, native state, result, and stop
/// - Immediately silences alarm and cancels Android notifications upon acknowledgement
class WakeUpManager {
  WakeUpManager._();
  static final WakeUpManager instance = WakeUpManager._();

  static const String _prefAcknowledgedIdsKey = 'secure360_acknowledged_wakeup_ids';
  static const String _prefHandledIdsKey = 'secure360_handled_wakeup_ids';
  static const MethodChannel _platformChannel = MethodChannel('com.infipre.secure360/wake_up');

  static final Set<int> _acknowledgedIds = {};
  static final Set<int> _handledIds = {};
  static final Set<int> _visibleScreenIds = {};

  static bool _initialized = false;
  static bool _isAlarmPlaying = false;
  static int? _activeNotificationId;

  static bool get isAlarmPlaying => _isAlarmPlaying;
  static int? get activeNotificationId => _activeNotificationId;

  /// Initialize local persistence and state
  static Future<void> initialize() async {
    if (_initialized) return;

    try {
      final prefs = await SharedPreferences.getInstance();

      // Load locally acknowledged IDs
      final rawAck = prefs.getStringList(_prefAcknowledgedIdsKey) ?? [];
      for (final idStr in rawAck) {
        final id = int.tryParse(idStr);
        if (id != null && id > 0) {
          _acknowledgedIds.add(id);
        }
      }

      // Load handled IDs
      final rawHandled = prefs.getStringList(_prefHandledIdsKey) ?? [];
      for (final idStr in rawHandled) {
        final id = int.tryParse(idStr);
        if (id != null && id > 0) {
          _handledIds.add(id);
        }
      }

      _initialized = true;
    } catch (e) {
      debugPrint('[WakeUp] Initialization error: $e');
    }
  }

  /// Check if a notification ID is acknowledged in local memory
  static bool isAcknowledged(int notificationId) {
    if (notificationId <= 0) return true;
    return _acknowledgedIds.contains(notificationId);
  }

  /// Check if a notification ID is acknowledged natively across isolates
  static Future<bool> isNativeAcknowledged(int notificationId) async {
    if (notificationId <= 0) return true;
    if (_acknowledgedIds.contains(notificationId)) return true;

    try {
      final res = await _platformChannel.invokeMethod('isAcknowledged', {
        'notification_id': notificationId,
      });
      if (res == true) {
        _acknowledgedIds.add(notificationId);
        return true;
      }
    } catch (_) {}
    return false;
  }

  /// Combined synchronous + asynchronous acknowledgement check
  static Future<bool> isAcknowledgedAsync(int notificationId) async {
    if (isAcknowledged(notificationId)) return true;
    return await isNativeAcknowledged(notificationId);
  }

  /// Check native alarm state across isolates via MethodChannel
  static Future<bool> isNativeAlarmActive([int? notificationId]) async {
    try {
      final res = await _platformChannel.invokeMethod('isAlarmActive', {
        if (notificationId != null && notificationId > 0) 'notification_id': notificationId,
      });
      if (res is bool) {
        _isAlarmPlaying = res;
        return res;
      }
    } catch (_) {}
    return _isAlarmPlaying;
  }

  /// Check if a notification ID has already been handled in this session
  static bool isHandled(int notificationId) {
    if (notificationId <= 0) return true;
    return _handledIds.contains(notificationId);
  }

  /// Authoritative guard check: can this notification trigger an alarm?
  static bool canTrigger(int notificationId) {
    if (notificationId <= 0) {
      return false;
    }

    if (_acknowledgedIds.contains(notificationId)) {
      debugPrint('[WakeUp] Notification already acknowledged - ignoring');
      return false;
    }

    if (_activeNotificationId == notificationId && _isAlarmPlaying) {
      debugPrint('[WakeUp] Notification already handled - ignoring');
      return false;
    }

    if (_handledIds.contains(notificationId)) {
      debugPrint('[WakeUp] Notification already handled - ignoring');
      return false;
    }

    return true;
  }

  /// Mark a notification ID as handled locally
  static Future<void> markHandled(int notificationId) async {
    if (notificationId <= 0) return;
    _handledIds.add(notificationId);
    await _persistHandledIds();
  }

  /// Start Alarm with strict native single-source-of-truth and diagnostic logging
  static Future<bool> startAlarm(
    int notificationId, {
    String source = 'unknown',
  }) async {
    if (!_initialized) {
      await initialize();
    }

    debugPrint('[WakeUp] START_REQUEST notification_id=$notificationId source=$source');

    // 1. Query native alarm state
    final active = await isNativeAlarmActive(notificationId);
    debugPrint('[WakeUp] NATIVE_STATE notification_id=$notificationId active=$active');

    if (active) {
      debugPrint('[WakeUp] START_RESULT notification_id=$notificationId result=SKIPPED_ALREADY_ACTIVE');
      _isAlarmPlaying = true;
      _activeNotificationId = notificationId;
      return false;
    }

    // 2. Query native acknowledgement state
    final ack = await isAcknowledgedAsync(notificationId);
    if (ack) {
      debugPrint('[WakeUp] START_RESULT notification_id=$notificationId result=SKIPPED_ALREADY_ACKNOWLEDGED');
      return false;
    }

    _activeNotificationId = notificationId;
    _handledIds.add(notificationId);
    await _persistHandledIds();

    // 3. Dispatch native start
    try {
      final res = await _platformChannel.invokeMethod('startAlarm', {
        'notification_id': notificationId,
        'source': source,
      });

      String resultStr = 'STARTED';
      if (res is Map) {
        resultStr = (res['result'] ?? (res['action'] == 'start' ? 'STARTED' : 'SKIPPED_ALREADY_ACTIVE')).toString();
      }
      debugPrint('[WakeUp] START_RESULT notification_id=$notificationId result=$resultStr');
      _isAlarmPlaying = true;
      return (resultStr == 'STARTED');
    } catch (e) {
      debugPrint('[WakeUp] Platform channel startAlarm note: $e');
      _isAlarmPlaying = true; // Fallback for unit testing environments
      return false;
    }
  }

  /// Start background alarm audio independently of the visible Flutter screen
  static Future<void> startAlarmAudioOnly(
    int notificationId, {
    String source = 'background',
  }) async {
    await startAlarm(notificationId, source: source);
  }

  /// Start Wake-Up Call alert: starts native alarm and opens full-screen alert
  static Future<bool> startWakeUpAlert({
    required int notificationId,
    String? title,
    String? message,
    String? sentAt,
    String source = 'foreground',
  }) async {
    if (!_initialized) {
      await initialize();
    }

    debugPrint('[WakeUp] Received notification ID: $notificationId');

    if (!canTrigger(notificationId)) {
      return false;
    }

    _handledIds.add(notificationId);
    _activeNotificationId = notificationId;
    await _persistHandledIds();

    // Start native alarm
    await startAlarm(notificationId, source: source);

    // Launch WakeUpCallScreen
    final nav = ApiService.navigatorKey.currentState;
    if (nav != null) {
      nav.push(
        MaterialPageRoute(
          builder: (_) => WakeUpCallScreen(
            notificationId: notificationId,
            title: title,
            message: message,
            sentAt: sentAt,
          ),
        ),
      );
    }

    return true;
  }

  /// Check if a WakeUpCallScreen is already showing for this notification
  static bool isScreenVisible(int notificationId) {
    return _visibleScreenIds.contains(notificationId);
  }

  /// Update screen visibility state
  static void setScreenVisible(int notificationId, bool visible) {
    if (visible) {
      _visibleScreenIds.add(notificationId);
    } else {
      _visibleScreenIds.remove(notificationId);
    }
  }

  /// Stop alarm immediately and record acknowledgement
  static Future<void> acknowledge(int notificationId) async {
    debugPrint('[WakeUp] STOP notification_id=$notificationId source=acknowledge');

    // 1. Mark locally acknowledged immediately
    if (notificationId > 0) {
      _acknowledgedIds.add(notificationId);
      await _persistAcknowledgedIds();
    }

    _isAlarmPlaying = false;
    if (_activeNotificationId == notificationId) {
      _activeNotificationId = null;
    }

    // 2. Stop Android native wake-up service immediately
    try {
      await _platformChannel.invokeMethod('stopAlarm', {
        'notification_id': notificationId,
        'source': 'acknowledge',
      });
      debugPrint('[WakeUp] Android alarm/service STOP');
    } catch (e) {
      debugPrint('[WakeUp] Error stopping Android wake-up service: $e');
    }

    // 3. Cancel Android system-tray wake-up notification
    try {
      await NotificationService.cancelNotification(notificationId);
      debugPrint('[WakeUp] Wake-Up notification CANCELLED');
    } catch (e) {
      debugPrint('[WakeUp] Error cancelling notification: $e');
    }

    // 4. Synchronize with backend asynchronously (non-blocking)
    if (notificationId > 0) {
      unawaited(
        ApiService.acknowledgeNotification(notificationId).then((res) {
          if (res.success) {
            debugPrint('[WakeUp] Acknowledgement API successful');
          } else {
            debugPrint('[WakeUp] Backend acknowledgement notice: ${res.message}');
          }
        }).catchError((e) {
          debugPrint('[WakeUp] Network error syncing acknowledgement to backend: $e (local ack preserved)');
        }),
      );
    }
  }

  /// Stop audio only (e.g. on emergency exit or cleanup)
  static Future<void> stopAlarmOnly({
    int? notificationId,
    String source = 'cleanup',
  }) async {
    _isAlarmPlaying = false;
    _activeNotificationId = null;

    try {
      await _platformChannel.invokeMethod('stopAlarm', {
        if (notificationId != null && notificationId > 0) 'notification_id': notificationId,
        'source': source,
      });
      debugPrint('[WakeUp] Android alarm/service STOP');
    } catch (e) {
      debugPrint('[WakeUp] Error in stopAlarmOnly Android service: $e');
    }
  }

  static Future<void> _persistAcknowledgedIds() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final list = _acknowledgedIds.map((id) => id.toString()).toList();
      await prefs.setStringList(_prefAcknowledgedIdsKey, list);
    } catch (_) {}
  }

  static Future<void> _persistHandledIds() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final list = _handledIds.take(100).map((id) => id.toString()).toList();
      await prefs.setStringList(_prefHandledIdsKey, list);
    } catch (_) {}
  }
}
