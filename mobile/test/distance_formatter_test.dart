import 'package:flutter_test/flutter_test.dart';
import 'package:secure360_mobile/core/utils/distance_formatter.dart';

void main() {
  group('DistanceFormatter Tests', () {
    test('Formats distances below 1000 meters as "X m"', () {
      expect(DistanceFormatter.formatDistance(0), equals('0 m'));
      expect(DistanceFormatter.formatDistance(42.3), equals('42 m'));
      expect(DistanceFormatter.formatDistance(150), equals('150 m'));
      expect(DistanceFormatter.formatDistance(999.4), equals('999 m'));
    });

    test('Formats distances at or above 1000 meters as "X.X km"', () {
      expect(DistanceFormatter.formatDistance(1000), equals('1.0 km'));
      expect(DistanceFormatter.formatDistance(1200), equals('1.2 km'));
      expect(DistanceFormatter.formatDistance(5600), equals('5.6 km'));
      expect(DistanceFormatter.formatDistance(326399), equals('326.4 km'));
    });

    test('Handles null and negative distances gracefully', () {
      expect(DistanceFormatter.formatDistance(null), equals('--'));
      expect(DistanceFormatter.formatDistance(-5), equals('0 m'));
    });

    test('Evaluates Within Assigned Area when distance <= allowedRadius', () {
      final status = DistanceFormatter.evaluateStatus(
        isLocating: false,
        locationError: null,
        distanceMeters: 42.0,
        accuracy: 10.0,
        siteName: 'Metro Plaza - Main Gate',
        allowedRadius: 150.0,
      );
      expect(status.state, equals(GeofenceStatusState.withinArea));
      expect(status.title, equals('Within Assigned Area'));
      expect(status.subtitle, equals('42 m from Metro Plaza - Main Gate'));
      expect(status.isWithin, isTrue);
    });

    test('Evaluates Outside Assigned Area when distance > allowedRadius', () {
      final status = DistanceFormatter.evaluateStatus(
        isLocating: false,
        locationError: null,
        distanceMeters: 326.0,
        accuracy: 15.0,
        siteName: 'assigned post',
        allowedRadius: 150.0,
      );
      expect(status.state, equals(GeofenceStatusState.outsideArea));
      expect(status.title, equals('Outside Assigned Area'));
      expect(status.subtitle, equals('326 m from assigned post'));
      expect(status.isWithin, isFalse);
    });

    test('Evaluates Checking Location when isLocating is true and distance is null', () {
      final status = DistanceFormatter.evaluateStatus(
        isLocating: true,
        locationError: null,
        distanceMeters: null,
        accuracy: null,
        siteName: 'Metro Plaza',
      );
      expect(status.state, equals(GeofenceStatusState.checking));
      expect(status.title, equals('Checking Location...'));
      expect(status.isWithin, isFalse);
    });

    test('Evaluates Location Unavailable when locationError is set', () {
      final status = DistanceFormatter.evaluateStatus(
        isLocating: false,
        locationError: 'GPS service disabled',
        distanceMeters: null,
        accuracy: null,
        siteName: 'Metro Plaza',
      );
      expect(status.state, equals(GeofenceStatusState.unavailable));
      expect(status.title, equals('Location Unavailable'));
      expect(status.isWithin, isFalse);
    });

    test('Evaluates GPS Accuracy Too Low when accuracy exceeds threshold', () {
      final status = DistanceFormatter.evaluateStatus(
        isLocating: false,
        locationError: null,
        distanceMeters: 50.0,
        accuracy: 150.0,
        siteName: 'Metro Plaza',
      );
      expect(status.state, equals(GeofenceStatusState.lowAccuracy));
      expect(status.title, equals('GPS Accuracy Too Low'));
      expect(status.isWithin, isFalse);
    });
  });
}
