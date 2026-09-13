# api/

JSON for the donor booking UI. Read `.agents/docs/booking-flow.md`.

- Default: require donor login (`getAuth()->isLoggedIn()`), JSON in/out, PDO via `getDB()`.
- Slot conflicts must match `booking-new.php` (whole_day vs any; morning/lunch vs same type+slot or whole_day).
- Prefer `check-availability-slots.php` over `check-availability.php` for new callers. The latter hardcodes a 30-day horizon.
- Price endpoints may stay GET. Do not return admin-only fields.
- Turn off `display_errors` if you edit a file that currently enables it.
- Do not widen CORS. `Access-Control-Allow-Origin: *` is already too open.
