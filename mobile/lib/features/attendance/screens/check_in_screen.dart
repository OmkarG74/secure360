import 'dart:io';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/services/api_service.dart';
import '../../../core/services/location_service.dart';
import '../../../core/utils/distance_formatter.dart';
import '../../../core/utils/time_formatter.dart';

class CheckInScreen extends StatefulWidget {
  final int siteId;
  final String siteName;
  final String? siteAddress;
  final int? assignmentId;
  final String? shiftName;
  final double? siteLatitude;
  final double? siteLongitude;
  final String? startTime;
  final String? endTime;

  const CheckInScreen({
    super.key,
    required this.siteId,
    required this.siteName,
    this.siteAddress,
    this.assignmentId,
    this.shiftName,
    this.siteLatitude,
    this.siteLongitude,
    this.startTime,
    this.endTime,
  });

  @override
  State<CheckInScreen> createState() => _CheckInScreenState();
}

class _CheckInScreenState extends State<CheckInScreen> {
  final _notesController = TextEditingController();
  final _picker = ImagePicker();

  Position? _currentPosition;
  bool _isLocating = false;
  String? _locationError;
  double? _distanceMeters;
  bool _isWithinGeofence = true;

  File? _selfieFile;
  bool _isCapturingSelfie = false;
  String? _selfieError;

  bool _isSubmitting = false;
  String? _submissionError;

  static const double geofenceRadius = 150.0;

