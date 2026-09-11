# Secure360 REST API Documentation (v1)

All endpoints reside under `/api/v1/*`.
All requests and responses use `Content-Type: application/json`.

---

## 1. Standard Response Payload Format

### Success Response (HTTP 200/201)
```json
{
  "success": true,
  "message": "Operation executed successfully.",
  "data": { ... },
  "status_code": 200
}
```

### Error Response (HTTP 400/401/403/404/500)
```json
{
  "success": false,
  "message": "Validation or authentication error description.",
  "errors": {
    "field": "Detailed error message"
  },
  "status_code": 400
}
```

---

## 2. Authentication Specification
Protected endpoints require a Bearer token in the `Authorization` HTTP header:
```http
Authorization: Bearer <64_character_hex_token>
```
The server computes `hash('sha256', $plainToken)` and matches against the `token_hash` column in `api_tokens`.

---

## 3. API Endpoints Reference

### Public Endpoints

#### `GET /api/v1/health`
Health check and database connectivity probe.
- **Response**:
```json
{
  "success": true,
  "message": "Secure360 API and Database operational",
  "data": {
    "status": "healthy",
    "version": "1.0.0",
    "database": "connected",
    "timestamp": "2026-09-11 12:40:00"
  },
  "status_code": 200
}
```

#### `POST /api/v1/auth/guard/login`
Guard mobile authentication.
- **Request Body**:
```json
{
  "email": "guard@apexsecurity.com",
  "password": "password123",
  "device_name": "Pixel 7 Pro",
  "device_type": "android"
}
```
- **Response**:
```json
{
  "success": true,
  "message": "Authentication successful",
  "data": {
    "token": "a1b2c3d4e5f6...",
    "token_type": "Bearer",
    "expires_at": "2026-10-11 12:40:00",
    "user": {
      "id": 3,
      "guard_id": 1,
      "name": "David Guard",
      "email": "guard@apexsecurity.com",
      "employee_code": "GRD-101",
      "organization_id": 1,
      "organization_name": "Apex Security Services"
    }
  },
  "status_code": 200
}
```

---

### Protected Endpoints (Bearer Token Required)

#### `GET /api/v1/guard/profile`
Returns logged-in guard user profile and badge info.

#### `POST /api/v1/guard/logout`
Revokes active Bearer token in `api_tokens`.

#### `GET /api/v1/guard/assignments`
Returns today's duty roster for the guard.
- **Response**:
```json
{
  "success": true,
  "message": "Assignments retrieved",
  "data": {
    "assignments": [
      {
        "assignment_id": 1,
        "site_id": 1,
        "site_name": "Metro Plaza - Main Gate",
        "site_code": "SITE-METRO-MAIN",
        "site_address": "500 Commerce Way, Gate 1",
        "shift_name": "Day Patrol Shift",
        "start_time": "08:00:00",
        "end_time": "16:00:00"
      }
    ]
  },
  "status_code": 200
}
```

#### `POST /api/v1/guard/attendance/check-in`
Submit GPS check-in to an assigned site post.
- **Request Body**:
```json
{
  "site_id": 1,
  "latitude": 18.520430,
  "longitude": 73.856743,
  "address": "500 Commerce Way, Gate 1",
  "notes": "On site, gates opened"
}
```
- **Response**:
```json
{
  "success": true,
  "message": "Checked in successfully",
  "data": {
    "attendance_id": 10,
    "check_in_at": "2026-09-11 12:45:00",
    "status": 0
  },
  "status_code": 200
}
```

#### `POST /api/v1/guard/attendance/check-out`
Complete duty shift.
- **Request Body**:
```json
{
  "attendance_id": 10,
  "latitude": 18.520430,
  "longitude": 73.856743,
  "address": "500 Commerce Way, Gate 1",
  "notes": "Shift handover complete"
}
```

#### `GET /api/v1/guard/attendance/history`
Retrieves past 30 days duty logs for the guard.
