/// Secure360 Mobile - Central API & Network Configuration
/// 
/// Communicates EXCLUSIVELY with the Core PHP REST APIs.
/// Flutter never connects directly to MySQL.
class ApiConfig {
  /// Default fallback URL for Android Emulator pointing to local Apache/WAMP:
  static const String _defaultBaseUrl = 'http://10.0.2.2/Secure360/api/v1';

  /// Base host configuration
  ///
  /// Can be overridden dynamically when running Flutter without changing code:
  /// - Android Emulator (default):
  ///     flutter run -d emulator-5554
  /// - Physical Android / iOS on Wi-Fi:
  ///     flutter run -d <device> --dart-define=API_BASE_URL=http://192.168.1.100/Secure360/api/v1
  /// - iOS Simulator / Localhost desktop:
  ///     flutter run -d chrome --dart-define=API_BASE_URL=http://localhost/Secure360/api/v1
  /// - Remote Production / Staging Server:
  ///     flutter run --dart-define=API_BASE_URL=https://api.secure360.example.com/api/v1
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

  // Request Timeouts
  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 15);
}
