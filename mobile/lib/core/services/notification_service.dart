import 'dart:convert';
import 'dart:io';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';
import 'wake_up_manager.dart';
import '../../features/attendance/screens/attendance_history_screen.dart';
import '../../features/duty/screens/home_dashboard_screen.dart';
import '../../features/notifications/screens/notifications_screen.dart';
import '../../features/notifications/screens/wake_up_call_screen.dart';

/// Top-level background message handler required by FirebaseMessaging
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  final data = message.data;
  final type = (data['type'] ?? '').toString().toLowerCase();
  final screen = (data['screen'] ?? '').toString().toLowerCase();
  final isWakeUp = (type == 'wake_up' ||
      type == 'wake_up_call' ||
      screen == 'wake_up' ||
      (message.notification?.title?.toLowerCase().contains('wake-up') ?? false));

  if (!isWakeUp) {
    return;
  }

  debugPrint('[WakeUp] FCM received');
  debugPrint('[WakeUp] App state=background');
  final rawId = data['notification_id'] ?? data['entity_id'] ?? data['id'] ?? '0';
  final notifId = int.tryParse(rawId.toString()) ?? 0;
  debugPrint('[WakeUp] notificationId=$notifId');

  await WakeUpManager.initialize();
  if (await WakeUpManager.isAcknowledgedAsync(notifId)) {
    debugPrint('[WakeUp] Notification already acknowledged - ignoring');
    return;
  }

  // 1. Mark as handled in background storage to prevent duplicate alerts
  await WakeUpManager.markHandled(notifId);

  // 2. Setup local notifications plugin in background isolate
  final localNotifications = FlutterLocalNotificationsPlugin();
  const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
  const initSettings = InitializationSettings(android: androidSettings);
  await localNotifications.initialize(initSettings);

  // 3. Ensure wake-up notification channel exists without native notification sound
  // (Alarm sound is strictly controlled via WakeUpManager / WakeUpAlarmService)
  const androidWakeUpChannel = AndroidNotificationChannel(
    NotificationService.wakeupChannelId,
    NotificationService.wakeupChannelName,
    description: NotificationService.wakeupChannelDesc,
    importance: Importance.max,
    playSound: false,
    enableVibration: true,
  );

  final androidPlugin = localNotifications
      .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
  if (androidPlugin != null) {
    try {
      await androidPlugin.deleteNotificationChannel(NotificationService.wakeupChannelId);
    } catch (_) {}
    await androidPlugin.createNotificationChannel(androidWakeUpChannel);
  }

  final title = data['title'] ?? message.notification?.title ?? 'URGENT WAKE-UP CALL';
  final body = data['message'] ?? data['body'] ?? message.notification?.body ?? 'Supervisor has dispatched an urgent wake-up alert.';
  final notifTagId = notifId > 0 ? notifId : message.hashCode;

  // 4. Trigger high-priority Full-Screen Intent notification immediately
  await localNotifications.show(
    notifTagId,
    title,
    body,
    const NotificationDetails(
      android: AndroidNotificationDetails(
        NotificationService.wakeupChannelId,
        NotificationService.wakeupChannelName,
        channelDescription: NotificationService.wakeupChannelDesc,
        importance: Importance.max,
        priority: Priority.max,
        icon: '@mipmap/ic_launcher',
        playSound: false,
        enableVibration: true,
        fullScreenIntent: true,
        ongoing: true,
        autoCancel: false,
        category: AndroidNotificationCategory.alarm,
        audioAttributesUsage: AudioAttributesUsage.alarm,
      ),
    ),
    payload: jsonEncode(data),
  );
  debugPrint('[WakeUp] Android notification displayed');
  debugPrint('[WakeUp] Wake-Up notification POSTED');

  // 5. Start alarm audio natively with source=background
  await WakeUpManager.startAlarmAudioOnly(notifId, source: 'background');
}

/// Centralized Push Notification Service for Secure360 Mobile Client
class NotificationService {
  NotificationService._();
  static final NotificationService instance = NotificationService._();

