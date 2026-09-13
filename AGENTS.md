# AGENTS.md

Dāna (alms-giving) reservation system for Support Theravada / Kshemabhoomi. Vanilla PHP + MySQL on Apache shared hosting (cPanel). Production: `https://supporttheravada.org`.

This file is the always-on index. Read a linked doc before touching that area. Nested `AGENTS.md` files in `admin/`, `api/`, `cron/`, `config/`, `includes/`, and `assets/` apply when working in those directories.

## Stack

- PHP 7.4+ (production is PHP 8.4 on MariaDB 11.4). No Composer, no framework, no bundler.
- MySQL/MariaDB via PDO (`config/database.php` → `getDB()`).
- Frontend: HTML + CSS + vanilla JS. Font Awesome 6 and Google Fonts loaded from CDN.
- Deploy: copy files to cPanel `public_html` (or subdomain docroot). No build step.

## Commands

There is no test suite, linter, or package manager. Verify by running the PHP page or API endpoint you changed.

Local (Apache + MySQL, e.g. XAMPP/Laragon):

```text
1. Copy config/database.example.php → config/database.php and fill credentials.
2. Copy config/email.example.php → config/email.php if you need mail.
3. Create DB, then import database/schema.sql then database/seed.sql.
4. Point the vhost document root at this folder.
5. Donor UI: /index.php     Admin UI: /admin/index.php
```

cPanel cron examples live in `.agents/docs/deploy-cpanel.md`.

## Invariants

- **Do** use `getDB()` / `Database` from `config/database.php` and PDO prepared statements. **Never** concatenate user input into SQL. **Never** add a second connection helper (`includes/Database.php` does not exist; `new PDO(...)` in admin pages is legacy — do not copy it).
- **Do** keep donor auth (`includes/auth.php`, `users` table, `$_SESSION['user_id']`) separate from admin auth (`admin/index.php`, `admin_users` table, `$_SESSION['admin_logged_in']`). They are two login systems.
- **Do** match existing page style: PHP at top, HTML below, CSS in `assets/css/`, JS in `assets/js/`. No React/Vue/jQuery unless already present.
- **Do** preserve bilingual UI (English + Sinhala) and the golden/brown theme (`#d4822a`, `#b8860b`).
- **Do** treat `config/database.php` and `config/email.php` as local secrets. Commit only the `*.example.php` files.
- **Never** commit production SQL dumps, receipt images, backups, `error_log`, or password hashes. **Never** log or echo DB/SMTP passwords.
- **Never** add Composer, npm, or a framework without an explicit request.
- **Never** “fix” spelling of public user-facing “dāna/dhana” inconsistently — the DB and many identifiers use `dhana`; UI copy often uses `dāna`. Keep both as they are unless renaming is the task.

## Docs (read when relevant)

| When | Read |
| --- | --- |
| Any feature that spans pages or tables | `.agents/docs/architecture.md` |
| Schema, queries, migrations | `.agents/docs/database.md` |
| Booking, calendar, payment, availability | `.agents/docs/booking-flow.md` |
| Admin panel, roles, permissions | `.agents/docs/admin-rbac.md` |
| CSS/JS | `.agents/docs/frontend.md` |
| Cron, backups, email | `.agents/docs/cron-backup.md` |
| cPanel / GitHub deploy | `.agents/docs/deploy-cpanel.md` |
| How to edit this codebase | `.agents/docs/conventions.md` |
| Broken or misleading code | `.agents/docs/known-issues.md` |

## Done when

- The changed PHP page or JSON endpoint runs without notices for the happy path and one failure path.
- New queries use prepared statements and respect booking uniqueness / slot conflict rules.
- Secrets and uploads are still gitignored.
- README/AGENTS docs updated only if you changed setup, schema, or deploy.
