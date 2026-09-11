# Secure360 - Architectural Blueprint & Design System

## Overview
Secure360 is an enterprise security operations management platform designed using a clean, scalable, MVC-inspired **Core PHP** architecture (no third-party PHP frameworks, no Laravel).

## Architecture Layers

```
                +------------------------------------+
                |        Web / Mobile Clients        |
                +-----------------+------------------+
                                  |
                                  v
                +------------------------------------+
                |  Web Server (.htaccess Front Gate) |
                +-----------------+------------------+
                                  |
                                  v
                +------------------------------------+
                |   public/index.php (Bootstrap)     |
                +-----------------+------------------+
                                  |
                   +--------------+--------------+
                   |                             |
                   v                             v
           [Config & Autoload]           [Session & Env]
                   |                             |
                   +--------------+--------------+
                                  |
                                  v
                +------------------------------------+
                |        Core\Router Engine          |
                +-----------------+------------------+
                                  |
                                  v
                +------------------------------------+
                |        Middleware Pipeline         |
                | (Auth, Role, Tenant, ApiAuth)      |
                +-----------------+------------------+
                                  |
                   +--------------+--------------+
                   |                             |
                   v                             v
      [Web Controllers]                 [Api Controllers]
             |                                  |
             v                                  v
     [Services Layer]                   [Services Layer]
             |                                  |
             v                                  v
      [Models / DB]                      [Models / DB]
             |                                  |
             v                                  v
      [View Engine]                      [JSON Response]
```

## Directory Responsibilities

1. **`public/`**: Web root accessible to Apache. Contains `index.php`, `.htaccess`, and static assets (`assets/css`, `assets/js`, etc.).
2. **`app/Core/`**: Foundational framework components:
   - `Autoloader.php`: Native PSR-4 autoloader for `App\` namespace.
   - `Router.php`: Regex route matching, route groups, and middleware pipelines.
   - `Request.php` & `Response.php`: Standardized HTTP request and response encapsulation.
   - `Database.php`: Singleton PDO connection manager.
   - `Model.php`: Base active-record / query builder foundation with multi-tenant scoping.
   - `Controller.php`: Base controller providing view rendering, JSON responses, and redirects.
   - `View.php`: Templating engine supporting layout inheritance and reusable components.
   - `Auth.php` & `Session.php`: Identity, role enforcement, and session security.
   - `Validator.php`: Input validation engine.
3. **`app/Middleware/`**: Request filters protecting routes (authentication, role access, tenant boundary isolation, mobile API token validation).
4. **`app/Controllers/`**: Grouped by interface (`Public/`, `Auth/`, `Admin/`, `SuperAdmin/`, `Api/Guard/`).
5. **`app/Models/`**: Domain entities representing database tables.
6. **`app/Services/`**: Business logic layer decoupled from controllers and database queries.
7. **`routes/`**: Route definitions separated by domain (`web.php`, `admin.php`, `superadmin.php`, `api.php`).
8. **`resources/views/`**: Presentation layer featuring layout inheritance (`layouts/admin.php`, `layouts/superadmin.php`) and reusable components (`components/sidebar.php`, `topbar.php`).
9. **`api/`**: Guard Flutter mobile app REST endpoint definitions and standardized response builders.
10. **`storage/`**: Secure logs, uploads, and cache storage outside of public web root.

## Multi-Tenancy Strategy
- Each organisation is treated as a separate tenant.
- Superadmins operate at the global level with `organisation_id = NULL`.
- Admins and Guards are strictly scoped to their respective `organisation_id`.
- All operational queries (clients, sites, guards, contracts, attendance) enforce tenant isolation via `TenantMiddleware` and `Model::findByTenant()` scoping.
