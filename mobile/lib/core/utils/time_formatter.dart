class TimeFormatter {
  /// Formats a time string (e.g. "08:00:00", "16:00", "17:32:03") to 12-hour AM/PM format.
  /// Examples:
  ///   "08:00:00" -> "8:00 AM"
  ///   "16:00:00" -> "4:00 PM"
  ///   "17:32:03" -> "5:32:03 PM"
  ///   "00:00:00" -> "12:00 AM"
  ///   "12:00:00" -> "12:00 PM"
  ///   "00:15:30" -> "12:15:30 AM"
  ///   "12:45"    -> "12:45 PM"
  static String formatTime(String? timeStr) {
    if (timeStr == null || timeStr.trim().isEmpty) return '';
    final trimmed = timeStr.trim();

    // Avoid double conversion if already formatted
    if (trimmed.toUpperCase().contains('AM') || trimmed.toUpperCase().contains('PM')) {
      return trimmed;
    }

    final parts = trimmed.split(':');
    if (parts.length < 2) return trimmed;

    final hour = int.tryParse(parts[0]);
    final minute = int.tryParse(parts[1]);
    if (hour == null || minute == null) return trimmed;

    final period = hour >= 12 ? 'PM' : 'AM';
    var hour12 = hour % 12;
    if (hour12 == 0) hour12 = 12;

    final minStr = minute.toString().padLeft(2, '0');

    // If seconds are present and not "00", retain seconds: e.g. "17:32:03" -> "5:32:03 PM"
    if (parts.length >= 3) {
      final secPart = parts[2].trim();
      final sec = int.tryParse(secPart.split('.')[0]); // ignore milliseconds if any
      if (sec != null && sec > 0) {
        final secStr = sec.toString().padLeft(2, '0');
        return '$hour12:$minStr:$secStr $period';
      }
    }

    // Default time format (no seconds or seconds were 00)
    return '$hour12:$minStr $period';
  }

  /// Formats a datetime string (e.g. "2026-09-11 17:32:03" or "2026-09-11T08:00:00")
  /// Preserves the date and converts the time portion to 12-hour format with AM/PM.
  /// Examples:
  ///   "2026-09-11 17:32:03" -> "2026-09-11 5:32:03 PM"
  ///   "2026-09-11 08:00:00" -> "2026-09-11 8:00:00 AM" (or "2026-09-11 8:00 AM" if clean)
  static String formatDateTime(String? dateTimeStr, {bool preserveSeconds = true}) {
    if (dateTimeStr == null || dateTimeStr.trim().isEmpty) return '';
    final trimmed = dateTimeStr.trim();

    // Avoid double conversion
    if (trimmed.toUpperCase().contains('AM') || trimmed.toUpperCase().contains('PM')) {
      return trimmed;
    }

    // Split by space or 'T'
    String datePart = '';
    String timePart = '';

    if (trimmed.contains(' ')) {
      final split = trimmed.split(' ');
      datePart = split[0];
      timePart = split.sublist(1).join(' ');
    } else if (trimmed.contains('T')) {
      final split = trimmed.split('T');
      datePart = split[0];
      timePart = split.sublist(1).join('T');
    } else {
      // Pure time string
      return formatTime(trimmed);
    }

    // Format time part
    final timeParts = timePart.split(':');
    if (timeParts.length < 2) return trimmed;

    final hour = int.tryParse(timeParts[0]);
    final minute = int.tryParse(timeParts[1]);
    if (hour == null || minute == null) return trimmed;

    final period = hour >= 12 ? 'PM' : 'AM';
    var hour12 = hour % 12;
    if (hour12 == 0) hour12 = 12;

    final minStr = minute.toString().padLeft(2, '0');

    if (timeParts.length >= 3 && preserveSeconds) {
      final secPart = timeParts[2].trim().split('.')[0];
      final sec = int.tryParse(secPart);
      if (sec != null) {
        final secStr = sec.toString().padLeft(2, '0');
        return '$datePart $hour12:$minStr:$secStr $period';
      }
    }

    return '$datePart $hour12:$minStr $period';
  }

  /// Formats a time range (e.g. "08:00:00", "16:00:00" -> "8:00 AM - 4:00 PM")
  static String formatTimeRange(String? start, String? end) {
    final startFormatted = formatTime(start);
    final endFormatted = formatTime(end);
    if (startFormatted.isEmpty && endFormatted.isEmpty) return '';
    if (startFormatted.isEmpty) return endFormatted;
    if (endFormatted.isEmpty) return startFormatted;
    return '$startFormatted - $endFormatted';
  }
}
