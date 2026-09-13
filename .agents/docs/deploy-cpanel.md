# Deploy (cPanel + GitHub)

Production is Apache on shared hosting. Document root is this project. `SITE_URL` in `config/database.php` must stay `https://supporttheravada.org` on production.

## What GitHub holds

Public repo contains source, `database/schema.sql`, `database/seed.sql`, and `*.example.php`. It must **not** contain:

- `config/database.php`, `config/email.php`
- `supporttheravada_dhana_booking.sql` or other live dumps
- `uploads/receipts/*` (except `index.php`)
- `backup/**` payloads, `error_log`, cron log files

On the server, those files already exist and must be left in place when pulling.

## First-time server layout

```text
public_html/          ← git clone or upload
  config/database.php ← created on server, never from git
  config/email.php
  uploads/receipts/   ← chmod 755, already has files
  backup/             ← writable, Deny from all
```

PHP extensions: PDO MySQL, GD, ZIP.

## Pulling updates

Preferred: Git in cPanel (Git Version Control) tracking `main`, then pull. After pull:

1. Confirm `config/database.php` and `config/email.php` were not overwritten (they are untracked).
2. Run any `ALTER` noted in the commit on phpMyAdmin.
3. Hit the changed page once as donor and once as admin.

FTP/File Manager upload is acceptable if Git is not enabled; do not upload `config/*.php` over production credentials.

## Cron in cPanel

```cron
0 2 * * * /usr/bin/php /home/USER/public_html/cron/daily-backup.php
0 3 1 * * /usr/bin/php /home/USER/public_html/cron/monthly-backup.php
0 1 * * * /usr/bin/php /home/USER/public_html/cron/auto-cancel-pending-bookings.php
0 4 1 * * /usr/bin/php /home/USER/public_html/cron/auto-append-pricing-month.php
0 0 * * * /usr/bin/php /home/USER/public_html/cron/auto-cancel-before-dhana.php
```

Replace `/home/USER/public_html` with the real home. The before-dhana job is currently non-functional until `includes/Database.php` is fixed.

## Local → GitHub

```bash
git add -A
git status   # must not list database.php, email.php, or the production .sql dump
git commit -m "..."
git push origin main
```

Use `gh` only after `gh auth status` is valid.
