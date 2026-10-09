# Teacher Revenue & Payout Report (`local_walletrevenue`)

A Moodle local plugin that tracks teacher course earnings from `enrol_wallet` (and `availability_wallet`), calculates commission shares based on hierarchical overrides (Course → Teacher → Global), and provides an auditable manual payout ledger with payment proof attachments (screenshots/receipts).

## Features
- **Teacher-Centric Revenue Reporting**: Primary summary report grouped by teacher and currency with full course breakdown drill-downs.
- **Hierarchical Commission Engine**: Global default commission rate with overrides per teacher and per course.
- **Informative Payout Ledger**: Record manual teacher payouts with payment method, reference, notes, and uploaded payment proof receipts.
- **Teacher Self-Service Dashboard**: Dedicated statement page (`my.php`) allowing teachers to view their earnings, balances, and download admin payment receipts.
- **GDPR & Privacy Compliant**: Anonymizes student identities on teacher views and implements Moodle's Privacy API.
- **Report Builder Integration**: Built-in system reports exportable to Excel, CSV, PDF, and ODS.

## Requirements
- Moodle 5.0+ (`2025041400`)
- `enrol_wallet` plugin installed and enabled.

## License
Licensed under the [GNU General Public License, version 3 (GPLv3)](https://www.gnu.org/licenses/gpl-3.0.html).
