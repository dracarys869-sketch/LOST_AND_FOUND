# 🔍 School Lost & Found Management System

A modern, full-featured **PHP/MySQL** web application for managing lost and found items in a school environment. Supports three user roles (Admin, Student, Security), smart item matching, QR code generation, PDF/CSV reports, and real-time in-app notifications.

> **Version:** 1.0.0 &nbsp;|&nbsp; **PHP:** 8.0+ &nbsp;|&nbsp; **Database:** MySQL (utf8mb4) &nbsp;|&nbsp; **Timezone:** Asia/Manila

---

## 🚀 Quick Access

**URL:** `http://localhost/lost_and_found_system`

| Role     | Email                   | Password   |
|----------|-------------------------|------------|
| Admin    | admin@school.edu.ph     | admin@skul123 |
| Student  | student@school.edu      | password   |
| Security | security@school.edu     | password   |

> ⚠️ **Change these passwords immediately before deploying to production.**

---

## 📋 Requirements

| Requirement | Details |
|-------------|---------|
| **XAMPP** | Apache + MySQL, PHP 8.0 or higher |
| **phpqrcode** | QR code generation (optional) |
| **FPDF** | PDF report export (optional) |
| **Web browser** | Any modern browser |

---

## ⚡ Quick Setup

### 1. Place Files
Copy the `lost_and_found_system/` folder into your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\lost_and_found_system\
```

### 2. Create Database
1. Start Apache & MySQL in XAMPP Control Panel
2. Open phpMyAdmin: `http://localhost/phpmyadmin`
3. Create a new database named **`lost_and_found_db`**
4. Select it → **Import** → choose `database/lost_and_found_db.sql` → click **Go**

### 3. Configure the Application
Open `config/config.php` and update the values to match your environment:

```php
// ─── Base URL ───────────────────────────────────────────────
define('BASE_URL', 'http://localhost/lost_and_found_system');

// ─── Database ───────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');        // Default MySQL port; change if needed
define('DB_NAME',    'lost_and_found_db');
define('DB_USER',    'root');
define('DB_PASS',    '');            // Your MySQL password

// ─── Environment ────────────────────────────────────────────
define('APP_ENV', 'development');    // Change to 'production' when live

// ─── Matching Engine ────────────────────────────────────────
define('MATCH_THRESHOLD', 75.0);    // Min score (%) for auto-match flagging

// ─── Session Timeout ────────────────────────────────────────
define('SESSION_TIMEOUT', 1800);    // 30 minutes
```

### 4. Install Optional Libraries

#### phpqrcode (QR Code generation)
1. Download from: http://phpqrcode.sourceforge.net/
2. Extract and place so that `includes/phpqrcode/qrlib.php` exists

#### FPDF (PDF reports)
1. Download from: http://www.fpdf.org/
2. Place so that `includes/fpdf/fpdf.php` exists

> **Note:** QR code scanning and PDF exports require these libraries. All other features — smart matching, claims, notifications, CSV export — work without them.

### 5. File Permissions
Ensure Apache can write to these directories (auto-created on first use):
- `assets/uploads/` — item photos
- `qr_codes/` — generated QR images

### 6. Open in Browser
Navigate to: **`http://localhost/lost_and_found_system`**

---

## 🗂️ Project Structure

