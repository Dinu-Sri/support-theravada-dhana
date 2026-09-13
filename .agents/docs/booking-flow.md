# Booking flow

Primary files: `booking-new.php`, `calendar.php`, `payment.php`, `my-bookings.php`, `assets/js/booking-steps.js`, `api/check-availability-slots.php`, `api/get-monthly-price.php`, `api/get-annual-prices.php`.

## Donor 4-step form (`booking-new.php` + `booking-steps.js`)

Steps: (1) dāna type (2) date (3) options (4) review. `totalSteps = 4`.

- Types come from `dhana_types WHERE is_active = 1 ORDER BY price DESC`.
- Date may be pre-filled from `?date=YYYY-MM-DD` (calendar).
- Options: `travel_support`, `is_annual_event`, `special_requests`. Monk flag is copied from `users.is_monk` at insert, not from the form (the register form has an `is_monk` checkbox).
- POST field `submit_booking` creates the row(s).

`assets/js/booking.js` and `booking-steps-minimal.js` are older variants. Prefer `booking-steps.js` unless the page you are on includes another file.

## Annual events

If `is_annual_event` is set:

1. Insert year-1 `bookings` row (`parent_booking_id` NULL).
2. Insert `annual_bookings (booking_id, year_start, year_end)` where end = start + `annual_booking_years` - 1.
3. For offsets 1..N-1, insert child `bookings` with `parent_booking_id` = year-1 id **only if** that future date has no conflict.

Children are independent rows (own status, own price). Cancelling the parent does not automatically cascade in application code (FK `ON DELETE CASCADE` would if the parent row is deleted).

## Payment

`payment.php?booking_id=`

- Must belong to the logged-in donor.
- Shows `settings.bank_details`.
- Upload: jpg/jpeg/png/pdf, max 5MB (`MAX_FILE_SIZE`, `ALLOWED_FILE_TYPES`).
- Saved as `uploads/receipts/receipt_{bookingId}_{unix}.{ext}`.
- Inserts `payment_receipts`, sets booking `receipt_submitted`.

## Calendar

`calendar.php` groups non-cancelled bookings by date/type/slot for the visible month. It also overlays `annual_bookings` year range. Year selector is clamped to `current year .. current year + 2`.

## Availability APIs

Use `api/check-availability-slots.php` for new work (includes `time_slot`). `api/check-availability.php` still exists and **hardcodes** “max 30 days ahead” regardless of `settings.booking_advance_days`.

Both require donor session. Both send `Access-Control-Allow-Origin: *` — do not widen that; prefer tightening if you touch CORS.

## Holds

Admins can `on_hold=1` a booking (`admin/toggle-booking-hold.php`). Auto-cancel pending cron skips held rows.
