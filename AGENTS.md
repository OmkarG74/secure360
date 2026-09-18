# AGENTS.md - Secure360 AI Pair Programming Guidelines & Invariants

This file defines the mandatory architectural rules, coding standards, and invariant constraints for any AI agent or developer working on **Secure360**.

---

## 1. Two-Developer Collaboration Model

Secure360 is built cooperatively by two developers:
- **Developer 1 (Web / Backend Lead)**: Manages the Core PHP web application, Superadmin and Organisation Admin portals, MySQL schema/migrations, and REST API routing/controllers in the project root.
- **Developer 2 (Mobile Client Lead)**: Manages the Flutter Guard mobile application located exclusively inside the `mobile/` directory.

### Key Collaboration Principles:
1. **Separation of Workspaces**:
   - Web & Admin portal changes take place in `app/`, `resources/views/`, `routes/web.php`, and `public/`.
   - Guard Mobile changes take place in `mobile/`.
   - REST API contracts and endpoints bridge the two layers via `routes/api.php`, `app/Controllers/Api/*`, and `docs/API_CONTRACT.md`.
2. **Contract-First API Evolution**:
   - Flutter **NEVER** connects directly to MySQL. All communication must occur through versioned REST APIs under `/api/v1/*`.
   - Any modification to API request bodies or response structures must be updated in `docs/API_CONTRACT.md` before code changes are merged.
3. **Database Schema as Single Source of Truth**:
   - The MySQL database `secure360_v2` is shared by both web and mobile via the API.
   - Any database modification requires an incremental numbered migration script in `database/migrations/`.

---

## 2. Non-Negotiable Technology Stack Rules

- **Pure Core PHP Only**: Use Core PHP (PHP 8.2+). **STRICTLY NEVER** install or introduce Laravel, Symfony, CodeIgniter, or any external full-stack PHP frameworks.
- **Autoloading**: Rely on `App\Core\Autoloader` (PSR-4 compliant) mapping `App\` to `app/`. Do not introduce arbitrary Composer packages unless specifically instructed.
- **Database Engine**: Centralized PDO MySQL prepared statements via `App\Core\Database` connecting to `secure360_v2`. **NEVER** write raw string-concatenated SQL queries that permit SQL injection.
- **Web Server Compatibility**: The application must run cleanly on standard Apache / WAMP / XAMPP environments under both root domains and subdirectories (e.g., `http://localhost/Secure360/`).
- **Apache MultiViews Disabled**: `.htaccess` must include `Options -MultiViews -Indexes +FollowSymLinks` to prevent Apache content-negotiation conflicts with REST API routes.
- **Frontend**: Plain HTML5, Vanilla CSS3 (using custom variables in `style.css`), and Vanilla JavaScript. Do NOT install TailwindCSS or npm build tools.
- **Mobile Client**: Pure Flutter/Dart project in `mobile/`. Supports dynamic API base URLs via `--dart-define=API_BASE_URL=...`.

---

## 3. Database Schema Invariants (`secure360_v2`)

The database `secure360_v2` is the single source of truth:
1. `roles` (`super_admin`, `admin`, `guard`)
2. `organizations` (tenant root, spelled with 'z')
3. `users` (credentials for Superadmins, Admins, and Guards)
4. `customers` (represents client accounts)
5. `sites` (physical posts; foreign key `customer_id` &rarr; 1 Client has Many Sites)
6. `guards` (linked to `users.id`)
7. `contracts` (bound to `customer_id` and `site_id`)
8. `contract_shifts` (shift schedules per contract)
9. `contract_guard_assignments` (allocates Guard to Site, Contract, Shift)
10. `attendance` (check-in/out timestamps and GPS coordinates)
11. `guard_live_locations` (real-time telemetry)
12. `selfies` (photo verification)
13. `activities` (audit trail)
14. `api_tokens` (Bearer tokens for Flutter API authentication)
15. `notifications` (system alerts)

### Status Conventions:
- Standard status column is `TINYINT`:
  - `0 = active / open`
  - `1 = inactive / completed`
  - `2 = deleted / cancelled`

---

## 4. Multi-Tenancy & Data Isolation Rules