```
lost_and_found_system/
├── admin/
│   ├── dashboard.php       # Admin overview & stats
│   ├── users.php           # User management
│   ├── categories.php      # Item category management
│   ├── locations.php       # Campus location management
│   ├── claims.php          # Review & approve/reject claims
│   ├── reports.php         # Full reporting interface
│   ├── audit_logs.php      # Full system audit trail
│   └── settings.php        # App-wide settings (session timeout, etc.)
│
├── student/
│   ├── dashboard.php       # Student home & activity summary
│   ├── lost_items.php      # Report & manage lost item reports
│   ├── found_items.php     # Browse publicly logged found items
│   ├── matches.php         # View smart match results
│   ├── claims.php          # File & track ownership claims
│   ├── notifications.php   # In-app notification centre
│   ├── my_reports.php      # History of own reports
│   └── profile.php         # Edit profile & password
│
├── security/
│   ├── dashboard.php       # Security staff overview
│   ├── found_items.php     # Log & manage found items
│   ├── claims.php          # Review incoming claims
│   ├── release.php         # Record item release to claimant
│   ├── qr_scanner.php      # Camera-based QR code scanner
│   └── storage.php         # Physical storage tracking
│
├── api/                    # AJAX / JSON API endpoints
│   ├── match_items.php     # Trigger matching engine
│   ├── notifications.php   # Fetch unread notification count (polling)
│   ├── qr_lookup.php       # Look up item by QR code
│   └── upload_photo.php    # Handle async photo uploads
│
├── config/
│   ├── config.php          # App configuration (DB, URLs, constants)
│   └── database.php        # PDO connection factory
│
├── database/
│   └── lost_and_found_db.sql  # Full schema + seed data
│
├── includes/
│   ├── auth.php            # Session/auth guards & helpers
│   ├── functions.php       # Shared utility functions
│   ├── matching_engine.php # Weighted smart-match algorithm
│   ├── notifications.php   # Notification creation helpers
│   ├── qr_generator.php    # QR code generation wrapper
│   ├── header.php          # Global HTML head / nav
│   ├── footer.php          # Global footer
│   ├── sidebar_admin.php   # Admin sidebar nav
│   ├── sidebar_student.php # Student sidebar nav
│   ├── sidebar_security.php# Security sidebar nav
│   ├── fpdf/               # FPDF library (place here)
│   └── phpqrcode/          # phpqrcode library (place here)
│
├── reports/
│   ├── export_csv.php      # Export filtered items to CSV
│   └── pdf_report.php      # Generate branded PDF report
│
├── assets/
│   ├── css/style.css       # Global stylesheet
│   ├── js/main.js          # Global JavaScript (dark mode, CSRF, etc.)
│   └── uploads/            # User-uploaded item photos
│
├── qr_codes/               # Auto-generated QR code images
├── .htaccess               # Apache rules (block direct access to config & db)
├── index.php               # Public landing page
├── login.php               # Login page
├── register.php            # Student self-registration
├── dashboard.php           # Role-based dashboard redirector
└── logout.php              # Session destroy & redirect
```

---

## ✨ Features

| Feature | Description |
|---------|-------------|
| **Smart Matching** | Weighted algorithm scores lost vs. found items on name, category, color, brand, location, and date. Flags pairs above the configurable threshold (default 75%). |
| **QR Codes** | Auto-generated unique QR code for every found item. Security staff can scan via camera (`security/qr_scanner.php`) for instant item lookup. |
| **Role-Based Access** | Admin, Student, and Security each have isolated dashboards, sidebars, and page access enforced server-side. |
| **Claims Workflow** | Student files claim → Admin or Security reviews & approves/rejects → Security records physical release → Student notified. |
| **In-App Notifications** | Badge count polled via `api/notifications.php`. Students receive alerts for new matches and claim status changes. |
| **Reports & Exports** | Admin can export filtered data to **CSV** (Excel-compatible) or a branded **PDF** report. |
| **Dark Mode** | Light/dark toggle persisted in a cookie and applied immediately without page reload. |
| **Audit Log** | Every significant action (create, update, delete, login, claim approval) is recorded with user, timestamp, and detail. |
| **CSRF Protection** | All state-changing forms include a rotating server-side token (`_csrf_token`). |
| **Pagination** | List pages paginate at 10 items per page (configurable via `ITEMS_PER_PAGE`). |
| **Photo Uploads** | Async photo upload (up to 5 MB) supporting JPEG, PNG, GIF, and WebP. |

---

## 👥 Role Capabilities

