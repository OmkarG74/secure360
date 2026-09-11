# Secure360 Role Permissions Matrix

Secure360 enforces a strict 3-tier role hierarchy across web and mobile interfaces.

---

## 1. System Roles

| Role Code | Role ID | Interface | Organization ID Scope | Description |
|---|---|---|---|---|
| `super_admin` | 1 | Web Master Portal | `NULL` (Global) | Cross-tenant platform owner |
| `admin` | 2 | Web Operations Portal | Specific Tenant ID | Organisation administrator |
| `guard` | 3 | Flutter Mobile App | Specific Tenant ID | Field security personnel |

---

## 2. Permissions Matrix

| Feature / Action | Superadmin | Organisation Admin | Guard (Mobile) |
|---|:---:|:---:|:---:|
| **Tenant Onboarding** | ✅ Yes | ❌ No | ❌ No |
| **Suspend Organisation** | ✅ Yes | ❌ No | ❌ No |
| **Manage Clients / Customers** | ❌ No | ✅ Scoped to Org | ❌ No |
| **Manage Sites & Posts** | ❌ No | ✅ Scoped to Org | ❌ No |
| **Provision Guards & Passwords** | ❌ No | ✅ Scoped to Org | ❌ No |
| **Create Contracts & Shifts** | ❌ No | ✅ Scoped to Org | ❌ No |
| **View Live Attendance & GPS** | ❌ No | ✅ Scoped to Org | ❌ No |
| **Mobile App Login** | ❌ No | ❌ No | ✅ Authorized |
| **GPS Geofenced Check-In** | ❌ No | ❌ No | ✅ Authorized |
| **Upload Field Selfies** | ❌ No | ❌ No | ✅ Authorized |

---

## 3. Enforcement Layers

1. **Route Middleware Pipeline**:
   - Web Superadmin routes protected by `SuperAdminMiddleware.php`.
   - Web Admin routes protected by `AdminMiddleware.php` and `TenantMiddleware.php`.
   - Mobile API routes protected by `ApiAuthMiddleware.php`.
2. **Model Query Scoping**:
   - `Model::allByTenant($organisationId)` automatically restricts SQL queries to `WHERE organization_id = :org_id`.
