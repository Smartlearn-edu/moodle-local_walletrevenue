# Implementation Plan: `local_walletrevenue` Teacher Revenue & Payout Report

A standalone Moodle local plugin that tracks teacher course earnings from `enrol_wallet` (and `availability_wallet`), calculates commission shares with hierarchical rate overrides, and maintains an auditable manual payout ledger with receipt/screenshot attachments.

## User Review Required

> [!IMPORTANT]
> **Key Architectural Decisions Finalized via /grill-me:**
> 1. **Payouts are per Teacher (grouped by Currency):** Payouts are not tied to individual courses. Proof of payment (screenshots/receipts) is uploaded by the admin using Moodle's File API and can be viewed/downloaded by the teacher.
> 2. **Teacher-Centric Reporting:** Main admin report groups by Teacher & Currency with a drill-down into courses.
> 3. **Hybrid Date Filtering:** Sales/Payouts filter by the selected period; the Balance column always displays the current Total Outstanding Balance owed.
> 4. **Revenue Scope:** Captures both course enrolments (`enrol_instance`) and pay-per-activity/section micro-payments (`availability_wallet`).
> 5. **Safe Install Floor:** Scheduled sync only tracks new transactions going forward; historical transactions require explicit admin backfill.

---

## Data Model & Schema (`db/install.xml`)

| Table Name | Description | Key Fields & Indexes |
|---|---|---|
| `local_walletrevenue_payee` | Maps each course to its designated payee teacher | `courseid` (UNIQUE), `teacherid`, `usermodified`, timestamps |
| `local_walletrevenue_rate` | Commission rate overrides (course or teacher) | `scope` ('course'/'teacher'), `itemid`, `percent`, UNIQUE(`scope`, `itemid`) |
| `local_walletrevenue_sale` | Persistent ledger of sales, micro-payments, and refunds | `transactionid` (UNIQUE), `type`, `sourceop`, `courseid`, `instanceid`, `studentid`, `teacherid`, `saleid`, `listprice`, `amount`, `percent`, `teachershare`, `currency`, `freecut`, `timecreated`, `timesynced` |
| `local_walletrevenue_payout` | Manual payouts to teachers with proof attachments | `teacherid`, `amount`, `currency`, `method`, `reference`, `notes`, `paidat`, filearea `payout_proof` |

---

## Phased Implementation Roadmap

### Phase 0: Scaffold & CI Setup
- Basic plugin structure: `version.php`, `lang/en/local_walletrevenue.php`, `README.md`, `LICENSE.md`, GPL boilerplate.
- GitHub Actions CI workflow with `moodle-plugin-ci` (phpcs, phpdoc, lint, mustache, phpunit, behat).
- Minimum Moodle version: 5.0 (`2025041400`), dependency: `enrol_wallet`.

### Phase 1: Data Model & Capabilities
- Database schema: `db/install.xml` creating the 4 core tables.
- System capabilities in `db/access.php`:
  - `local/walletrevenue:viewall` (Manager, Admin)
  - `local/walletrevenue:managepayouts` (Manager, Admin)
  - `local/walletrevenue:manageconfig` (Manager, Admin)
  - `local/walletrevenue:viewown` (Editing teacher)
  - `local/walletrevenue:recalculate` (Admin)
- `db/install.php`: Set `installfloor = max(enrol_wallet_transactions.id)`.

### Phase 2: Payees & Commission Rate Engine
- Plugin configuration in `settings.php` (global commission rate, default currency, sync interval).
- `classes/local/rate_resolver.php`: Evaluates hierarchy (Course override → Teacher override → Global default).
- `classes/local/payee_resolver.php`: Single payee per course manager with validation.
- Admin UI pages:
  - `payees.php`: Course payee assignment and unassigned warning banner.
  - `rates.php`: Teacher and course override management.

### Phase 3: Real-Time Sync & Ingestion Engine
- `classes/local/sale_sync.php`:
  - Anti-join ingestion from `mdl_enrol_wallet_transactions` where `id >= installfloor`.
  - Handles `enrol_instance`, `enrol`, `cm`, `section`, `unenrol`, and `rollback`.
  - Links refunds to original sales (`saleid`) with proportional fee-sharing clawback.
  - Re-evaluates unassigned sales with full hierarchy when a payee is assigned.
- Event observer: `classes/observer.php` listening to `\enrol_wallet\event\transactions_triggered` (captures `other['freecut']`).
- Scheduled task: `classes/task/sync_sales.php` (15-minute periodic safety net).

### Phase 4: Admin Report Builder Reports
- Moodle Report Builder system reports (`classes/reportbuilder/local/systemreports/`):
  - `teachers_revenue_summary`: Primary report grouped by Teacher and Currency (Sales, Share, Transferred, Balance, Period Delta, Actions).
  - `course_revenue_breakdown`: Drill-down into courses for a specific teacher.
  - `sales_ledger`: Granular transaction ledger.
- Export formats: CSV, Excel, PDF, ODS.
- Entry page `index.php` under *Site administration › Reports › Wallet revenue*.

### Phase 5: Payouts & Proof Attachment Ledger
- Payout management page `payouts.php` with modal dynamic form (`classes/form/payout_form.php`).
- Payment proof attachment using Moodle File API (`filemanager` field in form, `filearea = 'payout_proof'`).
- File serving via `lib.php` `local_walletrevenue_pluginfile()` with strict access checks.
- Audit events: `payout_created`, `payout_updated`, `payout_deleted`.

### Phase 6: Teacher Self-Service Portal & Privacy (GDPR)
- `my.php`: Teacher earnings dashboard showing balance cards per currency, course list, payout history, and downloadable payment receipts.
- Student data anonymization (`Student #1042`) in teacher views for privacy compliance.
- GDPR subsystem integration: `classes/privacy/provider.php` (export and deletion requests).

### Phase 7: Commission Correction & CLI Tools
- Recalculation tool `recalculate.php`: Allows admin to fix incorrect rates for a date range with a diff preview and `commission_recalculated` audit event.
- CLI backfill script `cli/backfill.php`: Imports pre-installation historical transactions.

### Phase 8: Testing, QA & Moodle Directory Validation
- Unit tests (`tests/`): Rate resolver, sale sync, refunds/clawbacks, payouts, privacy provider.
- Behat acceptance tests (`tests/behat/`): End-to-end admin configuration, purchase sync, payout with receipt, teacher verification.
- Moodle code validator: PHPCS (Moodle standard), PHPDoc, Mustache linting.

---

## Verification Plan

### Automated Tests
```bash
# Run PHPUnit test suite
vendor/bin/phpunit --testsuite local_walletrevenue_testsuite

# Run Behat tests
php admin/tool/behat/cli/run.php --tags=@local_walletrevenue

# Run Moodle code quality checks
moodle-plugin-ci phpcs
moodle-plugin-ci phpdoc
moodle-plugin-ci validate
```

### Manual Verification
1. Configure Global Rate (80%) and Ahmad's Teacher Override (70%).
2. Enrol student via wallet in a $50 course; verify sale record created with 70% ($35 share).
3. Student purchases $5 activity; verify micro-payment synced ($3.50 share).
4. View admin summary: check Total Sales ($55), Share ($38.50), Transferred ($0), Balance ($38.50).
5. Record payout of $38.50 with attached `receipt.png`; verify balance becomes $0.00.
6. Open teacher portal (`my.php` as Ahmad); verify balance ($0.00), course share, and download attached receipt.
7. Unenrol student with 10% cancellation fee; verify clawback applied proportionally.
