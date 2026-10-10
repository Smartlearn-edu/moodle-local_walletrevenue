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

namespace local_walletrevenue;

use local_walletrevenue\local\sale_sync;

/**
 * Event observer for near-real-time wallet transactions ingestion.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Observer callback for enrol_wallet transactions_triggered event.
     *
     * @param \core\event\base $event The triggered event object.
     * @return void
     */
    public static function transaction_triggered(\core\event\base $event): void {
        $transactionid = (int) $event->objectid;
        if ($transactionid <= 0) {
            return;
        }

        $other = $event->other ?? [];
        $freecut = isset($other['freecut']) ? (float) $other['freecut'] : 0.0;

        sale_sync::sync_single_transaction($transactionid, $freecut);
    }

    /**
     * Observer callback for core user_enrolment_created event.
     *
     * Ingests payments made via Moodle core payment gateways (e.g. enrol_fee).
     *
     * @param \core\event\base $event The triggered event object.
     * @return void
     */
    public static function user_enrolment_created(\core\event\base $event): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('payments')) {
            return;
        }

        $other = $event->other ?? [];
        $enrolid = isset($other['enrolid']) ? (int) $other['enrolid'] : 0;
        $userid = (int) $event->relateduserid;

        if ($enrolid <= 0 || $userid <= 0) {
            return;
        }

        $payments = $DB->get_records_select(
            'payments',
            'itemid = :itemid AND userid = :userid AND component <> :walletcomponent',
            [
                'itemid'          => $enrolid,
                'userid'          => $userid,
                'walletcomponent' => 'enrol_wallet',
            ],
            'id DESC',
            'id',
            0,
            1
        );

        if (!empty($payments)) {
            $payment = reset($payments);
            sale_sync::sync_single_payment((int) $payment->id);
        }
    }
}
