import 'dart:io';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/services/api_service.dart';
import '../../../core/services/location_service.dart';

class CheckInScreen extends StatefulWidget {
  final int siteId;
  final String siteName;
  final String? siteAddress;
  final int? assignmentId;
  final String? shiftName;

  const CheckInScreen({
    super.key,
    required this.siteId,
    required this.siteName,
    this.siteAddress,
    this.assignmentId,
    this.shiftName,
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

  File? _selfieFile;
  bool _isCapturingSelfie = false;
  String? _selfieError;

  bool _isSubmitting = false;
  String? _submissionError;

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
      final pos = await LocationService.getCurrentLocation();
      if (!mounted) return;
      setState(() {
        _currentPosition = pos;
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

      if (photo != null) {
        setState(() {
          _selfieFile = File(photo.path);
          _isCapturingSelfie = false;
        });
      } else {
        setState(() {
          _isCapturingSelfie = false;
        });
      }
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
    if (_currentPosition == null) {
      setState(() => _submissionError = 'Real device GPS location is required. Please tap "Acquire GPS Fix".');
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
                          color: const Color(0xFFEFF6FF),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          widget.shiftName ?? 'Assigned Shift',
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF2563EB)),
                        ),
                      ),
                      const Spacer(),
                      Text('Site #${widget.siteId}', style: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8))),
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
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Step 1: Real GPS Coordinates Card
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: _currentPosition != null ? const Color(0xFF86EFAC) : const Color(0xFFE2E8F0),
                  width: _currentPosition != null ? 1.5 : 1,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(
                        _currentPosition != null ? Icons.gps_fixed : Icons.gps_not_fixed,
                        color: _currentPosition != null ? const Color(0xFF10B981) : const Color(0xFF64748B),
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
                    const SizedBox(height: 8),
                    ElevatedButton.icon(
                      onPressed: _fetchLiveGps,
                      icon: const Icon(Icons.location_searching, size: 16, color: Colors.white),
                      label: const Text('Grant Permission & Retry GPS', style: TextStyle(fontSize: 13, color: Colors.white)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF2563EB),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                    ),
                  ] else if (_currentPosition != null) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF0FDF4),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Lat: ${_currentPosition!.latitude.toStringAsFixed(6)} | Lng: ${_currentPosition!.longitude.toStringAsFixed(6)}',
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF166534)),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Accuracy: ±${_currentPosition!.accuracy.toStringAsFixed(1)}m | Altitude: ${_currentPosition!.altitude.toStringAsFixed(1)}m',
                            style: const TextStyle(fontSize: 11, color: Color(0xFF15803D)),
                          ),
                        ],
                      ),
                    ),
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
                        icon: const Icon(Icons.refresh, size: 16),
                        label: const Text('Retake Selfie'),
                        style: OutlinedButton.styleFrom(
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                      ),
                    ),
                  ] else ...[
                    SizedBox(
                      width: double.infinity,
                      height: 46,
                      child: OutlinedButton.icon(
                        onPressed: _isCapturingSelfie ? null : _captureSelfie,
                        icon: _isCapturingSelfie
                            ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Icon(Icons.photo_camera, color: Color(0xFF2563EB)),
                        label: Text(
                          _isCapturingSelfie ? 'Opening Camera...' : 'Take Front-Camera Selfie',
                          style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2563EB)),
                        ),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: Color(0xFF93C5FD)),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
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

            // Step 3: Guard Notes (Optional)
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
                onPressed: (_isSubmitting || _currentPosition == null || _selfieFile == null) ? null : _submitCheckIn,
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
