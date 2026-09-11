# Secure360 - Local Environment Setup Guide

This guide walks you through setting up **Secure360** on your local machine using standard Apache, MySQL, and PHP (via XAMPP or WAMP).

---

## 1. Prerequisites

- **PHP**: 8.2 or higher (with `pdo_mysql`, `openssl`, `mbstring`, `curl` extensions enabled)
- **MySQL**: 8.0+ or MariaDB 10.4+
- **Web Server**: Apache 2.4+ (with `mod_rewrite` and `mod_headers` enabled)
- **Git**: Installed for version control

---

## 2. Directory Placement

Ensure the repository is placed directly in your web server root:
- **WAMP**: `C:\wamp64\www\Secure360`
- **XAMPP**: `C:\xampp\htdocs\Secure360`

---

## 3. Configuration (.env)

Copy the configuration template:
```bash
cp .env.example .env
```

Open `.env` and verify database parameters:
```ini
APP_ENV=development
APP_DEBUG=true
APP_NAME=Secure360
APP_URL=http://localhost/Secure360

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=secure360_v2
DB_USER=root
DB_PASS=
```

---

## 4. Database Setup

1. Start Apache and MySQL from your XAMPP/WAMP Control Panel.
2. Open phpMyAdmin (`http://localhost/phpmyadmin/`) or MySQL terminal:
   ```sql
   CREATE DATABASE IF NOT EXISTS secure360_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the schema:
   ```bash
   mysql -u root -p secure360_v2 < database/schema/secure360_v2_schema.sql
   ```
4. Import seed data (optional, for testing):
   ```bash
   mysql -u root -p secure360_v2 < database/seeders/development_seed.sql
   ```

---

## 5. Web Server Verification

1. Open your browser and navigate to:
   ```
   http://localhost/Secure360/
   ```
   You should see the Secure360 public landing page.
2. Verify API Health:
   ```
   http://localhost/Secure360/api/v1/health
   ```
   Expected JSON:
   ```json
   {
     "success": true,
     "message": "Secure360 API is running",
     "database": "connected",
     "database_name": "secure360_v2"
   }
   ```
3. Test Login entry point:
   ```
   http://localhost/Secure360/login
   ```

---

## 6. Seed Accounts & Credentials

| Role | Email | Password | Employee Code |
|---|---|---|---|
| **Superadmin** | `superadmin@secure360.local` | `password123` | `SA-001` |
| **Admin** | `admin@apexsecurity.com` | `password123` | `ADM-101` |
| **Guard** | `guard@apexsecurity.com` | `password123` | `GRD-101` |
