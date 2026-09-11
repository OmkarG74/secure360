# Secure360 - Security Operations Management Platform

[![Platform](https://img.shields.io/badge/Platform-Web%20%7C%20Mobile-blue.svg)](https://github.com/)
[![Backend](https://img.shields.io/badge/Backend-Core%20PHP%208.2+-purple.svg)](https://php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%208.0+-orange.svg)](https://mysql.com/)
[![Mobile](https://img.shields.io/badge/Mobile-Flutter%203.x-cyan.svg)](https://flutter.dev/)

Secure360 is an enterprise multi-tenant security operations platform designed for security agencies. It pairs a **Core PHP MVC web portal** for management with a **Flutter mobile client** for guards in the field, unified through a versioned REST API.

---

## 1. Two-Developer Collaboration Model

The project is architected for seamless two-developer collaboration:

```
┌────────────────────────────────────────────────────────┐
│                      Secure360                         │
├───────────────────────────┬────────────────────────────┤
│   Developer 1 (Web/Lead)  │   Developer 2 (Mobile)     │
│   • Core PHP Web MVC      │   • Flutter Mobile Client  │
│   • Superadmin & Admin UI │   • Guard Operations UI    │
│   • MySQL Database & Migr │   • GPS Telemetry & Selfie │
│   • REST API Controllers  │   • Mobile Local Storage   │
└─────────────┬─────────────┴─────────────┬──────────────┘
              │                           │
              └─────────► REST API ◄──────┘
               (docs/API_CONTRACT.md)
```

1. **Developer 1 (Web / Backend Lead)**:
   - Manages the Core PHP web application, Superadmin and Organisation Admin portals, routing, controllers, models, and MySQL database schema in the root project.
2. **Developer 2 (Guard Mobile Lead)**:
   - Manages the Flutter Guard mobile application located in `mobile/`.
3. **The Shared Contract**:
   - Both developers share the same MySQL database (`secure360_v2`) via the versioned REST API (`/api/v1/*`).
   - The API contract is documented in [docs/API_CONTRACT.md](docs/API_CONTRACT.md). Any change must be agreed upon and updated in this contract.

---

## 2. Technology Stack

- **Web Frontend**: HTML5, Vanilla CSS3 (custom CSS variables in `style.css`), Vanilla JavaScript. No node_modules, Tailwind, or build tools required.
- **Backend**: Pure Core PHP (PHP 8.2+). **STRICTLY NO** Laravel, Symfony, or full-stack PHP frameworks. Custom PSR-4 autoloader (`App\Core\Autoloader`).
- **Database**: MySQL 8.0+ (`secure360_v2`, InnoDB, `utf8mb4`).
- **Web Server**: Apache / WAMP / XAMPP with front-controller rewriting (`.htaccess`).
- **Mobile Client**: Flutter 3.x (Dart 3.x) targeting Android (SDK 33+) and iOS.
- **Authentication**:
  - Web: Native PHP sessions with CSRF tokens and role-based middleware.
  - Mobile API: Bearer tokens hashed with SHA-256 in the database.

---

## 3. System Roles & Boundaries

| Role | Interface | Description |
| :--- | :--- | :--- |
| **SUPERADMIN** | Web (`/superadmin/*`) | Global platform administrator (`organization_id = NULL`). Manages client organisations, provisions tenant admins, sets system-wide configurations. |
| **ADMIN** | Web (`/admin/*`) | Organisation administrator. Manages clients, sites, guard rosters, shift schedules, contract assignments, and real-time attendance logs within their organisation. |
| **GUARD** | Mobile App (`/api/v1/guard/*`) | Field security personnel. Logs in via the Flutter app, views daily assigned posts, records GPS-stamped attendance, and submits field verification selfies. |

---

## 4. Repository Folder Structure

```
Secure360/
├── app/                       # Core PHP MVC Source Code
│   ├── Controllers/           # Web & API Controllers
│   │   ├── Admin/             # Organisation Admin Controllers
│   │   ├── Api/               # API Controllers (Health, GuardAuth, GuardDuty, etc.)
│   │   ├── Auth/              # Web Login/Logout Controller
│   │   └── SuperAdmin/        # Superadmin Controllers
│   ├── Core/                  # Framework Engine (Router, Database, Request, Response, Env)
│   ├── Middleware/            # AuthMiddleware, RoleMiddleware, TenantMiddleware, ApiAuth
│   └── Models/                # Data Models (User, Guard, Site, Contract, Attendance, etc.)
├── database/                  # Database Schemas & Migrations
│   ├── migrations/            # Incremental numbered SQL migration scripts
│   ├── schema/                # Full secure360_v2 base DDL schema
│   └── seeders/               # Test seed data for local development
├── docs/                      # Documentation
│   ├── API_CONTRACT.md        # Master REST API contract between PHP and Flutter
│   ├── architecture.md        # Deep architectural design overview
│   └── database-structure.md  # Detailed MySQL schema and table relationships
├── mobile/                    # Independent Flutter Mobile Application
│   ├── android/               # Native Android configuration & Manifest
│   ├── ios/                   # Native iOS project
│   ├── lib/                   # Dart source (core config, services, auth, duty, attendance)
│   ├── test/                  # Flutter widget and unit tests
│   ├── pubspec.yaml           # Flutter dependencies & metadata
│   └── README.md              # Dedicated mobile setup & run guide
├── public/                    # Apache Web Server Root (Front Controller)
│   ├── assets/                # CSS stylesheets, client JavaScript, images
│   ├── uploads/               # Uploaded images and media
│   ├── .htaccess              # Front-controller URL rewriting rules
│   └── index.php              # Application entry point
├── resources/views/           # PHP Template Views
│   ├── admin/                 # Admin portal screens
│   ├── auth/                  # Web login screen
│   ├── superadmin/            # Superadmin portal screens
│   └── layouts/               # Master layouts, headers, and footers
├── routes/                    # Route Definitions
│   ├── web.php                # Web application routes
│   └── api.php                # Mobile REST API routes (/api/v1/*)
├── storage/                   # Storage (Logs, Uploads, Cache)
├── .env.example               # Template environment configuration
├── .gitignore                 # Multi-stack Git ignore rules
├── AGENTS.md                  # Project invariants and coding rules
└── README.md                  # Main repository README
```

---

## 5. Getting Started (Local Setup)

### Prerequisites
- **Apache & MySQL**: WAMP or XAMPP (Apache 2.4+, PHP 8.2+, MySQL 8.0+)
- **Flutter SDK**: `>= 3.0.0 < 4.0.0`
- **Android Studio / Android SDK**: Platform SDK 33+ (with an Android emulator or device)

---

### Step 1: Clone Repository & Configure Environment
```bash
git clone https://github.com/<YOUR_ORGANIZATION>/Secure360.git
cd Secure360

# Create your local .env configuration:
cp .env.example .env
```

Verify your `.env` contains:
```ini
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/Secure360

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=secure360_v2
DB_USER=root
DB_PASS=
```

---

### Step 2: Set Up MySQL Database (`secure360_v2`)
1. Create the database in phpMyAdmin or MySQL CLI:
   ```sql
   CREATE DATABASE IF NOT EXISTS secure360_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import the base schema and test seed data:
   ```bash
   mysql -u root secure360_v2 < database/schema/secure360_v2_schema.sql
   mysql -u root secure360_v2 < database/seeders/development_seed.sql
   ```

---

### Step 3: Run the Web Application
Ensure Apache is running in WAMP or XAMPP. The project must be located in your Apache root (e.g. `C:\wamp64\www\Secure360`).

- Open your browser to:
  `http://localhost/Secure360/login`
- Or directly access the landing page:
  `http://localhost/Secure360/`

#### Pre-seeded Credentials (Password for all accounts is `password123`):
- **Superadmin**: `superadmin@secure360.local`
- **Organisation Admin**: `admin@apexsecurity.com`
- **Guard**: `guard@apexsecurity.com` *(or Badge Code: `GRD-101`)*

---

### Step 4: Run the Flutter Guard Mobile App
1. Open a new terminal in the `mobile/` directory:
   ```bash
   cd mobile
   flutter pub get
   ```
2. Start an Android emulator or connect a physical device.
3. Launch the app:
   ```bash
   # Android Emulator (default host 10.0.2.2 automatically configured):
   flutter run -d emulator-5554

   # Physical Phone on local Wi-Fi (replace with your PC's LAN IP):
   flutter run -d <DEVICE_ID> --dart-define=API_BASE_URL=http://192.168.1.100/Secure360/api/v1
   ```
4. Log in using the Guard credentials:
   - **Email**: `guard@apexsecurity.com`
   - **Password**: `password123`

---

## 6. Git Collaboration Workflow

To collaborate cleanly without breaking each other's environments:

### Branching Strategy
- **`main`**: Always production-ready, clean, and fully tested.
- **`web-features` / `mobile-features`**: Dedicated feature branches.
- Use descriptive branch names:
  - `feature/guard-shift-timer` (Mobile)
  - `feature/client-billing-pdf` (Web)
  - `fix/api-history-format` (API)

### Invariants Before Committing
1. **Never commit `.env`**: `.env` is ignored in `.gitignore`. Only commit changes to `.env.example`.
2. **Never commit machine-specific files**: Files like `mobile/android/local.properties`, `*.iml`, `.idea/`, and `.vscode/` are strictly ignored.
3. **Database Migrations**:
   - Never alter previous migration scripts.
   - Always add a new incremental file under `database/migrations/` (e.g. `002_add_field_to_sites.sql`).
4. **API Changes**:
   - Any change to an endpoint, request parameter, or response field must be updated in `docs/API_CONTRACT.md` and tested against both PHP and Flutter.

---

## 7. Documentation Directory

- 📜 [docs/API_CONTRACT.md](docs/API_CONTRACT.md) - Master REST API contract
- 📜 [mobile/README.md](mobile/README.md) - Flutter setup and running guide
- 📜 [docs/architecture.md](docs/architecture.md) - High-level architectural diagrams
- 📜 [docs/database-structure.md](docs/database-structure.md) - MySQL table structure
- 📜 [AGENTS.md](AGENTS.md) - Core coding standards and invariants
