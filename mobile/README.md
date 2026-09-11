# Secure360 - Guard Mobile Application (Flutter)

The **Secure360 Guard Mobile Application** is the mobile client for security guards in the Secure360 platform. It connects exclusively via versioned Core PHP REST APIs to authenticate guards, display daily site assignments, manage GPS check-in/check-out, submit selfies, and stream field telemetry.

---

## 1. Prerequisites & SDK Requirements

- **Flutter SDK**: `>= 3.0.0 < 4.0.0` (Dart SDK `>= 3.0.0 < 4.0.0`)
- **Android Studio / Android SDK**: Platform SDK 33+ (API 34/35 supported)
- **Android Emulator** or **Physical Android Device** with Developer Options enabled
- **Backend**: Local Apache/WAMP or remote server running Secure360 Core PHP backend with MySQL (`secure360_v2`)

---

## 2. Quick Start

1. Open your terminal and navigate to the mobile directory:
   ```bash
   cd mobile
   ```

2. Install dependencies:
   ```bash
   flutter pub get
   ```

3. Run analysis to verify code integrity:
   ```bash
   flutter analyze
   ```

4. Launch the application:
   ```bash
   # On connected Android emulator:
   flutter run -d emulator-5554

   # Or select an available device interactively:
   flutter run
   ```

---

## 3. Backend Setup Requirements

Before testing the mobile application:
1. Ensure **Apache** and **MySQL** are running (e.g. via WAMP or XAMPP).
2. Ensure the database `secure360_v2` is imported and active on `127.0.0.1:3306`.
3. Confirm the backend API is reachable at:
   - Host machine: `http://localhost/Secure360/api/v1/health`
   - Android Emulator: `http://10.0.2.2/Secure360/api/v1/health`

---

## 4. API URL & Network Configuration

The Flutter app connects to the PHP REST API using the base URL defined in [`lib/core/config/api_config.dart`](lib/core/config/api_config.dart).

You **never** need to modify source code to change the API URL. Instead, pass `--dart-define=API_BASE_URL=...` when launching Flutter.

### Scenario A: Android Emulator (Default)
Android emulator maps the host machine's `127.0.0.1` to `10.0.2.2`. This is preconfigured as the default:
```bash
flutter run -d emulator-5554
# Uses default: http://10.0.2.2/Secure360/api/v1
```

### Scenario B: Physical Android / iOS Device on Local Wi-Fi
When testing on a physical phone connected to the same Wi-Fi network as your development PC, determine your computer's local IP address (e.g. `ipconfig` on Windows &rarr; `192.168.1.100`), then launch Flutter with:
```bash
flutter run -d <DEVICE_ID> --dart-define=API_BASE_URL=http://192.168.1.100/Secure360/api/v1
```

### Scenario C: iOS Simulator / Desktop / Web
```bash
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost/Secure360/api/v1
```

### Scenario D: Remote Staging / Production Server
```bash
flutter run --dart-define=API_BASE_URL=https://api.yourdomain.com/Secure360/api/v1
```

---

## 5. Required Device Permissions

The following permissions are preconfigured in [`android/app/src/main/AndroidManifest.xml`](android/app/src/main/AndroidManifest.xml):

- **Internet Access**: `android.permission.INTERNET` (REST API communication)
- **Network State**: `android.permission.ACCESS_NETWORK_STATE` (connectivity checks)
- **Fine Location**: `android.permission.ACCESS_FINE_LOCATION` (GPS verification on check-in/out)
- **Coarse Location**: `android.permission.ACCESS_COARSE_LOCATION` (general geo-coordinates)
- **Camera**: `android.permission.CAMERA` (facial selfie verification on duty posts)

---

## 6. Seeded Guard Credentials (for Local Testing)

Use these credentials to log in on the mobile app:
- **Email**: `guard@apexsecurity.com`
- **Employee Code / Badge**: `GRD-101`
- **Password**: `password123`

---

## 7. Project Architecture

```
mobile/
├── android/                   # Android native platform project
├── ios/                       # iOS native platform project
├── lib/
│   ├── core/
│   │   ├── config/            # ApiConfig (dynamically configurable API URLs)
│   │   ├── services/          # ApiService (HTTP client, Bearer session, safe parsing)
│   │   └── theme/             # Color tokens, typography, and styling
│   ├── features/
│   │   ├── auth/              # LoginScreen, authentication handling
│   │   ├── duty/              # HomeDashboardScreen, active posts, site details
│   │   └── attendance/        # CheckInScreen, CheckOutScreen, AttendanceHistoryScreen
│   └── main.dart              # Application bootstrap & session check
├── test/                      # Unit and widget tests
└── pubspec.yaml               # Package manifests and asset declarations
```

---

## 8. Collaboration Guidelines for Mobile Developers

1. **Never connect directly to MySQL**: All communication must go through `/api/v1/*` Core PHP REST API endpoints.
2. **Synchronize with Shared Contract**: Refer to [`docs/API_CONTRACT.md`](../docs/API_CONTRACT.md) before altering request bodies or expecting new response keys.
3. **Never commit local SDK paths**: Keep `android/local.properties` ignored by Git.
