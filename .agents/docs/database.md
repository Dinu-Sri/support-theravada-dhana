# Database

Engine: MariaDB 11.4 (production), utf8mb4. PDO from `getDB()`.

Source of truth for empty installs: `database/schema.sql` + `database/seed.sql`.

The phpMyAdmin dump `supporttheravada_dhana_booking.sql` (repo root) is a **local production snapshot**. It is gitignored. Use it to inspect live data on this machine; never commit it.

## Tables

| Table | Role |
| --- | --- |
| `users` | Donors. Unique `email`. `is_monk`, `role` (enum), optional `admin_user_id` → `admin_users`. |
| `admin_users` | Staff. Unique `username` + `email`. Role enum: `administrator`, `editor`, `supervisor`. |
| `dhana_types` | Catalog. Seeded: 1 Whole Day 180000, 2 Lunch 100000, 3 Morning 80000, 4 Extra Item 0 (`time_slot=extra`, "Coming Soon"). |
| `bookings` | One row per date instance. Status enum below. `parent_booking_id` links annual children to year-1. `on_hold` skips auto-cancel. |
| `annual_bookings` | Metadata for a year-1 booking: `year_start`/`year_end`. |
| `monthly_pricing` | Unique `(dhana_type_id, year, month)`. `is_confirmed` 1=firm, 0=tentative. |
| `pricing_history` | Audit of price edits. |
| `payment_receipts` | Uploaded files under `uploads/receipts/`. |
| `blocked_dates` | Admin-blocked calendar days. |
| `settings` | Key/value. See keys below. |
| `role_hierarchy` | donor=1, supervisor=2, editor=3, administrator=4. |
| `role_permissions` | Unique `(role_name, permission_name)`. |
| `admin_actions` | Staff change requests; `status` pending/approved/rejected. |
| `user_role_changes` | Audit when a `users.role` changes. |
| `password_reset_tokens` | Donor reset tokens. |
| `role_migration_backup` | One-off 2025 role rename leftover. Do not write new code against it. |

Views (no DEFINER in `schema.sql`): `pending_admin_actions`, `user_permissions`.

## Booking status

`pending` → `receipt_submitted` → `payment_pending` → `confirmed` → `completed`, or `cancelled` from several points.

Code and cron treat `cancelled` as “not occupying the slot”. Unique key `unique_booking_date_slot (booking_date, dhana_type_id, booking_time_slot)` still applies to cancelled rows — a cancelled booking can block a new insert of the same triple unless you delete or change the slot. Prefer updating status, and if re-booking the same slot is required, handle that unique key.

## Slot conflict rule (application-level)

In `booking-new.php` (and the slots API):

- `whole_day` conflicts with **any** non-cancelled booking on that date.
- `morning` / `lunch` conflicts with the same `(dhana_type_id, time_slot)` **or** any `whole_day` on that date.

Morning and lunch of different types can coexist. Type 4 Extra is not a real bookable slot yet.

## Settings keys used in code

| Key | Default / live | Used by |
| --- | --- | --- |
| `site_name` | Dāna Reservation System | display |
| `contact_email` | | display |
| `bank_details` | | `payment.php` |
| `booking_advance_days` | 30 | **stored but `check-availability.php` hardcodes 30** |
| `max_bookings_per_day` | 1 | stored; conflict logic is slot-based |
| `annual_booking_years` | 2 | `booking-new.php`, annual API |
| `pending_booking_timeout_hours` | 48 | auto-cancel pending cron; `0` disables |
| `pricing_window_months` | 21 | monthly table + tentative flag |
| `last_pricing_append` | | cron |
| `last_booking_cleanup` | | cron |
| `auto_cancel_days_before_dhana` | inserted from admin settings | before-dhana cron |
| `backup_retention_days` | inserted from admin settings | `BackupManager` |
| `role_system_version` | 2.0 | historical |

## Pricing resolution

For a booking date: `monthly_pricing` for that type/year/month → else latest earlier month → else `dhana_types.price`. If the date is on/after (first of this month + `pricing_window_months`), set `bookings.is_price_tentative = 1`.

## Schema changes

There is no migration tool. To change schema:

1. Edit `database/schema.sql` (and `seed.sql` if defaults change).
2. Write the `ALTER TABLE` (or equivalent) you will run on production; note it in the PR/commit message.
3. Do not silently rely on “the app creates tables” — it does not.
