# Secure360 - Database Management & Synchronization

## Database Information
- **Database Name**: `secure360_v2`
- **Engine**: MySQL 8.0+ / MariaDB (InnoDB, `utf8mb4_unicode_ci`)
- **Host**: `127.0.0.1` (Port: `3306`)

---

## Existing Tables

| Table | Description | Primary Key | Key Relationships |
|---|---|---|---|
| `roles` | System roles (`super_admin`, `admin`, `guard`) | `id` | Referenced by `users.role_id` |
| `organizations` | Customer security companies (tenant root) | `id` | Referenced by users, customers, sites, contracts, attendance |
| `users` | All portal users & login credentials | `id` | FK to `roles`, `organizations`. Referenced by `guards.user_id`, `api_tokens.user_id` |
| `customers` | Client accounts belonging to an organization | `id` | FK to `organizations`. Referenced by `sites.customer_id` |
| `sites` | Physical security posts / geofenced locations | `id` | FK to `organizations`, `customers`. 1 Customer &rarr; Many Sites |
| `guards` | Guard profiles | `id` | FK to `users.id` (1:1 with user record) |
| `contracts` | Client service contracts | `id` | FK to `organizations`, `customers`, `sites` |
| `contract_shifts` | Shift definitions per contract | `id` | FK to `contracts.id` |
| `contract_guard_assignments`| Allocates guard to site, contract, and shift | `id` | FK to `contracts`, `contract_shifts`, `guards`, `sites` |
| `attendance` | Check-in/out timestamps and GPS logs | `id` | FK to `organizations`, `guards`, `sites`, `contract_guard_assignments` |
| `guard_live_locations` | Real-time GPS location tracking telemetry | `id` | FK to `organizations`, `guards`, `contract_guard_assignments` |
| `selfies` | Facial check-in/out verification pictures | `id` | FK to `organizations`, `guards`, `attendance` |
| `activities` | System audit logs | `id` | FK to `organizations`, `users` |
| `api_tokens` | Bearer token authentication for Flutter app | `id` | FK to `users.id` (SHA-256 token hash) |
| `notifications` | Guard and user system notifications | `id` | FK to `organizations`, `users` |

---

## Setup & Import Instructions

### 1. Create the Database
In phpMyAdmin or MySQL CLI:
```sql
CREATE DATABASE IF NOT EXISTS secure360_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Import Base Schema
Import the initial baseline schema:
```bash
mysql -u root -p secure360_v2 < database/schema/secure360_v2_schema.sql
```
*(Or import `database/schema/secure360_v2_schema.sql` via phpMyAdmin Import tab)*.

### 3. Import Development Seed Data
To populate the database with sample organization, superadmin, admin, guard, customer, site, and duty assignments:
```bash
mysql -u root -p secure360_v2 < database/seeders/development_seed.sql
```

**Default Test Credentials (All password: `password123`)**:
- Superadmin: `superadmin@secure360.local`
- Organisation Admin: `admin@apexsecurity.com`
- Mobile Guard: `guard@apexsecurity.com` (or Employee Code: `GRD-101`)

---

## Database Migration & Synchronization Workflow

GitHub tracks code changes, but **not** MySQL database state. To synchronize database changes between developers (Web developer & Flutter developer):

### When you need to change the database:
1. **Never alter production directly** without a migration script.
2. Create a new SQL file in `database/migrations/` using sequential numeric prefixes:
   ```
   database/migrations/
       001_initial_schema.sql
       002_add_guard_device_token.sql
       003_add_attendance_notes_column.sql
   ```
3. Test the migration locally in your MySQL instance.
4. Update `database/schema/secure360_v2_schema.sql` if tables/columns changed.
5. Commit and push the migration SQL file to GitHub:
   ```bash
   git add database/migrations/00X_your_change.sql database/schema/
   git commit -m "db: add attendance notes column"
   git push
   ```
6. Notify the other developer that a new database migration is available.
7. The other developer pulls and runs the new migration script against their local `secure360_v2` database.
