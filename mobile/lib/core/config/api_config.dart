/// Secure360 Mobile - Central API & Network Configuration
/// 
/// Communicates EXCLUSIVELY with the Core PHP REST APIs.
/// Flutter never connects directly to MySQL.
class ApiConfig {
  /// Default production API base URL on Railway:
  static const String _defaultBaseUrl = 'https://secure360-production.up.railway.app/api/v1';

  /// Base host configuration
  ///
  /// Connects by default to Railway Production:
  ///   https://secure360-production.up.railway.app/api/v1
  ///
  /// Can be overridden dynamically via `--dart-define` if needed:
  /// - Local WAMP / Android Emulator:
  ///     flutter run --dart-define=API_BASE_URL=http://10.0.2.2/Secure360/api/v1
  /// - Local Wi-Fi Device:
  ///     flutter run --dart-define=API_BASE_URL=http://192.168.1.100/Secure360/api/v1
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: _defaultBaseUrl,
  );

  // Auth Endpoints
  static const String loginEndpoint = '/auth/guard/login';
  static const String logoutEndpoint = '/guard/logout';
  static const String profileEndpoint = '/guard/profile';

  // Duty & Site Endpoints
  static const String assignmentsEndpoint = '/guard/assignments';
  static String siteDetailsEndpoint(int siteId) => '/guard/sites/$siteId';

  // Attendance Endpoints
  static const String checkInEndpoint = '/guard/attendance/check-in';
  static const String checkOutEndpoint = '/guard/attendance/check-out';
  static const String historyEndpoint = '/guard/attendance/history';

  // Telemetry & Field Endpoints
  static const String locationTelemetryEndpoint = '/guard/location';
  static const String selfieUploadEndpoint = '/guard/selfie';
  static const String notificationsEndpoint = '/guard/notifications';
  static String markNotificationReadEndpoint(int id) => '/guard/notifications/$id/read';
  static const String markAllNotificationsReadEndpoint = '/guard/notifications/read-all';

  // Request Timeouts
  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 15);
}
