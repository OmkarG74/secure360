import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/services/api_service.dart';
import '../../../core/services/location_service.dart';
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
  int _unreadNotifications = 0;

  bool _isLoading = true;
  String? _errorMessage;

  Timer? _dutyTicker;


  // Dedicated Selfie tab state
  File? _tabSelfieFile;
  bool _isCapturingTabSelfie = false;
  bool _isUploadingTabSelfie = false;
  String? _tabSelfieSuccessMessage;

  // Dedicated Location tab state
  Position? _livePosition;
  bool _isRefreshingLocation = false;
  String? _locationStatusMessage;

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

    if (!mounted) return;

    setState(() {
      _user = user;
      _activeDuty = activeDuty;
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

  /// Fetch location for Location tab
  Future<void> _fetchTabLocation() async {
    setState(() => _isRefreshingLocation = true);
    try {
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

  /// Capture verification selfie in Selfie tab
  Future<void> _captureTabSelfie() async {
    setState(() => _isCapturingTabSelfie = true);
    try {
      final picker = ImagePicker();
      final photo = await picker.pickImage(
        source: ImageSource.camera,
        preferredCameraDevice: CameraDevice.front,
        imageQuality: 80,
        maxWidth: 1024,
        maxHeight: 1024,
      );

      if (!mounted) return;
      setState(() {
        _isCapturingTabSelfie = false;
        if (photo != null) {
          _tabSelfieFile = File(photo.path);
          _tabSelfieSuccessMessage = null;
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _isCapturingTabSelfie = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Camera access failed: $e'),
          backgroundColor: const Color(0xFFDC2626),
        ),
      );
    }
  }

  /// Upload captured selfie in Selfie tab
  Future<void> _uploadTabSelfie() async {
    if (_tabSelfieFile == null) return;
    setState(() => _isUploadingTabSelfie = true);

    try {
      final attendanceId = _activeDuty?['id'] != null ? int.tryParse(_activeDuty!['id'].toString()) : null;
      final response = await ApiService.submitSelfie(
        filePath: _tabSelfieFile!.path,
        attendanceId: attendanceId,
      );

      if (!mounted) return;
      setState(() => _isUploadingTabSelfie = false);

      if (response.success) {
        setState(() {
          _tabSelfieSuccessMessage = 'Verification selfie uploaded and saved to dispatch server.';
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Row(
              children: [
                Icon(Icons.check_circle, color: Colors.white, size: 20),
                SizedBox(width: 8),
                Text('Verification photo successfully recorded!'),
              ],
            ),
            backgroundColor: Color(0xFF10B981),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Upload failed: ${response.message}'),
            backgroundColor: const Color(0xFFDC2626),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _isUploadingTabSelfie = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Upload error: $e'),
          backgroundColor: const Color(0xFFDC2626),
        ),
      );
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
                  // Tab 2: Selfie
                  _buildSelfieTab(context),
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
                icon: Icon(Icons.camera_alt_outlined),
                activeIcon: Icon(Icons.camera_alt),
                label: 'Selfie',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.my_location_outlined),
                activeIcon: Icon(Icons.my_location),
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
                            'Within Assigned Area (${activeDistance.round()}m from post)',
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
                                'Outside Assigned Area (${activeDistance.round()}m away). Return to post.',
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
                  title: 'Verify Selfie',
                  subtitle: _tabSelfieFile != null ? 'Captured' : 'Ready',
                  icon: Icons.camera_alt,
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
              bool isWithinSite = true;
              if (_livePosition != null && asgn['latitude'] != null && asgn['longitude'] != null) {
                final sLat = double.tryParse(asgn['latitude'].toString());
                final sLng = double.tryParse(asgn['longitude'].toString());
                if (sLat != null && sLng != null) {
                  distToSite = Geolocator.distanceBetween(_livePosition!.latitude, _livePosition!.longitude, sLat, sLng);
                  isWithinSite = distToSite <= 150.0;
                }
              }

              return Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: isShiftActive
                        ? (isWithinSite ? const Color(0xFF86EFAC) : const Color(0xFFFCA5A5))
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

                        // Geofence status pill (if GPS location is available)
                        if (distToSite != null)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: isWithinSite ? const Color(0xFFF0FDF4) : const Color(0xFFFEF2F2),
                              borderRadius: BorderRadius.circular(4),
                              border: Border.all(color: isWithinSite ? const Color(0xFFBBF7D0) : const Color(0xFFFECACA)),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  isWithinSite ? Icons.check : Icons.location_off,
                                  size: 12,
                                  color: isWithinSite ? const Color(0xFF15803D) : const Color(0xFFB91C1C),
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  isWithinSite ? 'Within Post (${distToSite.round()}m)' : 'Outside Post (${distToSite.round()}m)',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                    color: isWithinSite ? const Color(0xFF15803D) : const Color(0xFFB91C1C),
                                  ),
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),

                    // OUTSIDE-GEOFENCE WARNING (if outside area)
                    if (isShiftActive && !isWithinSite && distToSite != null) ...[
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
                                'You are outside your assigned post area (${distToSite.round()}m away). Move closer to check in.',
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
  // TAB 2: SELFIE
  // ==========================================
  Widget _buildSelfieTab(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16.0),
      children: [
        Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFF3E8FF),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.camera_front, color: Color(0xFF7C3AED), size: 22),
            ),
            const SizedBox(width: 12),
            const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Selfie Verification',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
                Text(
                  'Facial verification confirms physical presence at post',
                  style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                ),
              ],
            ),
          ],
        ),
        const SizedBox(height: 18),

        // Photo preview card
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Column(
            children: [
              if (_tabSelfieFile != null) ...[
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.file(
                    _tabSelfieFile!,
                    width: double.infinity,
                    height: 260,
                    fit: BoxFit.cover,
                  ),
                ),
                const SizedBox(height: 14),
                const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.check_circle, color: Color(0xFF10B981), size: 18),
                    SizedBox(width: 6),
                    Text(
                      'Front-Camera Selfie Ready',
                      style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A), fontSize: 14),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: _isCapturingTabSelfie || _isUploadingTabSelfie ? null : _captureTabSelfie,
                        icon: const Icon(Icons.refresh, size: 16),
                        label: const Text('Retake'),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: _isUploadingTabSelfie ? null : _uploadTabSelfie,
                        icon: _isUploadingTabSelfie
                            ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : const Icon(Icons.cloud_upload, size: 16, color: Colors.white),
                        label: const Text('Upload Selfie', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF2563EB),
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                      ),
                    ),
                  ],
                ),
              ] else ...[
                Container(
                  width: 120,
                  height: 120,
                  decoration: const BoxDecoration(
                    color: Color(0xFFF1F5F9),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.person, size: 64, color: Color(0xFF94A3B8)),
                ),
                const SizedBox(height: 16),
                const Text(
                  'No Verification Selfie Taken',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Capture a clear, well-lit photo using the front camera before or during duty.',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  height: 46,
                  child: ElevatedButton.icon(
                    onPressed: _isCapturingTabSelfie ? null : _captureTabSelfie,
                    icon: _isCapturingTabSelfie
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.camera_alt, color: Colors.white, size: 18),
                    label: const Text('Take Front-Camera Selfie', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF7C3AED),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),

        if (_tabSelfieSuccessMessage != null) ...[
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFECFDF5),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFA7F3D0)),
            ),
            child: Row(
              children: [
                const Icon(Icons.verified, color: Color(0xFF059669), size: 18),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _tabSelfieSuccessMessage!,
                    style: const TextStyle(fontSize: 12, color: Color(0xFF059669), fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  // ==========================================
  // TAB 3: LOCATION
  // ==========================================
  Widget _buildLocationTab(BuildContext context, bool isOnDuty) {
    return ListView(
      padding: const EdgeInsets.all(16.0),
      children: [
        Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFECFDF5),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.my_location, color: Color(0xFF059669), size: 22),
            ),
            const SizedBox(width: 12),
            const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Live GPS Telemetry',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
                Text(
                  'Real-time coordinates logged to dispatch center',
                  style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                ),
              ],
            ),
          ],
        ),
        const SizedBox(height: 18),

        // Live Telemetry Status Card
        Container(
          padding: const EdgeInsets.all(18),
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
                  const Text(
                    'Telemetry State',
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: isOnDuty ? const Color(0xFFECFDF5) : const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(
                        color: isOnDuty ? const Color(0xFFA7F3D0) : const Color(0xFFCBD5E1),
                      ),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        CircleAvatar(
                          radius: 4,
                          backgroundColor: isOnDuty ? const Color(0xFF10B981) : const Color(0xFF94A3B8),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          isOnDuty ? 'TRANSMITTING' : 'STANDBY (OFF-DUTY)',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: isOnDuty ? const Color(0xFF059669) : const Color(0xFF64748B),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                isOnDuty
                    ? 'Background telemetry is transmitting your GPS location every 30 seconds to the supervisor console.'
                    : 'Telemetry transmission is suspended while off-duty. Updates resume automatically upon duty check-in.',
                style: const TextStyle(fontSize: 13, color: Color(0xFF64748B), height: 1.4),
              ),
              const Divider(height: 24, color: Color(0xFFF1F5F9)),

              // Coordinates
              if (_livePosition != null) ...[
                _buildGpsRow('Latitude', _livePosition!.latitude.toStringAsFixed(6)),
                const SizedBox(height: 6),
                _buildGpsRow('Longitude', _livePosition!.longitude.toStringAsFixed(6)),
                const SizedBox(height: 6),
                _buildGpsRow('Accuracy', '±${_livePosition!.accuracy.toStringAsFixed(1)} meters'),
                const SizedBox(height: 6),
                _buildGpsRow('Altitude', '${_livePosition!.altitude.toStringAsFixed(1)} m'),
              ] else ...[
                const Center(
                  child: Padding(
                    padding: EdgeInsets.symmetric(vertical: 12),
                    child: Text('Acquiring device GPS satellite fix...', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 13)),
                  ),
                ),
              ],
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                height: 42,
                child: OutlinedButton.icon(
                  onPressed: _isRefreshingLocation ? null : _fetchTabLocation,
                  icon: _isRefreshingLocation
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF059669)))
                      : const Icon(Icons.refresh, size: 16, color: Color(0xFF059669)),
                  label: const Text('Refresh GPS Fix', style: TextStyle(color: Color(0xFF059669), fontWeight: FontWeight.bold)),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: Color(0xFF059669)),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                ),
              ),
            ],
          ),
        ),
        if (_locationStatusMessage != null) ...[
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(12),
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
      ],
    );
  }

  Widget _buildGpsRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(fontSize: 13, color: Color(0xFF64748B))),
        Text(value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF0F172A), fontFamily: 'monospace')),
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
