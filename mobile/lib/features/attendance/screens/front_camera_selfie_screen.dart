import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';

/// Full-screen camera interface dedicated strictly to front-facing selfie verification.
/// Uses [CameraLensDirection.front] by default and prevents silent fallback to rear camera.
class FrontCameraSelfieScreen extends StatefulWidget {
  const FrontCameraSelfieScreen({super.key});

  @override
  State<FrontCameraSelfieScreen> createState() => _FrontCameraSelfieScreenState();
}

class _FrontCameraSelfieScreenState extends State<FrontCameraSelfieScreen> with WidgetsBindingObserver {
  CameraController? _controller;
  bool _isInitializing = true;
  bool _isCapturing = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initFrontCamera();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller?.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final cameraController = _controller;
    if (cameraController == null || !cameraController.value.isInitialized) {
      return;
    }

    if (state == AppLifecycleState.inactive) {
      cameraController.dispose();
    } else if (state == AppLifecycleState.resumed) {
      _initFrontCamera();
    }
  }

  Future<void> _initFrontCamera() async {
    setState(() {
      _isInitializing = true;
      _errorMessage = null;
    });

    try {
      final cameras = await availableCameras();

      // Find the camera with CameraLensDirection.front
      CameraDescription? frontCamera;
      for (final cam in cameras) {
        if (cam.lensDirection == CameraLensDirection.front) {
          frontCamera = cam;
          break;
        }
      }

      // If no front-facing camera is available on the device, do NOT silently fall back to rear.
      if (frontCamera == null) {
        if (!mounted) return;
        setState(() {
          _isInitializing = false;
          _errorMessage = 'No front-facing camera found on this device. Selfie verification requires a front camera.';
        });
        return;
      }

      final controller = CameraController(
        frontCamera,
        ResolutionPreset.high,
        enableAudio: false,
        imageFormatGroup: ImageFormatGroup.jpeg,
      );

      await controller.initialize();

      if (!mounted) {
        await controller.dispose();
        return;
      }

      setState(() {
        _controller = controller;
        _isInitializing = false;
      });
    } on CameraException catch (e) {
      if (!mounted) return;
      setState(() {
        _isInitializing = false;
        _errorMessage = 'Camera initialization failed: ${e.description ?? e.code}';
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isInitializing = false;
        _errorMessage = 'Failed to access camera: $e';
      });
    }
  }

  Future<void> _takeSelfie() async {
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized || _isCapturing) {
      return;
    }

    setState(() => _isCapturing = true);

    try {
      final XFile photo = await controller.takePicture();
      if (!mounted) return;
      Navigator.of(context).pop(File(photo.path));
    } on CameraException catch (e) {
      if (!mounted) return;
      setState(() {
        _isCapturing = false;
        _errorMessage = 'Failed to capture selfie: ${e.description ?? e.code}';
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isCapturing = false;
        _errorMessage = 'Error capturing selfie: $e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF0F172A),
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.close, color: Colors.white),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: const Text(
          'Front-Camera Selfie',
          style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w600),
        ),
        centerTitle: true,
      ),
      body: SafeArea(
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isInitializing) {
      return const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(color: Color(0xFF2563EB)),
            SizedBox(height: 16),
            Text(
              'Opening front camera...',
              style: TextStyle(color: Color(0xFF94A3B8), fontSize: 14),
            ),
          ],
        ),
      );
    }

    if (_errorMessage != null || _controller == null || !_controller!.value.isInitialized) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 28.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.no_photography_outlined, size: 56, color: Color(0xFFEF4444)),
              const SizedBox(height: 16),
              Text(
                _errorMessage ?? 'Front camera is unavailable.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 24),
              ElevatedButton.icon(
                onPressed: () => Navigator.of(context).pop(),
                icon: const Icon(Icons.arrow_back, size: 18),
                label: const Text('Back to Duty'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1E293B),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Column(
      children: [
        // Camera Preview Area with face alignment guide
        Expanded(
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: Stack(
                fit: StackFit.expand,
                alignment: Alignment.center,
                children: [
                  CameraPreview(_controller!),

                  // Facial Alignment Guide Overlay
                  Center(
                    child: Container(
                      width: 240,
                      height: 310,
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.white.withValues(alpha: 0.55), width: 2),
                        borderRadius: const BorderRadius.all(Radius.elliptical(240, 310)),
                      ),
                    ),
                  ),

                  // Framing Instruction Badge
                  Positioned(
                    top: 16,
                    left: 20,
                    right: 20,
                    child: Center(
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.65),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Text(
                          'Position your face clearly within the oval frame',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w500),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),

        // Bottom Capture Controls
        Container(
          padding: const EdgeInsets.symmetric(vertical: 24.0),
          child: Column(
            children: [
              InkWell(
                onTap: _isCapturing ? null : _takeSelfie,
                customBorder: const CircleBorder(),
                child: Container(
                  width: 76,
                  height: 76,
                  padding: const EdgeInsets.all(4),
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 4),
                  ),
                  child: Container(
                    decoration: BoxDecoration(
                      color: _isCapturing ? const Color(0xFF94A3B8) : const Color(0xFF2563EB),
                      shape: BoxShape.circle,
                    ),
                    child: _isCapturing
                        ? const Center(
                            child: SizedBox(
                              width: 28,
                              height: 28,
                              child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white),
                            ),
                          )
                        : const Icon(Icons.camera_alt, color: Colors.white, size: 34),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              const Text(
                'Tap to Capture Verification Selfie',
                style: TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
