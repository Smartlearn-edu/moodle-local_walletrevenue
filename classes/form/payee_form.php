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
 * Form for assigning or updating course payee teacher.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payee_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $courseid = $this->_customdata['courseid'] ?? 0;
        $teachers = $this->_customdata['teachers'] ?? [];

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $options = [0 => get_string('autodetectoption', 'local_walletrevenue')];
        foreach ($teachers as $teacher) {
            $options[$teacher->id] = fullname($teacher) . ' (' . $teacher->email . ')';
        }

        $mform->addElement('select', 'teacherid', get_string('payeeteacher', 'local_walletrevenue'), $options);
        $mform->setType('teacherid', PARAM_INT);
        $mform->addRule('teacherid', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
