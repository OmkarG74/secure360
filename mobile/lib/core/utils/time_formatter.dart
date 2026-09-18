import 'package:intl/intl.dart';

/// Centralized Date & Time Formatter for Secure360 Mobile Guard Application
///
/// Follows Global Display Standards:
/// - Date only: dd-MMM-yy (e.g. 18-Sep-26)
/// - Date & Time: dd-MMM-yy hh:mm AM/PM (e.g. 18-Sep-26 05:45 PM)
/// - Time only: hh:mm AM/PM (e.g. 05:45 PM, 08:00 AM)
/// - Date range: dd-MMM-yy → dd-MMM-yy (e.g. 01-Jan-26 → 31-Dec-26)
/// - Shift range: hh:mm AM/PM - hh:mm AM/PM (e.g. 08:00 AM - 04:00 PM)
class TimeFormatter {
  static final DateFormat _dateFormat = DateFormat('dd-MMM-yy');
  static final DateFormat _dateTimeFormat = DateFormat('dd-MMM-yy hh:mm a');
  static final DateFormat _timeFormat = DateFormat('hh:mm a');

  /// Parse dynamic input (String, DateTime, int) into DateTime safely.
  static DateTime? parseDateTime(dynamic input) {
    if (input == null) return null;
    if (input is DateTime) return input;
    if (input is int) return DateTime.fromMillisecondsSinceEpoch(input);

    if (input is String) {
      final trimmed = input.trim();
      if (trimmed.isEmpty || trimmed == '0000-00-00' || trimmed == '0000-00-00 00:00:00') {
        return null;
      }

      // If it's pure time e.g. "08:00:00" or "16:00"
      if (RegExp(r'^\d{1,2}:\d{2}(:\d{2})?$').hasMatch(trimmed)) {
        try {
          return DateTime.parse('1970-01-01 $trimmed');
        } catch (_) {}
      }

      // Try standard DateTime.tryParse (handles ISO 8601 and YYYY-MM-DD HH:MM:SS)
      final parsed = DateTime.tryParse(trimmed);
      if (parsed != null) return parsed;

      // Try common alternative separators
      try {
        final normalized = trimmed.replaceAll('/', '-');
        final retry = DateTime.tryParse(normalized);
        if (retry != null) return retry;
      } catch (_) {}
    }

    return null;
  }

  /// Formats date only: dd-MMM-yy (e.g. 18-Sep-26)
  static String formatDate(dynamic dateInput, {String fallback = '—'}) {
    final dt = parseDateTime(dateInput);
    if (dt == null) return fallback;
    return _dateFormat.format(dt);
  }

  /// Formats date and time: dd-MMM-yy hh:mm AM/PM (e.g. 18-Sep-26 05:45 PM)
  static String formatDateTime(dynamic dateTimeInput, {String fallback = '—'}) {
    final dt = parseDateTime(dateTimeInput);
    if (dt == null) return fallback;
    final formatted = _dateTimeFormat.format(dt);
    return _uppercaseAmPm(formatted);
  }

  /// Formats time only: hh:mm AM/PM (e.g. 05:45 PM, 08:00 AM)
  static String formatTime(dynamic timeInput, {String fallback = '—'}) {
    if (timeInput == null) return fallback;
    final trimmed = timeInput.toString().trim();
    if (trimmed.isEmpty) return fallback;

    // Avoid double conversion if already formatted with AM/PM
    if (trimmed.toUpperCase().contains('AM') || trimmed.toUpperCase().contains('PM')) {
      return _uppercaseAmPm(trimmed);
    }

    final dt = parseDateTime(trimmed);
    if (dt != null) {
      return _uppercaseAmPm(_timeFormat.format(dt));
    }

    // Fallback manual parse for HH:MM:SS or HH:MM
    final parts = trimmed.split(':');
    if (parts.length >= 2) {
      final hour = int.tryParse(parts[0]);
      final minute = int.tryParse(parts[1]);
      if (hour != null && minute != null) {
        final period = hour >= 12 ? 'PM' : 'AM';
        var hour12 = hour % 12;
        if (hour12 == 0) hour12 = 12;
        final hrStr = hour12.toString().padLeft(2, '0');
        final minStr = minute.toString().padLeft(2, '0');
        return '$hrStr:$minStr $period';
      }
    }

    return trimmed;
  }

  /// Formats date range: dd-MMM-yy → dd-MMM-yy (e.g. 01-Jan-26 → 31-Dec-26)
  static String formatDateRange(dynamic start, dynamic end, {String fallback = '—'}) {
    final startStr = formatDate(start, fallback: '');
    final endStr = end != null ? formatDate(end, fallback: '') : 'Ongoing';
    if (startStr.isEmpty && (endStr.isEmpty || endStr == 'Ongoing')) return fallback;
    if (startStr.isEmpty) return endStr;
    return '$startStr → $endStr';
  }

  /// Formats shift time range: hh:mm AM/PM - hh:mm AM/PM (e.g. 08:00 AM - 04:00 PM)
  static String formatTimeRange(dynamic start, dynamic end, {String fallback = '—'}) {
    final startFormatted = formatTime(start, fallback: '');
    final endFormatted = formatTime(end, fallback: '');
    if (startFormatted.isEmpty && endFormatted.isEmpty) return fallback;
    if (startFormatted.isEmpty) return endFormatted;
    if (endFormatted.isEmpty) return startFormatted;
    return '$startFormatted - $endFormatted';
  }

  /// Ensures AM/PM indicators are displayed in uppercase
  static String _uppercaseAmPm(String text) {
    return text.replaceAll('am', 'AM').replaceAll('pm', 'PM');
  }
}