  static const String _channelId = 'secure360_notifications';
  static const String _channelName = 'Secure360 Notifications';
  static const String _channelDesc = 'Important alerts, duty rosters, and operational notifications';

  static const String wakeupChannelId = 'secure360_wakeup';
  static const String wakeupChannelName = 'Secure360 Wake-Up Calls';
  static const String wakeupChannelDesc = 'Urgent high-priority alarms dispatched by supervisors';

  static const String _wakeupChannelId = wakeupChannelId;
  static const String _wakeupChannelName = wakeupChannelName;
  static const String _wakeupChannelDesc = wakeupChannelDesc;

  static const String _lastFcmTokenKey = 'secure360_last_fcm_token';
  static const String _lastSyncedUserIdKey = 'secure360_last_synced_user_id';

  static final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  static final FlutterLocalNotificationsPlugin _localNotifications = FlutterLocalNotificationsPlugin();

  static bool _initialized = false;
  static OverlayEntry? _currentBannerEntry;
  static Map<String, dynamic>? _pendingInitialPayload;

  /// Check if there is an unhandled notification payload from terminated launch
  static bool get hasPendingInitialPayload => _pendingInitialPayload != null;
  static Map<String, dynamic>? get pendingInitialPayload => _pendingInitialPayload;

  /// Clear the pending initial payload
  static void clearPendingInitialPayload() {
    _pendingInitialPayload = null;
  }

  /// Process the pending initial route once app navigation is established
  static void handlePendingInitialRoute() {
    if (_pendingInitialPayload != null) {
      final payload = _pendingInitialPayload!;
      _pendingInitialPayload = null;
      final type = (payload['type'] ?? '').toString().toLowerCase();
      final screen = (payload['screen'] ?? '').toString().toLowerCase();
      final isWakeUp = (type == 'wake_up' || type == 'wake_up_call' || screen == 'wake_up');
      if (isWakeUp) {
        final rawId = payload['notification_id'] ?? payload['entity_id'] ?? payload['id'] ?? '0';
        final notifId = int.tryParse(rawId.toString()) ?? 0;
        debugPrint('[WakeUp] Notification tapped');
        debugPrint('[WakeUp] notificationId=$notifId');
        debugPrint('[WakeUp] App state=terminated');
        debugPrint('[WakeUp] Opening WakeUpCallScreen from initial message');
      }
      _handleNavigation(payload);
    }
  }

  /// Cancel an active system tray notification (e.g. on acknowledge)
  static Future<void> cancelNotification(int id) async {
    try {
      if (id > 0) {
        await _localNotifications.cancel(id);
        debugPrint('[WakeUp] Wake-Up notification CANCELLED');
      }
    } catch (e) {
      debugPrint('[NotificationService] cancelNotification error: $e');
    }
  }

