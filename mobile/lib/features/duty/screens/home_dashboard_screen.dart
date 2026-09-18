import 'dart:async';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../../../core/services/api_service.dart';
import '../../../core/services/location_service.dart';
import '../../../core/utils/distance_formatter.dart';
import '../../../core/utils/time_formatter.dart';
import '../../attendance/screens/check_in_screen.dart';
import '../../attendance/screens/check_out_screen.dart';
import '../../attendance/screens/attendance_history_screen.dart';
import '../../notifications/screens/notifications_screen.dart';
import '../../profile/screens/profile_screen.dart';


class HomeDashboardScreen extends StatefulWidget {
  const HomeDashboardScreen({super.key});

  @override
  State<HomeDashboardScreen> createState() => _HomeDashboardScreenState();
}

class _HomeDashboardScreenState extends State<HomeDashboardScreen> {
  int _currentTabIndex = 0;

  Map<String, dynamic>? _user;
  List<dynamic> _assignments = [];
  Map<String, dynamic>? _activeDuty;
  Map<String, dynamic>? _latestAttendance;
  int _unreadNotifications = 0;

  bool _isLoading = true;
  String? _errorMessage;

  Timer? _dutyTicker;

  // Dedicated Location tab state
  Position? _livePosition;
  bool _isRefreshingLocation = false;
  String? _locationStatusMessage;
  LocationPermission _permissionStatus = LocationPermission.unableToDetermine;
  bool _locationServiceEnabled = true;
  bool _showTechnicalDetails = false;

  @override
  void initState() {
    super.initState();
    _loadDashboardData();
    _fetchTabLocation();

    // Refresh active duty display timer every minute
    _dutyTicker = Timer.periodic(const Duration(minutes: 1), (_) {
      if (mounted && _activeDuty != null) {
        setState(() {});
      }
    });
  }

  @override
  void dispose() {
    _dutyTicker?.cancel();
    super.dispose();
  }

  /// Reload all backend data: User profile, assignments, active duty session, and notifications
  Future<void> _loadDashboardData() async {
    setState(() => _isLoading = true);

    // 1. Fetch user profile
    final profileResponse = await ApiService.getProfile();
    final user = profileResponse.success && profileResponse.data != null
        ? profileResponse.data!
        : await ApiService.getUser();

    // 2. Fetch assignments
    final assignmentsResponse = await ApiService.getAssignments();

    // 3. Check active attendance session from backend
    final activeDuty = await ApiService.getActiveDuty();

    // 4. Check notifications count
    int unreadCount = 0;
    try {
      final notifsResponse = await ApiService.getNotifications();
      if (notifsResponse.success && notifsResponse.data != null) {
        unreadCount = notifsResponse.data!.where((n) => n['is_read'] != 1 && n['is_read'] != true).length;
      }
    } catch (_) {}

    // 5. Fetch latest attendance record for verification history
    Map<String, dynamic>? latestAttendance;
    try {
      final historyResponse = await ApiService.getAttendanceHistory();
      if (historyResponse.success && historyResponse.data != null && historyResponse.data!.isNotEmpty) {
        latestAttendance = historyResponse.data!.first;
      }
    } catch (_) {}

    if (!mounted) return;

    setState(() {
      _user = user;
      _activeDuty = activeDuty;
      _latestAttendance = latestAttendance;
      _unreadNotifications = unreadCount;
      _isLoading = false;

      if (assignmentsResponse.success && assignmentsResponse.data != null) {
        _assignments = assignmentsResponse.data!;
      } else {
        _errorMessage = assignmentsResponse.message;
      }
    });

    // Sync live telemetry state with backend active duty status
    if (_activeDuty != null) {
      LocationService.startLiveTracking();
    } else {
      LocationService.stopLiveTracking();
    }
  }

  /// Fetch location for Location tab with full permission and service status
  Future<void> _fetchTabLocation() async {
    setState(() => _isRefreshingLocation = true);
    try {
      _locationServiceEnabled = await Geolocator.isLocationServiceEnabled();
      _permissionStatus = await LocationService.checkPermission();
      final pos = await LocationService.getCurrentLocation();
      if (!mounted) return;
      setState(() {
        _livePosition = pos;
        _isRefreshingLocation = false;
        _locationStatusMessage = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isRefreshingLocation = false;
        _locationStatusMessage = e.toString().replaceAll('Exception: ', '');
      });
    }
  }

  /// Validate shift window for client UX and disabled states
  Map<String, dynamic> _validateShiftWindow(String startTime, String endTime) {
    try {
      final now = DateTime.now();
      final startParts = startTime.split(':').map(int.parse).toList();
      final endParts = endTime.split(':').map(int.parse).toList();

      final nowMinutes = now.hour * 60 + now.minute;
      final startMinutes = startParts[0] * 60 + startParts[1];
      final endMinutes = endParts[0] * 60 + endParts[1];

      final formattedStart = TimeFormatter.formatTime(startTime);

      if (startMinutes <= endMinutes) {
        if (nowMinutes < startMinutes) {
          return {
            'allowed': false,
            'state': 'before_shift',
            'message': 'Your shift starts at $formattedStart.',
            'badge': 'Starts at $formattedStart',
          };
        }
        if (nowMinutes > endMinutes) {
          return {
            'allowed': false,
            'state': 'after_shift',
            'message': 'Your assigned shift has ended.',
            'badge': 'Shift Ended',
          };
        }
        return {
          'allowed': true,
          'state': 'active',
          'message': 'Shift is active.',
          'badge': 'Shift Active',
        };
      }

      // Overnight shift (e.g. 22:00 to 06:00)
      if (nowMinutes >= startMinutes || nowMinutes <= endMinutes) {
        return {
          'allowed': true,
          'state': 'active',
          'message': 'Shift is active.',
          'badge': 'Shift Active',
        };
      }

      final midpoint = endMinutes + ((startMinutes - endMinutes) ~/ 2);
      if (nowMinutes <= midpoint) {
        return {
          'allowed': false,
          'state': 'after_shift',
          'message': 'Your assigned shift has ended.',
          'badge': 'Shift Ended',
        };
      }

      return {
        'allowed': false,
        'state': 'before_shift',
        'message': 'Your shift starts at $formattedStart.',
        'badge': 'Starts at $formattedStart',
      };
    } catch (_) {
      return {'allowed': true, 'state': 'active', 'message': 'Shift is active.', 'badge': 'Assigned Shift'};
    }
  }

