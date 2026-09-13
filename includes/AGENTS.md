# includes/

Shared donor-side PHP.

- `auth.php` is donor-only (`users` table). Admin login must stay in `admin/index.php`.
- `requireLogin()` redirects to `index.php`. Fine for root pages; admin pages must not use it.
- `email.php` + `booking-emails.php`: HTML email, escape names/ids in templates. SMTP constants exist but sending is `mail()`.
- `favicon.php` is include-only markup. Keep paths relative to the calling page (`assets/...` from root, `../assets/...` from admin — today root pages include this file).
