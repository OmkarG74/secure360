import 'package:flutter_test/flutter_test.dart';
import 'package:secure360_mobile/core/utils/time_formatter.dart';

void main() {
  group('TimeFormatter Global Standard Tests', () {
    test('Date only: dd-MMM-yy', () {
      expect(TimeFormatter.formatDate('2026-09-18'), equals('18-Sep-26'));
      expect(TimeFormatter.formatDate('2026-01-01'), equals('01-Jan-26'));
      expect(TimeFormatter.formatDate('2026-12-31'), equals('31-Dec-26'));
      expect(TimeFormatter.formatDate(DateTime(2026, 9, 18)), equals('18-Sep-26'));
      expect(TimeFormatter.formatDate(null), equals('—'));
    });

    test('Date and time: dd-MMM-yy hh:mm AM/PM', () {
      expect(
        TimeFormatter.formatDateTime('2026-09-18 17:45:00'),
        equals('18-Sep-26 05:45 PM'),
      );
      expect(
        TimeFormatter.formatDateTime('2026-09-18 08:00:00'),
        equals('18-Sep-26 08:00 AM'),
      );
      expect(
        TimeFormatter.formatDateTime('2026-01-01 00:00:00'),
        equals('01-Jan-26 12:00 AM'),
      );
      expect(
        TimeFormatter.formatDateTime('2026-12-31 12:00:00'),
        equals('31-Dec-26 12:00 PM'),
      );
      expect(TimeFormatter.formatDateTime(null), equals('—'));
    });

    test('Time only: hh:mm AM/PM', () {
      expect(TimeFormatter.formatTime('08:00:00'), equals('08:00 AM'));
      expect(TimeFormatter.formatTime('16:00:00'), equals('04:00 PM'));
      expect(TimeFormatter.formatTime('00:00:00'), equals('12:00 AM'));
      expect(TimeFormatter.formatTime('12:00:00'), equals('12:00 PM'));
      expect(TimeFormatter.formatTime('17:45'), equals('05:45 PM'));
      expect(TimeFormatter.formatTime('08:00 AM'), equals('08:00 AM'));
      expect(TimeFormatter.formatTime('04:00 PM'), equals('04:00 PM'));
      expect(TimeFormatter.formatTime(null), equals('—'));
    });

    test('Date range: dd-MMM-yy → dd-MMM-yy', () {
      expect(
        TimeFormatter.formatDateRange('2026-01-01', '2026-12-31'),
        equals('01-Jan-26 → 31-Dec-26'),
      );
      expect(
        TimeFormatter.formatDateRange('2026-01-01', null),
        equals('01-Jan-26 → Ongoing'),
      );
    });

    test('Shift time range: hh:mm AM/PM - hh:mm AM/PM', () {
      expect(
        TimeFormatter.formatTimeRange('08:00:00', '16:00:00'),
        equals('08:00 AM - 04:00 PM'),
      );
      expect(
        TimeFormatter.formatTimeRange('22:00:00', '06:00:00'),
        equals('10:00 PM - 06:00 AM'),
      );
    });
  });
}
