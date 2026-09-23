import 'package:flutter/material.dart';
import '../../../core/services/wake_up_manager.dart';
import '../../../core/utils/time_formatter.dart';

/// Full-screen high-priority Wake-Up Call Alert Screen
///
/// Features:
/// - Continuous looped alarm audio coordinated by WakeUpManager
/// - Urgent pulsating visual animation
/// - Immediate audio silencing upon [ ACKNOWLEDGE ]
/// - Permanent acknowledgement tracking in local SharedPreferences and MySQL
class WakeUpCallScreen extends StatefulWidget {
  final int notificationId;
  final String? title;
  final String? message;
  final String? sentAt;

  const WakeUpCallScreen({
    super.key,
    required this.notificationId,
    this.title,
    this.message,
    this.sentAt,
  });

  @override
  State<WakeUpCallScreen> createState() => _WakeUpCallScreenState();
}

class _WakeUpCallScreenState extends State<WakeUpCallScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulseController;
  late final Animation<double> _pulseAnimation;

  bool _isAcknowledging = false;

  @override
  void initState() {
    super.initState();
    WakeUpManager.setScreenVisible(widget.notificationId, true);

    // 1. Setup Pulsing Animation
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1000),
    )..repeat(reverse: true);

    _pulseAnimation = Tween<double>(begin: 0.92, end: 1.15).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
    );
  }

  Future<void> _onAcknowledge() async {
    if (_isAcknowledging) return;

    setState(() {
      _isAcknowledging = true;
    });

    // 1. Stop native alarm audio, cancel Android notification, mark acknowledged immediately
    await WakeUpManager.acknowledge(widget.notificationId);

    // 2. Close screen
    if (mounted) {
      Navigator.of(context).pop(true);
    }
  }

  @override
  void dispose() {
    WakeUpManager.setScreenVisible(widget.notificationId, false);
    _pulseController.dispose();
    if (!_isAcknowledging) {
      WakeUpManager.stopAlarmOnly(notificationId: widget.notificationId, source: 'screen_dispose');
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final now = DateTime.now();
    final timeFormatted = TimeFormatter.formatDateTime(now);

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) return;
        await WakeUpManager.stopAlarmOnly();
        if (context.mounted) {
          Navigator.of(context).pop();
        }
      },
      child: Scaffold(
        backgroundColor: const Color(0xFF0F172A), // Dark slate / Night-shift theme
        body: SafeArea(
          child: Column(
            children: [
              // Top Urgent Banner
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
                decoration: const BoxDecoration(
                  color: Color(0xFFDC2626),
                  boxShadow: [
                    BoxShadow(
                      color: Color(0x66DC2626),
                      blurRadius: 16,
                      offset: Offset(0, 4),
                    ),
                  ],
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.warning_amber_rounded, color: Colors.white, size: 22),
                    SizedBox(width: 8),
                    Text(
                      'URGENT SUPERVISOR ALERT',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.5,
                      ),
                    ),
                  ],
                ),
              ),

              // Main Body Content
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      // Pulsating Alarm Bell Icon
                      ScaleTransition(
                        scale: _pulseAnimation,
                        child: Container(
                          width: 130,
                          height: 130,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: const Color(0x2EDC2626),
                            border: Border.all(
                              color: const Color(0xFFDC2626),
                              width: 3,
                            ),
                            boxShadow: const [
                              BoxShadow(
                                color: Color(0x66DC2626),
                                blurRadius: 30,
                                spreadRadius: 8,
                              ),
                            ],
                          ),
                          child: const Center(
                            child: Icon(
                              Icons.notifications_active_rounded,
                              size: 64,
                              color: Color(0xFFEF4444),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 36),

                      // Title
                      Text(
                        widget.title ?? 'WAKE-UP CALL',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.w900,
                          color: Colors.white,
                          letterSpacing: 1.2,
                        ),
                      ),
                      const SizedBox(height: 10),

                      // Current Time / Alert Time
                      Container(
                        padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 14),
                        decoration: BoxDecoration(
                          color: const Color(0xFF1E293B),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: const Color(0xFF334155)),
                        ),
                        child: Text(
                          timeFormatted,
                          style: const TextStyle(
                            color: Color(0xFF94A3B8),
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Message Box
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(18),
                        decoration: BoxDecoration(
                          color: const Color(0xFF1E293B),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: const Color(0x66DC2626),
                          ),
                        ),
                        child: Column(
                          children: [
                            Text(
                              widget.message ??
                                  'Your supervisor has sent an urgent wake-up call to verify that you are alert, awake, and actively on duty.',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontSize: 15,
                                color: Color(0xFFE2E8F0),
                                height: 1.45,
                              ),
                            ),
                            const SizedBox(height: 12),
                            const Text(
                              'Tap ACKNOWLEDGE below to stop the alarm and notify your supervisor.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 12,
                                color: Color(0xFF94A3B8),
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              // Bottom [ ACKNOWLEDGE ] Action Button
              Padding(
                padding: const EdgeInsets.all(24),
                child: SizedBox(
                  width: double.infinity,
                  height: 60,
                  child: ElevatedButton(
                    onPressed: _isAcknowledging ? null : _onAcknowledge,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF16A34A), // Rich green confirmation
                      foregroundColor: Colors.white,
                      elevation: 8,
                      shadowColor: const Color(0x6616A34A),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    child: _isAcknowledging
                        ? const Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                ),
                              ),
                              SizedBox(width: 12),
                              Text(
                                'CONFIRMING...',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 1.2,
                                ),
                              ),
                            ],
                          )
                        : const Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.check_circle_rounded, size: 26),
                              SizedBox(width: 10),
                              Text(
                                'ACKNOWLEDGE',
                                style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 1.5,
                                ),
                              ),
                            ],
                          ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