  /// Check-out verification flow requiring fresh selfie, GPS validation, and API submission
  Future<void> _handleCheckOut() async {
    if (_activeDuty == null) return;


    final rawId = _activeDuty?['id'];
    final attendanceId = rawId != null ? (rawId is int ? rawId : int.tryParse(rawId.toString())) : null;
    if (attendanceId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No active duty session ID found to check out from.'),
          backgroundColor: Color(0xFFDC2626),
        ),
      );
      return;
    }

    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => CheckOutScreen(
          attendanceId: attendanceId,
          siteName: _activeDuty?['site_name'] ?? 'Duty Post',
          siteId: _activeDuty?['site_id'] != null ? int.tryParse(_activeDuty!['site_id'].toString()) : null,
          siteLatitude: _activeDuty?['site_latitude'] != null ? double.tryParse(_activeDuty!['site_latitude'].toString()) : null,
          siteLongitude: _activeDuty?['site_longitude'] != null ? double.tryParse(_activeDuty!['site_longitude'].toString()) : null,
          checkInTime: _activeDuty?['check_in_at']?.toString(),
          shiftName: _activeDuty?['shift_name']?.toString(),
        ),
      ),
    );

    if (result == true || mounted) {
      await _loadDashboardData();
    }
  }


  @override
  Widget build(BuildContext context) {
    final guardName = _user?['name'] ?? _user?['full_name'] ?? 'Guard Operator';
    final badgeCode = _user?['employee_code'] ?? 'GRD-101';
    final orgName = _user?['organization_name'] ?? 'Apex Security';
    final isOnDuty = _activeDuty != null;

    return PopScope(
      canPop: _currentTabIndex == 0,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop && _currentTabIndex != 0) {
          setState(() => _currentTabIndex = 0);
        }
      },
      child: Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: AppBar(
          backgroundColor: Colors.white,
          elevation: 0,
          title: Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.shield_outlined, color: Color(0xFF2563EB), size: 20),
              ),
              const SizedBox(width: 10),
              const Text(
                'Secure360',
                style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18),
              ),
            ],
          ),
          actions: [
            // Notifications with badge (non-duplicating)
            Stack(
              alignment: Alignment.center,
              children: [
                IconButton(
                  icon: const Icon(Icons.notifications_outlined, color: Color(0xFF475569)),
                  tooltip: 'Alerts & Notices',
                  onPressed: () async {
                    await Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const NotificationsScreen()),
                    );
                    _loadDashboardData();
                  },
                ),
                if (_unreadNotifications > 0)
                  Positioned(
                    top: 8,
                    right: 8,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: const BoxDecoration(
                        color: Color(0xFFDC2626),
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        '$_unreadNotifications',
                        style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
              ],
            ),
            // Profile icon (non-duplicating)
            IconButton(
              icon: const Icon(Icons.account_circle_outlined, color: Color(0xFF475569)),
              tooltip: 'Profile',
              onPressed: () async {
                await Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const ProfileScreen()),
                );
                _loadDashboardData();
              },
            ),
          ],
        ),
        body: _isLoading
            ? const Center(child: CircularProgressIndicator(color: Color(0xFF2563EB)))
            : IndexedStack(
                index: _currentTabIndex,
                children: [
                  // Tab 0: Home
                  _buildHomeTab(context, guardName, badgeCode, orgName, isOnDuty),
                  // Tab 1: Shift
                  _buildShiftTab(context, isOnDuty),
                  // Tab 2: Attendance Verification
                  _buildVerificationTab(context, isOnDuty),
                  // Tab 3: Location
                  _buildLocationTab(context, isOnDuty),
                  // Tab 4: History (persistent embedded attendance history)
                  const AttendanceHistoryScreen(isEmbedded: true),
                ],
              ),
        bottomNavigationBar: Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: Color(0xFFE2E8F0), width: 1)),
          ),
          child: BottomNavigationBar(
            currentIndex: _currentTabIndex,
            onTap: (index) {
              setState(() => _currentTabIndex = index);
              if (index == 3) {
                _fetchTabLocation();
              }
            },
            type: BottomNavigationBarType.fixed,
            backgroundColor: Colors.white,
            selectedItemColor: const Color(0xFF2563EB),
            unselectedItemColor: const Color(0xFF64748B),
            selectedFontSize: 11,
            unselectedFontSize: 11,
            selectedLabelStyle: const TextStyle(fontWeight: FontWeight.bold),
            elevation: 0,
            items: const [
              BottomNavigationBarItem(
                icon: Icon(Icons.home_outlined),
                activeIcon: Icon(Icons.home),
                label: 'Home',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.schedule_outlined),
                activeIcon: Icon(Icons.schedule),
                label: 'Shift',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.verified_user_outlined),
                activeIcon: Icon(Icons.verified_user),
                label: 'Verification',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.location_on_outlined),
                activeIcon: Icon(Icons.location_on),
                label: 'Location',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.history_outlined),
                activeIcon: Icon(Icons.history),
                label: 'History',
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ==========================================
  // TAB 0: HOME
  // ==========================================
  Widget _buildHomeTab(BuildContext context, String guardName, String badgeCode, String orgName, bool isOnDuty) {
    double? activeDistance;
    bool isWithinDutyArea = true;
    if (isOnDuty && _livePosition != null && _activeDuty?['site_latitude'] != null && _activeDuty?['site_longitude'] != null) {
      final sLat = double.tryParse(_activeDuty!['site_latitude'].toString());
      final sLng = double.tryParse(_activeDuty!['site_longitude'].toString());
      if (sLat != null && sLng != null) {
        activeDistance = Geolocator.distanceBetween(_livePosition!.latitude, _livePosition!.longitude, sLat, sLng);
        isWithinDutyArea = activeDistance <= 150.0;
      }
    }

    return RefreshIndicator(
      onRefresh: _loadDashboardData,
      child: ListView(
        padding: const EdgeInsets.all(16.0),
        children: [
          // Guard Profile Header Card
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: isOnDuty
                    ? [const Color(0xFF064E3B), const Color(0xFF059669)]
                    : [const Color(0xFF1E3A8A), const Color(0xFF2563EB)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(14),
              boxShadow: [
                BoxShadow(
                  color: isOnDuty ? const Color(0x40059669) : const Color(0x402563EB),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      badgeCode,
                      style: const TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 0.5,
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0x40000000),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: isOnDuty ? const Color(0xFF34D399) : Colors.white38,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          CircleAvatar(
                            radius: 4,
                            backgroundColor: isOnDuty ? const Color(0xFF34D399) : const Color(0xFF94A3B8),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            isOnDuty ? 'ON-DUTY ACTIVE' : 'OFF-DUTY',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  guardName,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  orgName,
                  style: const TextStyle(color: Colors.white70, fontSize: 13),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),

          // ACTIVE DUTY BANNER (when On-Duty)
          if (isOnDuty) ...[
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: isWithinDutyArea ? const Color(0xFF86EFAC) : const Color(0xFFFCA5A5),
                  width: 1.5,
                ),
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x1410B981),
                    blurRadius: 10,
                    offset: Offset(0, 4),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFECFDF5),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Icon(Icons.security, color: Color(0xFF059669), size: 22),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Currently Stationed At',
                              style: TextStyle(fontSize: 12, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
                            ),
                            Text(
                              _activeDuty?['site_name'] ?? 'Assigned Duty Post',
                              style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const Divider(height: 24, color: Color(0xFFF1F5F9)),
                  Row(
                    children: [
                      const Icon(Icons.access_time, size: 16, color: Color(0xFF64748B)),
                      const SizedBox(width: 6),
                      Text(
                        'Checked in: ${_activeDuty?['check_in_at'] != null ? TimeFormatter.formatDateTime(_activeDuty!['check_in_at'].toString()) : 'Just now'}',
                        style: const TextStyle(fontSize: 13, color: Color(0xFF475569)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Container(
                        width: 8,
                        height: 8,
                        decoration: const BoxDecoration(
                          color: Color(0xFF10B981),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 6),
                      const Text(
                        'Live GPS Status: Active • Transmitting',
                        style: TextStyle(fontSize: 12, color: Color(0xFF059669), fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),

                  // Geofence status indicator
                  if (activeDistance != null) ...[
                    if (isWithinDutyArea) ...[
                      Row(
                        children: [
                          const Icon(Icons.verified, size: 16, color: Color(0xFF16A34A)),
                          const SizedBox(width: 6),
                          Text(
                            'Within Assigned Area (${DistanceFormatter.formatDistance(activeDistance)} from post)',
                            style: const TextStyle(fontSize: 12, color: Color(0xFF166534), fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ] else ...[
                      Container(
                        margin: const EdgeInsets.only(top: 6),
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF2F2),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFFCA5A5)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.warning_amber_rounded, size: 18, color: Color(0xFFDC2626)),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Outside Assigned Area (${DistanceFormatter.formatDistance(activeDistance)} from post). Return to post.',
                                style: const TextStyle(fontSize: 12, color: Color(0xFF991B1B), fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],

                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    height: 46,
                    child: ElevatedButton.icon(
                      onPressed: _handleCheckOut,
                      icon: const Icon(Icons.exit_to_app, color: Colors.white, size: 18),
                      label: const Text(
                        'Check-Out of Duty Post',
                        style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                      ),

                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFDC2626),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        elevation: 0,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
          ],

          // Quick Navigation Grid (Shift, Selfie, Location, History)
          Row(
            children: [
              Expanded(
                child: _buildQuickActionCard(
                  title: 'My Shift',
                  subtitle: '${_assignments.length} assigned',
                  icon: Icons.schedule,
                  color: const Color(0xFF2563EB),
                  onTap: () => setState(() => _currentTabIndex = 1),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildQuickActionCard(
                  title: 'Verification',
                  subtitle: isOnDuty ? 'Check-in Verified' : 'Duty Verification',
                  icon: Icons.verified_user,
                  color: const Color(0xFF7C3AED),
                  onTap: () => setState(() => _currentTabIndex = 2),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _buildQuickActionCard(
                  title: 'Live GPS',
                  subtitle: isOnDuty ? 'Transmitting' : 'Standby',
                  icon: Icons.my_location,
                  color: const Color(0xFF059669),
                  onTap: () {
                    setState(() => _currentTabIndex = 3);
                    _fetchTabLocation();
                  },
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildQuickActionCard(
                  title: 'Duty Logs',
                  subtitle: 'History records',
                  icon: Icons.history,
                  color: const Color(0xFFD97706),
                  onTap: () => setState(() => _currentTabIndex = 4),
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),

          // Today's Assigned Posts Preview
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                "Today's Assigned Posts",
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF0F172A),
                ),
              ),
              TextButton(
                onPressed: () => setState(() => _currentTabIndex = 1),
                child: const Text('View All', style: TextStyle(color: Color(0xFF2563EB), fontWeight: FontWeight.bold, fontSize: 13)),
              ),
            ],
          ),
          const SizedBox(height: 8),

          if (_assignments.isEmpty) ...[
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Center(
                child: Column(
                  children: [
                    const Icon(Icons.assignment_late_outlined, size: 40, color: Color(0xFF94A3B8)),
                    const SizedBox(height: 8),
                    Text(
                      _errorMessage ?? 'No duty assignments dispatched today.',
                      style: const TextStyle(color: Color(0xFF64748B), fontSize: 14),
                    ),
                  ],
                ),
              ),
            ),
          ] else ...[
            ..._assignments.map((asgn) {
              final siteName = asgn['site_name'] ?? 'Protected Site';
              final siteAddress = asgn['site_address'] ?? 'No address registered';
              final shiftName = asgn['shift_name'] ?? 'Standard Shift';
              final startTime = asgn['start_time']?.toString() ?? '08:00:00';
              final endTime = asgn['end_time']?.toString() ?? '16:00:00';
              final formattedShiftTiming = TimeFormatter.formatTimeRange(startTime, endTime);
              final siteId = asgn['site_id'] ?? 1;
              final assignmentId = asgn['id'] ?? asgn['assignment_id'];

              final shiftStatus = _validateShiftWindow(startTime, endTime);
              final isShiftActive = shiftStatus['allowed'] == true;

              double? distToSite;
              double? sLat;
              double? sLng;
              if (asgn['latitude'] != null && asgn['longitude'] != null) {
                sLat = double.tryParse(asgn['latitude'].toString());
                sLng = double.tryParse(asgn['longitude'].toString());
                if (_livePosition != null && sLat != null && sLng != null) {
                  distToSite = Geolocator.distanceBetween(_livePosition!.latitude, _livePosition!.longitude, sLat, sLng);
                }
              }

              final geofenceStatus = DistanceFormatter.evaluateStatus(
                isLocating: _isRefreshingLocation,
                locationError: _locationStatusMessage,
                distanceMeters: distToSite,
                accuracy: _livePosition?.accuracy,
                siteName: siteName,
                allowedRadius: 150.0,
              );

              // Diagnostic debug logging
              if (sLat != null && sLng != null && _livePosition != null) {
                debugPrint('[Secure360 Location] Post: $siteName (ID: $siteId) at ($sLat, $sLng)');
                debugPrint('[Secure360 Location] Current GPS: (${_livePosition!.latitude}, ${_livePosition!.longitude}), accuracy: ±${_livePosition!.accuracy.toStringAsFixed(1)}m');
                debugPrint('[Secure360 Location] Calculated Distance: ${DistanceFormatter.formatDistance(distToSite)} (${distToSite?.round()}m), Allowed: 150m, Status: ${geofenceStatus.title}');
              }

              return Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: isShiftActive
                        ? (geofenceStatus.isWithin ? const Color(0xFF86EFAC) : const Color(0xFFFCA5A5))
                        : const Color(0xFFE2E8F0),
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: isShiftActive ? const Color(0xFFEFF6FF) : const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            shiftName,
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: isShiftActive ? const Color(0xFF2563EB) : const Color(0xFF64748B),
                            ),
                          ),
                        ),
                        Text(
                          formattedShiftTiming,
                          style: const TextStyle(fontSize: 12, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      siteName,
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    if (siteAddress.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        siteAddress,
                        style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                      ),
                    ],

                    // Shift status & Geofence pills
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 6,
                      children: [
                        // Shift status pill
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: isShiftActive ? const Color(0xFFF0FDF4) : const Color(0xFFFEF2F2),
                            borderRadius: BorderRadius.circular(4),
                            border: Border.all(color: isShiftActive ? const Color(0xFFBBF7D0) : const Color(0xFFFECACA)),
                          ),
                          child: Text(
                            shiftStatus['badge'] as String,
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: isShiftActive ? const Color(0xFF15803D) : const Color(0xFFB91C1C),
                            ),
                          ),
                        ),

                        // Geofence status component
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: geofenceStatus.backgroundColor,
                            borderRadius: BorderRadius.circular(4),
                            border: Border.all(color: geofenceStatus.borderColor),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                geofenceStatus.icon,
                                size: 12,
                                color: geofenceStatus.primaryColor,
                              ),
                              const SizedBox(width: 4),
                              Text(
                                distToSite != null
                                    ? '${geofenceStatus.title} (${DistanceFormatter.formatDistance(distToSite)})'
                                    : geofenceStatus.title,
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                  color: geofenceStatus.primaryColor,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    // OUTSIDE-GEOFENCE WARNING (if outside area and shift active)
                    if (isShiftActive && !geofenceStatus.isWithin && distToSite != null) ...[
                      const SizedBox(height: 10),
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF2F2),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFFCA5A5)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.warning_amber_rounded, size: 18, color: Color(0xFFDC2626)),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Outside Assigned Area (${DistanceFormatter.formatDistance(distToSite)} from post). Move closer to check in.',
                                style: const TextStyle(fontSize: 12, color: Color(0xFF991B1B), fontWeight: FontWeight.w500),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    const SizedBox(height: 16),

                    if (isOnDuty) ...[
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF1F5F9),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.info_outline, size: 16, color: Color(0xFF64748B)),
                            SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'You have an active check-in. Check out before checking into a new post.',
                                style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ] else ...[
                      SizedBox(
                        width: double.infinity,
                        height: 44,
                        child: ElevatedButton.icon(
                          onPressed: !isShiftActive
                              ? null
                              : () async {
                                  final result = await Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) => CheckInScreen(
                                        siteId: siteId is int ? siteId : int.parse(siteId.toString()),
                                        siteName: siteName,
                                        siteAddress: siteAddress,
                                        assignmentId: assignmentId != null ? (assignmentId is int ? assignmentId : int.tryParse(assignmentId.toString())) : null,
                                        shiftName: shiftName,
                                        siteLatitude: asgn['latitude'] != null ? double.tryParse(asgn['latitude'].toString()) : null,
                                        siteLongitude: asgn['longitude'] != null ? double.tryParse(asgn['longitude'].toString()) : null,
                                        startTime: startTime,
                                        endTime: endTime,
                                      ),
                                    ),
                                  );

                                  if (result == true || mounted) {
                                    _loadDashboardData();
                                  }
                                },
                          icon: Icon(
                            isShiftActive ? Icons.login : Icons.schedule,
                            size: 18,
                            color: Colors.white,
                          ),
                          label: Text(
                            !isShiftActive
                                ? (shiftStatus['message'] as String)
                                : 'Check In to Duty Post',
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF10B981),
                            disabledBackgroundColor: const Color(0xFF94A3B8),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                            elevation: 0,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              );
            }).toList(),
          ],
        ],
      ),
    );
  }


  // ==========================================
  // TAB 1: SHIFT
  // ==========================================
  Widget _buildShiftTab(BuildContext context, bool isOnDuty) {
    return RefreshIndicator(
      onRefresh: _loadDashboardData,
      child: ListView(
        padding: const EdgeInsets.all(16.0),
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.calendar_month, color: Color(0xFF2563EB), size: 22),
              ),
              const SizedBox(width: 12),
              const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Assigned Shifts & Posts',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                  ),
                  Text(
                    'Scheduled guard posts dispatched for today',
                    style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 16),

          if (_assignments.isEmpty) ...[
            Container(
              padding: const EdgeInsets.all(32),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: const Center(
                child: Column(
                  children: [
                    Icon(Icons.event_busy, size: 48, color: Color(0xFF94A3B8)),
                    SizedBox(height: 12),
                    Text(
                      'No Shifts Assigned Today',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Contact your security administrator if your schedule is missing.',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ),
          ] else ...[
            ..._assignments.map((asgn) {
              final siteName = asgn['site_name'] ?? 'Protected Site';
              final siteAddress = asgn['site_address'] ?? 'No address registered';
              final shiftName = asgn['shift_name'] ?? 'Standard Shift';
              final startTime = asgn['start_time']?.toString() ?? '08:00:00';
              final endTime = asgn['end_time']?.toString() ?? '16:00:00';
              final formattedShiftTiming = TimeFormatter.formatTimeRange(startTime, endTime);
              final siteId = asgn['site_id'] ?? 1;
              final assignmentId = asgn['id'] ?? asgn['assignment_id'];

              return Container(
                margin: const EdgeInsets.only(bottom: 14),
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                  boxShadow: const [
                    BoxShadow(color: Color(0x05000000), blurRadius: 6, offset: Offset(0, 2)),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFEFF6FF),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            shiftName,
                            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF2563EB)),
                          ),
                        ),
                        Row(
                          children: [
                            const Icon(Icons.access_time, size: 14, color: Color(0xFF64748B)),
                            const SizedBox(width: 4),
                            Text(
                              formattedShiftTiming,
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF334155)),
                            ),
                          ],
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(
                      siteName,
                      style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      siteAddress,
                      style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                    ),
                    const Divider(height: 24, color: Color(0xFFF1F5F9)),
                    if (isOnDuty) ...[
                      Container(
                        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFECFDF5),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(color: const Color(0xFFA7F3D0)),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.check_circle, size: 16, color: Color(0xFF059669)),
                            SizedBox(width: 8),
                            Text('Shift Currently Active (On Duty)', style: TextStyle(fontSize: 12, color: Color(0xFF059669), fontWeight: FontWeight.bold)),
                          ],
                        ),
                      ),
                    ] else ...[
                      Builder(
                        builder: (_) {
                          final shiftStatus = _validateShiftWindow(startTime, endTime);
                          final isShiftActive = shiftStatus['allowed'] == true;

                          return SizedBox(
                            width: double.infinity,
                            height: 44,
                            child: ElevatedButton.icon(
                              onPressed: !isShiftActive
                                  ? null
                                  : () async {
                                      final result = await Navigator.of(context).push(
                                        MaterialPageRoute(
                                          builder: (_) => CheckInScreen(
                                            siteId: siteId is int ? siteId : int.parse(siteId.toString()),
                                            siteName: siteName,
                                            siteAddress: siteAddress,
                                            assignmentId: assignmentId != null ? (assignmentId is int ? assignmentId : int.tryParse(assignmentId.toString())) : null,
                                            shiftName: shiftName,
                                            siteLatitude: asgn['latitude'] != null ? double.tryParse(asgn['latitude'].toString()) : null,
                                            siteLongitude: asgn['longitude'] != null ? double.tryParse(asgn['longitude'].toString()) : null,
                                            startTime: startTime,
                                            endTime: endTime,
                                          ),
                                        ),
                                      );

                                      if (result == true || mounted) {
                                        _loadDashboardData();
                                      }
                                    },
                              icon: Icon(isShiftActive ? Icons.login : Icons.schedule, size: 16, color: Colors.white),
                              label: Text(
                                !isShiftActive ? (shiftStatus['message'] as String) : 'Check In to Duty Post',
                                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                              ),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF10B981),
                                disabledBackgroundColor: const Color(0xFF94A3B8),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                elevation: 0,
                              ),
                            ),
                          );
                        },
                      ),
                    ],

                  ],
                ),
              );
            }).toList(),
          ],
        ],
      ),
    );
  }

  // ==========================================
  // TAB 2: ATTENDANCE VERIFICATION
  // ==========================================
  Widget _buildVerificationTab(BuildContext context, bool isOnDuty) {
    return RefreshIndicator(
      onRefresh: _loadDashboardData,
      child: ListView(
        padding: const EdgeInsets.all(16.0),
        children: [
          // Header
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.verified_user, color: Color(0xFF2563EB), size: 22),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Attendance Verification',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                    ),
                    Text(
                      'Capture a fresh selfie when starting or ending your duty.',
                      style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),

          // State-based verification content
          if (isOnDuty && _activeDuty != null) ...[
            _buildOnDutyVerificationCard(context),
          ] else if (_latestAttendance != null &&
              (_latestAttendance!['check_out_at'] != null ||
                  _latestAttendance!['status'] == 1 ||
                  _latestAttendance!['status'] == '1')) ...[
            _buildAfterCheckoutVerificationCard(context),
          ] else ...[
            _buildOffDutyVerificationCard(context),
          ],
        ],
      ),
    );
  }

  /// On-Duty Verification State
  Widget _buildOnDutyVerificationCard(BuildContext context) {
    final siteName = _activeDuty?['site_name']?.toString() ?? 'Assigned Post';
    final shiftName = _activeDuty?['shift_name']?.toString() ?? 'Active Shift';
    final checkInAt = _activeDuty?['check_in_at']?.toString() ?? _activeDuty?['created_at']?.toString();
    final formattedCheckIn = TimeFormatter.formatDateTime(checkInAt);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFF86EFAC), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(8),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Active Session Badge
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    width: 10,
                    height: 10,
                    decoration: const BoxDecoration(
                      color: Color(0xFF10B981),
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 8),
                  const Text(
                    'ACTIVE DUTY SESSION',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF059669), letterSpacing: 0.5),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFECFDF5),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFA7F3D0)),
                ),
                child: const Text('ON-DUTY', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF059669))),
              ),
            ],
          ),
          const SizedBox(height: 12),

          Text(
            siteName,
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
          ),
          Text(
            shiftName,
            style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
          ),

          const Padding(
            padding: EdgeInsets.symmetric(vertical: 16.0),
            child: Divider(height: 1, color: Color(0xFFF1F5F9)),
          ),

          // Check-in Selfie Status
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFECFDF5),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFA7F3D0)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.check_circle, color: Color(0xFF059669), size: 22),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Check-in Selfie Verified',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                          ),
                          Text(
                            'VERIFIED',
                            style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF059669)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Captured: $formattedCheckIn',
                        style: const TextStyle(fontSize: 12, color: Color(0xFF059669), fontWeight: FontWeight.w500),
                      ),
                      const SizedBox(height: 2),
                      const Text(
                        'Facial match and presence confirmed at start of duty.',
                        style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),

          // Checkout Selfie Requirement Notice
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFFFFBEB),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFFDE68A)),
            ),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.camera_front, color: Color(0xFFD97706), size: 22),
                SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Checkout Selfie Required',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                          ),
                          Text(
                            'REQUIRED',
                            style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFFD97706)),
                          ),
                        ],
                      ),
                      SizedBox(height: 4),
                      Text(
                        'A fresh front-camera selfie will be required when concluding duty to verify post presence until checkout.',
                        style: TextStyle(fontSize: 12, color: Color(0xFF92400E)),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 22),

          // Checkout Flow Shortcut Action
          SizedBox(
            width: double.infinity,
            height: 46,
            child: ElevatedButton.icon(
              onPressed: () async {
                final activeDutyId = _activeDuty!['id'] is int
                    ? _activeDuty!['id'] as int
                    : int.tryParse(_activeDuty!['id']?.toString() ?? '') ?? 0;
                final activeSiteName = _activeDuty!['site_name']?.toString() ?? 'Assigned Post';
                final activeSiteId = _activeDuty!['site_id'] != null ? int.tryParse(_activeDuty!['site_id'].toString()) : null;
                final activeSiteLat = double.tryParse(_activeDuty!['site_lat']?.toString() ?? _activeDuty!['site_latitude']?.toString() ?? '');
                final activeSiteLng = double.tryParse(_activeDuty!['site_lng']?.toString() ?? _activeDuty!['site_longitude']?.toString() ?? '');
                final activeCheckInTime = _activeDuty!['check_in_at']?.toString();
                final activeShiftName = _activeDuty!['shift_name']?.toString();

                final result = await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => CheckOutScreen(
                      attendanceId: activeDutyId,
                      siteName: activeSiteName,
                      siteId: activeSiteId,
                      siteLatitude: activeSiteLat,
                      siteLongitude: activeSiteLng,
                      checkInTime: activeCheckInTime,
                      shiftName: activeShiftName,
                    ),
                  ),
                );
                if (result == true) {
                  _loadDashboardData();
                }
              },
              icon: const Icon(Icons.logout, color: Colors.white, size: 18),
              label: const Text(
                'End Duty / Check Out With Selfie',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFDC2626),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// After Checkout / Completed Duty Verification State
  Widget _buildAfterCheckoutVerificationCard(BuildContext context) {
    final siteName = _latestAttendance?['site_name']?.toString() ?? 'Assigned Post';
    final shiftName = _latestAttendance?['shift_name']?.toString() ?? 'Recent Duty Session';
    final checkInAt = _latestAttendance?['check_in_at']?.toString();
    final checkOutAt = _latestAttendance?['check_out_at']?.toString();
    final formattedCheckIn = TimeFormatter.formatDateTime(checkInAt);
    final formattedCheckOut = TimeFormatter.formatDateTime(checkOutAt);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(6),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.task_alt, color: Color(0xFF059669), size: 20),
                  SizedBox(width: 8),
                  Text(
                    'Verification Complete',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFECFDF5),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFA7F3D0)),
                ),
                child: const Text('COMPLETED', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF059669))),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(siteName, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
          Text(shiftName, style: const TextStyle(fontSize: 13, color: Color(0xFF64748B))),

          const Padding(
            padding: EdgeInsets.symmetric(vertical: 14.0),
            child: Divider(height: 1, color: Color(0xFFF1F5F9)),
          ),

          // Check-in Verified
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              children: [
                const Icon(Icons.check_circle, color: Color(0xFF10B981), size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Check-in Selfie Verified', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A))),
                      Text('Recorded: $formattedCheckIn', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                    ],
                  ),
                ),
                const Text('VERIFIED', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF059669))),
              ],
            ),
          ),
          const SizedBox(height: 10),

          // Checkout Verified
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              children: [
                const Icon(Icons.check_circle, color: Color(0xFF10B981), size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Checkout Selfie Verified', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A))),
                      Text('Recorded: $formattedCheckOut', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                    ],
                  ),
                ),
                const Text('VERIFIED', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF059669))),
              ],
            ),
          ),

          const SizedBox(height: 14),
          const Text(
            'Both check-in and checkout facial verifications were successfully recorded and verified with dispatch.',
            style: TextStyle(fontSize: 12, color: Color(0xFF64748B), height: 1.4),
          ),

          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            height: 42,
            child: OutlinedButton.icon(
              onPressed: () {
                setState(() => _currentTabIndex = 0);
              },
              icon: const Icon(Icons.assignment, size: 16),
              label: const Text('View Assigned Posts / Check In'),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF2563EB),
                side: const BorderSide(color: Color(0xFF2563EB)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// Off-Duty / Before Check-In State
  Widget _buildOffDutyVerificationCard(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        children: [
          Container(
            width: 80,
            height: 80,
            decoration: const BoxDecoration(
              color: Color(0xFFF1F5F9),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.shield_outlined, size: 44, color: Color(0xFF94A3B8)),
          ),
          const SizedBox(height: 16),
          const Text(
            'No active attendance',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17, color: Color(0xFF0F172A)),
          ),
          const SizedBox(height: 8),
          const Text(
            'You are currently off-duty. Attendance selfies are securely verified during your duty check-in and checkout flows.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: Color(0xFF64748B), height: 1.4),
          ),
          const SizedBox(height: 20),

          // Process Explanation
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: const Column(
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.login, size: 18, color: Color(0xFF2563EB)),
                    SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('1. Check-in Selfie', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A))),
                          Text('Captured when starting duty at your assigned post to verify physical presence.', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                        ],
                      ),
                    ),
                  ],
                ),
                Divider(height: 16, color: Color(0xFFE2E8F0)),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.logout, size: 18, color: Color(0xFFD97706)),
                    SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('2. Checkout Selfie', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A))),
                          Text('Captured when ending duty to verify completion of shift.', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          const SizedBox(height: 22),
          SizedBox(
            width: double.infinity,
            height: 44,
            child: ElevatedButton.icon(
              onPressed: () {
                setState(() => _currentTabIndex = 0);
              },
              icon: const Icon(Icons.login, color: Colors.white, size: 18),
              label: const Text('Go to Duty Check-In', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF2563EB),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ==========================================
  // TAB 3: LOCATION & POST STATUS
  // ==========================================
  Widget _buildLocationTab(BuildContext context, bool isOnDuty) {
    // Resolve current assigned post
    String? assignedSiteName;
    double? assignedSiteLat;
    double? assignedSiteLng;

    if (isOnDuty && _activeDuty != null) {
      assignedSiteName = _activeDuty!['site_name']?.toString();
      assignedSiteLat = double.tryParse(_activeDuty!['site_lat']?.toString() ?? _activeDuty!['site_latitude']?.toString() ?? '');
      assignedSiteLng = double.tryParse(_activeDuty!['site_lng']?.toString() ?? _activeDuty!['site_longitude']?.toString() ?? '');
    } else if (_assignments.isNotEmpty) {
      final primary = _assignments.first;
      assignedSiteName = primary['site_name']?.toString();
      assignedSiteLat = double.tryParse(primary['site_lat']?.toString() ?? primary['site_latitude']?.toString() ?? primary['latitude']?.toString() ?? '');
      assignedSiteLng = double.tryParse(primary['site_lng']?.toString() ?? primary['site_longitude']?.toString() ?? primary['longitude']?.toString() ?? '');
    }

    return RefreshIndicator(
      onRefresh: _fetchTabLocation,
      child: ListView(
        padding: const EdgeInsets.all(16.0),
        children: [
          // Header
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.location_on, color: Color(0xFF2563EB), size: 22),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Location & Post Status',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                    ),
                    Text(
                      'Check your current location and assigned post status.',
                      style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),

          // SECTION A: Location Permission
          _buildPermissionSection(),
          const SizedBox(height: 14),

          // SECTION B: Current Location
          _buildCurrentLocationSection(),
          const SizedBox(height: 14),

          // SECTION C: Assigned Post & Geofence
          _buildAssignedPostSection(
            siteName: assignedSiteName,
            siteLat: assignedSiteLat,
            siteLng: assignedSiteLng,
            isOnDuty: isOnDuty,
          ),
          const SizedBox(height: 14),

          // SECTION D: Live Tracking
          _buildLiveTrackingSection(isOnDuty),
          const SizedBox(height: 16),

          // SECTION E: Actions
          _buildLocationActions(context, isOnDuty),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  /// Section A: Location Permission Status Card
  Widget _buildPermissionSection() {
    final bool isServiceDisabled = !_locationServiceEnabled;
    final bool isDeniedForever = _permissionStatus == LocationPermission.deniedForever;
    final bool isDenied = _permissionStatus == LocationPermission.denied;
    final bool isGranted = !isServiceDisabled && !isDenied && !isDeniedForever;

    String statusLabel = 'Enabled';
    Color statusColor = const Color(0xFF059669);
    Color bgColor = const Color(0xFFECFDF5);
    Color borderColor = const Color(0xFFA7F3D0);
    IconData statusIcon = Icons.check_circle;
    String description = 'Location services and permissions are active for high-accuracy GPS verification.';

    if (isServiceDisabled) {
      statusLabel = 'Required';
      statusColor = const Color(0xFFDC2626);
      bgColor = const Color(0xFFFEF2F2);
      borderColor = const Color(0xFFFECACA);
      statusIcon = Icons.location_off;
      description = 'Device GPS location service is turned off. Please turn it on in device settings.';
    } else if (isDeniedForever) {
      statusLabel = 'Permission Denied';
      statusColor = const Color(0xFFDC2626);
      bgColor = const Color(0xFFFEF2F2);
      borderColor = const Color(0xFFFECACA);
      statusIcon = Icons.block;
      description = 'Location permission is permanently denied. Please enable location access in application settings.';
    } else if (isDenied) {
      statusLabel = 'Required';
      statusColor = const Color(0xFFD97706);
      bgColor = const Color(0xFFFFFBEB);
      borderColor = const Color(0xFFFDE68A);
      statusIcon = Icons.warning_amber_rounded;
      description = 'Location permission is required to verify your physical presence at your assigned post.';
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.security, size: 18, color: Color(0xFF64748B)),
                  SizedBox(width: 8),
                  Text(
                    'Location Permission',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: bgColor,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: borderColor),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(statusIcon, size: 12, color: statusColor),
                    const SizedBox(width: 5),
                    Text(
                      statusLabel,
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: statusColor),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(description, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B), height: 1.4)),

          if (!isGranted) ...[
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              height: 38,
              child: ElevatedButton.icon(
                onPressed: () async {
                  if (isServiceDisabled) {
                    await LocationService.openLocationSettings();
                  } else if (isDeniedForever) {
                    await LocationService.openAppSettings();
                  } else {
                    await LocationService.requestPermission();
                  }
                  _fetchTabLocation();
                },
                icon: const Icon(Icons.settings, size: 16, color: Colors.white),
                label: Text(
                  isServiceDisabled ? 'Turn On Device Location' : (isDeniedForever ? 'Open App Settings' : 'Enable Location Permission'),
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Colors.white),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: isServiceDisabled || isDeniedForever ? const Color(0xFFDC2626) : const Color(0xFF2563EB),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  /// Section B: Current Location Card
  Widget _buildCurrentLocationSection() {
    final bool hasPosition = _livePosition != null;
    final bool isChecking = _isRefreshingLocation;

    String availabilityLabel = hasPosition ? 'Available' : (isChecking ? 'Checking Location...' : 'Location Unavailable');
    Color availColor = hasPosition ? const Color(0xFF059669) : (isChecking ? const Color(0xFF2563EB) : const Color(0xFFDC2626));
    Color availBg = hasPosition ? const Color(0xFFECFDF5) : (isChecking ? const Color(0xFFEFF6FF) : const Color(0xFFFEF2F2));
    Color availBorder = hasPosition ? const Color(0xFFA7F3D0) : (isChecking ? const Color(0xFFBFDBFE) : const Color(0xFFFECACA));

    String accuracyText = hasPosition ? '±${_livePosition!.accuracy.round()} m' : '--';
    String lastUpdatedText = hasPosition ? _formatTimeAgo(LocationService.lastPositionTime ?? DateTime.now()) : '--';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.my_location, size: 18, color: Color(0xFF2563EB)),
                  SizedBox(width: 8),
                  Text(
                    'Current Location',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: availBg,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: availBorder),
                ),
                child: Text(
                  availabilityLabel,
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: availColor),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Essential GPS info
          Row(
            children: [
              Expanded(
                child: _buildGpsMetric(
                  label: 'GPS Accuracy',
                  value: accuracyText,
                  icon: Icons.gps_fixed,
                  isHighlighted: hasPosition && _livePosition!.accuracy <= 50,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildGpsMetric(
                  label: 'Last Updated',
                  value: lastUpdatedText,
                  icon: Icons.access_time,
                  isHighlighted: false,
                ),
              ),
            ],
          ),

          if (_locationStatusMessage != null) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF2F2),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFFECACA)),
              ),
              child: Text(
                _locationStatusMessage!,
                style: const TextStyle(fontSize: 12, color: Color(0xFFDC2626)),
              ),
            ),
          ],

          // Collapsible Technical Details
          if (hasPosition) ...[
            const SizedBox(height: 12),
            InkWell(
              onTap: () {
                setState(() => _showTechnicalDetails = !_showTechnicalDetails);
              },
              borderRadius: BorderRadius.circular(8),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 4.0),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Technical Details',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF64748B)),
                    ),
                    Icon(
                      _showTechnicalDetails ? Icons.keyboard_arrow_up : Icons.keyboard_arrow_down,
                      size: 18,
                      color: const Color(0xFF64748B),
                    ),
                  ],
                ),
              ),
            ),

            if (_showTechnicalDetails) ...[
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  children: [
                    _buildGpsRow('Latitude', _livePosition!.latitude.toStringAsFixed(6)),
                    const SizedBox(height: 6),
                    _buildGpsRow('Longitude', _livePosition!.longitude.toStringAsFixed(6)),
                    const SizedBox(height: 6),
                    _buildGpsRow('Altitude', '${_livePosition!.altitude.toStringAsFixed(1)} m'),
                    const SizedBox(height: 6),
                    _buildGpsRow('Speed', '${(_livePosition!.speed * 3.6).toStringAsFixed(1)} km/h'),
                    const SizedBox(height: 6),
                    _buildGpsRow('Heading', '${_livePosition!.heading.toStringAsFixed(1)}°'),
                  ],
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }

  /// Section C: Assigned Post & Geofence Card
  Widget _buildAssignedPostSection({
    required String? siteName,
    required double? siteLat,
    required double? siteLng,
    required bool isOnDuty,
  }) {
    if (siteName == null) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.storefront, size: 18, color: Color(0xFF64748B)),
                SizedBox(width: 8),
                Text('Assigned Post', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A))),
              ],
            ),
            SizedBox(height: 8),
            Text('No active post assignment found for today.', style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
          ],
        ),
      );
    }

    double? distanceMeters;
    if (_livePosition != null && siteLat != null && siteLng != null) {
      distanceMeters = DistanceFormatter.distanceBetweenMeters(
        _livePosition!.latitude,
        _livePosition!.longitude,
        siteLat,
        siteLng,
      );
    }

    final geoInfo = DistanceFormatter.evaluateStatus(
      isLocating: _isRefreshingLocation,
      locationError: _locationStatusMessage,
      distanceMeters: distanceMeters,
      accuracy: _livePosition?.accuracy,
      siteName: siteName,
      allowedRadius: 150.0,
    );

    final formattedDistance = distanceMeters != null ? DistanceFormatter.formatDistance(distanceMeters) : '--';
    final isWithin = geoInfo.isWithin;
    final isOutside = geoInfo.state == GeofenceStatusState.outsideArea;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: geoInfo.borderColor,
          width: isWithin || isOutside ? 1.5 : 1.0,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.storefront, size: 18, color: Color(0xFF2563EB)),
                  SizedBox(width: 8),
                  Text(
                    'Assigned Post',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: geoInfo.backgroundColor,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: geoInfo.borderColor),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(geoInfo.icon, size: 12, color: geoInfo.primaryColor),
                    const SizedBox(width: 5),
                    Text(
                      geoInfo.title,
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: geoInfo.primaryColor),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          Text(
            siteName,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
          ),
          const SizedBox(height: 6),

          // Distance and allowed radius summary
          Row(
            children: [
              const Icon(Icons.navigation, size: 14, color: Color(0xFF64748B)),
              const SizedBox(width: 6),
              Text(
                '$formattedDistance from post',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: isWithin ? const Color(0xFF059669) : (isOutside ? const Color(0xFFB45309) : const Color(0xFF64748B)),
                ),
              ),
              const SizedBox(width: 12),
              const Text('•', style: TextStyle(color: Color(0xFFCBD5E1))),
              const SizedBox(width: 12),
              const Text(
                'Allowed Radius: 150 m',
                style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
              ),
            ],
          ),

          if (isOutside) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFFFFBEB),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFFDE68A)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.info_outline, size: 16, color: Color(0xFFD97706)),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Outside Assigned Area ($formattedDistance). Move within 150 m of $siteName to check in or out.',
                      style: const TextStyle(fontSize: 12, color: Color(0xFF92400E)),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  /// Section D: Live Tracking Status Card
  Widget _buildLiveTrackingSection(bool isOnDuty) {
    final hasSyncError = LocationService.lastSyncError != null && !LocationService.lastSyncSuccess;
    final lastSyncTime = LocationService.lastSyncTime;

    String trackingStatus = 'Inactive';
    Color trackingColor = const Color(0xFF64748B);
    Color trackingBg = const Color(0xFFF1F5F9);
    Color trackingBorder = const Color(0xFFCBD5E1);
    String trackingDesc = 'Live tracking starts automatically after check-in.';

    if (isOnDuty) {
      if (hasSyncError) {
        trackingStatus = 'Connection Issue';
        trackingColor = const Color(0xFFDC2626);
        trackingBg = const Color(0xFFFEF2F2);
        trackingBorder = const Color(0xFFFECACA);
        trackingDesc = 'Unable to send location updates to dispatch. Please check your internet connectivity.';
      } else {
        trackingStatus = 'Active';
        trackingColor = const Color(0xFF059669);
        trackingBg = const Color(0xFFECFDF5);
        trackingBorder = const Color(0xFFA7F3D0);
        trackingDesc = 'Location updates are being sent to dispatch.';
      }
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: isOnDuty ? const Color(0xFF86EFAC) : const Color(0xFFE2E8F0),
          width: isOnDuty ? 1.5 : 1,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.track_changes, size: 18, color: Color(0xFF2563EB)),
                  SizedBox(width: 8),
                  Text(
                    'Live Tracking',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: trackingBg,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: trackingBorder),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CircleAvatar(
                      radius: 4,
                      backgroundColor: trackingColor,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      trackingStatus,
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: trackingColor),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(trackingDesc, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B), height: 1.4)),

          if (isOnDuty) ...[
            const SizedBox(height: 10),
            Row(
              children: [
                const Icon(Icons.sync, size: 14, color: Color(0xFF64748B)),
                const SizedBox(width: 6),
                Text(
                  lastSyncTime != null ? 'Last sync: ${_formatTimeAgo(lastSyncTime)}' : 'Syncing with dispatch...',
                  style: const TextStyle(fontSize: 12, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  /// Section E: Actions
  Widget _buildLocationActions(BuildContext context, bool isOnDuty) {
    final bool isDenied = _permissionStatus == LocationPermission.denied || _permissionStatus == LocationPermission.deniedForever;
    final bool isServiceDisabled = !_locationServiceEnabled;

    return Column(
      children: [
        SizedBox(
          width: double.infinity,
          height: 44,
          child: OutlinedButton.icon(
            onPressed: _isRefreshingLocation ? null : _fetchTabLocation,
            icon: _isRefreshingLocation
                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF2563EB)))
                : const Icon(Icons.refresh, size: 18, color: Color(0xFF2563EB)),
            label: Text(
              _isRefreshingLocation ? 'Refreshing Location...' : 'Refresh Location',
              style: const TextStyle(color: Color(0xFF2563EB), fontWeight: FontWeight.bold, fontSize: 14),
            ),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: Color(0xFF2563EB)),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
          ),
        ),

        if (isDenied || isServiceDisabled) ...[
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            height: 44,
            child: ElevatedButton.icon(
              onPressed: () async {
                if (isServiceDisabled) {
                  await LocationService.openLocationSettings();
                } else if (_permissionStatus == LocationPermission.deniedForever) {
                  await LocationService.openAppSettings();
                } else {
                  await LocationService.requestPermission();
                }
                _fetchTabLocation();
              },
              icon: const Icon(Icons.lock_open, size: 18, color: Colors.white),
              label: const Text(
                'Enable Location Permission',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF2563EB),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            ),
          ),
        ],

        if (!isOnDuty) ...[
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            height: 44,
            child: ElevatedButton.icon(
              onPressed: () {
                setState(() => _currentTabIndex = 0);
              },
              icon: const Icon(Icons.assignment, size: 18, color: Colors.white),
              label: const Text(
                'Go to Duty Check-In',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0F172A),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            ),
          ),
        ],
      ],
    );
  }

  /// Metric Card for Section B
  Widget _buildGpsMetric({
    required String label,
    required String value,
    required IconData icon,
    required bool isHighlighted,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 18, color: isHighlighted ? const Color(0xFF059669) : const Color(0xFF64748B)),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: isHighlighted ? const Color(0xFF059669) : const Color(0xFF0F172A),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// Formatter for elapsed time
  String _formatTimeAgo(DateTime time) {
    final diff = DateTime.now().difference(time);
    if (diff.inSeconds < 15) return 'Just now';
    if (diff.inSeconds < 60) return '${diff.inSeconds}s ago';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    return '${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}';
  }

  Widget _buildGpsRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
        Text(
          value,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF0F172A), fontFamily: 'monospace'),
        ),
      ],
    );
  }

  Widget _buildQuickActionCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withAlpha(25),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A))),
                  Text(subtitle, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
