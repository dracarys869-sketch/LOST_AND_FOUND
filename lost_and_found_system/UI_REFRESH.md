# Campus Lost & Found — UI refresh

## Design

- A consistent forest-green, white, and soft-neutral theme across public, student, administrator, and security screens.
- A redesigned landing page with an original campus-belongings illustration, live report totals, a searchable item board, and an explanation of the recovery process.
- Clear dashboard priorities, readable statistics, compact report summaries, and consistent navigation.
- Matching sign-in, registration, and password-reset layouts using the configured school name.
- Responsive layouts, dark mode, keyboard focus management, readable status colors, and explicit empty/loading/error states.

## Workflow improvements

- **Browse items:** `student/browse.php` provides search, category filters, sorting, pagination, and direct ownership-claim links. The shared public board keeps private descriptions out of search results.
- **Sign-in return links:** a selected found item survives sign-in; redirects are restricted to the installation and permitted role routes.
- **Remembered sign-in:** the 30-day option restores a new session using a hashed, expiring, rotating token. Signing out revokes it.
- **Report forms:** inline feedback identifies invalid fields, password confirmations are checked, entered details survive server errors, and unsupported/oversized photos have useful error messages.
- **Report persistence:** shared validation handles category/location choices, field lengths, dates, and times. Failed uploads roll back partial reports. A matching failure after saving is reported separately so a successful report is not submitted twice.
- **Report numbering:** deleting older reports no longer causes the next report number to collide with an existing one.
- **Staff navigation:** security and administrator lost-report submissions return to an authorized screen. Security intake reopens with entered values after an error. The released-items filter now returns released records.
- **Notifications:** updates link to relevant workflows; read-state changes require POST and CSRF verification. Sidebar and mobile notification counts refresh together.
- **Claims:** the new-claim form offers an item selector instead of requiring students to know an internal numeric ID.
- **QR generation:** localized PHP 8 compatibility fixes prevent the bundled encoder from emitting deprecation output during found-item creation. Generation only reports success when an image exists.

## Verification

Run from `lost_and_found_system/` with PHP 8.2 and the configured MySQL connection:

```sh
php tests/lint.php
php tests/decision_tree_test.php
php tests/integration.php
php tests/report_workflow_test.php
node --check assets/js/main.js
```

Verified during this refresh:

- PHP syntax: **91 files**.
- Existing logic: **68 checks**.
- Existing isolated database/workflow/authorization suite: **49 checks**.
- New report validation, save/recovery, search, redirect, and real QR-generation regression suite: **42 checks**.
- Rendered review: **188 page/theme/viewport combinations**. Contrast and narrow-screen table issues found in review were corrected and rechecked.
- Final browser interaction suite: **156 assertions passed**, including 320px and 768px layouts, WCAG A/AA checks, mobile navigation, actual sign-in/remember/logout behavior, form recovery, claim payloads, QR lookup retries, and CSV/PDF downloads.

Database tests create uniquely named, isolated databases. The report regression also generates and removes a uniquely named QR test image.
