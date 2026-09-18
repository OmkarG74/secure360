import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
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

  /// Global navigator key for handling session expiration (401)
  static final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

  /// Global session expiration hook
  static void Function()? onSessionExpired;

  /// Save auth credentials in device storage
  static Future<void> saveAuthSession(String token, Map<String, dynamic> user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
    await prefs.setString(_userKey, jsonEncode(user));
  }

  /// Retrieve active Bearer token
  static Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
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

  /// Clear session on logout or session expiration
  static Future<void> clearAuthSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_userKey);
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
  static ApiResponse<T> _parseResponse<T>(
    http.Response response,
    T Function(dynamic)? fromJsonT,
  ) {
    // Global 401 Session Expiration Handler
    if (response.statusCode == 401) {
      clearAuthSession();
      onSessionExpired?.call();
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

      final apiResponse = _parseResponse<Map<String, dynamic>>(
        response,
        (data) => data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{},
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
  static Future<ApiResponse<void>> logout() async {
    try {
      final url = Uri.parse('${ApiConfig.baseUrl}${ApiConfig.logoutEndpoint}');
      final headers = await _buildHeaders();
      final response = await http.post(url, headers: headers).timeout(ApiConfig.connectTimeout);
      await clearAuthSession();

      return _parseResponse<void>(response, null);
    } catch (e) {
      await clearAuthSession();
      return ApiResponse<void>(
        success: true,
        message: 'Logged out locally.',
        statusCode: 200,
      );
    }
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
}
