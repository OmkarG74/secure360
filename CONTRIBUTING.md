# Contributing to Secure360

Welcome to the **Secure360** collaborative repository! This project is maintained jointly by the Web (Core PHP) and Mobile (Flutter) developers.

---

## Non-Negotiable Invariants

1. **No Frameworks**: Core PHP only for the web backend (No Laravel, No Symfony).
2. **Centralized Database**: All database operations use `App\Core\Database` prepared statements on `secure360_v2`.
3. **No Direct MySQL from Flutter**: The Flutter Guard mobile app communicates **strictly via REST APIs** (`/api/v1/*`).
4. **No Secrets in Git**: Never commit `.env` or sensitive API keys. Use `.env.example` as a template.
5. **Database Changes via Migrations**: Always create a numbered SQL script under `database/migrations/` when altering database structure.

---

## Contribution Steps

1. Clone repository and run `git checkout develop`.
2. Create a feature branch: `git checkout -b feature/your-feature-name`.
3. Test all PHP files with `php -l` and run `php tests/test_health.php`.
4. Commit focused changes with clear messages (`feat:`, `fix:`, `docs:`, `db:`).
5. Push to GitHub and submit a Pull Request to `develop`.
6. Inform your co-developer if database migrations or API contracts changed.

For full workflow details, see:
- 📖 [docs/development/COLLABORATION_WORKFLOW.md](docs/development/COLLABORATION_WORKFLOW.md)
- 📖 [docs/api/SHARED_DATA_CONTRACT.md](docs/api/SHARED_DATA_CONTRACT.md)
- 📖 [docs/mobile/FLUTTER_SETUP.md](docs/mobile/FLUTTER_SETUP.md)
