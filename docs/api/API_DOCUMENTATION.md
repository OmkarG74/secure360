# Secure360 - REST API Documentation (Flutter Integration)

This document specifies the complete REST API contract for the **Secure360 Flutter Guard Mobile Application**.

---

## 1. General API Specifications

### Base URLs
- **Local Machine**: `http://localhost/Secure360/api/v1`
- **Android Emulator**: `http://10.0.2.2/Secure360/api/v1`
- **Physical Device (Same Wi-Fi)**: `http://<YOUR_COMPUTER_LOCAL_IP>/Secure360/api/v1` *(e.g. `http://192.168.1.50/Secure360/api/v1`)*

### Required Headers
All requests should specify:
```http
Content-Type: application/json
Accept: application/json
```
For protected endpoints, include the Bearer token:
```http
Authorization: Bearer <your_api_token>
```

### Standard Response Formats

#### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { ... },
  "status_code": 200
}
```

#### Error Response
```json
{
  "success": false,
  "message": "Detailed error description",
  "errors": { "field_name": ["Validation error message"] },
  "status_code": 400
}
```

### HTTP Status Codes
- `200 OK`: Request succeeded
- `201 Created`: Resource successfully created (check-in, token created)
- `400 Bad Request`: Malformed request or missing parameters
- `401 Unauthorized`: Missing, invalid, or expired Bearer token
- `403 Forbidden`: Authenticated user lacks guard privileges
- `404 Not Found`: Requested resource (site, assignment, guard) does not exist
- `409 Conflict`: Business state conflict (e.g. guard already has an open check-in)
- `422 Unprocessable Entity`: Input validation failure
- `500 Internal Server Error`: Backend database or server error

---

## 2. API Endpoints

### 2.1 System Health Check
Check backend server and MySQL database connectivity.

- **Method**: `GET`
- **URL**: `/health`
- **Auth Required**: No
- **Response (200)**:
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

### 2.2 Guard Authentication (Login)
Authenticates guard and returns a 64-character bearer token valid for 30 days.

- **Method**: `POST`
- **URL**: `/auth/guard/login` *(or `/guard/login`)*
- **Auth Required**: No
- **Request Body**:
```json
{
  "email": "guard@apexsecurity.com",
  "password": "password123",
  "device_name": "Samsung Galaxy S23",
  "device_type": "android"
}
```
*(Note: Guards can also log in by passing `"employee_code": "GRD-101"` instead of `"email"`)*

- **Response (200)**:
```json
{
  "success": true,
  "message": "Guard login successful",
  "data": {
    "token": "90edb394a98f2fb7b56cdf506157352af1c049b602a29c82b30383699c20d9bc",
    "guard": {
      "guard_id": 1,
      "user_id": 3,
      "full_name": "David Guard",
      "email": "guard@apexsecurity.com",
      "phone": "+1-555-0102",
      "employee_code": "GRD-101",
      "organization_id": 1,
      "organization_name": "Apex Security Services",
      "photo_url": "uploads/guards/sample_guard.jpg"
    }
  },
  "status_code": 200
}
```

---

### 2.3 Guard Profile
Fetch current authenticated guard's profile.

- **Method**: `GET`
- **URL**: `/guard/profile`
- **Auth Required**: Yes (`Bearer <token>`)
- **Response (200)**:
```json
{
  "success": true,
  "message": "Guard profile retrieved successfully",
  "data": {
    "guard": {
      "guard_id": 1,
      "user_id": 3,
      "full_name": "David Guard",
      "email": "guard@apexsecurity.com",
      "phone": "+1-555-0102",
      "employee_code": "GRD-101",
      "organization_id": 1,
      "photo_url": "uploads/guards/sample_guard.jpg"
    }
  },
  "status_code": 200
}
```

---

### 2.4 Guard Logout (Revoke Token)
Revokes the current Bearer token in `api_tokens`.

- **Method**: `POST`
- **URL**: `/guard/logout`
- **Auth Required**: Yes (`Bearer <token>`)
- **Response (200)**:
```json
{
  "success": true,
  "message": "Logged out successfully, token revoked",
  "status_code": 200
}
```

---

### 2.5 Guard Duty Assignments
Fetch active duty assignments, assigned site, and shift details for the guard.

- **Method**: `GET`
- **URL**: `/guard/assignments`
- **Auth Required**: Yes (`Bearer <token>`)
- **Response (200)**:
```json
{
  "success": true,
  "message": "Duty assignments retrieved successfully",
  "data": {
    "assignments": [
      {
        "id": 1,
        "contract_id": 1,
        "contract_shift_id": 1,
        "guard_id": 1,
        "site_id": 1,
        "status": 0,
        "notes": "Assigned to Main Gate day patrol",
        "site_name": "Metro Plaza - Main Gate",
        "site_code": "SITE-METRO-MAIN",
        "site_address": "500 Commerce Way, Gate 1",
        "latitude": "18.5204303",
        "longitude": "73.8567437",
        "shift_name": "Day Patrol Shift",
        "shift_code": "SHIFT-DAY",
        "start_time": "08:00:00",
        "end_time": "16:00:00",
        "contract_code": "CTR-2026-001",
        "customer_name": "Metro Commercial Plaza"
      }
    ]
  },
  "status_code": 200
}
```

---

### 2.6 Site Details
Retrieve details and coordinates for an assigned site.

- **Method**: `GET`
- **URL**: `/guard/sites/{id}`
- **Auth Required**: Yes (`Bearer <token>`)
- **Response (200)**:
```json
{
  "success": true,
  "message": "Site details retrieved successfully",
  "data": {
    "site": {
      "id": 1,
      "organization_id": 1,
      "customer_id": 1,
      "site_code": "SITE-METRO-MAIN",
      "site_name": "Metro Plaza - Main Gate",
      "site_address": "500 Commerce Way, Gate 1",
      "area": "Main Entry",
      "latitude": "18.5204303",
      "longitude": "73.8567437",
      "zone_gate": "Gate A",
      "status": 0,
      "customer_name": "Metro Commercial Plaza",
      "client_code": "CLT-METRO"
    }
  },
  "status_code": 200
}
```

---

### 2.7 Attendance Check-in
Submit attendance check-in with GPS telemetry.

- **Method**: `POST`
- **URL**: `/guard/attendance/check-in`
- **Auth Required**: Yes (`Bearer <token>`)
- **Request Body**:
```json
{
  "site_id": 1,
  "assignment_id": 1,
  "latitude": 18.5204303,
  "longitude": 73.8567437,
  "address": "500 Commerce Way, Gate 1",
  "notes": "On site on time"
}
```
- **Response (201 Created)**:
```json
{
  "success": true,
  "message": "Attendance check-in recorded successfully",
  "data": {
    "attendance_id": 1,
    "guard_id": 1,
    "site_id": 1,
    "check_in_at": "2026-09-11 12:35:00",
    "status": "checked_in"
  },
  "status_code": 201
}
```

---

### 2.8 Attendance Check-out
Submit attendance check-out.

- **Method**: `POST`
- **URL**: `/guard/attendance/check-out`
- **Auth Required**: Yes (`Bearer <token>`)
- **Request Body**:
```json
{
  "attendance_id": 1,
  "latitude": 18.5204303,
  "longitude": 73.8567437,
  "address": "500 Commerce Way, Gate 1",
  "notes": "Shift ended normally"
}
```
*(Note: `attendance_id` is optional; if omitted, the backend automatically finds the guard's active open check-in).*

- **Response (200)**:
```json
{
  "success": true,
  "message": "Attendance check-out recorded successfully",
  "data": {
    "attendance_id": 1,
    "guard_id": 1,
    "check_out_at": "2026-09-11 16:30:00",
    "status": "completed"
  },
  "status_code": 200
}
```

---

### 2.9 Guard Attendance History
Retrieve historical check-in/out records.

- **Method**: `GET`
- **URL**: `/guard/attendance/history?start_date=2026-09-01&end_date=2026-09-30`
- **Auth Required**: Yes (`Bearer <token>`)
- **Response (200)**:
```json
{
  "success": true,
  "message": "Attendance history retrieved successfully",
  "data": {
    "records": [
      {
        "id": 1,
        "site_id": 1,
        "site_name": "Metro Plaza - Main Gate",
        "check_in_at": "2026-09-11 08:02:15",
        "check_out_at": "2026-09-11 16:05:30",
        "check_in_latitude": "18.5204303",
        "check_in_longitude": "73.8567437",
        "status": 1,
        "notes": "Regular shift"
      }
    ]
  },
  "status_code": 200
}
```

---

### 2.10 Live Location Telemetry
Submit real-time guard GPS coordinates during an active shift.

- **Method**: `POST`
- **URL**: `/guard/location`
- **Auth Required**: Yes (`Bearer <token>`)
- **Request Body**:
```json
{
  "latitude": 18.5204303,
  "longitude": 73.8567437,
  "accuracy_meters": 5.2,
  "address": "North perimeter gate",
  "assignment_id": 1,
  "attendance_id": 1
}
```
- **Response (201 Created)**:
```json
{
  "success": true,
  "message": "Live location telemetry recorded",
  "data": {
    "location_id": 12,
    "recorded_at": "2026-09-11 12:45:00"
  },
  "status_code": 201
}
```

---

### 2.11 Selfie / Verification Photo
Upload/register selfie verification during check-in or duty patrol.

- **Method**: `POST`
- **URL**: `/guard/selfie`
- **Auth Required**: Yes (`Bearer <token>`)
- **Request Body**:
```json
{
  "image_path": "uploads/guards/selfie_1_20260911_123000.jpg",
  "attendance_id": 1
}
```
- **Response (201 Created)**:
```json
{
  "success": true,
  "message": "Selfie verification recorded",
  "data": {
    "selfie_id": 4
  },
  "status_code": 201
}
```

---

### 2.12 Guard Notifications
Fetch notification announcements.

- **Method**: `GET`
- **URL**: `/guard/notifications`
- **Auth Required**: Yes (`Bearer <token>`)
- **Response (200)**:
```json
{
  "success": true,
  "message": "Notifications retrieved successfully",
  "data": {
    "notifications": []
  },
  "status_code": 200
}
```
