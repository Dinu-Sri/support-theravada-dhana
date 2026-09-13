# config/

- `database.php` and `email.php` are **local secrets**. Never stage them. Edit `*.example.php` when you add a constant so new clones stay in sync.
- `getDB()` / class `Database` in `database.php` is the only connection API.
- New `define()`s belong next to the existing blocks (DB, site, security, upload).
- `backup.php` is broken on production (missing `config.php`, Windows mysqldump path). Fix it in place if asked; do not add a parallel backup library.
