# Architecture

Two apps share one MySQL database and one document root.

## Donor app (public)

| URL | File | Auth |
| --- | --- | --- |
| `/` login + register | `index.php` | none; redirects if `user_id` session |
| `/dashboard.php` | welcome + pricing + CTAs | `requireLogin()` |
| `/calendar.php` | month grid of availability | `requireLogin()` |
| `/booking-new.php` | 4-step reservation form | `requireLogin()` |
| `/payment.php?booking_id=` | bank details + receipt upload | `requireLogin()` + owns booking |
| `/my-bookings.php` | donor's reservations | `requireLogin()` |
| `/settings.php` | donor profile / password | `requireLogin()` |
| `/forgot-password.php`, `/reset-password.php` | token reset | none |

Session: `includes/auth.php` → `users` table. Helpers: `getAuth()`, `requireLogin()`. Timeout: `SESSION_TIMEOUT` (3600s) from `config/database.php`.

## Admin app (`/admin/`)

Separate login on `admin/index.php` against `admin_users`. Session keys: `admin_logged_in`, `admin_id`, `admin_role`, `is_administrator`, `is_editor`, `is_supervisor`, `is_super_admin` (alias of administrator).

Shared chrome: `admin/includes/sidebar.php`. Permission checks are **copied** into each page as a local `hasPermission($name)` that reads `role_permissions`. There is no shared admin auth include — do not invent one unless the task is to extract it.

## Shared PHP

- `config/database.php` — constants + singleton `Database` + `getDB()`.
- `config/email.php` — SMTP constants (Gmail app password in production).
- `config/backup.php` — `BackupManager` (see known-issues: it still expects `config/config.php` and a Windows `mysqldump` path).
- `includes/email.php` — `EmailService` (currently PHP `mail()`, not a real SMTP client).
- `includes/booking-emails.php` — `BookingEmailService`.
- `includes/favicon.php` — `<link>` tags.

## JSON APIs (`/api/`)

Used by the booking JS. All require donor login except price GETs.

- `check-availability.php` — POST JSON `{date, dhana_type_id}` (legacy, 30-day cap hardcoded).
- `check-availability-slots.php` — POST JSON `{date, dhana_type_id, time_slot}` (the one the step form should use).
- `get-monthly-price.php` — GET `dhana_type_id`, `date`.
- `get-annual-prices.php` — GET for multi-year annual quotes.

## Data flow (happy path)

1. Donor registers/logs in (`users`).
2. Picks a date on `calendar.php` or goes to `booking-new.php`.
3. JS loads prices via `/api/get-monthly-price.php` and slots via `/api/check-availability-slots.php`.
4. POST `booking-new.php` inserts `bookings` (`status=pending`) and, if annual, child rows + `annual_bookings`.
5. Donor uploads receipt on `payment.php` → `payment_receipts` + `status=receipt_submitted`.
6. Admin on `admin/index.php` moves status (`payment_pending` / `confirmed` / `cancelled` / `completed`). Sensitive edits can go through `admin_actions` for administrator approval (`admin/pending-approvals.php`).
7. Cron cancels stale pending rows and extends `monthly_pricing`.

## Hosting shape

Document root = this repo. `uploads/receipts/` must be writable. `backup/` is written by cron (protect with `.htaccess` Deny). No `.htaccess` is committed at repo root today.
