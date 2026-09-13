# admin/

Staff UI. Separate session from the donor app. Read `.agents/docs/admin-rbac.md` before changing access control.

- Guard: `$_SESSION['admin_logged_in']`. Administrator-only extras use `$_SESSION['is_super_admin']` or `admin_role === 'administrator'`.
- `hasPermission('...')` is defined **inside this folder's pages**, not in `includes/`. Copy the existing function; do not call a donor `Auth` method.
- Mutations (status, delete, hold, roles) must re-check permission on POST, not only hide the button.
- Receipts: `view-receipt.php` streams files from `uploads/receipts/`. Never dump the file path to the client beyond the existing viewer.
- Sidebar: `includes/sidebar.php`. Add new nav items there and keep Font Awesome icons consistent.
- Do not introduce a second PDO connection. Use `getDB()` from `../config/database.php`.
