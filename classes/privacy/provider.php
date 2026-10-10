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

namespace local_walletrevenue\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use context_system;
use context_course;

/**
 * Privacy API provider for local_walletrevenue.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the metadata stored by this plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_walletrevenue_payee',
            [
                'courseid'     => 'privacy:metadata:payee:courseid',
                'teacherid'    => 'privacy:metadata:payee:teacherid',
                'usermodified' => 'privacy:metadata:payee:teacherid',
                'timecreated'  => 'privacy:metadata:payee:teacherid',
                'timemodified' => 'privacy:metadata:payee:teacherid',
            ],
            'privacy:metadata:payee'
        );

        $collection->add_database_table(
            'local_walletrevenue_rate',
            [
                'scope'        => 'privacy:metadata:rate',
                'itemid'       => 'privacy:metadata:rate',
                'percent'      => 'privacy:metadata:rate',
                'usermodified' => 'privacy:metadata:rate',
                'timecreated'  => 'privacy:metadata:rate',
                'timemodified' => 'privacy:metadata:rate',
            ],
            'privacy:metadata:rate'
        );

        $collection->add_database_table(
            'local_walletrevenue_sale',
            [
                'transactionid' => 'privacy:metadata:sale',
                'source'        => 'privacy:metadata:sale:source',
                'gateway'       => 'privacy:metadata:sale:gateway',
                'type'          => 'privacy:metadata:sale',
                'courseid'      => 'privacy:metadata:sale',
                'studentid'     => 'privacy:metadata:sale:studentid',
                'teacherid'     => 'privacy:metadata:sale:teacherid',
                'amount'        => 'privacy:metadata:sale:amount',
                'teachershare'  => 'privacy:metadata:sale:teachershare',
                'currency'      => 'privacy:metadata:sale',
                'timecreated'   => 'privacy:metadata:sale',
            ],
            'privacy:metadata:sale'
        );

        $collection->add_database_table(
            'local_walletrevenue_payout',
            [
                'teacherid'    => 'privacy:metadata:payout:teacherid',
                'amount'       => 'privacy:metadata:payout:amount',
                'currency'     => 'privacy:metadata:payout',
                'method'       => 'privacy:metadata:payout:method',
                'reference'    => 'privacy:metadata:payout:reference',
                'notes'        => 'privacy:metadata:payout',
                'paidat'       => 'privacy:metadata:payout',
                'usermodified' => 'privacy:metadata:payout',
            ],
            'privacy:metadata:payout'
        );

        $collection->link_subsystem('core_files', 'privacy:metadata:payout');

        return $collection;
    }

    /**
     * Get contexts that contain personal data for a user.
     *
     * @param int $userid The user ID.
     * @return contextlist Contexts containing data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // System context for payouts and global records.
        $contextlist->add_from_sql(
            "SELECT c.id
               FROM {context} c
              WHERE c.contextlevel = :syslevel",
            ['syslevel' => CONTEXT_SYSTEM]
        );

        return $contextlist;
    }

    /**
     * Export personal data for an approved context list.
     *
     * @param approved_contextlist $contextlist List of approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $context = context_system::instance();

        // Export payouts received.
        $payouts = $DB->get_records('local_walletrevenue_payout', ['teacherid' => $userid]);
        if (!empty($payouts)) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_walletrevenue'), get_string('payouts', 'local_walletrevenue')],
                (object) ['payouts' => array_values($payouts)]
            );
        }

        // Export course sales as teacher.
        $sales = $DB->get_records('local_walletrevenue_sale', ['teacherid' => $userid]);
        if (!empty($sales)) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_walletrevenue'), get_string('recordsale', 'local_walletrevenue')],
                (object) ['earnings' => array_values($sales)]
            );
        }
    }

    /**
     * Delete all personal data for all users in the given context.
     *
     * @param \context $context Context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // System context financial records are preserved for accounting ledger integrity.
    }

    /**
     * Delete personal data for a single user in approved contexts.
     *
     * @param approved_contextlist $contextlist The contextlist to delete for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // Legally required financial and payout ledger entries are retained for tax/audit trail.
    }
}
