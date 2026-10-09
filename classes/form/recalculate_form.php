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

namespace local_walletrevenue\form;

use moodleform;

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for selecting parameters to recalculate sales commissions.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalculate_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        global $DB;
        $mform = $this->_form;

        // Course filter.
        $courses = [0 => get_string('all')] + $DB->get_records_menu('course', ['id' => $DB->sql_concat('id', '')], 'fullname ASC', 'id, fullname');
        $mform->addElement('select', 'courseid', get_string('course', 'local_walletrevenue'), $courses);
        $mform->setType('courseid', PARAM_INT);

        // Teacher filter.
        $teachers = [0 => get_string('all')];
        $teachersql = "SELECT DISTINCT u.id, u.firstname, u.lastname
                         FROM {user} u
                         JOIN {local_walletrevenue_sale} s ON s.teacherid = u.id
                     ORDER BY u.firstname, u.lastname";
        $trows = $DB->get_records_sql($teachersql);
        foreach ($trows as $t) {
            $teachers[$t->id] = fullname($t);
        }
        $mform->addElement('select', 'teacherid', get_string('teacher', 'local_walletrevenue'), $teachers);
        $mform->setType('teacherid', PARAM_INT);

        // Date range.
        $mform->addElement('date_selector', 'timefrom', get_string('timefrom', 'local_walletrevenue'), ['optional' => true]);
        $mform->addElement('date_selector', 'timeto', get_string('timeto', 'local_walletrevenue'), ['optional' => true]);

        // Mode: Preview or Apply.
        $mform->addElement('advcheckbox', 'previewonly', get_string('previewdiff', 'local_walletrevenue'));
        $mform->setDefault('previewonly', 1);

        $this->add_action_buttons(true, get_string('continue'));
    }
}
