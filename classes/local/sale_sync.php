<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_walletrevenue\local;

/**
 * Sync engine for ingesting and processing wallet transactions into revenue sales records.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sale_sync {
    /** Source identifier for wallet transactions. */
    public const SOURCE_WALLET = 'wallet';

    /** Source identifier for Moodle core payment gateway transactions. */
    public const SOURCE_CORE_PAYMENT = 'core_payment';

    /** Allowed debit operation types representing sales. */
    public const VALID_SALE_OPS = ['enrol_instance', 'enrol', 'cm', 'section'];

    /** Allowed credit operation types representing refunds. */
    public const VALID_REFUND_OPS = ['unenrol', 'rollback'];

    /**
     * Synchronize all pending transactions from all enabled sources that have not yet been ingested.
     *
     * @param int $customfloor Optional custom floor ID (for backfill).
     * @return int Total number of processed transactions across all sources.
     */
    public static function sync_all_pending(int $customfloor = -1): int {
        $walletcount = self::sync_pending_wallet_transactions($customfloor);
        $paymentcount = self::sync_pending_core_payments($customfloor);

        return $walletcount + $paymentcount;
    }

    /**
     * Synchronize pending wallet transactions from enrol_wallet_transactions.
     *
     * @param int $customfloor Optional custom floor ID (for backfill).
     * @return int Number of processed wallet transactions.
     */
    public static function sync_pending_wallet_transactions(int $customfloor = -1): int {
        global $DB;

        $enabled = get_config('local_walletrevenue', 'enablewalletsync');
        if ($enabled !== false && empty($enabled)) {
            return 0;
        }

        if (!$DB->get_manager()->table_exists('enrol_wallet_transactions')) {
            return 0;
        }

        $floor = $customfloor >= 0 ? $customfloor : (int) get_config('local_walletrevenue', 'installfloor');

        $ops = array_merge(self::VALID_SALE_OPS, self::VALID_REFUND_OPS);
        [$insql, $params] = $DB->get_in_or_equal($ops, SQL_PARAMS_NAMED, 'op');

        $sql = "SELECT t.*
                  FROM {enrol_wallet_transactions} t
             LEFT JOIN {local_walletrevenue_sale} s
                    ON s.transactionid = t.id AND s.source = :source
                 WHERE s.id IS NULL
                   AND t.id >= :floor
                   AND t.opby $insql
              ORDER BY t.id ASC";

        $params['floor'] = $floor;
        $params['source'] = self::SOURCE_WALLET;

        $transactions = $DB->get_records_sql($sql, $params);
        $count = 0;

        foreach ($transactions as $t) {
            if (self::process_transaction($t)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Synchronize pending transactions from Moodle core payment gateway (payments table).
     *
     * @param int $customfloor Optional custom floor ID (for backfill).
     * @return int Number of processed core payment transactions.
     */
    public static function sync_pending_core_payments(int $customfloor = -1): int {
        global $DB;

        $enabled = get_config('local_walletrevenue', 'enablepaymentsync');
        if ($enabled !== false && empty($enabled)) {
            return 0;
        }

        if (!$DB->get_manager()->table_exists('payments')) {
            return 0;
        }

        $floor = $customfloor >= 0 ? $customfloor : (int) get_config('local_walletrevenue', 'installfloor_payment');

        // Exclude payments made to top-up enrol_wallet to avoid double-counting.
        $sql = "SELECT p.*
                  FROM {payments} p
             LEFT JOIN {local_walletrevenue_sale} s
                    ON s.transactionid = p.id AND s.source = :source
                 WHERE s.id IS NULL
                   AND p.id >= :floor
                   AND p.component <> :walletcomponent
              ORDER BY p.id ASC";

        $params = [
            'source'          => self::SOURCE_CORE_PAYMENT,
            'floor'           => $floor,
            'walletcomponent' => 'enrol_wallet',
        ];

        $payments = $DB->get_records_sql($sql, $params);
        $count = 0;

        foreach ($payments as $p) {
            if (self::process_core_payment($p)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Synchronize a single wallet transaction by ID (e.g. from event observer).
     *
     * @param int $transactionid The transaction ID in enrol_wallet_transactions.
     * @param float $freecut Optional free credits used, captured from event data.
     * @return bool True if processed and saved, false otherwise.
     */
    public static function sync_single_transaction(int $transactionid, float $freecut = 0.0): bool {
        global $DB;

        $enabled = get_config('local_walletrevenue', 'enablewalletsync');
        if ($enabled !== false && empty($enabled)) {
            return false;
        }

        $exists = $DB->record_exists('local_walletrevenue_sale', [
            'transactionid' => $transactionid,
            'source'        => self::SOURCE_WALLET,
        ]);
        if ($exists) {
            return false;
        }

        $transaction = $DB->get_record('enrol_wallet_transactions', ['id' => $transactionid]);
        if (!$transaction) {
            return false;
        }

        return self::process_transaction($transaction, $freecut);
    }

    /**
     * Synchronize a single core payment by ID (e.g. from event observer).
     *
     * @param int $paymentid The payment ID in payments table.
     * @return bool True if processed and saved, false otherwise.
     */
    public static function sync_single_payment(int $paymentid): bool {
        global $DB;

        $enabled = get_config('local_walletrevenue', 'enablepaymentsync');
        if ($enabled !== false && empty($enabled)) {
            return false;
        }

        $exists = $DB->record_exists('local_walletrevenue_sale', [
            'transactionid' => $paymentid,
            'source'        => self::SOURCE_CORE_PAYMENT,
        ]);
        if ($exists) {
            return false;
        }

        $payment = $DB->get_record('payments', ['id' => $paymentid]);
        if (!$payment || $payment->component === 'enrol_wallet') {
            return false;
        }

        return self::process_core_payment($payment);
    }

    /**
     * Process and ingest an enrol_wallet_transactions record.
     *
     * @param \stdClass $t Transaction record.
     * @param float $freecut Free credit cut if known.
     * @return bool True if created, false if ignored.
     */
    protected static function process_transaction(\stdClass $t, float $freecut = 0.0): bool {
        global $DB;

        $opby = (string) $t->opby;
        // Transaction type (debit or credit).
        $type = (string) $t->type;
        $thingid = (int) $t->thingid;
        $studentid = (int) $t->userid;

        // Skip micro-payments if disabled in settings.
        $includemicro = (bool) get_config('local_walletrevenue', 'includemicropayments');
        if (!$includemicro && in_array($opby, ['cm', 'section'], true)) {
            return false;
        }

        // Resolve course ID, instance ID, list price, and currency.
        $resolution = self::resolve_context($opby, $thingid);
        if (!$resolution || empty($resolution['courseid'])) {
            return false;
        }

        $courseid = $resolution['courseid'];
        $instanceid = $resolution['instanceid'];
        $listprice = $resolution['listprice'];
        $currency = $resolution['currency'];

        $now = time();
        $txntime = !empty($t->timecreated) ? (int) $t->timecreated : $now;
        $rawamount = abs((float) $t->amount);

        if ($type === 'debit' && in_array($opby, self::VALID_SALE_OPS, true)) {
            // SALE RECORD.
            $teacherid = payee_resolver::get_payee($courseid);
            $rate = rate_resolver::resolve($courseid, $teacherid);
            $teachershare = round(($rawamount * $rate) / 100.0, 4);

            $sale = (object) [
                'transactionid' => $t->id,
                'source'        => self::SOURCE_WALLET,
                'gateway'       => 'wallet',
                'type'          => 'sale',
                'sourceop'      => $opby,
                'courseid'      => $courseid,
                'instanceid'    => $instanceid,
                'studentid'     => $studentid,
                'teacherid'     => $teacherid,
                'saleid'        => 0,
                'listprice'     => $listprice,
                'amount'        => $rawamount,
                'percent'       => $rate,
                'teachershare'  => $teachershare,
                'currency'      => $currency,
                'freecut'       => $freecut,
                'timecreated'   => $txntime,
                'timesynced'    => $now,
            ];

            return (bool) $DB->insert_record('local_walletrevenue_sale', $sale);
        } else if ($type === 'credit' && in_array($opby, self::VALID_REFUND_OPS, true)) {
            // REFUND OR ROLLBACK RECORD.
            // Match to the most recent preceding sale for this student and course.
            $originalsale = $DB->get_record_select(
                'local_walletrevenue_sale',
                'courseid = :cid AND studentid = :sid AND type = :type AND source = :src AND timecreated <= :ttime',
                [
                    'cid'   => $courseid,
                    'sid'   => $studentid,
                    'type'  => 'sale',
                    'src'   => self::SOURCE_WALLET,
                    'ttime' => $txntime + 10, // Buffer for near-instant rollback.
                ],
                'id, percent, teachershare, teacherid',
                IGNORE_MULTIPLE
            );

            if ($originalsale) {
                $teacherid = (int) $originalsale->teacherid;
                $rate = (float) $originalsale->percent;
                $saleid = (int) $originalsale->id;
                // Proportional clawback, capped at original sale share.
                $calculatedshare = round(($rawamount * $rate) / 100.0, 4);
                $teachershare = min($calculatedshare, (float) $originalsale->teachershare);
            } else {
                $teacherid = payee_resolver::get_payee($courseid);
                $rate = rate_resolver::resolve($courseid, $teacherid);
                $teachershare = round(($rawamount * $rate) / 100.0, 4);
                $saleid = 0;
            }

            $refund = (object) [
                'transactionid' => $t->id,
                'source'        => self::SOURCE_WALLET,
                'gateway'       => 'wallet',
                'type'          => 'refund',
                'sourceop'      => $opby,
                'courseid'      => $courseid,
                'instanceid'    => $instanceid,
                'studentid'     => $studentid,
                'teacherid'     => $teacherid,
                'saleid'        => $saleid,
                'listprice'     => $listprice,
                'amount'        => -1.0 * $rawamount,
                'percent'       => $rate,
                'teachershare'  => -1.0 * $teachershare,
                'currency'      => $currency,
                'freecut'       => 0.0,
                'timecreated'   => $txntime,
                'timesynced'    => $now,
            ];

            return (bool) $DB->insert_record('local_walletrevenue_sale', $refund);
        }

        return false;
    }

    /**
     * Process and ingest a Moodle core payment record from the payments table.
     *
     * @param \stdClass $p Payments record.
     * @return bool True if created, false if ignored.
     */
    protected static function process_core_payment(\stdClass $p): bool {
        global $DB;

        $rawamount = abs((float) $p->amount);
        if ($rawamount <= 0.0) {
            return false;
        }

        $resolution = self::resolve_core_payment_context($p);
        if (!$resolution || empty($resolution['courseid'])) {
            return false;
        }

        $courseid = $resolution['courseid'];
        $instanceid = $resolution['instanceid'];
        $listprice = $resolution['listprice'];
        $currency = $resolution['currency'];

        $teacherid = payee_resolver::get_payee($courseid);
        $rate = rate_resolver::resolve($courseid, $teacherid);
        $teachershare = round(($rawamount * $rate) / 100.0, 4);

        $now = time();
        $txntime = !empty($p->timecreated) ? (int) $p->timecreated : $now;
        $gateway = !empty($p->gateway) ? (string) $p->gateway : 'core_payment';

        $sale = (object) [
            'transactionid' => (int) $p->id,
            'source'        => self::SOURCE_CORE_PAYMENT,
            'gateway'       => $gateway,
            'type'          => 'sale',
            'sourceop'      => (string) $p->component,
            'courseid'      => $courseid,
            'instanceid'    => $instanceid,
            'studentid'     => (int) $p->userid,
            'teacherid'     => $teacherid,
            'saleid'        => 0,
            'listprice'     => $listprice,
            'amount'        => $rawamount,
            'percent'       => $rate,
            'teachershare'  => $teachershare,
            'currency'      => $currency,
            'freecut'       => 0.0,
            'timecreated'   => $txntime,
            'timesynced'    => $now,
        ];

        return (bool) $DB->insert_record('local_walletrevenue_sale', $sale);
    }

    /**
     * Resolve the course context and pricing information for an operation.
     *
     * @param string $opby Operation reason string.
     * @param int $thingid Target entity ID.
     * @return array|null Context array or null if unresolvable.
     */
    protected static function resolve_context(string $opby, int $thingid): ?array {
        global $DB;

        $defaultcurrency = get_config('local_walletrevenue', 'defaultcurrency') ?: 'USD';

        if ($opby === 'enrol_instance' || $opby === 'unenrol' || $opby === 'rollback') {
            if ($thingid <= 0) {
                return null;
            }
            $instance = $DB->get_record('enrol', ['id' => $thingid]);
            if (!$instance) {
                return null;
            }
            return [
                'courseid'   => (int) $instance->courseid,
                'instanceid' => (int) $instance->id,
                'listprice'  => (float) ($instance->cost ?? 0.0),
                'currency'   => !empty($instance->currency) ? (string) $instance->currency : $defaultcurrency,
            ];
        }

        if ($opby === 'enrol') {
            if ($thingid <= 0 || !$DB->record_exists('course', ['id' => $thingid])) {
                return null;
            }
            return [
                'courseid'   => $thingid,
                'instanceid' => 0,
                'listprice'  => 0.0,
                'currency'   => $defaultcurrency,
            ];
        }

        if ($opby === 'cm') {
            $cm = $DB->get_record('course_modules', ['id' => $thingid]);
            if (!$cm) {
                return null;
            }
            return [
                'courseid'   => (int) $cm->course,
                'instanceid' => 0,
                'listprice'  => 0.0,
                'currency'   => $defaultcurrency,
            ];
        }

        if ($opby === 'section') {
            $section = $DB->get_record('course_sections', ['id' => $thingid]);
            if (!$section) {
                return null;
            }
            return [
                'courseid'   => (int) $section->course,
                'instanceid' => 0,
                'listprice'  => 0.0,
                'currency'   => $defaultcurrency,
            ];
        }

        return null;
    }

    /**
     * Resolve the course context and pricing information for a Moodle core payment record.
     *
     * @param \stdClass $p Payment record.
     * @return array|null Context array or null if unresolvable.
     */
    protected static function resolve_core_payment_context(\stdClass $p): ?array {
        global $DB;

        $defaultcurrency = get_config('local_walletrevenue', 'defaultcurrency') ?: 'USD';
        $itemid = (int) $p->itemid;

        // In enrol_fee and most enrolment plugins using core_payment, itemid is the enrol instance ID.
        if ($itemid > 0) {
            $instance = $DB->get_record('enrol', ['id' => $itemid]);
            if ($instance) {
                return [
                    'courseid'   => (int) $instance->courseid,
                    'instanceid' => (int) $instance->id,
                    'listprice'  => (float) ($instance->cost ?? (float) $p->amount),
                    'currency'   => !empty($p->currency) ? (string) $p->currency :
                        (!empty($instance->currency) ? (string) $instance->currency : $defaultcurrency),
                ];
            }
        }

        return null;
    }
}
