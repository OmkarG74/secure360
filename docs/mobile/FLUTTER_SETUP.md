# Secure360 - Flutter Developer Setup & Integration Guide

Welcome to the **Secure360 Guard Mobile Application** integration guide. This document contains everything you need to connect your Flutter application to the Core PHP backend and MySQL database.

---

## 1. Cloning the Repository & Project Organization

### Clone the Repository:
```bash
git clone https://github.com/<your-org>/Secure360.git
cd Secure360
```

### Where Flutter Application Lives:
You have two options:
1. **Inside the monorepo**: Place your Flutter project inside the `mobile/` directory (`Secure360/mobile/`).
2. **In a separate repository**: Keep your Flutter codebase in an independent repository, cloning this repository as a reference for API contracts, database schemas, and running the local backend.

---

## 2. Running the PHP Backend Locally (XAMPP / WAMP)

1. Ensure **Apache** and **MySQL** are running in your XAMPP/WAMP control panel.
2. Put the `Secure360` directory in your web root:
   - WAMP: `C:\wamp64\www\Secure360`
   - XAMPP: `C:\xampp\htdocs\Secure360`
3. Copy configuration file:
   ```bash
   cp .env.example .env
   ```
4. Import the database:
   - Create database `secure360_v2` in phpMyAdmin.
   - Import `database/schema/secure360_v2_schema.sql`.
   - Import `database/seeders/development_seed.sql`.

---

## 3. Configuring the API Base URL

> [!WARNING]
> **Never hardcode `localhost` directly into your mobile app!**
> On mobile devices and emulators, `localhost` points to the mobile device itself, not your computer hosting Apache.

### Network Environments:

| Environment | Base URL | Notes |
|---|---|---|
| **Android Emulator** | `http://10.0.2.2/Secure360/api/v1` | `10.0.2.2` is the special alias provided by the Android emulator loopback interface to reach the host computer's `127.0.0.1`. |
| **iOS Simulator** | `http://127.0.0.1/Secure360/api/v1` | iOS Simulator shares the host network stack. |
| **Physical Phone (Android / iOS)** | `http://<HOST_LAN_IP>/Secure360/api/v1` | e.g. `http://192.168.1.105/Secure360/api/v1`. Both computer and phone must be on the **same Wi-Fi network**. |

### Recommended Flutter Config Pattern
Create an environment file or Dart configuration class:

```dart
// lib/core/config/api_config.dart
class ApiConfig {
  // Use flutter run --dart-define=API_URL=http://10.0.2.2/Secure360/api/v1
  static const String baseUrl = String.fromEnvironment(
    'API_URL',
    defaultValue: 'http://10.0.2.2/Secure360/api/v1',
  );

  static const Duration timeout = Duration(seconds: 15);
}
```

When running:
```bash
# For Android Emulator
flutter run --dart-define=API_URL=http://10.0.2.2/Secure360/api/v1

# For Physical Phone over Wi-Fi
flutter run --dart-define=API_URL=http://192.168.1.105/Secure360/api/v1
```

---

## 4. Setting up Physical Device Wi-Fi Access

To connect a physical phone to your local laptop running Apache:
1. Connect your computer and your phone to the **same Wi-Fi network**.
2. Find your computer's local IPv4 address:
   - **Windows**: Open terminal and run `ipconfig` &rarr; note `IPv4 Address` (e.g. `192.168.1.105`).
3. Verify Apache allows external LAN requests:
   - In your phone's browser, visit: `http://192.168.1.105/Secure360/api/v1/health`
   - If it loads JSON, your phone can reach Apache!
4. **If connection fails or times out**:
   - Check Windows Firewall: allow **Apache HTTP Server** on Private Networks.
   - In Apache `httpd.conf`, make sure `Require all granted` is set for document root.

---

## 5. Testing API Connectivity (Health Check)

Before writing UI logic in Flutter, verify connectivity:
```bash
curl -X GET http://localhost/Secure360/api/v1/health
```
Expected output:
```json
{
  "success": true,
  "message": "Secure360 API is running",
  "database": "connected",
  "database_name": "secure360_v2",
  "php_version": "8.2.12",
  "timestamp": 1789110247
}
```

---

## 6. Authentication & Token Lifecycle in Flutter

### Step 1: Login
Call `POST /api/v1/auth/guard/login` with:
```json
{
  "email": "guard@apexsecurity.com",
  "password": "password123",
  "device_name": "Pixel 8",
  "device_type": "android"
}
```
*(Or use `"employee_code": "GRD-101"`)*

### Step 2: Store Token
Save the returned token string (`data.token`) in secure storage (`flutter_secure_storage` or `shared_preferences`).

### Step 3: Attach Header
Attach the token to all future requests:
```http
Authorization: Bearer 90edb394a98f2fb7b56cdf506157352af1c049b602a29c82b30383699c20d9bc
```

### Step 4: Handle 401 Unauthorized
If an API request returns `401 Unauthorized`, clear the stored token and navigate the guard back to the Login screen.

---

## 7. Available Endpoints Summary

- `GET  /api/v1/health` &mdash; Health check
- `POST /api/v1/auth/guard/login` &mdash; Guard authentication
- `POST /api/v1/guard/logout` &mdash; Revoke active token
- `GET  /api/v1/guard/profile` &mdash; Current guard profile
- `GET  /api/v1/guard/assignments` &mdash; Assigned duties, shifts, and sites
- `GET  /api/v1/guard/sites/{id}` &mdash; Site details & geofence coordinates
- `POST /api/v1/guard/attendance/check-in` &mdash; Check-in with GPS
- `POST /api/v1/guard/attendance/check-out` &mdash; Check-out with GPS
- `GET  /api/v1/guard/attendance/history` &mdash; Attendance history
- `POST /api/v1/guard/location` &mdash; Submit live GPS telemetry
- `POST /api/v1/guard/selfie` &mdash; Submit verification selfie
- `GET  /api/v1/guard/notifications` &mdash; Guard alerts & notifications

See [API_DOCUMENTATION.md](file:///c:/wamp64/www/Secure360/docs/api/API_DOCUMENTATION.md) for full request/response schemas.

---

## 8. Database Synchronization Protocol

- **Never connect Flutter directly to MySQL**: All communication travels through the REST API.
- If backend models or table schemas change, check `database/migrations/` and `docs/api/SHARED_DATA_CONTRACT.md`.
- Test seeded credentials:
  - Guard: `guard@apexsecurity.com` / `password123` (Employee Code: `GRD-101`)
