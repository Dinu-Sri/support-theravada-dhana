# database/

- `schema.sql` — empty-database structure. Edit this when you add columns/tables.
- `seed.sql` — catalog, roles, permissions, settings, 24 months of prices, one local admin (`admin@admin.com` / `ChangeMe123!`).
- Never add production dumps here. The live phpMyAdmin file at the repo root is gitignored.

Read `.agents/docs/database.md` before changing schema.
