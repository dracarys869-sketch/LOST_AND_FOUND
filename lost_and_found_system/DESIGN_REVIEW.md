# Campus Lost & Found — design audit and implementation

The design now uses a neutral campus noticeboard aesthetic, one forest-green accent, and the application's actual reports and workflows. Changes are applied in the PHP templates, shared stylesheet, and existing JavaScript.

## 1. Audit summary

All locations below refer to the **original, pre-edit files**, relative to `lost_and_found_system/`. These are the violations found, including an error-state typography issue identified during the final review.

| Checklist | Violation | Original location |
| --- | --- | --- |
| 1 | Decorative portal badge adds no information beyond the page identity. | `index.php:68–70` |
| 2 | Repeated labels and descriptions compete with useful content; report titles and approval status appear twice. | `student/dashboard.php:84–119`; `admin/reports.php:139–190`; `security/release.php:79–82` |
| 3 | Missing-content message is arbitrarily italicized. | `security/claims.php:106` |
| 4 | The database error state sets the entire message, including its UI heading, in monospace. | `config/database.php:31–33` |
| 5 | Multiple competing theme layers and numerous inline font sizes, radii, colors, and spacing values. | `assets/css/style.css:9–79, 1851–1920`; templates across `admin/`, `student/`, and `security/` |
| 6 | Generic dashboard illustration and decorative leaves occupy space that could communicate the real workflow. | `student/dashboard.php:71–73, 149–153` |
| 7 | Large marketing hero pushes real reports down; colored summary cards compete with the next useful action. | `assets/css/style.css:2780–2786`; all three role dashboards |
| 8 | Cards inside a feed card, boxed instructional tips, and repeated dividers add unnecessary enclosure. | `index.php:89–111`; `student/lost_items.php:200–211`; `student/found_items.php:193–200` |
| 9 | Generic promises and “smart/intelligent” marketing language explain less than the existing product workflow. | `index.php:71–75`; `login.php:94`; `student/dashboard.php:44` |
| 10 | Multiple filled actions have similar emphasis, including repeated row actions. | `security/dashboard.php:40–55, 145`; `admin/claim_review.php:169, 206`; `security/qr_scanner.php:55, 72` |
| 11 | Rainbow stat treatments, role colors, chart colors, and gradient buttons compete for attention. | `assets/css/style.css:2164–2332, 2451–2454`; `admin/users.php:207–213`; `admin/dashboard.php:298–316` |
| 12 | Emoji, filled icons, text-symbol icons, and artificially thickened strokes are mixed. Some icon-only controls lack accessible names. | `student/dashboard.php:68, 81, 151–157, 187`; `student/matches.php:155, 182`; `includes/functions.php:167–188` |
| 13 | Animated blobs, glows, patterned overlays, and decorative movement add visual noise. | `login.php:83–86`; `forgot_password.php:164–166`; `assets/css/style.css:1958–2050, 2788–2795` |
| 14 | Nested radii are unrelated to their padding, and input-group corners are overridden inconsistently. | `assets/css/style.css:2562–2581, 2695–2709` |
| 15 | Pale helper text and bright button backgrounds reduce contrast; dark mode has inconsistent text and surface colors. | `assets/css/style.css:1861, 1900, 2423–2454`; `login.php:114` |
| 16 | No balanced heading wrapping, a forced hero break, and truncated item titles. | `index.php:72, 140, 186`; `assets/css/style.css:148–159` |
| 17 | Absolutely positioned back buttons crowd auth headings; icon spacing and data baselines vary. | `register.php:80–89`; `forgot_password.php:172–183`; `student/dashboard.php:156–157` |
| 18 | Aligned numbers, dates, scores, counters, and tables lack tabular numerals. | `assets/css/style.css`; dashboard and report templates |
| 19 | Native form submissions, camera initialization, and QR lookup have no pending state or duplicate-action protection. | Auth and report forms; `security/qr_scanner.php:125–200`; `assets/js/main.js` |
| 20 | Non-interactive cards move on hover; the scanner animates while idle; some JavaScript motion ignores reduced-motion settings. | `assets/css/style.css:2156–2162, 2372–2385`; `security/qr_scanner.php:49`; `security/claims.php:141` |

## 2. Updated code

The complete application changes are in [DESIGN_UPDATE.patch](DESIGN_UPDATE.patch). Its paths are relative to the repository root. To inspect the patch, open it directly; the working files already contain the changes.

Main entry points:

- [Shared design tokens and components](assets/css/style.css)
- [Shared interaction and pending-state behavior](assets/js/main.js)
- [Public noticeboard](index.php)
- [Sign in](login.php), [registration](register.php), and [password reset](forgot_password.php)
- [Student dashboard](student/dashboard.php), [administrator dashboard](admin/dashboard.php), and [security dashboard](security/dashboard.php)
- [QR scanner](security/qr_scanner.php)
- [Shared header](includes/header.php), [footer](includes/footer.php), and [pagination helpers](includes/functions.php)

