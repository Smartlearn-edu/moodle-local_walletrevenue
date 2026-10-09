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
    /** Allowed debit operation types representing sales. */
    public const VALID_SALE_OPS = ['enrol_instance', 'enrol', 'cm', 'section'];

    /** Allowed credit operation types representing refunds. */
    public const VALID_REFUND_OPS = ['unenrol', 'rollback'];

    /**
     * Synchronize all pending transactions that have not yet been ingested.
     *
     * Uses an anti-join query to avoid cursor skipping or race conditions.
     *
     * @param int $customfloor Optional custom floor ID (for backfill).
     * @return int Number of processed transactions.
     */
    public static function sync_all_pending(int $customfloor = -1): int {
        global $DB;

        if (!$DB->get_manager()->table_exists('enrol_wallet_transactions')) {
            return 0;
        }

        $floor = $customfloor >= 0 ? $customfloor : (int) get_config('local_walletrevenue', 'installfloor');

        $ops = array_merge(self::VALID_SALE_OPS, self::VALID_REFUND_OPS);
        [$insql, $params] = $DB->get_in_or_equal($ops, SQL_PARAMS_NAMED, 'op');

        $sql = "SELECT t.*
                  FROM {enrol_wallet_transactions} t
             LEFT JOIN {local_walletrevenue_sale} s ON s.transactionid = t.id
                 WHERE s.id IS NULL
                   AND t.id >= :floor
                   AND t.opby $insql
              ORDER BY t.id ASC";

        $params['floor'] = $floor;

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
     * Synchronize a single transaction by ID (e.g. from event observer).
     *
     * @param int $transactionid The transaction ID in enrol_wallet_transactions.
     * @param float $freecut Optional free credits used, captured from event data.
     * @return bool True if processed and saved, false otherwise.
     */
    public static function sync_single_transaction(int $transactionid, float $freecut = 0.0): bool {
        global $DB;

        if ($DB->record_exists('local_walletrevenue_sale', ['transactionid' => $transactionid])) {
            return false;
        }

        $transaction = $DB->get_record('enrol_wallet_transactions', ['id' => $transactionid]);
        if (!$transaction) {
            return false;
        }

        return self::process_transaction($transaction, $freecut);
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
        $type = (string) $t->type; // 'debit' or 'credit'
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
                'courseid = :cid AND studentid = :sid AND type = :type AND timecreated <= :ttime',
                [
                    'cid'   => $courseid,
                    'sid'   => $studentid,
                    'type'  => 'sale',
                    'ttime' => $txntime + 10, // Buffer for near-instant rollback
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
}
