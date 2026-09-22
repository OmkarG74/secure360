import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../config/api_config.dart';

/// Standardized API Response Model matching Backend SHARED_DATA_CONTRACT.md
class ApiResponse<T> {
  final bool success;
  final String message;
  final T? data;
  final Map<String, dynamic>? errors;
  final int statusCode;

  ApiResponse({
    required this.success,
    required this.message,
    this.data,
    this.errors,
    required this.statusCode,
  });

  factory ApiResponse.fromJson(
    Map<String, dynamic> json,
    T Function(dynamic)? fromJsonT,
  ) {
    T? parsedData;
    if (json['data'] != null && fromJsonT != null) {
      try {
        parsedData = fromJsonT(json['data']);
      } catch (_) {
        parsedData = null;
      }
    } else if (json['data'] is T) {
      parsedData = json['data'] as T;
    }

    Map<String, dynamic>? parsedErrors;
    if (json['errors'] != null && json['errors'] is Map) {
      parsedErrors = Map<String, dynamic>.from(json['errors']);
    }

    return ApiResponse<T>(
      success: json['success'] == true,
      message: (json['message'] ?? '').toString(),
      data: parsedData,
      errors: parsedErrors,
      statusCode: (json['status_code'] is int)
          ? json['status_code'] as int
          : (int.tryParse(json['status_code']?.toString() ?? '') ?? 200),
    );
  }
}

/// Centralized API Service for Flutter Guard App
class ApiService {
  static const String _tokenKey = 'secure360_guard_token';
  static const String _userKey = 'secure360_guard_user';

  /// Standard secure storage instance (without encryptedSharedPreferences: true)
  static const FlutterSecureStorage _secureStorage = FlutterSecureStorage();

  /// Synchronization lock for concurrent legacy token migration
  static Future<String?>? _inFlightMigration;

  /// Global navigator key for handling session expiration (401)
  static final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

  /// Global session expiration hook
  static void Function()? onSessionExpired;

  /// Intentional-logout state guard.
  /// true  → the session was intentionally logged out; stale/in-flight 401
  ///          responses from background requests must NOT trigger [onSessionExpired].
  /// false → a new authenticated session has been successfully saved in
  ///          [saveAuthSession]; normal protected-request 401 handling is active.
  /// Set to true at the start of [logout()]. Reset to false only in [saveAuthSession()]
  /// after the new token and user record have been fully persisted.
  static bool _isLoggingOut = false;

  /// Hook called at the start of [logout()] before any network or storage
  /// operations. Used to stop background services (e.g. location telemetry)
  /// without creating a circular import between api_service and location_service.
  /// Set this in main.dart: ApiService.onBeforeLogout = LocationService.stopLiveTracking;
  static void Function()? onBeforeLogout;

