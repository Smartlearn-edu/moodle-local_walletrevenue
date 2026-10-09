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
 * Admin settings for local_walletrevenue.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_walletrevenue',
        get_string('pluginname', 'local_walletrevenue')
    );

    $ADMIN->add('localplugins', $settings);

    // Default global commission rate (0.00 to 100.00).
    $settings->add(new admin_setting_configtext(
        'local_walletrevenue/defaultrate',
        get_string('defaultrate', 'local_walletrevenue'),
        get_string('defaultrate_desc', 'local_walletrevenue'),
        '80.00',
        '/^(100(\.0{1,2})?|[0-9]{1,2}(\.[0-9]{1,2})?)$/'
    ));

    // Default currency.
    $settings->add(new admin_setting_configtext(
        'local_walletrevenue/defaultcurrency',
        get_string('defaultcurrency', 'local_walletrevenue'),
        get_string('defaultcurrency_desc', 'local_walletrevenue'),
        'USD',
        PARAM_ALPHANUM
    ));

    // Include micro-payments.
    $settings->add(new admin_setting_configcheckbox(
        'local_walletrevenue/includemicropayments',
        get_string('includemicropayments', 'local_walletrevenue'),
        get_string('includemicropayments_desc', 'local_walletrevenue'),
        1
    ));
}
