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
 * Form for assigning teachers to a partner organization.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class partner_assign_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $partnerid = (int) ($this->_customdata['partnerid'] ?? 0);
        $teachers = $this->_customdata['teachers'] ?? [];
        $action = $this->_customdata['action'] ?? 'assign';

        $mform->addElement('hidden', 'id', $partnerid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'partnerid', $partnerid);
        $mform->setType('partnerid', PARAM_INT);

        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_ALPHA);

        $options = [];
        foreach ($teachers as $t) {
            $options[$t->id] = fullname($t) . ' (' . $t->email . ')';
        }

        $select = $mform->addElement(
            'select',
            'teachers',
            get_string('assignedteachers', 'local_walletrevenue'),
            $options,
            ['multiple' => 'multiple', 'size' => '12', 'class' => 'w-100']
        );
        $mform->setType('teachers', PARAM_INT);
        $mform->addHelpButton('teachers', 'assignedteachers', 'local_walletrevenue');

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
