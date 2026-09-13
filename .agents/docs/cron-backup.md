# Cron, backups, email

## Cron scripts (`cron/`)

| Script | Intended schedule | What it does |
| --- | --- | --- |
| `auto-cancel-pending-bookings.php` | hourly or daily | Cancels `status=pending` older than `pending_booking_timeout_hours`. Skips `on_hold`. Timeout `0` disables. CLI or `?key=dhana_cron_2024`. |
| `auto-cancel-before-dhana.php` | daily midnight | Cancels unconfirmed bookings whose dāna date is within `auto_cancel_days_before_dhana`. **Broken today** — requires missing `includes/Database.php`. See known-issues. |
| `auto-append-pricing-month.php` | daily | Extends `monthly_pricing` out to `pricing_window_months`. Logs to `cron/logs/pricing-append.log` (gitignored). |
| `daily-backup.php` | 02:00 | `BackupManager::createDatabaseBackup('daily')`. |
| `monthly-backup.php` | 1st 03:00 | Monthly DB backup + receipt zip. |

New cron files must refuse casual browser hits (CLI and/or a secret query key). Do not add new hardcoded keys like `dhana_cron_2024`; if you touch that key, move it to config.

## Backups

`config/backup.php` → class `BackupManager`. Directories: `backup/daily`, `backup/monthly`, `backup/receipts`. Retention from `backup_retention_days`.

**Do not assume backups work on cPanel.** The dump command is hardcoded to `C:\xampp\mysql\bin\mysqldump.exe` and credentials are loaded from missing `config/config.php`. Fixing that is a real task, not a drive-by.

Never commit files under `backup/`.

## Email

- Config: `config/email.php` (gitignored) from `config/email.example.php`.
- `includes/email.php` `EmailService::sendEmail()` uses PHP `mail()` plus MIME headers. SMTP_* constants are **not** used to open an SMTP socket. Password-reset and booking emails depend on the host’s `mail()` (cPanel usually relays).
- Booking templates: `includes/booking-emails.php`.

If you add mail, keep HTML + plain parts, and do not put tokens in query logs.
