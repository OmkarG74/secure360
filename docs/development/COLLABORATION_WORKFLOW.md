# Secure360 - Git & GitHub Collaboration Workflow

This guide establishes the version control and collaborative workflow between the **Web Developer** (Core PHP) and the **Mobile Developer** (Flutter).

---

## 1. Git Branching Strategy

To prevent accidental code overwrites while allowing rapid independent development:

```
main (Production / Stable Release)
  └── develop (Integration Branch)
        ├── feature/web-admin          (Web Admin / Superadmin work)
        ├── feature/guard-api          (PHP Backend API adjustments)
        └── feature/flutter-guard      (Flutter mobile app development)
```

### Branch Rules:
- **`main`**: Always contains working, tested code. Never commit directly to `main`.
- **`develop`**: The primary staging branch where web and mobile changes merge.
- **Feature Branches**: Named descriptively: `feature/web-dashboard`, `feature/flutter-auth`, `feature/guard-checkin`.

---

## 2. Day-to-Day Development Workflow

### Step 1: Always Pull Latest Changes Before Starting Work
```bash
git checkout develop
git pull origin develop
```

### Step 2: Create a Feature Branch
```bash
# For web work:
git checkout -b feature/web-client-modal

# For mobile work:
git checkout -b feature/flutter-duty-screen
```

### Step 3: Check Status & Stage Only Relevant Files
```bash
git status
git add app/Controllers/Admin/ClientSiteController.php
git add resources/views/admin/clients-sites/index.php
```

### Step 4: Commit with a Clear Message
Use conventional commit prefixes: `feat:`, `fix:`, `docs:`, `db:`, `refactor:`:
```bash
git commit -m "feat(admin): add client site modal and validation"
```

### Step 5: Push Feature Branch to GitHub
```bash
git push origin feature/web-client-modal
```

### Step 6: Create Pull Request (PR) on GitHub
- Open a Pull Request targeting the `develop` branch.
- Ask the other developer to review before merging.
- Once merged into `develop`, both developers run `git pull origin develop`.

---

## 3. Database Synchronization Workflow

Since Git **does not track MySQL database state**, follow this exact protocol whenever database changes occur:

### When changing the database:
1. Create a numbered SQL file inside `database/migrations/`:
   ```
   database/migrations/002_add_guard_device_token.sql
   ```
2. Test the SQL statement locally.
3. Commit and push the migration file to GitHub:
   ```bash
   git add database/migrations/002_add_guard_device_token.sql
   git commit -m "db: add guard device token column"
   git push origin <your-branch>
   ```
4. Tell the other developer: *"New migration 002 committed, please pull and run it locally."*
5. The other developer runs:
   ```bash
   mysql -u root -p secure360_v2 < database/migrations/002_add_guard_device_token.sql
   ```

---

## 4. What Must NEVER Be Committed to GitHub

Check `.gitignore` to ensure these are excluded:
- ❌ **`.env`** (Contains local passwords, secret keys)
- ❌ **Real production credentials or passwords**
- ❌ **`storage/logs/*`** (Server logs)
- ❌ **`storage/uploads/*`** (Private uploaded documents/photos)
- ❌ **IDE configs** (`.idea/`, `.vscode/`, `*.sublime-project`)
- ❌ **Temporary build files** (`.dart_tool/`, `build/`, `vendor/`)

---

## 5. Git Command Cheatsheet

| Task | Command |
|---|---|
| Clone repository | `git clone https://github.com/<org>/Secure360.git` |
| View current branch & changes | `git status` |
| Switch branch | `git checkout <branch-name>` |
| Create and switch to new branch | `git checkout -b <new-branch>` |
| Pull latest changes from remote | `git pull origin <branch-name>` |
| Stage file | `git add <file-path>` |
| Stage all tracked changes | `git add -u` |
| Commit staged changes | `git commit -m "descriptive message"` |
| Push branch to GitHub | `git push origin <branch-name>` |
| Stash local uncommitted work | `git stash` |
| Restore stashed work | `git stash pop` |
| View recent commits | `git log --oneline -n 10` |

---

## 6. Resolving Merge Conflicts

If Git reports a conflict during `git pull` or `git merge`:
1. Open the conflicting files highlighted by Git.
2. Look for conflict markers:
   ```
   <<<<<<< HEAD
   Current local changes
   =======
   Incoming remote changes
   >>>>>>> develop
   ```
3. Discuss with your co-developer to keep the correct lines and delete the markers.
4. Test that the code still runs without syntax errors:
   ```bash
   php tests/test_health.php
   ```
5. Commit the resolved conflict:
   ```bash
   git add <resolved-file>
   git commit -m "merge: resolve conflict in api.php"
   ```
