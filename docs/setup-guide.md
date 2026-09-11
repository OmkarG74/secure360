# Secure360 Local Setup Guide

Follow this guide to set up and run the Secure360 full-stack environment on your local machine using Apache / WAMP / XAMPP.

---

## 1. Prerequisites
- **Web Server**: Apache (via WAMP, XAMPP, or standalone)
- **PHP**: PHP 8.2 or higher (with `pdo_mysql`, `mbstring`, `json`, `openssl` extensions enabled)
- **Database**: MySQL 8.0+ or MariaDB 10.4+
- **Flutter SDK**: Version 3.10+ (for mobile development)

---

## 2. Web Application Setup

### Step 1: Clone or Copy Project
Ensure the project directory is placed inside your Apache web root:
- WAMP: `c:\wamp64\www\Secure360`
- XAMPP: `c:\xampp\htdocs\Secure360`

### Step 2: Configure `.env` File
Verify that `.env` exists in the project root:
```env
APP_NAME=Secure360
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/Secure360

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=secure360_v2
DB_USER=root
DB_PASS=

SESSION_LIFETIME=7200
```

### Step 3: Database Import
Import the database schema and sample development seed into MySQL:
1. Open phpMyAdmin or your MySQL CLI.
2. Ensure database `secure360_v2` is selected.
3. Import seed records from `database/seeders/development_seed.sql`:
```bash
mysql -u root -p secure360_v2 < database/seeders/development_seed.sql
```

---

## 3. Seed User Accounts & Passwords

All seed accounts use the default password: **`password123`**

| Role | Portal URL | Login Email | Description |
|---|---|---|---|
| **Superadmin** | `http://localhost/Secure360/login` | `superadmin@secure360.local` | Master platform administrator (cross-tenant) |
| **Organisation Admin** | `http://localhost/Secure360/login` | `admin@apexsecurity.com` | Apex Security agency administrator |
| **Security Guard** | Mobile Flutter App | `guard@apexsecurity.com` | Field guard (Badge `GRD-101`) |

---

## 4. Testing Endpoints & Verification

### Test Web Portal
1. Open your browser and navigate to:
   ```
   http://localhost/Secure360/
   ```
2. Click **Sign In** and authenticate with `admin@apexsecurity.com` / `password123`.
3. Navigate to **Clients & Sites** (`/admin/clients-sites`) to view registered client accounts and dynamic multi-site registration.
4. Navigate to **Guards** (`/admin/guards`) to view the security guard roster.

### Test REST API
Run a test request against the health endpoint:
```bash
curl -X GET http://localhost/Secure360/api/v1/health
```
Expected output:
```json
{"success":true,"message":"Secure360 API and Database operational","data":{"status":"healthy","database":"connected"},"status_code":200}
```
