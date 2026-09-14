# Dāna Booking System

A comprehensive web-based reservation system for managing Buddhist monastery dāna (alms-giving) bookings with dynamic pricing, automated backups, and role-based access control.

Production: [supporttheravada.org](https://supporttheravada.org). PHP/MySQL on cPanel shared hosting. No Composer, no Node build.

**AI agents:** start at [`AGENTS.md`](AGENTS.md). Nested `AGENTS.md` files apply under `admin/`, `api/`, `cron/`, `config/`, `includes/`, `assets/`, and `database/`. Detail lives in [`.agents/docs/`](.agents/docs/).

## ✨ Features

### 📅 Booking Management
- **Multi-step booking form** with date selection, dāna type, and payment details
- **Real-time availability checking** to prevent double bookings
- **Annual booking support** with automatic price calculation
- **Receipt upload** for payment verification
- **Booking status tracking** (Pending, Confirmed, Cancelled)
- **User dashboard** to view and manage bookings

### 💰 Dynamic Pricing System
- **Monthly pricing table** with flexible month management
- **Add/remove months** dynamically
- **Pricing history tracking** with admin attribution
- **Price disclaimer modal** (bilingual: English & Sinhala)

### 👥 Role-Based Access Control
- **4 user roles**: Administrator, Editor, Supervisor, Donor
- **17 granular permissions** for fine-grained access control
- **Permission management UI** in admin settings

### 🔐 Admin Panel
- **Dashboard** with booking statistics and calendar view
- **Pending approvals** management
- **Analytics** with charts and reports
- **User management** with role assignment
- **Receipt viewer** for uploaded payment proofs
- **Settings page** for system configuration

### 💾 Automated Backup System
- **Daily database backups** with configurable retention (1-30 days)
- **Monthly permanent backups** (never auto-deleted)
- **Receipt file backups** (ZIP archives)
- **Automatic rotation** (FIFO - First In, First Out)
- **Backup management UI** with statistics

### 🔄 Automated Tasks (Cron Jobs)
- **Auto-cancel pending bookings** after configurable days
- **Auto-cancel before dāna date** (24 hours before)
- **Auto-append pricing months** to maintain booking window
- **Daily and monthly backups**

### 🎨 User Interface
- **Responsive design** for mobile and desktop
- **Golden/brown theme** (#d4822a, #b8860b)
- **Interactive calendar** for date selection
- **Bilingual support** (English & Sinhala)
- **Modern UI** with Font Awesome icons

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Icons**: Font Awesome 6.0
- **Server**: Apache (XAMPP/cPanel compatible)

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server
- PDO PHP Extension
- GD PHP Extension (for image handling)
- ZIP PHP Extension (for backups)

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/Dinu-Sri/support-theravada-dhana.git
cd support-theravada-dhana
```

### 2. Configure Database
```bash
# Copy example config file (do not commit the copy)
cp config/database.example.php config/database.php

# Edit database.php with your credentials
# Update: DB_HOST, DB_NAME, DB_USER, DB_PASS, SITE_URL
```

### 3. Configure Email (Optional)
```bash
# Copy example email config (do not commit the copy)
cp config/email.example.php config/email.php

# Edit email.php with your SMTP settings
```

### 4. Import Database
- Create a new MySQL/MariaDB database
- Import `database/schema.sql`, then `database/seed.sql`
- The app does **not** create tables on first run

### 5. Set Permissions
```bash
chmod 755 uploads/
chmod 755 uploads/receipts/
chmod 755 backup/
```

### 6. Access the Application
- Donor UI: `http://yourdomain.com/`
- Admin UI: `http://yourdomain.com/admin/`
- Seeded admin: `admin@admin.com` / `ChangeMe123!` (change immediately)

`config/database.php`, `config/email.php`, live SQL dumps, receipt uploads, and backup files are gitignored and must stay on the server only.

### Updating cPanel from GitHub

The two configuration files above are deliberately absent from GitHub. A first checkout will return HTTP 500 until both server-only files have been created with valid production values.

Before the first pull, move any old WordPress/SpeedyCache `.htaccess` out of the document root. This repository includes its own `.htaccess`; the old rewrite rules do not belong to this application.

After every checkout or pull, run the preflight from cPanel Terminal:

```bash
php tools/deployment-preflight.php
```

It checks the PHP version and extensions, server-only configuration, writable upload/backup directories, and the root `.htaccess`. Do not test the public site until it reports `Deployment preflight passed.`

A safe update sequence is:

1. Preserve `config/database.php`, `config/email.php`, uploaded receipts, and backups.
2. Pull `main` from GitHub.
3. Run each newly added file in `database/migrations/` once through phpMyAdmin. For this release, run `database/migrations/2026-09-13-booking-integrity.sql` before reopening reservations.
4. Run the deployment preflight and correct every reported error.
5. Test the donor and admin login pages.

The first deployment of this release also requires refreshing the server-only `config/database.php` from the current example (then restoring its real credentials). The preflight detects the legacy template and explains this rather than allowing a raw database error to reach visitors.

## ⚙️ Configuration

### Backup Settings
Edit `admin/settings.php` to configure:
- Daily backup retention (1-30 days, default: 30)
- Backup paths and schedules

### Cron Jobs Setup
Set up the following cron jobs for automation:

**Daily Backup** (Recommended: 2:00 AM):
```bash
0 2 * * * /usr/bin/php /path/to/project/cron/daily-backup.php
```

**Monthly Backup** (1st of month at 3:00 AM):
```bash
0 3 1 * * /usr/bin/php /path/to/project/cron/monthly-backup.php
```

**Auto-cancel Pending Bookings** (Daily at 1:00 AM):
```bash
0 1 * * * /usr/bin/php /path/to/project/cron/auto-cancel-pending-bookings.php
```

**Auto-cancel Unconfirmed Near-date Bookings** (Daily at midnight):
```bash
0 0 * * * /usr/bin/php /path/to/project/cron/auto-cancel-before-dhana.php
```

Cron scripts are command-line only. If `mysqldump` is not on the hosting command path, set `MYSQLDUMP_PATH` in the untracked server `config/database.php`.

**Auto-append Pricing Months** (Monthly on 1st at 4:00 AM):
```bash
0 4 1 * * /usr/bin/php /path/to/project/cron/auto-append-pricing-month.php
```

## 📁 Project Structure

```
dhana-booking-system/
├── AGENTS.md           # Agent entrypoint (read this first)
├── .agents/docs/       # Architecture, schema, deploy notes
├── admin/              # Admin panel (separate login)
├── api/                # JSON endpoints for the booking UI
├── assets/             # CSS, JS, images
├── backup/             # Cron backup output (not in git)
├── config/             # database.php / email.php (local only)
├── cron/               # cPanel cron scripts
├── database/           # schema.sql + seed.sql
├── includes/           # Shared PHP (donor auth, email)
├── uploads/            # Receipts (not in git) + login background
├── booking-new.php     # Main booking page
├── dashboard.php       # User dashboard
├── index.php           # Donor login / register
└── README.md
```

## 🔒 Security Features

- **Password hashing** with PHP's password_hash()
- **SQL injection protection** with PDO prepared statements
- **XSS protection** with htmlspecialchars()
- **Session management** with timeout, ID rotation, and active-account checks
- **File upload validation** (content type, size, and extension) with authenticated receipt viewing
- **Role-based access control**
- **CSRF protection** on donor state-changing forms

## License

All rights reserved. Public repository is for development and collaboration; do not treat production credentials or donor data as open data.

## 👨‍💻 Author

Developed for Buddhist monastery dāna booking management.

## 🙏 Acknowledgments

Built with dedication for the Buddhist community.

