Quick Access

**URL:** `http://localhost/lost_and_found_system`

| Role     | Email                   | Password   |
|----------|-------------------------|------------|
| Admin    | admin@school.edu.ph     | admin@skul123 |
| Student  | student@school.edu      | password   |
| Security | security@school.edu     | secu123   |

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
5. Import `database/migrations/001_decision_tree.sql` into the same database. For an existing installation, run only this additive migration.

### Decision Tree Ownership Recommendations

The system now supports a single PHP Decision Tree classifier for ownership claims. Open **Admin → Decision Tree** for data readiness, training, and evaluation, and **Claims → Review** for private evidence and the final administrator decision.

Start by recording independently inspected item references and independently verified claim labels. Until sufficient reliable training/test data exists, claims are explicitly **Not classified** and reviewed manually.

See [DECISION_TREE.md](DECISION_TREE.md) for setup, database changes, training/testing procedures, evaluation status, security behavior, usage, and the source-file map.

### 3. Configure the Application
Open `config/config.php` and update the values to match your environment:

define('BASE_URL', 'http://localhost/lost_and_found_system');

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
| **Claims Workflow** | Student files claim → Decision Tree recommendation (when trained) → Admin reviews & approves/rejects → Security records physical release → Student notified. |
| **Decision Tree** | Single CART classifier using ownership-only evidence, private verification records, independent labels, grouped train/test separation, saved model versions, and administrator-only recommendations. |
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
- Record private evidence reviews for incoming claims; administrators make final ownership decisions
- Record physical item release to approved claimants
- Manage storage locations
---

## 📊 Demo Flow

1. **Admin** logs in → creates categories (e.g., Electronics, Clothing) and campus locations
2. **Student** registers → reports a **lost item** with description, photo, and date
3. **Security** logs in → logs a **found item** → QR code auto-generated
4. **Matching engine** runs → calculates weighted score → student notified if ≥ threshold
5. **Student** reviews match → files an **ownership claim** with supporting proof
6. **Admin** reviews the claim, supporting evidence, and available Decision Tree recommendation → **approves** or **rejects** it
7. **Security** records **item release** → student receives confirmation notification

## UI refresh and checks

See [UI_REFRESH.md](UI_REFRESH.md) for the current design, workflow improvements, and verification commands. Students can use **Browse Items** to search campus reports and start an ownership claim directly from a found item.