  /// Save auth credentials in device storage.
  ///
  /// - The authentication Bearer token is persisted to [FlutterSecureStorage].
  /// - The non-credential user profile is saved to [SharedPreferences].
  /// - Any legacy plaintext token in [SharedPreferences] is removed.
  /// - Resets [_isLoggingOut] to false only after successful persistence.
  /// If secure token storage fails, local auth state is safely cleared to prevent
  /// a half-authenticated or inconsistent local state.
  static Future<void> saveAuthSession(String token, Map<String, dynamic> user) async {
    try {
      // 1. Write authentication token into secure storage
      await _secureStorage.write(key: _tokenKey, value: token);

      // 2. Write user profile to SharedPreferences
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_userKey, jsonEncode(user));

      // 3. Ensure any legacy plaintext token is purged from SharedPreferences
      await prefs.remove(_tokenKey);

      // 4. New session is fully persisted. Normal 401 handling resumes from this point.
      _isLoggingOut = false;
    } catch (e) {
      // If secure storage fails, do not leave the client in an inconsistent state.
      debugPrint('[Secure360 Auth] Failed to save session securely: $e');
      await clearAuthSession();
      rethrow;
    }
  }

  /// Retrieve active Bearer token.
  ///
  /// Lazy migration order:
  /// CASE A — Secure token exists:
  ///   - Returns the token from Android Keystore-backed secure storage.
  ///   - Does NOT check or use legacy SharedPreferences.
  /// CASE B — Secure token is genuinely absent/null:
  ///   - Legitimate legacy upgrade scenario.
  ///   - Performs synchronized lazy migration via [_migrateLegacyTokenIfPresent].
  /// CASE C — Secure storage throws an exception:
  ///   - Does NOT fall back to legacy SharedPreferences.
  ///   - Fails closed: logs error (without sensitive token contents) and returns null.
  ///   - Forces standard re-authentication cleanly.
  static Future<String?> getToken() async {
    final String? secureToken;
    try {
      secureToken = await _secureStorage.read(key: _tokenKey);
    } catch (e) {
      // CASE C — Fail closed: Keystore or secure storage error.
      // Do NOT fall back to legacy plaintext storage.
      debugPrint('[Secure360 Auth] Secure storage read exception (failing closed): $e');
      return null;
    }

    // CASE A — Secure token exists and is valid
    if (secureToken != null && secureToken.isNotEmpty) {
      return secureToken;
    }

    // CASE B — Secure token is genuinely absent/null
    return await _migrateLegacyTokenIfPresent();
  }

  /// Synchronized lazy migration of legacy SharedPreferences token
  static Future<String?> _migrateLegacyTokenIfPresent() async {
    if (_inFlightMigration != null) {
      return await _inFlightMigration;
    }

    _inFlightMigration = _doMigrateLegacyToken();
    try {
      return await _inFlightMigration;
    } finally {
      _inFlightMigration = null;
    }
  }

  static Future<String?> _doMigrateLegacyToken() async {
    try {
      // Double check secure storage first
      try {
        final existing = await _secureStorage.read(key: _tokenKey);
        if (existing != null && existing.isNotEmpty) {
          final prefs = await SharedPreferences.getInstance();
          await prefs.remove(_tokenKey);
          return existing;
        }
      } catch (e) {
        debugPrint('[Secure360 Auth] Secure storage read exception during migration check: $e');
        return null;
      }

      final prefs = await SharedPreferences.getInstance();
      final legacyToken = prefs.getString(_tokenKey);

      if (legacyToken == null || legacyToken.isEmpty) {
        return null;
      }

      // 1. Write legacy token to FlutterSecureStorage
      await _secureStorage.write(key: _tokenKey, value: legacyToken);

      // 2. Verify secure-storage write completed successfully
      final verified = await _secureStorage.read(key: _tokenKey);
      if (verified != legacyToken) {
        debugPrint('[Secure360 Auth] Secure storage migration verification failed.');
        return null;
      }

      // 3. Remove legacy plaintext key from SharedPreferences
      await prefs.remove(_tokenKey);
      debugPrint('[Secure360 Auth] Legacy authentication token migrated successfully.');

      // 4. Return the migrated token
      return legacyToken;
    } catch (e) {
      debugPrint('[Secure360 Auth] Legacy token migration failed: $e');
      return null;
    }
  }

  /// Retrieve saved user data
  static Future<Map<String, dynamic>?> getUser() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_userKey);
    if (raw == null) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }
    } catch (_) {}
    return null;
  }

  /// Clear session on logout or session expiration.
  ///
  /// Completely removes:
  /// A. Secure-storage authentication token
  /// B. Legacy SharedPreferences authentication token
  /// C. SharedPreferences user profile
  static Future<void> clearAuthSession() async {
    try {
      await _secureStorage.delete(key: _tokenKey);
    } catch (e) {
      debugPrint('[Secure360 Auth] Error clearing secure storage: $e');
    }

    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_tokenKey);
      await prefs.remove(_userKey);
      await prefs.remove('secure360_last_fcm_token');
      await prefs.remove('secure360_last_synced_user_id');
    } catch (e) {
      debugPrint('[Secure360 Auth] Error clearing SharedPreferences: $e');
    }
  }

  /// Build standard headers with Bearer token injection
  static Future<Map<String, String>> _buildHeaders() async {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    final token = await getToken();
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }
    return headers;
  }

  /// Safely parse HTTP response into an ApiResponse<T>
  ///
  /// [suppressSessionExpiry]: When true, a 401 response will NOT clear the local
  /// session or call [onSessionExpired]. Use this for public/unauthenticated
  /// endpoints (e.g. login) where a 401 means "wrong credentials", not
  /// "the authenticated session has expired".
  static ApiResponse<T> _parseResponse<T>(
    http.Response response,
    T Function(dynamic)? fromJsonT, {
    bool suppressSessionExpiry = false,
  }) {
    // Global 401 Session Expiration Handler.
    //
    // Suppressed in two cases:
    //   1. suppressSessionExpiry == true  → public/login endpoint (wrong credentials,
    //      not an expired authenticated session; do not clear state or navigate away).
    //   2. _isLoggingOut == true          → intentional logout in progress (concurrent
    //      in-flight requests racing the session clear must not show "session expired").
    if (response.statusCode == 401) {
      if (!suppressSessionExpiry && !_isLoggingOut) {
        clearAuthSession();
        onSessionExpired?.call();
      }
    }

    if (response.body.isEmpty) {
      return ApiResponse<T>(
        success: response.statusCode >= 200 && response.statusCode < 300,
        message: response.statusCode >= 400
            ? 'Server returned HTTP ${response.statusCode}'
            : 'Empty server response',
        statusCode: response.statusCode,
      );
    }

    dynamic decoded;
    try {
      decoded = jsonDecode(response.body);
    } catch (e) {
      // Non-JSON response (e.g. Apache HTML 404 or 500)
      final preview = response.body.length > 80
          ? '${response.body.substring(0, 80).replaceAll(RegExp(r'\s+'), ' ')}...'
          : response.body;
      return ApiResponse<T>(
        success: false,
        message: 'Invalid response from server (HTTP ${response.statusCode}): $preview',
        statusCode: response.statusCode,
      );
    }

    if (decoded is! Map<String, dynamic>) {
      return ApiResponse<T>(
        success: false,
        message: 'Unexpected server response structure',
        statusCode: response.statusCode,
      );
    }

    final apiResponse = ApiResponse<T>.fromJson(decoded, fromJsonT);

    // If server returned HTTP error status but success flag wasn't explicitly false, ensure consistency
    if (response.statusCode >= 400 && apiResponse.success) {
      return ApiResponse<T>(
        success: false,
        message: apiResponse.message.isNotEmpty
            ? apiResponse.message
            : 'Server returned HTTP error ${response.statusCode}',
        data: apiResponse.data,
        errors: apiResponse.errors,
        statusCode: response.statusCode,
      );
    }

    return apiResponse;
  }

  /// Guard Authentication (Login)
  static Future<ApiResponse<Map<String, dynamic>>> login({
    required String email,
    required String password,
    String deviceName = 'Flutter Mobile Client',
  }) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.loginEndpoint}');
      final response = await http
          .post(
            url,
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: jsonEncode({
              'email': email,
              'password': password,
              'device_name': deviceName,
              'device_type': 'android',
            }),
          )
          .timeout(ApiConfig.connectTimeout);

      // Login is a PUBLIC authentication endpoint — not a protected session request.
      // suppressSessionExpiry: true ensures that a 401 (wrong credentials) does NOT:
      //   - clear an existing authenticated session
      //   - fire onSessionExpired (which would show "Your session has expired")
      // The 401 error message from the server flows back through ApiResponse.message
      // and is displayed in login_screen.dart's _errorMessage banner.
      final apiResponse = _parseResponse<Map<String, dynamic>>(
        response,
        (data) => data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{},
        suppressSessionExpiry: true,
      );

      if (apiResponse.success && apiResponse.data != null) {
        final token = (apiResponse.data!['token'] ?? '').toString();
        final rawUser = apiResponse.data!['user'] ?? apiResponse.data!['guard'];
        if (rawUser is Map) {
          final userMap = Map<String, dynamic>.from(rawUser);
          await saveAuthSession(token, userMap);
        }
      }

      return apiResponse;
    } on http.ClientException catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Cannot connect to backend (${ApiConfig.baseUrl}): ${e.message}',
        statusCode: 503,
      );
    } catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Connection failed: $e',
        statusCode: 500,
      );
    }
  }

  /// Guard Logout
  ///
  /// Logout lifecycle (A → F):
  /// A. Mark intentional-logout state — suppresses global 401/session-expired handler
  ///    for ALL subsequent requests until the next successful login.
  /// B. Stop live location tracking via [onBeforeLogout] hook (set in main.dart).
  /// C. Revoke the server token via POST /api/v1/guard/logout.
  /// D. Clear local token and user data from SharedPreferences.
  /// E. Return result to caller so it can navigate to LoginScreen.
  /// F. [_isLoggingOut] remains true after this method returns. It is only reset
  ///    to false inside [saveAuthSession()] when the guard successfully logs in again.
  ///    This prevents stale in-flight requests (getProfile, getAssignments, etc.)
  ///    that were started before logout from triggering onSessionExpired after the
  ///    finally block would have prematurely re-enabled the handler.
  static Future<ApiResponse<void>> logout() async {
    // A. Mark intentional logout — suppresses onSessionExpired for any concurrent 401.
    //    Remains true until saveAuthSession() confirms a new session is established.
    _isLoggingOut = true;

    // B. Stop live location telemetry immediately to prevent orphaned timer pings
    //    from firing tokenless requests after the session is cleared.
    onBeforeLogout?.call();

    try {
      // C. Revoke the server-side token.
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.logoutEndpoint}');
      final headers = await _buildHeaders();
      final response = await http.post(url, headers: headers).timeout(ApiConfig.connectTimeout);

      // D. Clear local auth state (token + user JSON).
      await clearAuthSession();

      // E. Return to caller; navigation is the caller's responsibility.
      //    _parseResponse will NOT call onSessionExpired because _isLoggingOut is true.
      return _parseResponse<void>(response, null);
    } catch (e) {
      // Network failure: still clear local state so the guard is signed out locally.
      await clearAuthSession();
      return ApiResponse<void>(
        success: true,
        message: 'Logged out locally.',
        statusCode: 200,
      );
    }
    // No finally block. _isLoggingOut intentionally stays true after logout()
    // returns. It is reset only in saveAuthSession() on the next successful login.
  }

  /// Guard Profile
  static Future<ApiResponse<Map<String, dynamic>>> getProfile() async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.profileEndpoint}');
      final headers = await _buildHeaders();
      final response = await http.get(url, headers: headers).timeout(ApiConfig.connectTimeout);

      final apiResponse = _parseResponse<Map<String, dynamic>>(
        response,
        (data) => data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{},
      );

      if (apiResponse.success && apiResponse.data != null) {
        // Update cached user details
        final token = await getToken();
        if (token != null) {
          await saveAuthSession(token, apiResponse.data!);
        }
      }

      return apiResponse;
    } on http.ClientException catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Cannot connect to backend: ${e.message}',
        statusCode: 503,
      );
    } catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Failed to fetch profile: $e',
        statusCode: 500,
      );
    }
  }

  /// Fetch Guard Duty Assignments (Today's Posts)
  static Future<ApiResponse<List<dynamic>>> getAssignments() async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.assignmentsEndpoint}');
      final headers = await _buildHeaders();
      final response = await http.get(url, headers: headers).timeout(ApiConfig.connectTimeout);

      return _parseResponse<List<dynamic>>(
        response,
        (data) {
          if (data is Map && data['assignments'] is List) {
            return data['assignments'] as List<dynamic>;
          }
          if (data is List) {
            return data;
          }
          return <dynamic>[];
        },
      );
    } on http.ClientException catch (e) {
      return ApiResponse<List<dynamic>>(
        success: false,
        message: 'Cannot connect to backend: ${e.message}',
        statusCode: 503,
      );
    } catch (e) {
      return ApiResponse<List<dynamic>>(
        success: false,
        message: 'Failed to fetch assignments: $e',
        statusCode: 500,
      );
    }
  }

  /// Check In on Site
  static Future<ApiResponse<Map<String, dynamic>>> checkIn({
    required int siteId,
    int? assignmentId,
    int? selfieId,
    required double latitude,
    required double longitude,
    String? address,
    String? notes,
  }) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.checkInEndpoint}');
      final headers = await _buildHeaders();
      final bodyMap = <String, dynamic>{
        'site_id': siteId,
        'latitude': latitude,
        'longitude': longitude,
        'address': address ?? '',
        'notes': notes ?? '',
      };
      if (assignmentId != null) {
        bodyMap['assignment_id'] = assignmentId;
      }
      if (selfieId != null) {
        bodyMap['selfie_id'] = selfieId;
      }

      final response = await http
          .post(
            url,
            headers: headers,
            body: jsonEncode(bodyMap),
          )
          .timeout(ApiConfig.connectTimeout);

      return _parseResponse<Map<String, dynamic>>(
        response,
        (data) => data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{},
      );
    } catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Check-in failed: $e',
        statusCode: 500,
      );
    }
  }

  /// Check Out of Site
  static Future<ApiResponse<Map<String, dynamic>>> checkOut({
    int? attendanceId,
    int? selfieId,
    required double latitude,
    required double longitude,
    String? address,
    String? notes,
  }) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.checkOutEndpoint}');
      final headers = await _buildHeaders();
      final bodyMap = <String, dynamic>{
        'latitude': latitude,
        'longitude': longitude,
        'address': address ?? '',
        'notes': notes ?? '',
      };
      if (attendanceId != null) {
        bodyMap['attendance_id'] = attendanceId;
      }
      if (selfieId != null) {
        bodyMap['selfie_id'] = selfieId;
      }


      final response = await http
          .post(
            url,
            headers: headers,
            body: jsonEncode(bodyMap),
          )
          .timeout(ApiConfig.connectTimeout);

      return _parseResponse<Map<String, dynamic>>(
        response,
        (data) => data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{},
      );
    } catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Check-out failed: $e',
        statusCode: 500,
      );
    }
  }

  /// Attendance History
  static Future<ApiResponse<List<dynamic>>> getAttendanceHistory() async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.historyEndpoint}');
      final headers = await _buildHeaders();
      final response = await http.get(url, headers: headers).timeout(ApiConfig.connectTimeout);

      return _parseResponse<List<dynamic>>(
        response,
        (data) {
          if (data is Map) {
            if (data['history'] is List) {
              return data['history'] as List<dynamic>;
            }
            if (data['records'] is List) {
              return data['records'] as List<dynamic>;
            }
          }
          if (data is List) {
            return data;
          }
          return <dynamic>[];
        },
      );
    } catch (e) {
      return ApiResponse<List<dynamic>>(
        success: false,
        message: 'Failed to fetch attendance history: $e',
        statusCode: 500,
      );
    }
  }

  /// Check if guard has an active (open) attendance session
  /// Returns the open attendance record map if On-Duty, or null if Off-Duty
  static Future<Map<String, dynamic>?> getActiveDuty() async {
    final response = await getAttendanceHistory();
    if (!response.success || response.data == null || response.data!.isEmpty) {
      return null;
    }

    final latest = response.data!.first;
    if (latest is Map) {
      final map = Map<String, dynamic>.from(latest);
      final status = map['status'];
      final checkOutAt = map['check_out_at'];
      // status == 0 and check_out_at is null means currently On-Duty
      if ((status == 0 || status == '0') && (checkOutAt == null || checkOutAt.toString().isEmpty)) {
        return map;
      }
    }
    return null;
  }

  /// Submit periodic live location telemetry
  static Future<ApiResponse<Map<String, dynamic>>> submitLocation({
    required double latitude,
    required double longitude,
    double? accuracy,
    double? speed,
    double? heading,
    double? batteryLevel,
    bool isCharging = false,
    String? activityType = 'patrol',
  }) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.locationTelemetryEndpoint}');
      final headers = await _buildHeaders();
      final response = await http
          .post(
            url,
            headers: headers,
            body: jsonEncode({
              'latitude': latitude,
              'longitude': longitude,
              'accuracy': accuracy,
              'accuracy_meters': accuracy,
              'speed': speed,
              'heading': heading,
              'battery_level': batteryLevel,
              'is_charging': isCharging,
              'activity_type': activityType ?? 'patrol',
            }),

          )
          .timeout(const Duration(seconds: 10));

      return _parseResponse<Map<String, dynamic>>(
        response,
        (data) => data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{},
      );
    } catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Location submission failed: $e',
        statusCode: 500,
      );
    }
  }

  /// Submit selfie verification image
  /// Supports file upload via multipart/form-data or base64 payload
  static Future<ApiResponse<Map<String, dynamic>>> submitSelfie({
    required String filePath,
    int? attendanceId,
  }) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.selfieUploadEndpoint}');
      final token = await getToken();

      final request = http.MultipartRequest('POST', url);
      if (token != null && token.isNotEmpty) {
        request.headers['Authorization'] = 'Bearer $token';
      }
      request.headers['Accept'] = 'application/json';

      if (attendanceId != null) {
        request.fields['attendance_id'] = attendanceId.toString();
      }

      final file = File(filePath);
      if (await file.exists()) {
        request.files.add(
          await http.MultipartFile.fromPath('image', filePath),
        );
      } else {
        return ApiResponse<Map<String, dynamic>>(
          success: false,
          message: 'Selfie image file does not exist at $filePath',
          statusCode: 400,
        );
      }

      final streamedResponse = await request.send().timeout(const Duration(seconds: 20));
      final response = await http.Response.fromStream(streamedResponse);

      return _parseResponse<Map<String, dynamic>>(
        response,
        (data) => data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{},
      );
    } catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Selfie upload failed: $e',
        statusCode: 500,
      );
    }
  }

  /// Fetch guard notifications
  static Future<ApiResponse<List<dynamic>>> getNotifications() async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.notificationsEndpoint}');
      final headers = await _buildHeaders();
      final response = await http.get(url, headers: headers).timeout(ApiConfig.connectTimeout);

      return _parseResponse<List<dynamic>>(
        response,
        (data) {
          if (data is Map && data['notifications'] is List) {
            return data['notifications'] as List<dynamic>;
          }
          if (data is List) {
            return data;
          }
          return <dynamic>[];
        },
      );
    } catch (e) {
      return ApiResponse<List<dynamic>>(
        success: false,
        message: 'Failed to fetch notifications: $e',
        statusCode: 500,
      );
    }
  }

  /// Mark a single notification as read
  static Future<ApiResponse<Map<String, dynamic>>> markNotificationRead(int id) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.markNotificationReadEndpoint(id)}');
      final headers = await _buildHeaders();
      final response = await http.post(url, headers: headers).timeout(ApiConfig.connectTimeout);

      return _parseResponse<Map<String, dynamic>>(
        response,
        (data) => data is Map<String, dynamic> ? data : {},
      );
    } catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Failed to mark notification as read: $e',
        statusCode: 500,
      );
    }
  }

  /// Mark all notifications as read for current guard
  static Future<ApiResponse<Map<String, dynamic>>> markAllNotificationsRead() async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.markAllNotificationsReadEndpoint}');
      final headers = await _buildHeaders();
      final response = await http.post(url, headers: headers).timeout(ApiConfig.connectTimeout);

      return _parseResponse<Map<String, dynamic>>(
        response,
        (data) => data is Map<String, dynamic> ? data : {},
      );
    } catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Failed to mark all notifications as read: $e',
        statusCode: 500,
      );
    }
  }

  /// Register FCM Device Token with backend
  static Future<ApiResponse<Map<String, dynamic>>> registerDeviceToken(
    String fcmToken, {
    String deviceType = 'android',
    String? deviceName,
  }) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}/guard/device-token');
      final headers = await _buildHeaders();
      final body = jsonEncode({
        'fcm_token': fcmToken,
        'device_type': deviceType,
        'device_name': deviceName ?? 'Android Device',
      });
      debugPrint('[ApiService] Registering device token: $url, type: $deviceType, authAttached: ${headers.containsKey('Authorization')}');
      final response = await http
          .post(url, headers: headers, body: body)
          .timeout(ApiConfig.connectTimeout);

      return _parseResponse<Map<String, dynamic>>(
        response,
        (data) => data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{},
      );
    } catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Failed to register device token: $e',
        statusCode: 500,
      );
    }
  }

  /// Deactivate FCM Device Token on logout
  static Future<ApiResponse<void>> removeDeviceToken(String fcmToken) async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}/guard/device-token/remove');
      final headers = await _buildHeaders();
      final body = jsonEncode({'fcm_token': fcmToken});
      final response = await http
          .post(url, headers: headers, body: body)
          .timeout(ApiConfig.connectTimeout);

      return _parseResponse<void>(response, null);
    } catch (_) {
      return ApiResponse<void>(
        success: true,
        message: 'Token cleared locally',
        statusCode: 200,
      );
    }
  }
}
