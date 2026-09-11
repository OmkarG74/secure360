# Secure360 - Mobile REST API Specifications (Flutter Integration)

## Base URL
`http://<host>/Secure360/api/v1`

## Authentication
All protected endpoints require HTTP Bearer Token header:
```
Authorization: Bearer <access_token>
```

## Standard Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation description",
  "data": { ... },
  "status_code": 200,
  "timestamp": 1726055550
}
```

### Error Response
```json
{
  "success": false,
  "message": "Detailed error message",
  "errors": { "field": ["validation message"] },
  "status_code": 400,
  "timestamp": 1726055550
}
```

## Endpoints

### 1. Guard Authentication
- **POST** `/guard/login`
  - **Payload**:
    ```json
    { "badge_number": "GRD-1001", "password": "password123" }
    ```
  - **Response (200)**: Returns `token`, `guard` profile, and tenant metadata.

### 2. Guard Profile
- **GET** `/guard/profile`
  - **Headers**: Bearer Token
  - **Response (200)**: Returns guard details and current assignment status.

### 3. Guard Duty Assignments
- **GET** `/guard/assignments`
  - **Headers**: Bearer Token
  - **Response (200)**: Returns list of upcoming and active duty assignments, shift times, and site locations.

### 4. Site Details
- **GET** `/guard/sites/{id}`
  - **Headers**: Bearer Token
  - **Response (200)**: Returns site details, geofence coordinates, contact person.

### 5. Mark Attendance (Check-in)
- **POST** `/guard/attendance/check-in`
  - **Headers**: Bearer Token
  - **Payload**:
    ```json
    {
      "site_id": 5,
      "assignment_id": 12,
      "latitude": 18.5204,
      "longitude": 73.8567
    }
    ```
  - **Response (200)**: Confirms check-in and logs GPS coordinates.

### 6. Mark Attendance (Check-out)
- **POST** `/guard/attendance/check-out`
  - **Headers**: Bearer Token
  - **Payload**:
    ```json
    {
      "attendance_id": 48,
      "latitude": 18.5204,
      "longitude": 73.8567,
      "notes": "Shift completed without incident"
    }
    ```
  - **Response (200)**: Confirms check-out and records total hours.

### 7. Guard Attendance History
- **GET** `/guard/attendance/history`
  - **Headers**: Bearer Token
  - **Query Params**: `start_date`, `end_date`
  - **Response (200)**: Returns guard's personal attendance history.
