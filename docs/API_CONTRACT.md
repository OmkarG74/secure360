# Secure360 - Shared REST API Contract

> **Version**: 1.1.0  
> **Source of Truth**: Core PHP REST API (`routes/api.php` & `app/Controllers/Api/Guard/*`)  
> **Client**: Flutter Guard Mobile Application (`mobile/lib/core/services/api_service.dart`)  
> **Database**: MySQL `secure360_v2`

This document defines the strict, synchronized API contract between the Core PHP backend and the Flutter mobile client. Any payload modification must be updated here first.

---

## 1. Network & Host Configuration

| Environment | Base URL |
| :--- | :--- |
| **Railway Production** | `https://secure360-production.up.railway.app/api/v1` |
| **Android Emulator (Local WAMP)** | `http://10.0.2.2/Secure360/api/v1` |
| **Physical Phone (Local Wi-Fi)** | `http://<YOUR_PC_LAN_IP>/Secure360/api/v1` |
| **iOS Simulator / Chrome Desktop** | `http://localhost/Secure360/api/v1` |

---

## 2. Global Headers & Envelopes

### Standard Request Headers
```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer <64-character-plain-token> # (for protected routes)
```

### Standard Success Envelope
```json
{
  "success": true,
  "message": "Human readable confirmation message",
  "data": { ... },
  "status_code": 200
}
```

### Standard Error Envelope
```json
{
  "success": false,
  "message": "Human readable error message",
  "data": null,
  "errors": { ... },
  "status_code": 400
}
```

---

## 3. Endpoints Specification

### 3.1 Public Health Check
- **Endpoint**: `GET /api/v1/health`
- **Auth**: None
- **Success Response (`200 OK`)**:
  ```json
  {
    "success": true,
    "message": "Secure360 API is running healthy",
    "data": {
      "app": "Secure360",
      "version": "1.0.0",
      "environment": "development",
      "database": "connected",
      "timestamp": "2026-09-11 14:30:00"
    },
    "status_code": 200
  }
  ```

---

