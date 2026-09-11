# Secure360 - Shared Data Contract (PHP Backend & Flutter Client)

The **Core PHP backend is the single source of truth** for all business entities, database relationships, data types, and status enumerations. The Flutter client must map its Dart data classes (`fromJson` / `toJson`) directly to the contracts detailed here.

---

## 1. Global Conventions & Standards

| Dimension | Standard | Example |
|---|---|---|
| **API Format** | JSON (`application/json`) | `{ "success": true, ... }` |
| **Date/Time** | ISO 8601 or `YYYY-MM-DD HH:MM:SS` (UTC) | `"2026-09-11 14:30:00"` |
| **Date only** | `YYYY-MM-DD` | `"2026-09-11"` |
| **Time only** | `HH:MM:SS` | `"08:00:00"` |
| **Coordinates** | Double / Decimal (`latitude`, `longitude`) | `18.5204303`, `73.8567437` |
| **Primary Keys** | Integer (BigInt unsigned from MySQL) | `1`, `42` |
| **Status Codes** | Integer (`0 = active/open`, `1 = inactive/completed`, `2 = deleted/cancelled`) | `0` |

---

## 2. Core Entities & Field Mappings

### 2.1 Guard Profile (`Guard`)
Mapped to `guards` joined with `users` and `organizations`.

```typescript
interface GuardProfile {
  guard_id: number;           // guards.id
  user_id: number;            // users.id
  full_name: string;          // users.full_name
  email: string;              // users.email
  phone: string | null;       // users.phone
  employee_code: string;      // users.employee_code (e.g. "GRD-101")
  organization_id: number;    // users.organization_id
  organization_name?: string; // organizations.name
  photo_url: string | null;   // guards.photo_url
}
```

---

### 2.2 Customer / Client (`Customer`)
Table: `customers`

```typescript
interface Customer {
  id: number;                 // customers.id
  organization_id: number;    // customers.organization_id
  client_code: string;        // e.g. "CLT-METRO"
  name: string;               // Customer company name
  contact_person: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  status: number;             // 0 = active, 1 = inactive, 2 = deleted
}
```

---

### 2.3 Security Site (`Site`)
Table: `sites` (Child of Customer: 1 Customer &rarr; Many Sites)

```typescript
interface Site {
  id: number;                 // sites.id
  organization_id: number;    // sites.organization_id
  customer_id: number;        // sites.customer_id (FK to customers.id)
  site_code: string;          // e.g. "SITE-METRO-MAIN"
  site_name: string;          // e.g. "Metro Plaza - Main Gate"
  site_address: string | null;
  area: string | null;
  latitude: number | null;    // e.g. 18.5204303
  longitude: number | null;   // e.g. 73.8567437
  zone_gate: string | null;   // e.g. "Gate A"
  status: number;             // 0 = active, 1 = inactive, 2 = deleted
}
```

---

### 2.4 Duty Assignment (`Assignment`)
Table: `contract_guard_assignments`

```typescript
interface DutyAssignment {
  id: number;                 // contract_guard_assignments.id
  contract_id: number;        // contracts.id
  contract_shift_id: number;  // contract_shifts.id
  guard_id: number;           // guards.id
  site_id: number;            // sites.id
  status: number;             // 0 = active, 1 = inactive, 2 = deleted
  notes: string | null;
  
  // Joined relational metadata for Flutter UI
  site_name: string;          // sites.site_name
  site_code: string;          // sites.site_code
  site_address: string | null;
  latitude: number;
  longitude: number;
  shift_name: string;         // contract_shifts.shift_name (e.g. "Day Shift")
  shift_code: string;         // contract_shifts.shift_code
  start_time: string;         // "08:00:00"
  end_time: string;           // "16:00:00"
  contract_code: string;      // contracts.contract_code
  customer_name: string;      // customers.name
}
```

---

### 2.5 Attendance Record (`Attendance`)
Table: `attendance`

```typescript
interface AttendanceRecord {
  id: number;                 // attendance.id
  organization_id: number;
  guard_id: number;           // guards.id
  assignment_id: number | null;
  site_id: number;            // sites.id
  site_name?: string;
  check_in_at: string;        // "2026-09-11 08:00:00"
  check_out_at: string | null;// "2026-09-11 16:00:00" or null if on duty
  check_in_latitude: number | null;
  check_in_longitude: number | null;
  check_out_latitude: number | null;
  check_out_longitude: number | null;
  check_in_address: string | null;
  check_out_address: string | null;
  status: number;             // 0 = open (on duty), 1 = completed, 2 = cancelled
  notes: string | null;
}
```

---

### 2.6 Live Location Telemetry (`GuardLiveLocation`)
Table: `guard_live_locations`

```typescript
interface GuardLiveLocation {
  id?: number;
  guard_id: number;
  latitude: number;           // Required
  longitude: number;          // Required
  accuracy_meters?: number;   // GPS accuracy in meters
  address?: string;
  assignment_id?: number;
  attendance_id?: number;
  recorded_at: string;
}
```

---

## 3. Communication & Synchronization Protocol

When a database column or data contract changes:
1. **Backend First**: The Core PHP developer alters the database using a migration in `database/migrations/`, updates the corresponding Model, and updates this document.
2. **Notification**: The backend developer informs the Flutter developer of the updated field name or status code.
3. **Flutter Update**: The Flutter developer updates Dart models and repository services.
4. **Never Invent Columns**: The Flutter app must never invent arbitrary backend field names that do not exist in `secure360_v2`.
