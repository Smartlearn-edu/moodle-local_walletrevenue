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
use local_walletrevenue\local\rate_resolver;

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for adding or editing a commission rate override.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('select', 'scope', get_string('rate_scope', 'local_walletrevenue'), [
            rate_resolver::SCOPE_COURSE  => get_string('rate_scope_course', 'local_walletrevenue'),
            rate_resolver::SCOPE_TEACHER => get_string('rate_scope_teacher', 'local_walletrevenue'),
        ]);
        $mform->setType('scope', PARAM_ALPHA);

        $mform->addElement('text', 'itemid', get_string('itemid', 'local_walletrevenue'), ['size' => 10]);
        $mform->setType('itemid', PARAM_INT);
        $mform->addRule('itemid', null, 'required', null, 'client');
        $mform->addHelpButton('itemid', 'itemid', 'local_walletrevenue');

        $mform->addElement('text', 'percent', get_string('rate_percent', 'local_walletrevenue'), ['size' => 6]);
        $mform->setType('percent', PARAM_FLOAT);
        $mform->addRule('percent', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validation.
     *
     * @param array $data Form data.
     * @param array $files Uploaded files.
     * @return array Array of errors.
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);

        $percent = (float) ($data['percent'] ?? -1);
        if ($percent < 0.0 || $percent > 100.0) {
            $errors['percent'] = get_string('error');
        }

        $itemid = (int) ($data['itemid'] ?? 0);
        $scope = $data['scope'] ?? '';

        if ($scope === rate_resolver::SCOPE_COURSE) {
            if (!$DB->record_exists('course', ['id' => $itemid])) {
                $errors['itemid'] = get_string('invalidcourseid', 'error');
            }
        } else if ($scope === rate_resolver::SCOPE_TEACHER) {
            if (!$DB->record_exists('user', ['id' => $itemid, 'deleted' => 0])) {
                $errors['itemid'] = get_string('invaliduser', 'error');
            }
        }

        return $errors;
    }
}
