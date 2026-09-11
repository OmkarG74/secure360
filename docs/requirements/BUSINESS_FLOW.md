# Secure360 - Business Operations Flow

## Global Request Flow

```
PUBLIC WEBSITE
    ↓
Login
    ↓
Authentication
    ↓
Role Detection
    ├── Superadmin → Superadmin Panel (/superadmin/dashboard)
    ├── Admin      → Admin Panel (/admin/dashboard)
    └── Guard      → Flutter Mobile API (/api/v1/guard)
```

---

## 1. Superadmin Flow
The Superadmin oversees the SaaS platform and provisions customer organisations.

```
Superadmin Dashboard
    ↓
Organisations
    ↓
Create Organisation
    ↓
Provision Organisation Admin Account
    ↓
Configure Tenant Settings & Policies
    ↓
Organisation Manages its own Clients, Sites, Guards, Contracts, and Attendance
```

### Key Capabilities:
- View multi-tenant operational summaries.
- Create new customer / organisation accounts.
- Activate, deactivate, or suspend tenant access.
- Manage global system configurations and audit trails.

---

## 2. Organisation Admin Flow
The Organisation Admin manages day-to-day security operations for their specific company.

```
Admin Dashboard
    ↓
Clients & Sites
    ↓
Client
    ↓
Multiple Sites
    ↓
Contracts
    ↓
Guard Assignments
    ↓
Attendance
    ↓
Reports & Operational Tracking
```

### Hierarchy Rule:
```
Organisation
  └── Client A
        ├── Site 1
        ├── Site 2
        └── Site 3
  └── Client B
        ├── Site 4
        └── Site 5
```
- A single organisation manages multiple clients.
- Each client can have multiple physical sites.
- Contracts bind clients and sites with security service terms.

---

## 3. Guard & Mobile Integration Flow
Field guards interact exclusively through the Flutter Mobile Application using dedicated REST APIs.

```
Flutter App Login
    ↓
Token Authentication (Bearer JWT)
    ↓
Fetch Guard Profile
    ↓
View Assigned Sites & Duties
    ↓
Attendance Check-in (GPS + Timestamp)
    ↓
API Submits to Core PHP Backend
    ↓
Admin Web Panel Views Updated Live Operations
    ↓
Attendance Check-out
```
