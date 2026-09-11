import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';
import 'api_service.dart';

/// Service managing real GPS location capture and periodic live tracking
class LocationService {
  static Timer? _telemetryTimer;
  static bool _isTracking = false;

  static bool get isTracking => _isTracking;

  /// Fetch current GPS position with full permission and error handling
  static Future<Position?> getCurrentLocation({
    LocationAccuracy accuracy = LocationAccuracy.high,
    Duration timeout = const Duration(seconds: 12),
  }) async {
    // 1. Check if location services are enabled
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw const LocationServiceDisabledException();
    }

    // 2. Check and request permissions
    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        throw Exception('Location permission was denied. Please allow location access to check in.');
      }
    }

    if (permission == LocationPermission.deniedForever) {
      throw Exception(
        'Location permission is permanently denied in device settings. Please enable location permissions for Secure360 in app settings.',
      );
    }

    // 3. Attempt to get current position
    try {
      return await Geolocator.getCurrentPosition(
        desiredAccuracy: accuracy,
        timeLimit: timeout,
      );
    } catch (e) {
      // Fallback to last known position if current timed out
      final lastKnown = await Geolocator.getLastKnownPosition();
      if (lastKnown != null) {
        return lastKnown;
      }
      rethrow;
    }
  }

  /// Start periodic live location telemetry (Active Duty tracking)
  /// Posts telemetry to POST /api/v1/guard/location every [interval]
  static void startLiveTracking({
    Duration interval = const Duration(seconds: 30),
  }) {
    if (_isTracking) return;
    _isTracking = true;
    debugPrint('[Secure360 Telemetry] Live tracking started (interval: ${interval.inSeconds}s)');

    // Immediate first ping
    _sendTelemetryPing();

    _telemetryTimer?.cancel();
    _telemetryTimer = Timer.periodic(interval, (_) {
      _sendTelemetryPing();
    });
  }

  /// Stop live location telemetry (on Checkout or Logout)
  static void stopLiveTracking() {
    if (!_isTracking) return;
    _isTracking = false;
    _telemetryTimer?.cancel();
    _telemetryTimer = null;
    debugPrint('[Secure360 Telemetry] Live tracking stopped');
  }

  /// Internal telemetry dispatch to backend API
  static Future<void> _sendTelemetryPing() async {
    if (!_isTracking) return;

    try {
      final position = await getCurrentLocation(
        accuracy: LocationAccuracy.medium,
        timeout: const Duration(seconds: 8),
      );

      if (position != null && _isTracking) {
        final response = await ApiService.submitLocation(
          latitude: position.latitude,
          longitude: position.longitude,
          accuracy: position.accuracy,
          speed: position.speed,
          heading: position.heading,
          activityType: 'patrol',
        );

        if (kDebugMode && response.success) {
          debugPrint('[Secure360 Telemetry] Ping sent: (${position.latitude}, ${position.longitude})');
        }
      }
    } catch (e) {
      // Silently handle telemetry network or GPS glitches without disrupting guard experience
      debugPrint('[Secure360 Telemetry] Ping failed: $e');
    }
  }
}
