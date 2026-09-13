# Admin panel and RBAC

Entry: `admin/index.php` (login + dashboard). Guard every new admin PHP file with `$_SESSION['admin_logged_in']`.

## Roles (hierarchy)

| Role | Level | Typical use |
| --- | --- | --- |
| donor | 1 | `users` only — not an admin login |
| supervisor | 2 | view calendar, names, analytics; no edits |
| editor | 3 | accept/reject, verify payments, edit prices |
| administrator | 4 | everything; `is_super_admin` session flag |

Permissions live in `role_permissions`. Each admin page currently copies:

```php
function hasPermission($permission) {
    global $db;
    $role = $_SESSION['admin_role'];
    $check = $db->fetchOne(
        "SELECT COUNT(*) as has_permission FROM role_permissions
         WHERE role_name = ? AND permission_name = ?",
        [$role, $permission]
    );
    return $check['has_permission'] > 0;
}
```

If you extract a shared helper, put it in `admin/includes/` and update every caller. Until then, copy this exact pattern.

## Pages

| File | Purpose |
| --- | --- |
| `index.php` | login, booking list, status changes |
| `analytics.php` + `get-analytics-data.php` | charts |
| `pending-approvals.php` | administrator approves `admin_actions` |
| `pricing-table.php` | monthly prices, add/remove months |
| `pricing-history.php` | price audit |
| `settings.php` | profile, passwords, annual years, auto-cancel, backups |
| `role_management.php` | roles, elevate users |
| `change-user-role.php`, `get-users.php` | administrator-only JSON/actions |
| `update-booking.php`, `delete-booking.php`, `toggle-booking-hold.php` | booking mutations |
| `get-booking-details.php`, `view-receipt.php` | detail + receipt stream |
| `admin-calendar-data.php` | calendar JSON |
| `generate-report.php` | export |

`pending-approvals.php` uses a raw `new PDO(...)` and only applies `booking_update` on approve. Other `admin_actions.action_type` values are stored but not all applied — check that switch before relying on it.

## Dual user records

Promoting a donor to staff historically created/linked `admin_users` and set `users.admin_user_id`. Donor login and admin login remain separate URLs and sessions. Changing `users.role` does not grant `/admin/` access.

## Adding a permission

1. Insert into `role_permissions` for each role that should have it (and into `database/seed.sql`).
2. Gate the UI and the POST handler with `hasPermission('...')`.
3. Do not trust hidden form fields or client JS as authorization.
