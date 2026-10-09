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

use tabobject;
use tabtree;
use moodle_url;

/**
 * UI helper for rendering navigation tabs and standard UI elements.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ui_helper {
    /**
     * Render the standard navigation tabs for admin pages.
     *
     * @param string $current The active tab ID ('reports', 'payees', 'rates', 'payouts', 'recalculate').
     * @return string Rendered HTML of tabs.
     */
    public static function render_tabs(string $current): string {
        global $OUTPUT;

        $tabs = [
            new tabobject('reports', new moodle_url('/local/walletrevenue/index.php'), get_string('reports', 'local_walletrevenue')),
            new tabobject('payees', new moodle_url('/local/walletrevenue/payees.php'), get_string('payees', 'local_walletrevenue')),
            new tabobject('rates', new moodle_url('/local/walletrevenue/rates.php'), get_string('rates', 'local_walletrevenue')),
            new tabobject('payouts', new moodle_url('/local/walletrevenue/payouts.php'), get_string('payouts', 'local_walletrevenue')),
            new tabobject('recalculate', new moodle_url('/local/walletrevenue/recalculate.php'), get_string('recalculate', 'local_walletrevenue')),
        ];

        return $OUTPUT->tabtree($tabs, $current);
    }

    /**
     * Render unassigned courses warning banner if there are any.
     *
     * @return string Rendered notification HTML or empty string.
     */
    public static function render_unassigned_banner(): string {
        global $OUTPUT;

        $count = payee_resolver::get_unassigned_courses_count();
        if ($count > 0) {
            $msg = get_string('unassignedalert', 'local_walletrevenue', $count);
            return $OUTPUT->notification($msg, \core\output\notification::NOTIFY_WARNING);
        }

        return '';
    }
}
