# Known issues

Fix these when they block a task; do not “clean up” them unsolicited.

1. **`config/backup.php` cannot dump on cPanel.** It `require`s missing `config/config.php` and calls `C:\xampp\mysql\bin\mysqldump.exe`. `admin/settings.php` still includes this file.
2. **`cron/auto-cancel-before-dhana.php` fatals.** It requires `includes/Database.php`, which does not exist. It should use `getDB()` from `config/database.php` like the other cron jobs.
3. **Email is not SMTP.** `EmailService` ignores socket-level SMTP and uses `mail()`. Gmail app-password settings will not send by themselves on local Windows.
4. **`hasPermission()` is copy-pasted** in `admin/index.php`, `analytics.php`, `pricing-history.php`, `role_management.php`, and others. Behavior can drift.
5. **Raw `new PDO(...)`** in `admin/pending-approvals.php` (and possibly others) bypasses `getDB()` and its options (`ERRMODE`, `EMULATE_PREPARES`).
6. **`check-availability.php` ignores `settings.booking_advance_days`** and hardcodes 30 days. `calendar.php` also caps years at +2.
7. **Cancelled bookings still occupy** `unique_booking_date_slot`. Re-booking the same date/type/slot after cancel can fail at insert even when the UI says free.
8. **CSRF is not implemented** (README already flags this). Forms are session-cookie POST only.
9. **Cron browser key** `dhana_cron_2024` is hardcoded in `auto-cancel-pending-bookings.php`. Other crons use `?manual_run=` with no key.
10. **No root `.htaccess`.** `config/`, `cron/`, `backup/`, `database/` are reachable if the vhost allows PHP/SQL download. Production may have a server-level rule; do not assume it.
11. **README is stale** in places: it says tables auto-create (they do not), mentions `backups/` (actual dir is `backup/`), and an older clone URL.
12. **`users.role` vs `admin_users.role`.** Some donor rows have empty `role`. Admin UI is gated on `admin_users` only.
13. **`pending-approvals.php` apply switch** only handles `booking_update`. Other `admin_actions` types can be approved without applying.

When you fix one of these, delete or shrink the matching bullet here.