  /// Initialize Firebase and Push Notification handlers
  static Future<void> initialize() async {
    if (_initialized) return;

    try {
      await Firebase.initializeApp();
      await WakeUpManager.initialize();
      FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

      // 1. Request notification permissions (POST_NOTIFICATIONS on Android 13+)
      await _messaging.requestPermission(
        alert: true,
        announcement: false,
        badge: true,
        carPlay: false,
        criticalAlert: true,
        provisional: false,
        sound: true,
      );

      // 2. Setup Android notification channels
      const androidChannel = AndroidNotificationChannel(
        _channelId,
        _channelName,
        description: _channelDesc,
        importance: Importance.high,
        playSound: true,
        enableVibration: true,
      );

      const androidWakeUpChannel = AndroidNotificationChannel(
        _wakeupChannelId,
        _wakeupChannelName,
        description: _wakeupChannelDesc,
        importance: Importance.max,
        playSound: false,
        enableVibration: true,
      );

      final androidPlugin = _localNotifications
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
      if (androidPlugin != null) {
        await androidPlugin.createNotificationChannel(androidChannel);
        try {
          await androidPlugin.deleteNotificationChannel(_wakeupChannelId);
        } catch (_) {}
        await androidPlugin.createNotificationChannel(androidWakeUpChannel);
      }

      // 3. Initialize Flutter Local Notifications for foreground display
      const initializationSettingsAndroid = AndroidInitializationSettings('@mipmap/ic_launcher');
      const initializationSettings = InitializationSettings(android: initializationSettingsAndroid);

      await _localNotifications.initialize(
        initializationSettings,
        onDidReceiveNotificationResponse: (NotificationResponse response) {
          if (response.payload != null && response.payload!.isNotEmpty) {
            try {
              final data = jsonDecode(response.payload!);
              if (data is Map) {
                _handleNavigation(Map<String, dynamic>.from(data), source: 'notification_tap');
              }
            } catch (_) {}
          }
        },
      );

      // 4. Capture any launch notification payload from terminated state
      final launchDetails = await _localNotifications.getNotificationAppLaunchDetails();
      if (launchDetails != null && launchDetails.didNotificationLaunchApp) {
        final payload = launchDetails.notificationResponse?.payload;
        if (payload != null && payload.isNotEmpty) {
          try {
            final data = jsonDecode(payload);
            if (data is Map) {
              _pendingInitialPayload = Map<String, dynamic>.from(data);
              debugPrint('[WakeUp] App state=terminated');
              debugPrint('[WakeUp] Stored pending launch payload from local notification');
            }
          } catch (_) {}
        }
      }

      final initialMessage = await _messaging.getInitialMessage();
      if (initialMessage != null && initialMessage.data.isNotEmpty) {
        _pendingInitialPayload = Map<String, dynamic>.from(initialMessage.data);
        debugPrint('[WakeUp] App state=terminated');
        debugPrint('[WakeUp] Stored pending launch payload from FCM');
      }

      // 5. Foreground message listener
      FirebaseMessaging.onMessage.listen((RemoteMessage message) async {
        debugPrint('[WakeUp] FCM received');
        debugPrint('[WakeUp] App state=foreground');

        final notification = message.notification;
        final data = message.data;
        final type = (data['type'] ?? '').toString().toLowerCase();
        final screen = (data['screen'] ?? '').toString().toLowerCase();
        final isWakeUp = (type == 'wake_up' ||
            type == 'wake_up_call' ||
            screen == 'wake_up' ||
            (notification?.title?.toLowerCase().contains('wake-up') ?? false));

        if (isWakeUp) {
          final rawId = data['notification_id'] ?? data['entity_id'] ?? data['id'] ?? '0';
          final notifId = int.tryParse(rawId.toString()) ?? 0;
          debugPrint('[WakeUp] notificationId=$notifId');

          if (!WakeUpManager.canTrigger(notifId)) {
            return;
          }

          final title = data['title'] ?? notification?.title ?? 'URGENT WAKE-UP CALL';
          final body = data['message'] ?? data['body'] ?? notification?.body ?? 'Supervisor has dispatched an urgent wake-up alert.';

          debugPrint('[WakeUp] Opening WakeUpCallScreen');
          debugPrint('[WakeUp] Starting alarm');

          // Directly launch full-screen WakeUpCallScreen without showing normal notification UI first
          await WakeUpManager.startWakeUpAlert(
            notificationId: notifId,
            title: title,
            message: body,
            sentAt: data['sent_at']?.toString(),
          );
          return;
        }

        if (notification != null) {
          // Standard local notification
          _localNotifications.show(
            message.hashCode,
            notification.title ?? 'Secure360 Alert',
            notification.body ?? '',
            const NotificationDetails(
              android: AndroidNotificationDetails(
                _channelId,
                _channelName,
                channelDescription: _channelDesc,
                importance: Importance.high,
                priority: Priority.high,
                icon: '@mipmap/ic_launcher',
                playSound: true,
                enableVibration: true,
              ),
            ),
            payload: jsonEncode(message.data),
          );

          // Show in-app overlay banner while the app is active
          _showInAppBanner(
            title: notification.title ?? 'Secure360 Alert',
            body: notification.body ?? '',
            data: message.data,
          );
        }
      });

      // 5. Background-tap message listener (app running in background)
      FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
        final data = message.data;
        final type = (data['type'] ?? '').toString().toLowerCase();
        final screen = (data['screen'] ?? '').toString().toLowerCase();
        final isWakeUp = (type == 'wake_up' || type == 'wake_up_call' || screen == 'wake_up');
        if (isWakeUp) {
          debugPrint('[WakeUp] App state=background');
        }
        _handleNavigation(message.data, source: 'notification_tap');
      });

