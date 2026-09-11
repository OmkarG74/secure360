# Secure360 - Database Design & Entity Architecture

## Entity Relationship Overview

```
[Organisations] (Tenant Root)
   ├── [Users] (Superadmin: NULL org, Admin: org_id)
   ├── [Clients]
   │      └── [Sites] (Physical post / geofenced location)
   ├── [Guards] (Mobile app security personnel)
   ├── [Contracts] (Agreements with Clients)
   ├── [Assignments] (Guards <-> Sites & Shifts)
   ├── [Attendance] (Check-in/out records with GPS timestamps)
   ├── [Settings] (Modular key-value per organisation)
   └── [Audit Logs] (Compliance tracking)
```

## Entity Details

### 1. `organisations`
Customer security organisations managed by Superadmin.
- `id` (PK)
- `name`, `code` (Unique slug), `email`, `phone`, `status`

### 2. `users`
Web portal users for system administration.
- `id` (PK)
- `organisation_id` (FK to organisations, NULL for Superadmin)
- `role_id` (FK to roles)
- `name`, `email`, `password_hash`, `status`

### 3. `clients`
Customers/clients of an organisation.
- `id` (PK)
- `organisation_id` (FK to organisations)
- `name`, `contact_person`, `email`, `phone`, `status`

### 4. `sites`
Physical security posts/sites belonging to a client.
- `id` (PK)
- `organisation_id` (FK to organisations)
- `client_id` (FK to clients)
- `name`, `address`, `latitude`, `longitude`, `geofence_radius_meters`, `status`
- **Rule**: One Client can have multiple Sites (1:N relationship).

### 5. `guards`
Security personnel employed by an organisation.
- `id` (PK)
- `organisation_id` (FK to organisations)
- `badge_number`, `first_name`, `last_name`, `phone`, `password_hash`, `api_token`, `status`

### 6. `contracts`
Client security agreements.
- `id` (PK)
- `organisation_id` (FK), `client_id` (FK)
- `title`, `reference_number`, `start_date`, `end_date`, `status`

### 7. `assignments`
Duty assignments allocating guards to specific sites and shifts.
- `id` (PK)
- `organisation_id` (FK), `guard_id` (FK), `site_id` (FK), `contract_id` (FK)
- `shift_title`, `start_time`, `end_time`, `status`

### 8. `attendance`
Mobile check-in/out telemetry records.
- `id` (PK)
- `organisation_id` (FK), `guard_id` (FK), `site_id` (FK), `assignment_id` (FK)
- `duty_date`, `check_in_time`, `check_out_time`, `check_in_latitude`, `check_in_longitude`, `status`, `notes`

### 9. `settings`
Modular configuration storage.
- `id` (PK)
- `organisation_id` (NULL for global), `setting_group`, `setting_key`, `setting_value`