### Design tokens

- Main font: native system sans-serif, with a separate technical monospace stack.
- Accent: `#216346` in light mode, with a contrast-adjusted green for dark mode.
- Neutral surfaces and separate, accessible semantic error/warning tokens.
- Shared type scale and 4px-based spacing scale.
- Shared radii, control sizes, focus treatment, shadows, and 150–200ms feedback durations.
- Nested media radius: `max(0px, outer radius − inset padding)`; tab radius: `outer radius − tab padding`.

## 3. Changelog

| Checklist | Applied change and reason |
| --- | --- |
| 1 | Removed the decorative hero pill. Status, unread, and report counts retain their informational purpose. |
| 2 | Removed duplicated report captions, repeated table titles, and repeated approval labels. Separated the document title from `$pageTitle`, so the school name is not accidentally added to in-page headings. |
| 3 | Replaced the italicized missing-description message with ordinary text. |
| 4 | Standardized interface text on the main font, including the database error state. Code, report identifiers, and IP addresses use the technical font where appropriate. |
| 5 | Replaced the layered stylesheet with one token system and removed one-off inline visual styling throughout role templates. Bootstrap continues to supply the grid and component behavior. |
| 6 | Removed the stock dashboard illustration and leaf decorations. Public reports, actual counts, match suggestions, and activity now carry the interface. Missing public photos have an explicit empty state. |
| 7 | Brought the public reports higher on the page. Student next actions depend on pending matches; administrator claim review and security intake have stronger hierarchy. Fixed a pre-existing variable collision that prevented the security pending-claim list from rendering. |
| 8 | Removed the public feed's outer card, made instructional tips simple supporting content, and reduced borders/shadows. Related fields and metadata are grouped with spacing. |
| 9 | Rewrote headlines and supporting copy around reporting campus items, reviewing matches, submitting ownership evidence, and collecting approved items. Corrected an empty storage result that previously claimed all items had been released even after a search. |
| 10 | Gave the next task the filled action treatment and made secondary, export-alternative, navigation, and repeated row actions quieter. |
| 11 | Applied a neutral palette with one green accent. Status distinctions remain readable through text. Administrator-selected category colors appear as small data swatches instead of controlling text contrast. Charts use a neutral/green palette and expose exact values in tables. |
| 12 | Standardized interface icons on Bootstrap Icons, removed emoji embellishments and custom stroke thickening, and added names to icon-only controls. |
| 13 | Removed gradient backgrounds, blobs, glows, decorative texture layers, and excessive shadows. |
| 14 | Calculated nested radii for public media and tabs. Restored consistent joined corners on input groups. |
| 15 | Added contrast-safe text, placeholder, button, status, and surface tokens in both themes, including hover/active states. Improved reset-email contrast. |
| 16 | Added balanced headings, responsive type sizes, sensible headline widths, and wrapping item titles. |
| 17 | Aligned button icons using consistent dimensions and spacing. Put auth back links in normal flow. Kept table identifiers and dates aligned and readable. |
| 18 | Added tabular numerals to tables, stats, counts, match scores, dates, number inputs, and OTP displays. |
| 19 | Added disabled/pending form states, live feedback, duplicate-submit protection, and back-navigation recovery. Named submit values such as `decision=approved` are preserved when buttons are disabled. QR lookup has skeletons, timeout/error recovery, and retry; camera startup handles permission denial and late cancellation. |
| 20 | Limited motion to useful control/panel feedback and loading indicators, respected reduced-motion preferences, and removed idle/decorative movement. Added keyboard focus management for mobile navigation and overflowing tables. |

### Verification

- PHP syntax: **85 files passed**; JavaScript syntax passed.
- Existing logic checks: **68 passed**.
- Existing isolated database/workflow/authorization checks: **49 passed**.
- Rendered browser review: **172 page/theme/viewport combinations**, including populated and empty states; no PHP render errors, axe WCAG A/AA findings, or horizontal page overflow in the final pass.
- Browser interaction checks: **94 passed**, including keyboard navigation, 320px layouts, modal validation, image removal, named approval/rejection payloads, duplicate submission prevention, theme persistence, and real CSV/PDF downloads.
- QR tests covered real item lookup, network failure/retry, and simulated camera success, denial, and cancellation. Camera streams were stopped and released correctly.
- `git diff --check` passed.

Browser testing used Chromium with 320px, 390px, and 1440px viewports and isolated, clearly labeled sample data.

## 4. Open questions

1. **Accent:** Is forest green (`#216346`) the preferred school accent, or is there an official brand color to use?
2. **Public photos:** Can you provide any additional item photos approved for the public feed? The existing report upload and administrator photo-review screens determine which photos appear; records without a public photo show a labeled empty state.

The revised product copy follows the campus workflow already present in the code and documentation.
