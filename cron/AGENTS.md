# cron/

CLI jobs for cPanel. Read `.agents/docs/cron-backup.md` and `.agents/docs/known-issues.md`.

- Use `getDB()` from `config/database.php`. Do not add `includes/Database.php` callers — that file is missing and breaks `auto-cancel-before-dhana.php`.
- Refuse browser access unless a documented key/`manual_run` flag is present.
- `on_hold` bookings must not be auto-cancelled.
- Timeout/days settings: `0` means disabled.
- Logs go under `cron/logs/` (gitignored). Echo enough for cPanel email output.
- Backup jobs go through `BackupManager`; that class is currently XAMPP-oriented.
