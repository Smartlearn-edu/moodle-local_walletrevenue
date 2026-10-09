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

namespace local_walletrevenue\event;

use core\event\base;
use moodle_url;

/**
 * Event triggered when a payout record is deleted.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payout_deleted extends base {
    /**
     * Initialize event data.
     */
    protected function init(): void {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_walletrevenue_payout';
    }

    /**
     * Get event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('deletepayout', 'local_walletrevenue');
    }

    /**
     * Get event description.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' deleted payout record '{$this->objectid}' " .
            "for teacher user '{$this->relateduserid}'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url('/local/walletrevenue/payouts.php');
    }
}