- **Tenant Root**: `organizations` is the tenant root.
- **Superadmin Boundary**: Superadmins operate globally across all organisations (`organization_id = NULL`). Superadmin routes MUST use `SuperAdminMiddleware`.
- **Organisation Admin Boundary**: Organisation Admins operate exclusively within their own organisation. Every query managing customers, sites, guards, contracts, or attendance MUST be scoped by `organization_id`.
- **Tenant Leakage Prevention**: Never display, query, update, or delete data belonging to another organisation. Always verify tenant ownership using `TenantMiddleware` and `Model::findByTenant()`.

---

## 5. API & Mobile Integration Invariants

- **Standard JSON Payload**: All API endpoints must return standardized JSON payloads:
  - **Success**:
    ```json
    {
      "success": true,
      "message": "Human-readable confirmation",
      "data": { ... },
      "status_code": 200
    }
    ```
  - **Failure**:
    ```json
    {
      "success": false,
      "message": "Human-readable error explanation",
      "data": null,
      "errors": { ... },
      "status_code": 400
    }
    ```
- **Consistent User & Guard Keys**: Guard login and profile endpoints must always provide unified `user` and `guard` objects containing `id`, `user_id`, `guard_id`, `name`, `full_name`, `email`, `employee_code`, `organization_id`, and `role`.
- **Safe Flutter Deserialization**: Mobile response parsing must use defensive validation (`_parseResponse<T>()`), safely handling `null` fields and non-JSON HTML error responses without Dart type-cast crashes.
- **Authentication**: Bearer tokens are stored as SHA-256 hashes in `api_tokens`. Plain 64-character tokens are sent in `Authorization: Bearer <token>`.
- **CORS Support**: `Router.php` handles preflight `OPTIONS` requests automatically.
- **Production API URL**: The deployed Railway production API endpoint is `https://secure360-production.up.railway.app/api/v1`. `ApiConfig.baseUrl` defaults directly to this production endpoint while remaining dynamically overridable via `--dart-define=API_BASE_URL=...` for local testing.
- **Local Network Support**: The API also supports connections from Android emulators (`10.0.2.2/Secure360/api/v1`) and physical Wi-Fi devices.

---

## 6. Collaboration & Version Control Invariants

- **No Secrets in Git**: Never commit `.env` or production passwords. Always use `.env.example`.
- **Ignore Platform Local Files**: Never commit machine-specific paths (e.g. `mobile/android/local.properties`, `*.iml`, `.idea/`, `.vscode/`).
- **Database Migrations**: Every database change requires a numbered SQL script in `database/migrations/` (e.g. `002_add_field.sql`).
- **Contract Integrity**: Any API payload change must be reflected in `docs/API_CONTRACT.md`.

---

## 7. Production Deployment & Hosting Invariants (Railway)

- **Docker Runtime**: Railway deployment uses the root `Dockerfile` based on `php:8.2-apache`.
- **No Host-Level Start Command**: Railway's host runtime does not have PHP installed. **NEVER** set a custom start command such as `php -S 0.0.0.0:$PORT -t public public/index.php` in Railway Settings. Apache itself starts as PID 1 via `docker-entrypoint.sh` executing `apache2-foreground`.
- **Apache Document Root**: The web root MUST be `/var/www/html/public`. The application root `/var/www/html` contains `app/`, `routes/`, `resources/`, etc., which are strictly protected from public access.
- **Front Controller & Rewrites**: `public/index.php` handles all incoming requests. Apache `AllowOverride All` is enabled for `/var/www/html/public`, allowing `public/.htaccess` to manage rewrite rules, forward `Authorization` headers, and set security headers.
- **Dynamic Port Binding**: Railway injects `$PORT` dynamically (e.g. 8080, 80). `docker-entrypoint.sh` automatically updates Apache's `ports.conf` and `<VirtualHost>` to listen on `$PORT`.
- **Strict File Permissions**: Only `storage/` and `public/uploads/` are writable by `www-data` (mode 775). All other application code is read-only to the web server.
- **Required PHP Extensions**: `pdo`, `pdo_mysql`, `mysqli`.
- **Production Environment Variables in Railway**:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_URL=https://<your-railway-domain>.up.railway.app`
  - `JWT_SECRET=<generate-a-64-character-random-hex-key>`
  - Database Priority 1: `DB_HOST`, `DB_PORT`, `DB_NAME` (or `DB_DATABASE`), `DB_USER` (or `DB_USERNAME`), `DB_PASS` (or `DB_PASSWORD`)
  - Database Priority 2: `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`
  - Database Priority 3: `DATABASE_URL` (or `MYSQL_URL`)