      // 6. Token refresh listener
      _messaging.onTokenRefresh.listen((newToken) {
        syncTokenWithBackend(explicitToken: newToken, force: true);
      });

      _initialized = true;
    } catch (e) {
      debugPrint('[NotificationService] Initialization error: $e');
    }
  }

  /// Check if app was launched by tapping a notification or full-screen intent from terminated state
  static Future<void> checkInitialMessage() async {
    // If pending initial payload is waiting for session validation, let SplashScreen dispatch it
    if (_pendingInitialPayload != null) {
      return;
    }

    try {
      // 1. Check if launched via local notification full-screen intent
      final launchDetails = await _localNotifications.getNotificationAppLaunchDetails();
      if (launchDetails != null && launchDetails.didNotificationLaunchApp) {
        final payload = launchDetails.notificationResponse?.payload;
        if (payload != null && payload.isNotEmpty) {
          try {
            final data = jsonDecode(payload);
            if (data is Map) {
              final map = Map<String, dynamic>.from(data);
              debugPrint('[WakeUp] App state=terminated');
              _handleNavigation(map, source: 'initial_message');
              return;
            }
          } catch (_) {}
        }
      }

      // 2. Check if launched via FCM initial message
      final initialMessage = await _messaging.getInitialMessage();
      if (initialMessage != null) {
        debugPrint('[WakeUp] App state=terminated');
        // Small delay to allow the navigator state to be fully mounted
        Future.delayed(const Duration(milliseconds: 500), () {
          _handleNavigation(initialMessage.data, source: 'initial_message');
        });
      }
    } catch (e) {
      debugPrint('[NotificationService] checkInitialMessage error: $e');
    }
  }

  /// Synchronize the current FCM token with the Secure360 backend
  /// (Only executes if the guard is authenticated)
  static Future<void> syncTokenWithBackend({String? explicitToken, bool force = false}) async {
    try {
      final authToken = await ApiService.getToken();
      if (authToken == null || authToken.isEmpty) {
        // Guard not authenticated yet; will sync upon login
        return;
      }

      final fcmToken = explicitToken ?? await _messaging.getToken();
      if (fcmToken == null || fcmToken.isEmpty) return;

      // Safe debug logging: never print full token in production logs
      final tokenPreview = fcmToken.length > 8 ? '${fcmToken.substring(0, 8)}...' : '***';
      debugPrint('[NotificationService] FCM token obtained. Length: ${fcmToken.length}, Prefix: $tokenPreview');

      final prefs = await SharedPreferences.getInstance();
      final lastToken = prefs.getString(_lastFcmTokenKey);
      final lastSyncedUserId = prefs.getString(_lastSyncedUserIdKey);

      final user = await ApiService.getUser();
      final currentUserId = user != null ? (user['id'] ?? user['user_id'])?.toString() : null;

      final tokenChanged = (lastToken != fcmToken);
      final userChanged = (currentUserId != null && lastSyncedUserId != currentUserId);
      final shouldSync = force || tokenChanged || userChanged || lastToken == null;

      if (shouldSync) {
        debugPrint('[NotificationService] Registering device token with backend (force: $force, tokenChanged: $tokenChanged, userChanged: $userChanged, userId: $currentUserId)');
        final response = await ApiService.registerDeviceToken(
          fcmToken,
          deviceType: Platform.isAndroid ? 'android' : 'ios',
          deviceName: Platform.isAndroid ? 'Android Guard Client' : 'iOS Guard Client',
        );

        debugPrint('[NotificationService] POST /guard/device-token status: ${response.statusCode}, success: ${response.success}');

        if (response.success) {
          await prefs.setString(_lastFcmTokenKey, fcmToken);
          if (currentUserId != null) {
            await prefs.setString(_lastSyncedUserIdKey, currentUserId);
          }
        } else {
          debugPrint('[NotificationService] Device token registration failed: ${response.message}');
        }
      }
    } catch (e) {
      debugPrint('[NotificationService] syncTokenWithBackend error: $e');
    }
  }

  /// Deactivate current device token on logout
  static Future<void> unregisterOnLogout() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final lastToken = prefs.getString(_lastFcmTokenKey);

      if (lastToken != null && lastToken.isNotEmpty) {
        await ApiService.removeDeviceToken(lastToken);
      }
      await prefs.remove(_lastFcmTokenKey);
      await prefs.remove(_lastSyncedUserIdKey);
    } catch (e) {
      debugPrint('[NotificationService] unregisterOnLogout error: $e');
    }
  }

  /// Public navigation handler for notification tap events (from notifications screen or banner)
  static void handleNotificationNavigation(Map<String, dynamic> data) {
    _handleNavigation(data, source: 'notification_tap');
  }

  /// Safe navigation handler based on notification data payload
  static Future<void> _handleNavigation(Map<String, dynamic> data, {String source = 'notification_tap'}) async {
    final nav = ApiService.navigatorKey.currentState;
    if (nav == null) return;

    final type = (data['type'] ?? '').toString().toLowerCase();
    final screen = (data['screen'] ?? '').toString().toLowerCase();

    try {
      if (screen == 'wake_up' || type == 'wake_up_call' || type == 'wake_up' || type.contains('wakeup') || type.contains('wake_up')) {
        final rawId = data['notification_id'] ?? data['entity_id'] ?? data['id'] ?? '0';
        final notifId = int.tryParse(rawId.toString()) ?? 0;

        debugPrint('[WakeUp] notification tap notification_id=$notifId');

        if (notifId > 0 && await WakeUpManager.isAcknowledgedAsync(notifId)) {
          debugPrint('[WakeUp] Already acknowledged - ignoring');
          return;
        }

        if (notifId > 0 && WakeUpManager.isScreenVisible(notifId)) {
          debugPrint('[WakeUp] Already handled - ignoring');
          return;
        }

        // Query native alarm state directly:
        final isAlarmActive = await WakeUpManager.isNativeAlarmActive(notifId);
        debugPrint('[WakeUp] NATIVE_STATE notification_id=$notifId active=$isAlarmActive');

        if (!isAlarmActive) {
          debugPrint('[WakeUp] Starting alarm');
          await WakeUpManager.startAlarm(notifId, source: source);
        } else {
          debugPrint('[WakeUp] START_RESULT notification_id=$notifId result=SKIPPED_ALREADY_ACTIVE');
        }

        final title = data['title']?.toString();
        final message = data['message']?.toString();
        final sentAt = data['sent_at']?.toString();

        debugPrint('[WakeUp] Opening WakeUpCallScreen');

        nav.push(
          MaterialPageRoute(
            builder: (_) => WakeUpCallScreen(
              notificationId: notifId,
              title: title,
              message: message,
              sentAt: sentAt,
            ),
          ),
        );
        return;
      } else if (screen == 'home' || type == 'test') {
        nav.pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const HomeDashboardScreen()),
          (route) => false,
        );
      } else if (screen == 'attendance' || type.contains('attendance') || type.contains('checkin') || type.contains('checkout')) {
        nav.push(MaterialPageRoute(builder: (_) => const AttendanceHistoryScreen()));
      } else if (screen == 'assignment_details' || screen == 'shift_details' || screen == 'contract' || type.contains('contract') || type.contains('duty') || type.contains('shift')) {
        nav.pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const HomeDashboardScreen()),
          (route) => false,
        );
      } else if (screen == 'notifications' || type.contains('alert') || type == 'notification' || type == 'system') {
        nav.push(MaterialPageRoute(builder: (_) => const NotificationsScreen()));
      } else {
        // Safe fallback to NotificationsScreen
        nav.push(MaterialPageRoute(builder: (_) => const NotificationsScreen()));
      }
    } catch (e) {
      debugPrint('[NotificationService] Navigation error: $e');
    }
  }

  // ---------------------------------------------------------------------------
  // In-app foreground notification banner
  // ---------------------------------------------------------------------------

  /// Shows a slide-in banner from the top of the screen when a foreground
  /// push notification arrives. Auto-dismisses after [_bannerDuration].
  static const Duration _bannerDuration = Duration(seconds: 4);
  static const Duration _animationDuration = Duration(milliseconds: 350);

  static void _showInAppBanner({
    required String title,
    required String body,
    required Map<String, dynamic> data,
  }) {
    final overlayState = ApiService.navigatorKey.currentState?.overlay;
    if (overlayState == null) return;

    // Dismiss any existing banner first
    _dismissCurrentBanner();

    late final OverlayEntry entry;
    entry = OverlayEntry(
      builder: (_) => _InAppNotificationBanner(
        title: title,
        body: body,
        onTap: () {
          _dismissCurrentBanner();
          _handleNavigation(data);
        },
        onDismiss: _dismissCurrentBanner,
      ),
    );

    _currentBannerEntry = entry;
    overlayState.insert(entry);

    // Auto-dismiss after duration
    Future.delayed(_bannerDuration, _dismissCurrentBanner);
  }

  static void _dismissCurrentBanner() {
    _currentBannerEntry?.remove();
    _currentBannerEntry = null;
  }
}

