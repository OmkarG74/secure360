import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/services/api_service.dart';
import '../../../core/services/location_service.dart';
import '../../../core/utils/time_formatter.dart';
import '../../attendance/screens/check_in_screen.dart';
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

  bool _isCheckingOut = false;
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

  /// Check-out modal dialog with real GPS acquisition and checkout API submission
  Future<void> _handleCheckOut() async {
    if (_isCheckingOut || _activeDuty == null) return;

    final notesController = TextEditingController();

    final shouldProceed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: MediaQuery.of(ctx).viewInsets.bottom + 20,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF2F2),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(Icons.exit_to_app, color: Color(0xFFDC2626), size: 22),
                ),
                const SizedBox(width: 12),
                const Text(
                  'Confirm Duty Check-Out',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              'You are checking out of duty from ${_activeDuty?['site_name'] ?? 'your post'}.\nDevice GPS will be logged and live telemetry will be terminated.',
              style: const TextStyle(fontSize: 13, color: Color(0xFF475569), height: 1.4),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: notesController,
              maxLines: 2,
              decoration: InputDecoration(
                hintText: 'Handover remarks (e.g. Relieved by Guard Sarah; All perimeter secure)',
                hintStyle: const TextStyle(fontSize: 13, color: Color(0xFF94A3B8)),
                filled: true,
                fillColor: const Color(0xFFF8FAFC),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                  borderSide: const BorderSide(color: Color(0xFFCBD5E1)),
                ),
              ),
            ),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: () => Navigator.of(ctx).pop(true),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFDC2626),
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                elevation: 0,
              ),
              child: const Text('Confirm & Check Out', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15)),
            ),
          ],
        ),
      ),
    );

    if (shouldProceed != true) return;

    setState(() => _isCheckingOut = true);

    // Acquire current GPS position for checkout record
    double lat = 0.0;
    double lng = 0.0;
    try {
      final pos = await LocationService.getCurrentLocation();
      if (pos != null) {
        lat = pos.latitude;
        lng = pos.longitude;
      }
    } catch (e) {
      debugPrint('[CheckOut] GPS warning: $e');
    }

    final attendanceId = _activeDuty?['id'] != null ? int.tryParse(_activeDuty!['id'].toString()) : null;

    final response = await ApiService.checkOut(
      attendanceId: attendanceId,
      latitude: lat,
      longitude: lng,
      notes: notesController.text.trim(),
    );

    if (!mounted) return;

    if (response.success) {
      LocationService.stopLiveTracking();

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Row(
            children: [
              Icon(Icons.check_circle, color: Colors.white, size: 20),
              SizedBox(width: 8),
              Text('Duty completed successfully. Status set to Off-Duty.'),
            ],
          ),
          backgroundColor: Color(0xFF10B981),
        ),
      );

      await _loadDashboardData();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Checkout failed: ${response.message}'),
          backgroundColor: const Color(0xFFDC2626),
        ),
      );
    }

    if (mounted) {
      setState(() => _isCheckingOut = false);
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
                border: Border.all(color: const Color(0xFF86EFAC), width: 1.5),
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
                        'Live GPS Telemetry: Transmitting every 30s',
                        style: TextStyle(fontSize: 12, color: Color(0xFF059669), fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    height: 46,
                    child: ElevatedButton.icon(
                      onPressed: _isCheckingOut ? null : _handleCheckOut,
                      icon: _isCheckingOut
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Icon(Icons.exit_to_app, color: Colors.white, size: 18),
                      label: Text(
                        _isCheckingOut ? 'Checking Out...' : 'Check-Out of Duty Post',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
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

              return Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          shiftName,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF2563EB),
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
                    const SizedBox(height: 4),
                    Text(
                      siteAddress,
                      style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                    ),
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
                          onPressed: () async {
                            final result = await Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => CheckInScreen(
                                  siteId: siteId is int ? siteId : int.parse(siteId.toString()),
                                  siteName: siteName,
                                  siteAddress: siteAddress,
                                  assignmentId: assignmentId != null ? (assignmentId is int ? assignmentId : int.tryParse(assignmentId.toString())) : null,
                                  shiftName: shiftName,
                                ),
                              ),
                            );

                            if (result == true || mounted) {
                              _loadDashboardData();
                            }
                          },
                          icon: const Icon(Icons.qr_code_scanner, size: 18, color: Colors.white),
                          label: const Text('Check In to Duty', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF10B981),
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
                      SizedBox(
                        width: double.infinity,
                        height: 42,
                        child: ElevatedButton.icon(
                          onPressed: () async {
                            final result = await Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => CheckInScreen(
                                  siteId: siteId is int ? siteId : int.parse(siteId.toString()),
                                  siteName: siteName,
                                  siteAddress: siteAddress,
                                  assignmentId: assignmentId != null ? (assignmentId is int ? assignmentId : int.tryParse(assignmentId.toString())) : null,
                                  shiftName: shiftName,
                                ),
                              ),
                            );

                            if (result == true || mounted) {
                              _loadDashboardData();
                            }
                          },
                          icon: const Icon(Icons.login, size: 16, color: Colors.white),
                          label: const Text('Check In to Duty Post', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF10B981),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
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