- **Database Fallback Invariant**: Production **never** silently defaults to `127.0.0.1` / localhost. If database configuration is missing in production, a descriptive exception is thrown and logged server-side.
- **Base Path Invariant**: The application dynamically resolves its base path via `base_path_url()`. On local WAMP, it evaluates to `/Secure360`. On production (domain root), it evaluates to `""`. All assets (`asset()`) and internal links (`url()`) automatically adapt without hardcoding.
- **Error Handling Invariant**: When `APP_DEBUG=false`, internal stack traces, database credentials, and raw exceptions are masked behind a generic 500 error page / JSON payload. Full diagnostic errors are logged server-side to `error_log`.
- **Health Verification**: Verify deployment via `GET /api/v1/health`. It returns HTTP 200 with database connection status and PHP version.
- **MySQL ONLY_FULL_GROUP_BY Invariant**: Railway MySQL and modern MySQL 8.0+ enforce `sql_mode=ONLY_FULL_GROUP_BY`. Never select non-aggregated columns in `GROUP BY` queries unless every selected column is explicitly in `GROUP BY` or guaranteed functionally dependent on unique keys. Avoid improper `GROUP BY` clauses to deduplicate `OR` joins; instead, design joins as 1-to-1 relationships on primary keys using scalar subqueries/`COALESCE` so duplicate rows are prevented at the join level.

---

## 8. Global Date and Time Formatting Standards

To ensure a seamless and professional presentation across Super Admin, Organisation Admin, and Flutter Mobile, all dates and times must adhere to the following unified standards:

### Standard Display Formats:
1. **Date only**: `dd-MMM-yy` (e.g. `18-Sep-26`)
2. **Date and time**: `dd-MMM-yy hh:mm AM/PM` (e.g. `18-Sep-26 05:45 PM`)
3. **Time only**: `hh:mm AM/PM` (e.g. `05:45 PM`, `08:00 AM`)
4. **Date ranges**: `dd-MMM-yy → dd-MMM-yy` (e.g. `01-Jan-26 → 31-Dec-26`)
5. **Shift time ranges**: `hh:mm AM/PM - hh:mm AM/PM` (e.g. `08:00 AM - 04:00 PM`)
6. **Relative time**: Friendly labels like "Today", "Yesterday", or "2h ago" may be used where appropriate for UX, but older timestamps must fall back to the standard date format (`dd-MMM-yy`).

### Timezone Invariant:
- Application timezone is standardized to **`Asia/Kolkata` (IST, UTC+5:30)** across PHP (`date_default_timezone_set('Asia/Kolkata')`), `.env` (`APP_TIMEZONE=Asia/Kolkata`), and MySQL connections (`SET time_zone = '+05:30'`).

### Implementation Rules:
- **Web Application (PHP)**: Use centralized helpers in `app/Helpers/helpers.php`:
  - `format_date($date)`
  - `format_datetime($datetime)`
  - `format_time($time)`
  - `format_date_range($startDate, $endDate)`
  - `format_time_range($startTime, $endTime)`
  - `format_time_ago($datetime)`
- **Mobile Client (Flutter)**: Use `TimeFormatter` in `mobile/lib/core/utils/time_formatter.dart`:
  - `TimeFormatter.formatDate(dateTime)`
  - `TimeFormatter.formatDateTime(dateTime)`
  - `TimeFormatter.formatTime(timeOfDayOrDateTime)`
  - `TimeFormatter.formatDateRange(start, end)`
  - `TimeFormatter.formatShiftRange(start, end)`
  - `TimeFormatter.formatTimeAgo(dateTime)`
- **HTML Forms Exception**: Native HTML `<input type="date">` and `<input type="time">` `value` attributes **MUST** remain in standard ISO format (`YYYY-MM-DD` and `HH:MM`) so that browser date/time pickers operate correctly. Display text and labels around the inputs must use the formatted strings.
- **Database & API Machine Invariant**: Database columns (`DATETIME`, `DATE`, `TIME`) and REST API payloads continue using standard machine-readable ISO/SQL formats (`YYYY-MM-DD HH:MM:SS`). Formatting is applied strictly at the presentation/UI layer.


