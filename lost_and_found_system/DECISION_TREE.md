# Decision Tree ownership-claim integration

## What is implemented

The application now has **one machine-learning algorithm: a single CART Decision Tree classifier**, implemented in PHP. It learns binary feature splits using weighted Gini impurity. Its three possible recommendations are:

- **Likely Legitimate** — independently labeled examples with strongly matching ownership evidence.
- **Needs Verification** — independently reviewed examples that remained inconclusive.
- **Suspicious** — examples with independently confirmed, significant ownership inconsistencies.

Recommendations are **not proof of ownership**. Prediction and training cannot approve a claim, change an ownership decision, or release an item. Administrators make final decisions; security staff record evidence and perform the subsequent physical handover.

The existing weighted lost-item/found-item matching engine is still an item-discovery feature. It is a deterministic scoring routine, not a trained classifier, and is not used as the ownership model or as a source of training labels.

## Installation / database changes

Requirements: the existing PHP 8.1+ / PDO MySQL application, `mbstring`, JSON, and MySQL 5.7+ or MariaDB 10.3+. No package manager, extra framework, Python service, or additional ML library is needed. The supplied environment was tested with PHP 8.2.12 and MariaDB 10.4.32.

From the `lost_and_found_system` directory:

```powershell
& "C:\xampp\php\php.exe" "scripts/migrate_decision_tree.php"
```

Or select the existing application database in phpMyAdmin and import:

```text
database/migrations/001_decision_tree.sql
```

For a fresh installation, import `database/lost_and_found_db.sql` first, then this migration. For an existing installation, run only the migration. It is additive and rerunnable; it does not replace tables, alter existing columns, import examples, infer labels, or modify historical records.

Five tables are added:

| Table | Purpose |
|---|---|
| `found_item_verification` | Staff-inspected private identifying phrases, expected answers, a separately written public-safe description, reference revision, recorder/date. |
| `claim_assessments` | Claim ID, immutable submission/reference/feature/evidence snapshots, feature version, fingerprint, classification, prediction state/reason, model version, prediction date, decision path. |
| `claim_verifications` | Independent verification status/label/evidence, verifier/date, private security notes, administrator's final decision/actor/date. |
| `decision_tree_models` | Versioned trained tree JSON, held-out metrics, dataset hash, train/test membership and persistent partitions, sample counts, trainer/date, active flag. |
| `item_photo_visibility` | Legacy administrator-reviewed public/private status for item photos. New report photos are public on upload. |

The existing `claims.status`, `reviewed_by`, and `review_notes` continue to support existing screens. They are updated transactionally with the new administrator authorization record. Existing release history remains intact. An old security-approved but unreleased claim must be confirmed by an administrator before a new release.

Deployment uses the project's Apache 2.4/XAMPP setup with `mod_rewrite` and `AllowOverride All`. Deploy the included `.htaccess` files, including those under the three ownership-image upload directories. They prevent direct image URLs from bypassing the permission-checking PHP endpoint. SQL files, test code, and CLI scripts are also blocked over HTTP.

Connection settings retain their current defaults. Optional `LAF_DB_HOST`, `LAF_DB_PORT`, `LAF_DB_NAME`, `LAF_DB_USER`, and `LAF_DB_PASS` environment variables support a separate deployment or isolated tests.

## Using the system

1. **Record the found item.** Security staff log an item as before. After saving, open **Private Item Verification** (also available via **Verify** in Found Items or **Private Reference** in Admin Reports).
2. **Inspect it independently.** Record concise identifying phrases, one per line (maximum 10), and expected answers to the two generic questions. Do this before claims arrive. Leave unknown information blank. Never derive a correct answer from a claimant's response.
3. **Prepare public information.** Keep item names general. The original detailed description is private; write a separate public-safe summary. Report photos appear on the public landing page after upload, so do not include private marks, serial numbers, labels, contents, or personal details.
4. **Submit a claim.** A student provides their own description, category, loss location/date, identifying characteristics, verification answers, and optional proof photo. Optional unknowns may be left blank. Linked matches/lost reports are checked against the actual logged-in claimant. The form never pre-fills correct ownership answers.
5. **Generate and save the assessment.** The server retrieves the found item/reference, extracts six features, and saves a frozen snapshot. If a compatible trained tree is active, its recommendation, version, date, leaf support and learned path are stored. Otherwise the record is explicitly **Not classified** with an unavailable/error reason. That state is not a fabricated “Needs Verification” prediction.
6. **Review.** Admin Dashboard → **Decision Tree — Ownership Claims**, or Admin → **Claims → Review**, displays claim ID, item, claimant, classification, comparisons, status, and submission date. The review page includes the private side-by-side evidence and protected proof photo.
7. **Decide.** The administrator explicitly approves or rejects the claim. Student-visible review notes are separate from private verification notes. Only independently checked evidence belongs in a training label. Security staff can save private review notes but cannot approve/reject claims or modify model results.
8. **Release physically.** Security → **Release Items** accepts only administrator-authorized claims. Item-level locks and current locking reads prevent competing approvals and duplicate releases. Prediction never calls the release operation.

