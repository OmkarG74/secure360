# Secure360 Flutter Mobile Setup Guide

This guide is for the **Mobile Developer** responsible for running and building the Flutter Guard application located in the `mobile/` directory.

---

## 1. Prerequisites
- **Flutter SDK**: 3.10.0+
- **Android Studio** / **VS Code** with Flutter & Dart extensions
- **Android Emulator** or **Physical Android Device** connected over USB/Wi-Fi

---

## 2. IP & Networking Configuration

In `mobile/lib/core/config/api_config.dart`, choose the appropriate base URL:

### A. Testing on Android Emulator
The Android emulator aliases host `localhost` to `10.0.2.2`:
```dart
static const String baseUrl = 'http://10.0.2.2/Secure360/api/v1';
```

### B. Testing on a Physical Android/iOS Device (Local Wi-Fi)
Find your computer's local IP address (e.g., `192.168.1.105`):
```dart
static const String baseUrl = 'http://192.168.1.105/Secure360/api/v1';
```
Ensure your Apache web server permits incoming connections across the local network.

### C. Testing on Chrome / Desktop
```dart
static const String baseUrl = 'http://localhost/Secure360/api/v1';
```

---

## 3. Running the App

1. Navigate to the mobile directory:
   ```bash
   cd mobile
   ```
2. Fetch dependencies:
   ```bash
   flutter pub get
   ```
3. Run on connected device:
   ```bash
   flutter run
   ```

---

## 4. Test Guard Account

Authenticate with the pre-seeded guard credentials:
- **Email**: `guard@apexsecurity.com`
- **Password**: `password123`
- **Assigned Post**: `Metro Plaza - Main Gate`

Upon login:
1. The app stores the SHA-256 Bearer token in device `shared_preferences`.
2. The Dashboard loads today's site duty from `GET /api/v1/guard/assignments`.
3. Tapping **Check In to Duty** displays the GPS location and facial selfie preview, and submits to `POST /api/v1/guard/attendance/check-in`.
4. The check-in immediately appears on the Admin Web Dashboard (`/admin/attendance`).
