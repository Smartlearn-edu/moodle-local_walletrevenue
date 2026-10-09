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

/**
 * Installation hook for local_walletrevenue.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Custom post-installation procedure for local_walletrevenue.
 *
 * Sets the safe install floor so the scheduled task only syncs new transactions.
 *
 * @return void
 */
function xmldb_local_walletrevenue_install(): void {
    global $DB;

    // Set safe install floor cursor to the latest current wallet transaction ID.
    $maxid = 0;
    if ($DB->get_manager()->table_exists('enrol_wallet_transactions')) {
        $maxid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {enrol_wallet_transactions}');
    }

    set_config('installfloor', $maxid, 'local_walletrevenue');
}
