import 'dart:io';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'front_camera_selfie_screen.dart';
import '../../../core/services/api_service.dart';
import '../../../core/services/location_service.dart';
import '../../../core/utils/distance_formatter.dart';
import '../../../core/utils/time_formatter.dart';

class CheckOutScreen extends StatefulWidget {
  final int attendanceId;
  final String siteName;
  final int? siteId;
  final double? siteLatitude;
  final double? siteLongitude;
  final String? checkInTime;
  final String? shiftName;

  const CheckOutScreen({
    super.key,
    required this.attendanceId,
    required this.siteName,
    this.siteId,
    this.siteLatitude,
    this.siteLongitude,
    this.checkInTime,
    this.shiftName,
  });

  @override
  State<CheckOutScreen> createState() => _CheckOutScreenState();
}

class _CheckOutScreenState extends State<CheckOutScreen> {
  final _notesController = TextEditingController();

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

  /// Acquire current device GPS coordinates and calculate distance to assigned post
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

  /// Capture mandatory fresh front-camera checkout selfie
  Future<void> _captureSelfie() async {
    setState(() {
      _isCapturingSelfie = true;
      _selfieError = null;
    });

    try {
      final File? capturedFile = await Navigator.of(context).push<File>(
        MaterialPageRoute(builder: (_) => const FrontCameraSelfieScreen()),
      );

      if (!mounted) return;

      setState(() {
        _isCapturingSelfie = false;
<<<<<<< HEAD
        if (photo != null) {
          _selfieFile = File(photo.path);
=======
        if (capturedFile != null) {
          _selfieFile = capturedFile;
          _selfieError = null;
          _submissionError = null;
>>>>>>> a228257 (Add selfie camera and authentication hardening)
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

  /// Complete checkout flow: fresh GPS -> fresh selfie upload -> API checkout -> stop tracking
  Future<void> _submitCheckOut() async {
    if (_currentPosition == null) {
      setState(() => _submissionError = 'Device GPS location is required. Please tap "Refresh GPS".');
      return;
    }

    if (_distanceMeters != null && !_isWithinGeofence) {
      setState(() => _submissionError = 'You are outside your assigned post area. Return to the assigned post before checking out.');
      return;
    }

    if (_selfieFile == null) {
      setState(() => _submissionError = 'A newly taken front-camera checkout selfie is required.');
      return;
    }

    setState(() {
      _isSubmitting = true;
      _submissionError = null;
    });

    int? checkoutSelfieId;

    // Step 1: Upload fresh checkout selfie
    try {
      final selfieResponse = await ApiService.submitSelfie(
        filePath: _selfieFile!.path,
        attendanceId: widget.attendanceId,
      );

      if (selfieResponse.success && selfieResponse.data != null) {
        final rawId = selfieResponse.data!['selfie_id'];
        if (rawId != null) {
          checkoutSelfieId = rawId is int ? rawId : int.tryParse(rawId.toString());
        }
      } else {
        if (!mounted) return;
        setState(() {
          _isSubmitting = false;
          _submissionError = 'Checkout selfie upload failed: ${selfieResponse.message}';
        });
        return;
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isSubmitting = false;
        _submissionError = 'Failed to upload checkout selfie: $e';
      });
      return;
    }

    // Step 2: Submit checkout request to backend
    final checkOutResponse = await ApiService.checkOut(
      attendanceId: widget.attendanceId,
      selfieId: checkoutSelfieId,
      latitude: _currentPosition!.latitude,
      longitude: _currentPosition!.longitude,
      address: widget.siteName,
      notes: _notesController.text.trim(),
    );

    if (!mounted) return;

    if (checkOutResponse.success) {
      // Step 3: Stop live location telemetry after successful checkout
      LocationService.stopLiveTracking();

      setState(() => _isSubmitting = false);

      // Show confirmation dialog then navigate back to dashboard
      await showDialog(
        context: context,
        barrierDismissible: false,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          title: const Row(
            children: [
              Icon(Icons.check_circle, color: Color(0xFF10B981), size: 28),
              SizedBox(width: 10),
              Text('Check-Out Verified', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Duty ended at ${widget.siteName}.',
                style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
              ),
              const SizedBox(height: 8),
              const Text(
                '• Fresh checkout selfie verified.\n• Final GPS position recorded.\n• Live patrol tracking has stopped.\n• Status updated to OFF-DUTY.',
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
              child: const Text('Return to Home', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ],
        ),
      );
    } else {
      setState(() {
        _isSubmitting = false;
        _submissionError = checkOutResponse.message;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
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
          'Duty Post Check-Out',
          style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Active Duty Summary Card
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
                          color: const Color(0xFFFEF2F2),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          widget.shiftName ?? 'Active Shift',
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFFDC2626)),
                        ),
                      ),
                      const Spacer(),
                      const Text('Completing Duty', style: TextStyle(fontSize: 12, color: Color(0xFF94A3B8))),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    widget.siteName,
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                  ),
                  if (widget.checkInTime != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      'Checked in: ${TimeFormatter.formatDateTime(widget.checkInTime!)}',
                      style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Step 1: Real GPS Check & Geofence Verification Card
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
                            ? (_isWithinGeofence ? Icons.check_circle : Icons.warning_amber_rounded)
                            : Icons.gps_not_fixed,
                        color: _currentPosition != null
                            ? (_isWithinGeofence ? const Color(0xFF10B981) : const Color(0xFFDC2626))
                            : const Color(0xFF64748B),
                        size: 20,
                      ),
                      const SizedBox(width: 8),
                      const Text(
                        'Location Verification',
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
                          label: const Text('Refresh GPS', style: TextStyle(fontSize: 12, color: Color(0xFF2563EB))),
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
                              'You are outside your assigned post area. Return to the assigned post before checking out.',
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
                                      : 'GPS Position Acquired',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF166534)),
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
                    const Text('Acquiring current GPS location...', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Step 2: Fresh Front-Camera Checkout Selfie Card
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
                        'Fresh Checkout Selfie Verification',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'A freshly taken selfie is required at checkout to verify your post handover.',
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
                          _isCapturingSelfie ? 'Opening Camera...' : 'Take Checkout Selfie',
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

            // Step 3: Handover remarks / notes
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
                  const Text(
                    'Handover Remarks (Optional)',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _notesController,
                    maxLines: 2,
                    decoration: InputDecoration(
                      hintText: 'e.g. Relieved by Sarah; all posts secured.',
                      hintStyle: const TextStyle(fontSize: 13, color: Color(0xFF94A3B8)),
                      filled: true,
                      fillColor: const Color(0xFFF8FAFC),
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

            // Submission Error Banner
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
                    const Icon(Icons.error, size: 20, color: Color(0xFFDC2626)),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _submissionError!,
                        style: const TextStyle(color: Color(0xFF991B1B), fontSize: 13, fontWeight: FontWeight.w500),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
            ],

            // Confirm & Check Out Button
            SizedBox(
              height: 50,
              child: ElevatedButton(
                onPressed: _isSubmitting ? null : _submitCheckOut,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFDC2626),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  elevation: 0,
                ),
                child: _isSubmitting
                    ? const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
                          SizedBox(width: 12),
                          Text('Verifying & Checking Out...', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15)),
                        ],
                      )
                    : const Text('Confirm & Check Out', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15)),
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}