## How the features work

All features are computed on the server using only ownership evidence available at submission time. The fixed preprocessing does not learn anything from the test set.

| Feature | Value |
|---|---|
| `category_match` | 1 for matching submitted/found category IDs, otherwise 0. This represents category agreement rather than imposing an arbitrary numeric order on categories. |
| `description_similarity` | Jaccard overlap of distinct normalized words in the claimant's description and the found-item description; fixed stop words are removed. Repeating words cannot increase the score beyond 1. |
| `location_similarity` | 1 for the same location, 0.5 for a different location in the same named building, 0 otherwise. |
| `date_consistency` | 1 when the reported loss is on/before the found date; 0 when it is after. The actual day gap is shown as evidence. A long interval is not automatically considered fraudulent. |
| `unique_characteristics_match` | Fraction of independently recorded identifying phrases found as complete normalized phrases in the claimant's description of unique marks. Normalization folds case and changes punctuation to word separators. |
| `verification_answers_match` | Average word overlap across recorded expected questions. A missing response to an expected question contributes zero if other questions were answered. |

If either side lacks a usable comparison, the feature is **-1 (unknown)**. Two blank descriptions/answers do not count as a match. A claim with no comparable features remains unclassified even with an active model. Invalid IDs, dates, non-text inputs, excessive lengths, and missing required proof are rejected server-side. Remaining optional missing features are explicitly shown and can be handled by the learned tree.

Text comparison is intentionally simple. It does not understand synonyms or paraphrases, authenticate documents, or interpret photographs. A feature agreement percentage and a leaf's class counts are not probabilities that the claimant owns an item. The review page exposes these limitations alongside the underlying evidence.

The tree chooses feature thresholds that reduce Gini impurity, recursively splitting its training examples. It stops at depth 5, when a split would leave fewer than 2 examples in a leaf, when a node is pure, or when no useful split exists. A leaf recommends its majority training class. Ties are broken deterministically. No manually programmed matching-score threshold supplies the three classifications.

## Collecting valid training labels

In **Claim Review → Independent Verification / Training Label**:

- Record the ownership evidence and method actually checked (for example, original purchase documentation or an independently observed identifying mark).
- Select the evidence label independently of the recommendation. The label control is never auto-populated from a model result.
- **Likely Legitimate:** independently checked evidence strongly supports ownership.
- **Needs Verification:** a real verification attempt was documented but remained inconclusive. Merely being pending does not qualify.
- **Suspicious:** significant ownership conflicts were independently confirmed. A rejection for administrative reasons does not automatically mean fraud.
- Save at least 20 characters of private verification evidence and confirm the independent check. Clearing the label excludes the claim from future training.

Legitimate/suspicious labels receive `verified` status; an inconclusive label receives `inconclusive` status. Unlabeled records remain `unverified`. Labeling does not change the final claim decision.

Only records with original feature snapshots, the supported feature version, a documented administrator verification, and at least one comparable feature are eligible. Legacy claims without submission-time snapshots remain available for manual review but are excluded from training. Later changes to item references, claims' final outcomes, or verification notes are never substituted into their prediction features.

## Training, testing, and retraining

Open **Admin → Decision Tree**. The readiness table shows per-class training/test counts and any blockers.

The starting minimum is 30 eligible records, at least 10 per class, at least 20 in training, at least 6 training and 2 held-out test examples per class, and at least two independent groups in each partition. More real data may be needed to satisfy the grouped split. These minimums do not establish accuracy or adequate real-world coverage.

Click **Train, Evaluate & Activate**, or run as a trusted local operator:

```powershell
& "C:\xampp\php\php.exe" "scripts/train_decision_tree.php" --admin-id=1
```

Replace `1` with an active administrator's actual ID. The CLI rechecks that role and writes an audit record. The script returns a nonzero exit code when data is insufficient.

### Leakage prevention

- The classifier receives exactly the six allowed ownership features. Names, email addresses, student IDs, roles, demographics, claim status, review notes, release status, and model predictions are not input features.
- Connected claims sharing a found item, claimant, linked lost report, or duplicate normalized submission are assigned to the same partition. Identity IDs are used only for separation, never for prediction.
- A fixed hash assigns previously unseen groups approximately 80% to training and 20% to testing, independently of class labels. The split is not reshuffled to obtain attractive metrics.
- Saved partition assignments persist across retraining. If new links connect an old training group to an old test group, the connected component is excluded rather than moving previously trained data into the test set.
- The tree is fitted on training records only. The very same tree is evaluated on test records and saved; it is not then refitted on the test set.
- Test evidence changes cannot change the fitted tree. Raw correct answers and personal fields are not present in tree JSON.

### Evaluation and saved artifacts

Each successful version stores and displays:

- Held-out **accuracy**.
- Per-class **precision**, **recall**, **F1-score**, and support.
- Macro and support-weighted averages.
- **Confusion matrix**, with actual labels as rows and predictions as columns. Undefined divisions are reported as zero.
- Training/test claim IDs and counts, partition registry, feature version, dataset SHA-256, training date and administrator.