### 3.2 Guard Authentication (Login)
- **Endpoint**: `POST /api/v1/auth/guard/login` *(also aliased to `/api/v1/guard/login`)*
- **Auth**: None
- **Request Body**:
  ```json
  {
    "email": "guard@apexsecurity.com",
    "password": "password123",
    "device_name": "Pixel 8 Emulator",
    "device_type": "android"
  }
  ```
  *(Note: `email` field also supports the guard's `employee_code`, e.g. `"GRD-101"`)*

- **Success Response (`200 OK`)**:
  ```json
  {
    "success": true,
    "message": "Guard login successful",
    "data": {
      "token": "dd5fb2d7ab472258c0058c47f04f9d053756cd585eb8f4a750766a8a7568cf16",
      "user": {
        "id": 3,
        "user_id": 3,
        "guard_id": 1,
        "name": "David Guard",
        "full_name": "David Guard",
        "email": "guard@apexsecurity.com",
        "phone": "+1-555-0102",
        "employee_code": "GRD-101",
        "organization_id": 1,
        "organization_name": "Apex Security Services",
        "photo_url": "uploads/guards/sample_guard.jpg",
        "role": "guard"
      },
      "guard": {
        "id": 3,
        "user_id": 3,
        "guard_id": 1,
        "name": "David Guard",
        "full_name": "David Guard",
        "email": "guard@apexsecurity.com",
        "phone": "+1-555-0102",
        "employee_code": "GRD-101",
        "organization_id": 1,
        "organization_name": "Apex Security Services",
        "photo_url": "uploads/guards/sample_guard.jpg",
        "role": "guard"
      }
    },
    "status_code": 200
  }
  ```

- **Error Responses**:
  - **Missing fields (`422 Unprocessable Entity`)**:
    ```json
    {
      "success": false,
      "message": "Email/Employee Code and Password are required",
      "data": null,
      "status_code": 422
    }
    ```
  - **Invalid credentials / Inactive guard (`401 Unauthorized`)**:
    ```json
    {
      "success": false,
      "message": "Invalid credentials or inactive guard account",
      "data": null,
      "status_code": 401
    }
    ```
  - **Role mismatch (`403 Forbidden`)**:
    ```json
    {
      "success": false,
      "message": "Access denied: User does not have a Guard role",
      "data": null,
      "status_code": 403
    }
    ```

---

### 3.3 Guard Profile
- **Endpoint**: `GET /api/v1/guard/profile`
- **Auth**: `Bearer <token>`
- **Success Response (`200 OK`)**:
  ```json
  {
    "success": true,
    "message": "Guard profile retrieved successfully",
    "data": {
      "user": {
        "id": 3,
        "guard_id": 1,
        "user_id": 3,
        "name": "David Guard",
        "full_name": "David Guard",
        "email": "guard@apexsecurity.com",
        "phone": "+1-555-0102",
        "employee_code": "GRD-101",
        "organization_id": 1,
        "photo_url": "uploads/guards/sample_guard.jpg",
        "role": "guard"
      },
      "guard": {
        "id": 3,
        "guard_id": 1,
        "user_id": 3,
        "name": "David Guard",
        "full_name": "David Guard",
        "email": "guard@apexsecurity.com",
        "phone": "+1-555-0102",
        "employee_code": "GRD-101",
        "organization_id": 1,
        "photo_url": "uploads/guards/sample_guard.jpg",
        "role": "guard"
      }
    },
    "status_code": 200
  }
  ```

---

### 3.4 Guard Logout (Revoke Token)
- **Endpoint**: `POST /api/v1/guard/logout`
- **Auth**: `Bearer <token>`
- **Success Response (`200 OK`)**:
  ```json
  {
    "success": true,
    "message": "Logged out successfully, token revoked",
    "data": null,
    "status_code": 200
  }
  ```

---

### 3.5 Duty Roster & Site Assignments
- **Endpoint**: `GET /api/v1/guard/assignments`
- **Auth**: `Bearer <token>`
- **Success Response (`200 OK`)**:
  ```json
  {
    "success": true,
    "message": "Duty assignments retrieved successfully",
    "data": {
      "assignments": [
        {
          "assignment_id": 1,
          "guard_id": 1,
          "site_id": 1,
          "site_name": "Metro Plaza - Main Gate",
          "site_code": "SITE-METRO-MAIN",
          "address": "500 Commerce Way, Gate 1",
          "city": "Metropolis",
          "latitude": "18.5204300",
          "longitude": "73.8567430",
          "geofence_radius_meters": 100,
          "shift_id": 1,
          "shift_name": "Day Patrol Shift",
          "start_time": "08:00:00",
          "end_time": "16:00:00",
          "is_night_shift": 0,
          "contract_id": 1,
          "customer_name": "Metro Retail Corp",
          "assigned_date": "2026-09-11",
          "assignment_status": 0
        }
      ]
    },
    "status_code": 200
  }
  ```

---

### 3.6 Assigned Site Details
- **Endpoint**: `GET /api/v1/guard/sites/{id}`
- **Auth**: `Bearer <token>`
- **Success Response (`200 OK`)**:
  ```json
  {
    "success": true,
    "message": "Site details retrieved successfully",
    "data": {
      "site": {
        "id": 1,
        "customer_id": 1,
        "name": "Metro Plaza - Main Gate",
        "code": "SITE-METRO-MAIN",
        "address": "500 Commerce Way, Gate 1",
        "city": "Metropolis",
        "state": "CA",
        "postal_code": "90210",
        "latitude": "18.5204300",
        "longitude": "73.8567430",
        "geofence_radius_meters": 100,
        "contact_person": "Frank Manager",
        "contact_phone": "+1-555-0103",
        "status": 0
      }
    },
    "status_code": 200
  }
  ```

---

### 3.7 Site Check-In
- **Endpoint**: `POST /api/v1/guard/attendance/check-in`
- **Auth**: `Bearer <token>`
- **Request Body**:
  ```json
  {
    "site_id": 1,
    "assignment_id": 1,
    "latitude": 18.520430,
    "longitude": 73.856743,
    "address": "500 Commerce Way, Gate 1",
    "notes": "On site, perimeter gate inspected"
  }
  ```
- **Success Response (`201 Created`)**:
  ```json
  {
    "success": true,
    "message": "Attendance check-in recorded successfully",
    "data": {
      "attendance_id": 15,
      "guard_id": 1,
      "site_id": 1,
      "check_in_at": "2026-09-11 08:02:15",
      "status": "checked_in"
    },
    "status_code": 201
  }
  ```
- **Duplicate Open Check-In Error (`409 Conflict`)**:
  ```json
  {
    "success": false,
    "message": "Guard already has an active open check-in. Check out first before checking in again.",
    "data": {
      "active_attendance_id": 14,
      "checked_in_at": "2026-09-11 06:15:00"
    },
    "status_code": 409
  }
  ```

---

### 3.8 Site Check-Out
- **Endpoint**: `POST /api/v1/guard/attendance/check-out`
- **Auth**: `Bearer <token>`
- **Request Body**:
  ```json
  {
    "attendance_id": 15,
    "latitude": 18.520430,
    "longitude": 73.856743,
    "address": "500 Commerce Way, Gate 1",
    "notes": "Shift completed, handed over keys to evening shift"
  }
  ```
  *(Note: `attendance_id` is optional; if omitted, the backend auto-resolves the guard's open active check-in)*

- **Success Response (`200 OK`)**:
  ```json
  {
    "success": true,
    "message": "Attendance check-out recorded successfully",
    "data": {
      "attendance_id": 15,
      "guard_id": 1,
      "check_out_at": "2026-09-11 16:01:22",
      "status": "completed"
    },
    "status_code": 200
  }
  ```

---

### 3.9 Attendance History
- **Endpoint**: `GET /api/v1/guard/attendance/history`
- **Auth**: `Bearer <token>`
- **Optional Query Params**: `?start_date=2026-09-01&end_date=2026-09-11`
- **Success Response (`200 OK`)**:
  ```json
  {
    "success": true,
    "message": "Attendance history retrieved successfully",
    "data": {
      "records": [
        {
          "id": 1,
          "organization_id": 1,
          "guard_id": 1,
          "site_id": 1,
          "site_name": "Metro Plaza - Main Gate",
          "site_code": "SITE-METRO-MAIN",
          "check_in_at": "2026-09-11 08:02:15",
          "check_out_at": "2026-09-11 16:01:22",
          "check_in_latitude": "18.5204300",
          "check_in_longitude": "73.8567430",
          "check_in_address": "500 Commerce Way, Gate 1",
          "status": 1,
          "notes": "Regular shift"
        }
      ],
      "history": [
        {
          "id": 1,
          "organization_id": 1,
          "guard_id": 1,
          "site_id": 1,
          "site_name": "Metro Plaza - Main Gate",
          "site_code": "SITE-METRO-MAIN",
          "check_in_at": "2026-09-11 08:02:15",
          "check_out_at": "2026-09-11 16:01:22",
          "status": 1
        }
      ]
    },
    "status_code": 200
  }
  ```

---

### 3.10 Live GPS Location Telemetry
- **Endpoint**: `POST /api/v1/guard/location`
- **Auth**: `Bearer <token>`
- **Request Body**:
  ```json
  {
    "latitude": 18.520430,
    "longitude": 73.856743,
    "accuracy_meters": 4.5,
    "address": "North perimeter fence",
    "assignment_id": 1,
    "attendance_id": 15
  }
  ```
- **Success Response (`201 Created`)**:
  ```json
  {
    "success": true,
    "message": "Live location telemetry recorded",
    "data": {
      "location_id": 842,
      "recorded_at": "2026-09-11 14:32:00"
    },
    "status_code": 201
  }
  ```

---

### 3.11 Selfie Verification Upload
- **Endpoint**: `POST /api/v1/guard/selfie`
- **Auth**: `Bearer <token>`
- **Request Body**:
  ```json
  {
    "image_path": "uploads/selfies/guard_1_20260911_080215.jpg",
    "attendance_id": 15
  }
  ```
- **Success Response (`201 Created`)**:
  ```json
  {
    "success": true,
    "message": "Selfie verification recorded",
    "data": {
      "selfie_id": 48
    },
    "status_code": 201
  }
  ```

---

### 3.12 Notifications
- **Endpoint**: `GET /api/v1/guard/notifications`
- **Auth**: `Bearer <token>`
- **Success Response (`200 OK`)**:
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
