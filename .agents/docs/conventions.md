# Conventions

## PHP

- Procedural pages + a few classes (`Database`, `Auth`, `EmailService`, `BackupManager`). Follow the file you are in.
- Require config with paths relative to the file (`require_once __DIR__ . '/...'` inside includes; `'../config/database.php'` from `admin/` and `api/`).
- Prepared statements only. Fetch helpers: `$db->fetchOne`, `$db->fetchAll`, `$db->query`, `$db->lastInsertId()`.
- `htmlspecialchars` on any value printed into HTML.
- `password_hash` / `password_verify` for passwords. Min length is `PASSWORD_MIN_LENGTH` (6).
- JSON endpoints: `Content-Type: application/json`, integer IDs cast with `(int)`, dates matched as `Y-m-d`.
- Do not add types/strict_types unless the file already has them.

## Naming

- Files: kebab-case PHP in `admin/` and `api/` (`get-booking-details.php`). Root pages are historical (`booking-new.php`).
- Tables/columns: snake_case. Status and slot enums are lowercase with underscores.
- Sessions: donor `user_*`, admin `admin_*`.

## Errors

- User-facing: short message in the page flash / JSON `error` key.
- Server: `error_log(...)`. Do not dump PDO exceptions to the browser in new code (`pending-approvals.php` still does — do not copy that).
- `api/check-availability-slots.php` has `display_errors = 1`. Do not spread that; turn it off if you edit that file.

## Scope

Change only the feature asked for. This codebase has several parallel implementations (two availability APIs, three booking JS files, duplicated `hasPermission`). Unify only when that is the task.

## Comments

Existing files have heavy emoji comments (`✅ FIX #2`). Do not add more of those. New comments only for non-obvious constraints (slot conflict, unique key vs cancelled rows, dual auth).