  @override
  void initState() {
    super.initState();
    _fetchLiveGps();
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  /// Request and acquire real device GPS coordinates
  Future<void> _fetchLiveGps() async {
    setState(() {
      _isLocating = true;
      _locationError = null;
    });

    try {
      final pos = await LocationService.getCurrentLocation(
        accuracy: LocationAccuracy.high,
        timeout: const Duration(seconds: 10),
      );

      if (!mounted) return;

      double? dist;
      bool within = true;

      if (pos != null && widget.siteLatitude != null && widget.siteLongitude != null) {
        dist = Geolocator.distanceBetween(
          pos.latitude,
          pos.longitude,
          widget.siteLatitude!,
          widget.siteLongitude!,
        );
        within = dist <= geofenceRadius;
      }

      setState(() {
        _currentPosition = pos;
        _distanceMeters = dist;
        _isWithinGeofence = within;
        _isLocating = false;
      });
    } on LocationServiceDisabledException {
      if (!mounted) return;
      setState(() {
        _isLocating = false;
        _locationError = 'Device GPS / Location Services are turned off. Please turn on GPS in device settings.';
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLocating = false;
        _locationError = e.toString().replaceAll('Exception: ', '');
      });
    }
  }

  /// Check shift timing availability
  Map<String, dynamic> _getShiftStatus() {
    if (widget.startTime == null || widget.endTime == null) {
      return {'allowed': true, 'state': 'active', 'message': 'Shift is active.'};
    }

    final now = DateTime.now();
    final startParts = widget.startTime!.split(':').map(int.parse).toList();
    final endParts = widget.endTime!.split(':').map(int.parse).toList();

    final nowMinutes = now.hour * 60 + now.minute;
    final startMinutes = startParts[0] * 60 + startParts[1];
    final endMinutes = endParts[0] * 60 + endParts[1];

    final formattedStart = TimeFormatter.formatTime(widget.startTime!);

    // Daytime shift (e.g. 08:00 to 16:00)
    if (startMinutes <= endMinutes) {
      if (nowMinutes < startMinutes) {
        return {
          'allowed': false,
          'state': 'before_shift',
          'message': 'Your shift starts at $formattedStart.',
        };
      }
      if (nowMinutes > endMinutes) {
        return {
          'allowed': false,
          'state': 'after_shift',
          'message': 'Your assigned shift has ended.',
        };
      }
      return {'allowed': true, 'state': 'active', 'message': 'Shift is active.'};
    }

    // Overnight shift (e.g. 22:00 to 06:00)
    if (nowMinutes >= startMinutes || nowMinutes <= endMinutes) {
      return {'allowed': true, 'state': 'active', 'message': 'Shift is active.'};
    }

    final midpoint = endMinutes + ((startMinutes - endMinutes) ~/ 2);
    if (nowMinutes <= midpoint) {
      return {
        'allowed': false,
        'state': 'after_shift',
        'message': 'Your assigned shift has ended.',
      };
    }

    return {
      'allowed': false,
      'state': 'before_shift',
      'message': 'Your shift starts at $formattedStart.',
    };
  }

  /// Capture mandatory verification selfie using device front camera
  Future<void> _captureSelfie() async {
    setState(() {
      _isCapturingSelfie = true;
      _selfieError = null;
    });

    try {
      final photo = await _picker.pickImage(
        source: ImageSource.camera,
        preferredCameraDevice: CameraDevice.front,
        imageQuality: 80,
        maxWidth: 1024,
        maxHeight: 1024,
      );

      if (!mounted) return;

      setState(() {
        _isCapturingSelfie = false;
        if (photo != null) {
          _selfieFile = File(photo.path);
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isCapturingSelfie = false;
        _selfieError = 'Camera access failed: $e';
      });
    }
  }

  /// Submit full check-in flow: upload selfie -> submit check-in -> start telemetry
  Future<void> _submitCheckIn() async {
    final shiftStatus = _getShiftStatus();
    if (shiftStatus['allowed'] == false) {
      setState(() => _submissionError = shiftStatus['message']);
      return;
    }

    if (_currentPosition == null) {
      setState(() => _submissionError = 'Real device GPS location is required. Please tap "Refresh".');
      return;
    }

    if (_distanceMeters != null && !_isWithinGeofence) {
      setState(() => _submissionError = 'You are outside your assigned post area. Move closer to check in.');
      return;
    }

    if (_selfieFile == null) {
      setState(() => _submissionError = 'A front-camera verification selfie is required before checking in.');
      return;
    }

    setState(() {
      _isSubmitting = true;
      _submissionError = null;
    });

    int? selfieId;

    // Step 1: Upload Selfie to Backend
    try {
      final selfieResponse = await ApiService.submitSelfie(
        filePath: _selfieFile!.path,
      );

      if (selfieResponse.success && selfieResponse.data != null) {
        final rawId = selfieResponse.data!['selfie_id'];
        if (rawId != null) {
          selfieId = rawId is int ? rawId : int.tryParse(rawId.toString());
        }
      } else {
        if (!mounted) return;
        setState(() {
          _isSubmitting = false;
          _submissionError = 'Selfie upload failed: ${selfieResponse.message}';
        });
        return;
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isSubmitting = false;
        _submissionError = 'Failed to upload verification selfie: $e';
      });
      return;
    }

    // Step 2: Record Check-In
    final checkInResponse = await ApiService.checkIn(
      siteId: widget.siteId,
      assignmentId: widget.assignmentId,
      selfieId: selfieId,
      latitude: _currentPosition!.latitude,
      longitude: _currentPosition!.longitude,
      address: widget.siteAddress ?? widget.siteName,
      notes: _notesController.text.trim(),
    );

    if (!mounted) return;

    if (checkInResponse.success) {
      // Step 3: Start live location tracking
      LocationService.startLiveTracking();

      setState(() => _isSubmitting = false);

      // Show confirmation dialog then navigate to dashboard
      await showDialog(
        context: context,
        barrierDismissible: false,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          title: const Row(
            children: [
              Icon(Icons.check_circle, color: Color(0xFF10B981), size: 28),
              SizedBox(width: 10),
              Text('Check-In Verified', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Duty commenced at ${widget.siteName}.',
                style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
              ),
              const SizedBox(height: 8),
              const Text(
                '• Real-time GPS coordinates logged.\n• Guard verification selfie uploaded.\n• Live patrol telemetry is now active.',
                style: TextStyle(fontSize: 13, color: Color(0xFF475569), height: 1.4),
              ),
            ],
          ),
          actions: [
            ElevatedButton(
              onPressed: () {
                Navigator.of(ctx).pop();
                Navigator.of(context).pop(true); // Return success to dashboard
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF2563EB),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: const Text('Go to Duty Dashboard', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ],
        ),
      );
    } else {
      setState(() {
        _isSubmitting = false;
        _submissionError = checkInResponse.message;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final shiftStatus = _getShiftStatus();
    final isShiftActive = shiftStatus['allowed'] == true;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Color(0xFF0F172A)),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: const Text(
          'Duty Post Check-In',
          style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Site & Shift Summary Card
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: isShiftActive ? const Color(0xFFEFF6FF) : const Color(0xFFFEF2F2),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          widget.shiftName ?? 'Assigned Shift',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: isShiftActive ? const Color(0xFF2563EB) : const Color(0xFFDC2626),
                          ),
                        ),
                      ),
                      const Spacer(),
                      if (widget.startTime != null && widget.endTime != null)
                        Text(
                          TimeFormatter.formatTimeRange(widget.startTime!, widget.endTime!),
                          style: const TextStyle(fontSize: 12, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    widget.siteName,
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                  ),
                  if (widget.siteAddress != null && widget.siteAddress!.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(
                      widget.siteAddress!,
                      style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                    ),
                  ],

                  // Shift status banner if not active
                  if (!isShiftActive) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEF2F2),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFFFCA5A5)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.schedule, size: 18, color: Color(0xFFDC2626)),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              shiftStatus['message'] as String,
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF991B1B)),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Step 1: Real GPS Coordinates & Geofence Card
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: _currentPosition == null
                      ? const Color(0xFFE2E8F0)
                      : (_isWithinGeofence ? const Color(0xFF86EFAC) : const Color(0xFFFCA5A5)),
                  width: _currentPosition != null ? 1.5 : 1,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(
                        _currentPosition != null
                            ? (_isWithinGeofence ? Icons.gps_fixed : Icons.warning_amber_rounded)
                            : Icons.gps_not_fixed,
                        color: _currentPosition != null
                            ? (_isWithinGeofence ? const Color(0xFF10B981) : const Color(0xFFDC2626))
                            : const Color(0xFF64748B),
                        size: 20,
                      ),
                      const SizedBox(width: 8),
                      const Text(
                        'Device GPS Location',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                      ),
                      const Spacer(),
                      if (_isLocating)
                        const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF2563EB)),
                        )
                      else
                        TextButton.icon(
                          onPressed: _fetchLiveGps,
                          icon: const Icon(Icons.refresh, size: 16, color: Color(0xFF2563EB)),
                          label: const Text('Refresh', style: TextStyle(fontSize: 12, color: Color(0xFF2563EB))),
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  if (_locationError != null) ...[
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEF2F2),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFFFCA5A5)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.error_outline, size: 18, color: Color(0xFFDC2626)),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _locationError!,
                              style: const TextStyle(fontSize: 12, color: Color(0xFF991B1B)),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ] else if (_currentPosition != null) ...[
                    if (_distanceMeters != null && !_isWithinGeofence) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF2F2),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFFCA5A5)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const Icon(Icons.location_off, color: Color(0xFFDC2626), size: 18),
                                const SizedBox(width: 8),
                                Text(
                                  'Outside Assigned Area (${DistanceFormatter.formatDistance(_distanceMeters)} away)',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF991B1B)),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'You are outside your assigned post area. Move closer to check in.',
                              style: TextStyle(fontSize: 12, color: Color(0xFFB91C1C), height: 1.3),
                            ),
                          ],
                        ),
                      ),
                    ] else ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF0FDF4),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const Icon(Icons.verified, color: Color(0xFF16A34A), size: 18),
                                const SizedBox(width: 8),
                                Text(
                                  _distanceMeters != null
                                      ? 'Within Assigned Area (${DistanceFormatter.formatDistance(_distanceMeters)} away)'
                                      : 'GPS Location Acquired',
                                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF166534)),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Lat: ${_currentPosition!.latitude.toStringAsFixed(6)} | Lng: ${_currentPosition!.longitude.toStringAsFixed(6)} (±${_currentPosition!.accuracy.toStringAsFixed(1)}m)',
                              style: const TextStyle(fontSize: 11, color: Color(0xFF15803D)),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ] else ...[
                    const Text('Acquiring real-time GPS fix...', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Step 2: Front Camera Selfie Card
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: _selfieFile != null ? const Color(0xFF86EFAC) : const Color(0xFFE2E8F0),
                  width: _selfieFile != null ? 1.5 : 1,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.camera_front, color: Color(0xFF2563EB), size: 20),
                      SizedBox(width: 8),
                      Text(
                        'Front-Camera Selfie Verification',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Photo verification confirms the guard is physically present at the post.',
                    style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                  ),
                  const SizedBox(height: 12),

                  if (_selfieFile != null) ...[
                    Center(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: Image.file(
                          _selfieFile!,
                          width: 140,
                          height: 140,
                          fit: BoxFit.cover,
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Center(
                      child: OutlinedButton.icon(
                        onPressed: _isCapturingSelfie ? null : _captureSelfie,
                        icon: const Icon(Icons.refresh, size: 16, color: Color(0xFF2563EB)),
                        label: const Text('Retake Photo', style: TextStyle(color: Color(0xFF2563EB))),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: Color(0xFF93C5FD)),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                      ),
                    ),
                  ] else ...[
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: ElevatedButton.icon(
                        onPressed: _isCapturingSelfie ? null : _captureSelfie,
                        icon: _isCapturingSelfie
                            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : const Icon(Icons.camera_alt, color: Colors.white, size: 18),
                        label: Text(
                          _isCapturingSelfie ? 'Opening Front Camera...' : 'Capture Front Selfie',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF2563EB),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          elevation: 0,
                        ),
                      ),
                    ),
                  ],

                  if (_selfieError != null) ...[
                    const SizedBox(height: 8),
                    Text(_selfieError!, style: const TextStyle(fontSize: 12, color: Color(0xFFDC2626))),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Step 3: Field Notes
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Field Notes (Optional)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A))),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _notesController,
                    maxLines: 2,
                    decoration: InputDecoration(
                      hintText: 'e.g. Relieved Guard John at Gate 1; Weather clear',
                      hintStyle: const TextStyle(fontSize: 13, color: Color(0xFF94A3B8)),
                      filled: true,
                      fillColor: const Color(0xFFF8FAFC),
                      contentPadding: const EdgeInsets.all(12),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8),
                        borderSide: const BorderSide(color: Color(0xFFCBD5E1)),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Error banner
            if (_submissionError != null) ...[
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF2F2),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFFCA5A5)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.error_outline, size: 20, color: Color(0xFFDC2626)),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _submissionError!,
                        style: const TextStyle(color: Color(0xFF991B1B), fontSize: 13),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
            ],

            // Submit Button
            SizedBox(
              height: 50,
              child: ElevatedButton.icon(
                onPressed: (_isSubmitting || _currentPosition == null || _selfieFile == null || !isShiftActive || !_isWithinGeofence)
                    ? null
                    : _submitCheckIn,
                icon: _isSubmitting
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.check_circle_outline, color: Colors.white),
                label: Text(
                  _isSubmitting ? 'Uploading & Checking In...' : 'Confirm & Check In to Duty',
                  style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Colors.white),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF10B981),
                  disabledBackgroundColor: const Color(0xFFCBD5E1),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  elevation: 0,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
