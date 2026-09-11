# Database Structure - secure360_v2

This document describes the 15 tables comprising the `secure360_v2` database schema.

---

## 1. Global Conventions & Invariants
- **Primary Keys**: `id BIGINT UNSIGNED AUTO_INCREMENT`
- **Standard Status Column**: `status TINYINT NOT NULL DEFAULT 0`
  - `0 = Active / Open`
  - `1 = Inactive / Suspended / Completed`
  - `2 = Deleted / Terminated`
- **Soft Deletes**: Managed via `deleted_at DATETIME NULL`
- **Audit Columns**: `created_at`, `updated_at`, `created_by`, `updated_by`

---

## 2. Table Summary

| Table Name | Description | Key Relationships |
|---|---|---|
| `roles` | System roles (`super_admin`, `admin`, `guard`) | Referenced by `users.role_id` |
| `organizations` | Tenant root entities (spelled with 'z') | Root of all tenant data |
| `users` | User credentials, emails, and employee codes | FK `organization_id`, `role_id` |
| `customers` | Client accounts | FK `organization_id` |
| `sites` | Physical posts, gates, and geofenced zones | FK `organization_id`, `customer_id` |
| `guards` | Guard profiles, portraits, and status | FK `user_id` (1:1 with `users`) |
| `contracts` | Service agreements | FK `organization_id`, `customer_id`, `site_id` |
| `contract_shifts` | Shift timings for a contract | FK `contract_id` |
| `contract_guard_assignments` | Allocates guard to site, contract, shift | FK `contract_id`, `contract_shift_id`, `guard_id`, `site_id` |
| `attendance` | Check-in/out timestamps and GPS logs | FK `organization_id`, `guard_id`, `site_id` |
| `guard_live_locations` | Real-time GPS telemetry pings | FK `guard_id` |
| `selfies` | Facial verification photo uploads | FK `guard_id`, referenced by `attendance` |
| `activities` | Audit trail and system event logs | FK `organization_id`, `user_id` |
| `api_tokens` | SHA-256 Bearer tokens for mobile APIs | FK `user_id` |
| `notifications` | System alerts and push notifications | FK `user_id` |

---

## 3. Core Entity Details

### `organizations`
- `id` (PK)
- `organization_code` (VARCHAR 50, UNIQUE)
- `name` (VARCHAR 150)
- `contact_person`, `email`, `phone`, `address`
- `status` (0=Active, 1=Suspended)

### `users`
- `id` (PK)
- `organization_id` (BIGINT UNSIGNED NULL - NULL for Superadmin)
- `role_id` (BIGINT UNSIGNED - 1=Superadmin, 2=Admin, 3=Guard)
- `full_name` (VARCHAR 150)
- `email` (VARCHAR 180, UNIQUE)
- `phone` (VARCHAR 40)
- `employee_code` (VARCHAR 50, UNIQUE)
- `password_hash` (VARCHAR 255)
- `last_login_at` (DATETIME NULL)
- `status` (0=Active, 1=Inactive)

### `customers` (Clients)
- `id` (PK)
- `organization_id` (BIGINT UNSIGNED)
- `client_code` (VARCHAR 50)
- `name` (VARCHAR 150)
- `contact_person`, `phone`, `email`, `address`
- `status` (0=Active, 1=Inactive)

### `sites`
- `id` (PK)
- `organization_id` (BIGINT UNSIGNED)
- `customer_id` (BIGINT UNSIGNED -> `customers.id`)
- `site_code` (VARCHAR 40, UNIQUE)
- `site_name` (VARCHAR 150)
- `site_address` (TEXT)
- `area`, `zone_gate`
- `latitude`, `longitude` (DECIMAL 10,7)
- `status` (0=Active, 1=Inactive)

### `guards`
- `id` (PK)
- `user_id` (BIGINT UNSIGNED UNIQUE -> `users.id`)
- `photo_url` (VARCHAR 500)
- `status` (0=Active, 1=Inactive)

### `contracts`
- `id` (PK)
- `organization_id`, `customer_id`, `site_id`
- `contract_code` (VARCHAR 60, UNIQUE)
- `start_date` (DATE), `end_date` (DATE NULL)
- `required_guard_count` (INT)
- `active_site_id` (Generated column enforcing 1 active contract per site)
- `status` (0=Active, 1=Expired)

### `attendance`
- `id` (PK)
- `organization_id` (BIGINT UNSIGNED)
- `guard_id` (BIGINT UNSIGNED -> `guards.id`)
- `site_id` (BIGINT UNSIGNED NULL -> `sites.id`)
- `check_in_at` (DATETIME), `check_out_at` (DATETIME NULL)
- `check_in_latitude`, `check_in_longitude`
- `check_out_latitude`, `check_out_longitude`
- `selfie_id` (BIGINT UNSIGNED NULL)
- `status` (0=On Duty, 1=Completed, 2=Cancelled)