The trained model is persisted as validated JSON in `decision_tree_models.model_json`, not executable PHP or an unsafe serialized object. New predictions load the active saved tree. Training failures preserve the previous model. Versioned models and their metrics remain stored when another version is activated.

Use the same train action to retrain as independently verified data grows. Existing claims retain their original assessment/model version until an administrator explicitly uses **Reassess with Active Tree**; reassessment uses the original frozen features. **Deactivate Model** stops new model predictions while keeping stored versions and the manual workflow available.

Repeated evaluation against the same holdout is not independent new evidence of accuracy. Do not select parameters or labels to improve that holdout score. The fixed settings avoid a test-driven hyperparameter search; a later formal capstone validation should use newly collected independent data.

## Evaluation status at implementation

The inspected application database contained **one approved historical claim, two found items, and one release**. The claim was reviewed by security and had no independent ML label or submission-time feature snapshot. The supplied SQL also contains demonstration records, not a validated training dataset.

Consequently, **no production model was trained and no real-data accuracy, precision, recall, F1-score, or confusion matrix can yet be reported**. The migration seeds no data and creates no pretend model. The dashboard correctly starts in data-collection/manual-review mode.

## Automated validation

Run from the application directory:

```powershell
& "C:\xampp\php\php.exe" "tests/decision_tree_test.php"
& "C:\xampp\php\php.exe" "tests/integration.php"
& "C:\xampp\php\php.exe" "tests/lint.php"
```

`integration.php` requires permission to create a temporary database. It always creates a new randomly named `laf_dt_test_*` database and drops only that database in cleanup. Synthetic fixtures test the plumbing; they never enter the application database and are not reported as ownership-model accuracy.

To include real Apache HTTP tests on XAMPP:

```powershell
$env:LAF_TEST_APACHE = 'C:\xampp\apache\bin\httpd.exe'
# Optional: point LAF_TEST_TMP to an existing temporary-work directory.
& "C:\xampp\php\php.exe" "tests/integration.php"
```

The HTTP suite starts its own loopback-only Apache instance on an available port, using the temporary database. It stops that instance and removes its generated files afterward.

Validation completed during implementation:

- 68 feature, input-validation, learned-classification, serialization, grouped-split, leakage, and metric checks.
- 171 combined database/workflow/HTTP checks, including actual claim submission, prediction storage, failure handling, immutable snapshots, administrator review, authorization/CSRF, stale-role access, competing approvals, releases, image privacy, and existing dashboard/report/match/notification pages.
- PHP syntax checking across application, test, and bundled library files.

The intentional corrupt-model test logs an evaluation failure, then verifies that the claim safely becomes unclassified and can be manually reviewed. That is an expected negative test, not a production-model failure.

## Source map

| Files | Responsibility |
|---|---|
| `includes/decision_tree/DecisionTree.php` | Actual trained CART classifier and JSON model validation. |
| `includes/decision_tree/ClaimFeatures.php` | Input validation, fixed comparisons, ownership feature schema and evidence. |
| `includes/decision_tree/ClaimTraining.php` | Grouped partitions, readiness checks, training and held-out metrics. |
| `includes/claim_ml.php` | Transactional claims, immutable snapshots, prediction persistence, private references, independent labels, admin decisions/releases, model storage/retraining, audit events. |
| `admin/decision_tree.php`, `admin/claim_review.php` | Training/evaluation dashboard and detailed evidence/final-decision interface. |
| `admin/dashboard.php`, `admin/claims.php`, `includes/sidebar_admin.php` | Existing dashboard/list/navigation integration. |
| `student/claims.php` | Structured ownership evidence collection and secure submission. |
| `security/item_verification.php`, `security/found_items.php` | Staff-inspected private reference collection. |
| `security/claims.php`, `security/release.php` | Staff evidence review and administrator-authorized handover. |
| `admin/photo_review.php`, `api/item_photo.php`, upload `.htaccess` files | Public report images and private ownership proof image protection. |
| `index.php`, `student/matches.php`, `student/found_items.php`, `admin/reports.php`, `api/qr_lookup.php`, `includes/functions.php` | Safe public descriptions, private photo URLs, staff-only detailed QR lookup, reference/photo links and form guidance. |
| `includes/auth.php`, `api/upload_photo.php`, `config/config.php`, `.htaccess` | Current-role checks, nonempty CSRF checks, protected upload endpoint, optional DB environment configuration, direct-access protection. |
| `database/migrations/001_decision_tree.sql`, `scripts/*.php` | Additive schema setup and trusted CLI training. |
| `tests/decision_tree_test.php`, `tests/integration.php`, `tests/http_smoke.php`, `tests/lint.php` | Functional tests and syntax checks. |

All machine-learning behavior is confined to the single Decision Tree. The ownership workflow, users, reporting, notifications, QR codes, storage, uploads, and manual handover remain part of the existing PHP application.
