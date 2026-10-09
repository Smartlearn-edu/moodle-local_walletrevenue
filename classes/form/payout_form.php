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
 * Form for recording or editing manual teacher payouts with receipt attachments.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payout_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        global $DB;
        $mform = $this->_form;

        $payoutid = $this->_customdata['id'] ?? 0;
        $teachers = $this->_customdata['teachers'] ?? [];

        $mform->addElement('hidden', 'id', $payoutid);
        $mform->setType('id', PARAM_INT);

        // Teacher selector.
        $teacheroptions = [];
        foreach ($teachers as $t) {
            $teacheroptions[$t->id] = fullname($t) . ' (' . $t->email . ')';
        }
        $mform->addElement('select', 'teacherid', get_string('teacher', 'local_walletrevenue'), $teacheroptions);
        $mform->setType('teacherid', PARAM_INT);
        $mform->addRule('teacherid', null, 'required', null, 'client');

        // Amount.
        $mform->addElement('text', 'amount', get_string('payoutamount', 'local_walletrevenue'), ['size' => 12]);
        $mform->setType('amount', PARAM_FLOAT);
        $mform->addRule('amount', null, 'required', null, 'client');

        // Currency.
        $defaultcurrency = get_config('local_walletrevenue', 'defaultcurrency') ?: 'USD';
        $mform->addElement('text', 'currency', get_string('currency', 'local_walletrevenue'), ['size' => 5]);
        $mform->setType('currency', PARAM_ALPHANUM);
        $mform->setDefault('currency', $defaultcurrency);
        $mform->addRule('currency', null, 'required', null, 'client');

        // Payment method.
        $methods = [
            'bank_transfer' => get_string('method_bank', 'local_walletrevenue'),
            'paypal'        => get_string('method_paypal', 'local_walletrevenue'),
            'cash'          => get_string('method_cash', 'local_walletrevenue'),
            'other'         => get_string('method_other', 'local_walletrevenue'),
        ];
        $mform->addElement('select', 'method', get_string('paymentmethod', 'local_walletrevenue'), $methods);
        $mform->setType('method', PARAM_ALPHAEXT);

        // Reference / Transaction ID.
        $mform->addElement('text', 'reference', get_string('paymentreference', 'local_walletrevenue'), ['size' => 30]);
        $mform->setType('reference', PARAM_TEXT);

        // Date paid.
        $mform->addElement('date_time_selector', 'paidat', get_string('paidat', 'local_walletrevenue'));
        $mform->setDefault('paidat', time());

        // Notes.
        $mform->addElement('textarea', 'notes', get_string('paymentnotes', 'local_walletrevenue'), 'wrap="virtual" rows="3" cols="50"');
        $mform->setType('notes', PARAM_TEXT);

        // Payment proof receipt filemanager.
        $filemanageroptions = [
            'subdirs'        => 0,
            'maxbytes'       => 20971520, // 20MB
            'maxfiles'       => 5,
            'accepted_types' => ['image', '.pdf'],
        ];
        $mform->addElement('filemanager', 'proof_filemanager', get_string('paymentproof', 'local_walletrevenue'), null, $filemanageroptions);
        $mform->addHelpButton('proof_filemanager', 'paymentproof', 'local_walletrevenue');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validation.
     *
     * @param array $data Form data.
     * @param array $files Files.
     * @return array Errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            $errors['amount'] = get_string('error');
        }

        return $errors;
    }
}
