# Secure360 System Architecture

## 1. Executive Summary
**Secure360** is a multi-tenant security operations management platform designed for security agencies, enterprise clients with distributed physical sites, and mobile field personnel.

The platform provides a dual-interface model:
1. **Web Command Portal**: Built with **Pure Core PHP (PHP 8.2+)**, Vanilla CSS3, and JavaScript running on Apache / WAMP / XAMPP, serving **Superadmin** and **Organisation Admin** users.
2. **Field Guard Mobile Client**: A **Flutter** cross-platform mobile application communicating exclusively through versioned REST APIs (`/api/v1/*`) using Bearer tokens. Flutter **never** connects directly to MySQL.

---

## 2. Core Architectural Invariants
- **Zero Full-Stack Frameworks**: No Laravel, Symfony, or CodeIgniter. All architecture uses native PHP with PSR-4 autoloading via `App\Core\Autoloader`.
- **Database Engine**: Centralized PDO MySQL connection with prepared statements in `App\Core\Database` connecting to `secure360_v2`.
- **Tenant Data Isolation**: Database root is `organizations`. Superadmins operate globally (`organization_id = NULL`), while Organisation Admins and Guards are strictly scoped to their tenant organization (`organization_id`).

---

## 3. High-Level Architecture Diagram

```
+-------------------------------------------------------------------------+
|                              CLIENT TIER                                |
+------------------------------------+------------------------------------+
|        Web Browser Clients         |        Mobile Guard Client         |
|  - Superadmin Master Portal        |  - Flutter Android/iOS App         |
|  - Organisation Admin Portal       |  - GPS Check-in & Selfies          |
|  (Vanilla HTML5 / CSS3 / ES6 JS)   |  (HTTP REST API Consumer)          |
+------------------------------------+------------------------------------+
                   |                                    |
                   | HTTP Session / Cookie              | Bearer Token (SHA-256)
                   v                                    v
+-------------------------------------------------------------------------+
|                           APPLICATION TIER                              |
|                    (Core PHP 8.2+ PSR-4 Application)                    |
+-------------------------------------------------------------------------+
|  Routing: App\Core\Router (RegEx parameter matching, CORS, Middleware)  |
|                                                                         |
|  Middleware Pipeline:                                                   |
|    - AuthMiddleware (Session validation)                                |
|    - AdminMiddleware / SuperAdminMiddleware (Role enforcement)          |
|    - TenantMiddleware (Tenant boundary verification)                    |
|    - ApiAuthMiddleware (SHA-256 Bearer token check in api_tokens)       |
|                                                                         |
|  Controllers:                                                           |
|    - Admin (ClientSiteController, GuardController, ContractController)   |
|    - SuperAdmin (OrganisationController, SettingsController)           |
|    - Api\Guard (GuardAuthController, GuardAttendanceController)         |
+-------------------------------------------------------------------------+
                                   |
                                   | Prepared PDO Statements
                                   v
+-------------------------------------------------------------------------+
|                            DATABASE TIER                                |
|                  MySQL (Database: secure360_v2)                         |
+-------------------------------------------------------------------------+
|  15 Tables: organizations, users, roles, customers, sites, guards,      |
|  contracts, contract_shifts, contract_guard_assignments, attendance,     |
|  guard_live_locations, selfies, activities, api_tokens, notifications   |
+-------------------------------------------------------------------------+
```

---

## 4. Multi-Tenancy Hierarchy
```
Organizations (Tenant Root)
  └── Users (Superadmin: null, Admin: org_id, Guard: org_id)
        └── Customers (Client Accounts)
              └── Sites (Physical Security Posts & Gates)
                    └── Contracts (Agreements bound to Site)
                          └── Contract Shifts (Daily Schedules)
                                └── Guard Assignments (Guard -> Post -> Shift)
                                      └── Attendance (GPS Check-ins & Selfies)
```

---

## 5. Security & Authentication Model
- **Web Sessions**: Secure PHP native session management with session ID regeneration on login and CSRF tokens on all POST requests.
- **Mobile API Tokens**: 64-character Bearer tokens generated on login, stored as SHA-256 hashes in `api_tokens`, and verified via `ApiAuthMiddleware`.
