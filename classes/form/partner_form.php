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
 * Form for creating or editing a partner organization.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class partner_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $users = $this->_customdata['users'] ?? [];
        $action = $this->_customdata['action'] ?? 'add';
        $partnerid = (int) ($this->_customdata['id'] ?? 0);

        $mform->addElement('hidden', 'id', $partnerid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('text', 'name', get_string('partnername', 'local_walletrevenue'), ['size' => '40']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'partnername', 'local_walletrevenue');

        $useroptions = ['' => get_string('choose')];
        foreach ($users as $u) {
            $useroptions[$u->id] = fullname($u) . ' (' . $u->email . ')';
        }

        $mform->addElement('select', 'userid', get_string('partneruser', 'local_walletrevenue'), $useroptions);
        $mform->setType('userid', PARAM_INT);
        $mform->addRule('userid', null, 'required', null, 'client');
        $mform->addHelpButton('userid', 'partneruser', 'local_walletrevenue');

        $mform->addElement('text', 'code', get_string('partnercode', 'local_walletrevenue'), ['size' => '20']);
        $mform->setType('code', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('code', 'partnercode', 'local_walletrevenue');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Form validation.
     *
     * @param array $data Form data.
     * @param array $files Uploaded files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);

        $id = (int) ($data['id'] ?? 0);
        $userid = (int) ($data['userid'] ?? 0);
        $code = trim($data['code'] ?? '');

        // Check if user is already assigned as a partner for another organization.
        if ($userid > 0) {
            $params = ['userid' => $userid];
            $sql = "userid = :userid";
            if ($id > 0) {
                $sql .= " AND id <> :id";
                $params['id'] = $id;
            }
            if ($DB->record_exists_select('local_walletrevenue_partner', $sql, $params)) {
                $errors['userid'] = get_string('erroruseralreadypartner', 'local_walletrevenue');
            }
        }

        // Check if code is unique.
        if ($code !== '') {
            $params = ['code' => $code];
            $sql = "code = :code";
            if ($id > 0) {
                $sql .= " AND id <> :id";
                $params['id'] = $id;
            }
            if ($DB->record_exists_select('local_walletrevenue_partner', $sql, $params)) {
                $errors['code'] = get_string('errorcodealreadyused', 'local_walletrevenue');
            }
        }

        return $errors;
    }
}