// =============================================================================
// In-App Notification Banner Widget
// =============================================================================

class _InAppNotificationBanner extends StatefulWidget {
  final String title;
  final String body;
  final VoidCallback onTap;
  final VoidCallback onDismiss;

  const _InAppNotificationBanner({
    required this.title,
    required this.body,
    required this.onTap,
    required this.onDismiss,
  });

  @override
  State<_InAppNotificationBanner> createState() => _InAppNotificationBannerState();
}

class _InAppNotificationBannerState extends State<_InAppNotificationBanner>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<Offset> _slideAnimation;
  late final Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: NotificationService._animationDuration,
    );
    _slideAnimation = Tween<Offset>(
      begin: const Offset(0, -1.2),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic));
    _fadeAnimation = Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeOut),
    );
    _controller.forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _dismiss() async {
    await _controller.reverse();
    widget.onDismiss();
  }

  @override
  Widget build(BuildContext context) {
    return Positioned(
      top: 0,
      left: 0,
      right: 0,
      child: SafeArea(
        child: FadeTransition(
          opacity: _fadeAnimation,
          child: SlideTransition(
            position: _slideAnimation,
            child: Dismissible(
              key: const Key('secure360_notif_banner'),
              direction: DismissDirection.up,
              onDismissed: (_) => widget.onDismiss(),
              child: GestureDetector(
                onTap: widget.onTap,
                child: Container(
                  margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(14),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.25),
                        blurRadius: 16,
                        offset: const Offset(0, 6),
                      ),
                    ],
                  ),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Icon
                        Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: const Color(0xFF2563EB).withValues(alpha: 0.2),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Icon(
                            Icons.notifications_active,
                            color: Color(0xFF60A5FA),
                            size: 20,
                          ),
                        ),
                        const SizedBox(width: 12),
                        // Title + Body
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                widget.title,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 14,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                              if (widget.body.isNotEmpty) ...[
                                const SizedBox(height: 3),
                                Text(
                                  widget.body,
                                  style: const TextStyle(
                                    color: Color(0xFFCBD5E1),
                                    fontSize: 12.5,
                                  ),
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ],
                          ),
                        ),
                        // Dismiss button
                        GestureDetector(
                          onTap: _dismiss,
                          child: const Padding(
                            padding: EdgeInsets.only(left: 8, top: 2),
                            child: Icon(Icons.close, color: Color(0xFF94A3B8), size: 18),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
