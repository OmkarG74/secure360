import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';

/// Geofence and distance status evaluator for Secure360 Guard Mobile App
enum GeofenceStatusState {
  checking,
  unavailable,
  lowAccuracy,
  withinArea,
  outsideArea,
}

class GeofenceStatusInfo {
  final GeofenceStatusState state;
  final String title;
  final String subtitle;
  final bool isWithin;
  final Color primaryColor;
  final Color backgroundColor;
  final Color borderColor;
  final IconData icon;

  const GeofenceStatusInfo({
    required this.state,
    required this.title,
    required this.subtitle,
    required this.isWithin,
    required this.primaryColor,
    required this.backgroundColor,
    required this.borderColor,
    required this.icon,
  });
}

class DistanceFormatter {
  /// Calculate distance between two coordinates in meters
  static double distanceBetweenMeters(
    double startLatitude,
    double startLongitude,
    double endLatitude,
    double endLongitude,
  ) {
    return Geolocator.distanceBetween(startLatitude, startLongitude, endLatitude, endLongitude);
  }

  /// Format distance safely and legibly:
  /// - Below 1000 meters: e.g. "42 m"
  /// - 1000 meters and above: e.g. "1.2 km" or "326.4 km"
  /// Never displays raw unformatted numbers like "326399m".
  static String formatDistance(double? meters) {
    if (meters == null) return '--';
    if (meters < 0) return '0 m';

    if (meters < 1000) {
      return '${meters.round()} m';
    }

    final km = meters / 1000.0;
    if (km >= 100) {
      return '${km.toStringAsFixed(1)} km';
    }
    return '${km.toStringAsFixed(1)} km';
  }

  /// Evaluates the complete geofence status based on GPS fix, accuracy, and distance.
  static GeofenceStatusInfo evaluateStatus({
    required bool isLocating,
    required String? locationError,
    required double? distanceMeters,
    required double? accuracy,
    required String siteName,
    double allowedRadius = 150.0,
  }) {
    if (isLocating && distanceMeters == null) {
      return const GeofenceStatusInfo(
        state: GeofenceStatusState.checking,
        title: 'Checking Location...',
        subtitle: 'Acquiring device GPS satellite fix...',
        isWithin: false,
        primaryColor: Color(0xFF2563EB),
        backgroundColor: Color(0xFFEFF6FF),
        borderColor: Color(0xFFBFDBFE),
        icon: Icons.sync,
      );
    }

    if (locationError != null && distanceMeters == null) {
      return GeofenceStatusInfo(
        state: GeofenceStatusState.unavailable,
        title: 'Location Unavailable',
        subtitle: locationError,
        isWithin: false,
        primaryColor: const Color(0xFFDC2626),
        backgroundColor: const Color(0xFFFEF2F2),
        borderColor: const Color(0xFFFECACA),
        icon: Icons.location_off,
      );
    }

    if (accuracy != null && accuracy > 120.0) {
      return GeofenceStatusInfo(
        state: GeofenceStatusState.lowAccuracy,
        title: 'GPS Accuracy Too Low',
        subtitle: 'Signal accuracy: ±${accuracy.round()} m. Move outdoors for a clearer fix.',
        isWithin: false,
        primaryColor: const Color(0xFFD97706),
        backgroundColor: const Color(0xFFFFFBEB),
        borderColor: const Color(0xFFFDE68A),
        icon: Icons.gps_not_fixed,
      );
    }

    if (distanceMeters != null) {
      final formattedDist = formatDistance(distanceMeters);
      final isWithin = distanceMeters <= allowedRadius;

      if (isWithin) {
        return GeofenceStatusInfo(
          state: GeofenceStatusState.withinArea,
          title: 'Within Assigned Area',
          subtitle: '$formattedDist from $siteName',
          isWithin: true,
          primaryColor: const Color(0xFF15803D),
          backgroundColor: const Color(0xFFF0FDF4),
          borderColor: const Color(0xFFBBF7D0),
          icon: Icons.check_circle,
        );
      } else {
        return GeofenceStatusInfo(
          state: GeofenceStatusState.outsideArea,
          title: 'Outside Assigned Area',
          subtitle: '$formattedDist from $siteName',
          isWithin: false,
          primaryColor: const Color(0xFFB91C1C),
          backgroundColor: const Color(0xFFFEF2F2),
          borderColor: const Color(0xFFFECACA),
          icon: Icons.warning_amber_rounded,
        );
      }
    }

    return const GeofenceStatusInfo(
      state: GeofenceStatusState.unavailable,
      title: 'Location Unavailable',
      subtitle: 'Unable to resolve device position against assigned post.',
      isWithin: false,
      primaryColor: Color(0xFF64748B),
      backgroundColor: Color(0xFFF1F5F9),
      borderColor: Color(0xFFE2E8F0),
      icon: Icons.location_disabled,
    );
  }
}