### 🛡️ Admin
- Full user management (create, edit, activate/deactivate any account)
- Manage item categories and campus locations
- View all lost and found items across all users
- Review and action all claims
- Access full audit log
- Configure system settings (session timeout, etc.)
- Export reports to CSV and PDF

### 🎓 Student
- Self-register an account
- Report lost items with description, photo, category, location, and date
- Browse publicly visible found items
- View smart match suggestions for their lost reports
- File an ownership claim with supporting proof
- Track claim status in real time
- Manage profile and change password

### 👮 Security
- Log found items (with photo, location, storage info)
- QR code auto-generated on item creation
- Scan QR codes via camera for instant item detail
- Review and act on incoming claims
- Record physical item release to approved claimants
- Manage storage locations

---

## 🔒 Security Notes

| Concern | Implementation |
|---------|---------------|
| **Password storage** | bcrypt with cost factor 12 |
| **SQL injection** | All queries use PDO prepared statements |
| **XSS** | All output is `htmlspecialchars()`-escaped |
| **CSRF** | Rotating token validated on every POST |
| **Directory access** | `config/` and `database/` blocked by `.htaccess` |
| **Session hardening** | `httponly`, `SameSite=Strict` cookies; 30-minute idle timeout |
| **File uploads** | MIME type + extension allow-list; files stored outside web root naming |

---

## 🔧 Configuration Reference (`config/config.php`)

| Constant | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `development` | Set to `production` to suppress errors |
| `BASE_URL` | `http://localhost/lost_and_found_system` | Public URL to the project root |
| `DB_HOST` | `localhost` | MySQL host |
| `DB_PORT` | `3367` | MySQL port (default MySQL is `3306`) |
| `DB_NAME` | `lost_and_found_db` | Database name |
| `DB_USER` | `root` | Database username |
| `DB_PASS` | _(empty)_ | Database password |
| `SESSION_TIMEOUT` | `1800` | Idle session timeout in seconds |
| `MATCH_THRESHOLD` | `75.0` | Min match score (%) to surface to users |
| `MAX_FILE_SIZE` | `5242880` | Max upload size in bytes (5 MB) |
| `ITEMS_PER_PAGE` | `10` | Pagination page size |
| `BCRYPT_COST` | `12` | bcrypt hashing cost |
| `LOST_PREFIX` | `LI` | Report number prefix for lost items |
| `FOUND_PREFIX` | `FI` | Report number prefix for found items |

---

## 📊 Demo Flow

1. **Admin** logs in → creates categories (e.g., Electronics, Clothing) and campus locations
2. **Student** registers → reports a **lost item** with description, photo, and date
3. **Security** logs in → logs a **found item** → QR code auto-generated
4. **Matching engine** runs → calculates weighted score → student notified if ≥ threshold
5. **Student** reviews match → files an **ownership claim** with supporting proof
6. **Admin / Security** reviews claim → **approves** or **rejects** it
7. **Security** records **item release** → student receives confirmation notification

---

## 🌐 API Endpoints

All endpoints return JSON and require an active authenticated session.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `api/match_items.php` | Trigger the matching engine for a lost item |
| `GET` | `api/notifications.php` | Get unread notification count for the current user |
| `GET` | `api/qr_lookup.php?code=<FI###>` | Look up a found item by its QR code value |
| `POST` | `api/upload_photo.php` | Upload an item photo asynchronously |

---

## 🛠️ Troubleshooting

| Problem | Solution |
|---------|----------|
| Blank page / 500 error | Set `APP_ENV = 'development'` in `config.php` to show errors |
| Cannot connect to database | Verify `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS` in `config.php` |
| QR codes not generating | Ensure `includes/phpqrcode/qrlib.php` exists |
| PDF export fails | Ensure `includes/fpdf/fpdf.php` exists |
| Uploads not saving | Check `assets/uploads/` exists and Apache has write permission |
| Session expires too fast | Increase `SESSION_TIMEOUT` in `config.php` or via Admin → Settings |
| Wrong timezone on dates | Update `date_default_timezone_set()` at the bottom of `config.php` |



