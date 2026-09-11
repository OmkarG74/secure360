import 'package:flutter_test/flutter_test.dart';
import 'package:secure360_mobile/core/utils/time_formatter.dart';

void main() {
  group('TimeFormatter Tests', () {
    test('Converts 08:00:00 to 8:00 AM', () {
      expect(TimeFormatter.formatTime('08:00:00'), equals('8:00 AM'));
    });

    test('Converts 16:00:00 to 4:00 PM', () {
      expect(TimeFormatter.formatTime('16:00:00'), equals('4:00 PM'));
    });

    test('Converts 17:32:03 to 5:32:03 PM (with non-zero seconds)', () {
      expect(TimeFormatter.formatTime('17:32:03'), equals('5:32:03 PM'));
    });

    test('Handles midnight (00:00:00 -> 12:00 AM)', () {
      expect(TimeFormatter.formatTime('00:00:00'), equals('12:00 AM'));
    });

    test('Handles noon (12:00:00 -> 12:00 PM)', () {
      expect(TimeFormatter.formatTime('12:00:00'), equals('12:00 PM'));
    });

    test('Handles midnight with seconds (00:15:30 -> 12:15:30 AM)', () {
      expect(TimeFormatter.formatTime('00:15:30'), equals('12:15:30 AM'));
    });

    test('Avoids double conversion if already formatted', () {
      expect(TimeFormatter.formatTime('8:00 AM'), equals('8:00 AM'));
      expect(TimeFormatter.formatTime('4:00 PM'), equals('4:00 PM'));
    });

    test('Converts datetime string preserving date', () {
      expect(
        TimeFormatter.formatDateTime('2026-09-11 17:32:03'),
        equals('2026-09-11 5:32:03 PM'),
      );
      expect(
        TimeFormatter.formatDateTime('2026-09-11 08:00:00'),
        equals('2026-09-11 8:00:00 AM'),
      );
      expect(
        TimeFormatter.formatDateTime('2026-09-11 08:00:00', preserveSeconds: false),
        equals('2026-09-11 8:00 AM'),
      );
    });

    test('Formats time range (shift timing)', () {
      expect(
        TimeFormatter.formatTimeRange('08:00:00', '16:00:00'),
        equals('8:00 AM - 4:00 PM'),
      );
    });
  });
}
