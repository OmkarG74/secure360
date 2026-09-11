# Secure360 Development & Collaboration Workflow

This document establishes the collaboration rules and engineering workflow between the **Web Core PHP Developer** and the **Mobile Flutter Developer**.

---

## 1. Golden Rules of Collaboration

1. **The Database is the Single Source of Truth**:
   The `secure360_v2` database schema defines all business entities. Never create ad-hoc database columns without a migration script in `database/migrations/`.
2. **Flutter Never Connects Directly to MySQL**:
   The Flutter client interacts with backend services **exclusively** through versioned REST APIs under `/api/v1/*`.
3. **Contracts Before Implementation**:
   Any changes to API payloads must first be updated in `docs/api/SHARED_DATA_CONTRACT.md` and `docs/api-documentation.md` before changing backend or Flutter code.

---

## 2. Git Branching Strategy

```
main (Production-ready stable branch)
  └── staging (Integration testing between Web and Mobile)
        ├── feat/admin-clients
        ├── feat/guard-roster
        └── feat/flutter-checkin
```

### Commit Guidelines
- Format: `<type>(<scope>): <subject>`
  - `feat(api)`: New API endpoint
  - `feat(web)`: New Admin or Superadmin view
  - `fix(db)`: Schema fix or seeder update
  - `docs`: Documentation updates

---

## 3. Database Migration Protocol

When adding new tables or columns:
1. Create a numbered script in `database/migrations/`:
   - e.g., `002_add_field_name.sql`
2. Update `docs/database-structure.md` to reflect the new columns.
3. Update corresponding models in `app/Models/`.

---

## 4. Testing & Verification Checklist

Before pushing changes:
1. **PHP Syntax Check**:
   ```bash
   php -l app/Controllers/...
   ```
2. **API Health Ping**:
   ```bash
   curl -X GET http://localhost/Secure360/api/v1/health
   ```
3. **Mobile Token Authentication**:
   Verify that login returns a 64-character token in `api_tokens`.
