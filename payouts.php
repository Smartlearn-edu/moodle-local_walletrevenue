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
 * Payouts management page: record, edit, and view teacher payouts with proof attachments.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_walletrevenue\form\payout_form;
use local_walletrevenue\local\ui_helper;
use local_walletrevenue\event\payout_created;
use local_walletrevenue\event\payout_updated;
use local_walletrevenue\event\payout_deleted;

require_login();
$context = context_system::instance();
require_capability('local/walletrevenue:managepayouts', $context);

$action    = optional_param('action', '', PARAM_ALPHA);
$id        = optional_param('id', 0, PARAM_INT);
$teacherid = optional_param('teacherid', 0, PARAM_INT);
$currency  = optional_param('currency', '', PARAM_ALPHANUM);
$amount    = optional_param('amount', 0.0, PARAM_FLOAT);

$url = new moodle_url('/local/walletrevenue/payouts.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('payouts', 'local_walletrevenue'));
$PAGE->set_heading(get_string('payouts', 'local_walletrevenue'));

// Handle deletion.
if ($action === 'delete' && $id > 0) {
    require_sesskey();
    $payout = $DB->get_record('local_walletrevenue_payout', ['id' => $id], '*', MUST_EXIST);

    // Delete proof files.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'local_walletrevenue', 'payout_proof', $payout->id);

    $DB->delete_records('local_walletrevenue_payout', ['id' => $id]);

    $event = payout_deleted::create([
        'context'       => $context,
        'objectid'      => $id,
        'relateduserid' => $payout->teacherid,
    ]);
    $event->trigger();

    redirect(new moodle_url('/local/walletrevenue/payouts.php'), get_string('deleted'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle Add or Edit.
if ($action === 'add' || ($action === 'edit' && $id > 0)) {
    // Eligible teachers who are payees or have sales.
    $teachersql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                     FROM {user} u
                     JOIN {local_walletrevenue_payee} p ON p.teacherid = u.id
                 ORDER BY u.firstname, u.lastname";
    $teachers = $DB->get_records_sql($teachersql);

    if ($teacherid > 0 && !isset($teachers[$teacherid])) {
        $u = $DB->get_record('user', ['id' => $teacherid]);
        if ($u) {
            $teachers[$u->id] = $u;
        }
    }

    $form = new payout_form(null, ['id' => $id, 'teachers' => $teachers]);

    $payout = null;
    $draftitemid = file_get_submitted_draft_itemid('proof_filemanager');

    if ($id > 0) {
        $payout = $DB->get_record('local_walletrevenue_payout', ['id' => $id], '*', MUST_EXIST);
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'local_walletrevenue',
            'payout_proof',
            $payout->id,
            ['subdirs' => 0, 'maxfiles' => 5]
        );
        $payout->proof_filemanager = $draftitemid;
        $form->set_data($payout);
    } else {
        $defaultdata = (object) [
            'teacherid'         => $teacherid,
            'amount'            => $amount > 0 ? $amount : '',
            'currency'          => $currency ?: (get_config('local_walletrevenue', 'defaultcurrency') ?: 'USD'),
            'paidat'            => time(),
            'proof_filemanager' => $draftitemid,
        ];
        $form->set_data($defaultdata);
    }

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/walletrevenue/payouts.php'));
    } else if ($data = $form->get_data()) {
        $now = time();
        if ($id > 0) {
            $payout->teacherid = $data->teacherid;
            $payout->amount = (float) $data->amount;
            $payout->currency = (string) $data->currency;
            $payout->method = (string) $data->method;
            $payout->reference = (string) ($data->reference ?? '');
            $payout->notes = (string) ($data->notes ?? '');
            $payout->paidat = (int) $data->paidat;
            $payout->usermodified = $USER->id;
            $payout->timemodified = $now;

            $DB->update_record('local_walletrevenue_payout', $payout);

            file_save_draft_area_files(
                $data->proof_filemanager,
                $context->id,
                'local_walletrevenue',
                'payout_proof',
                $payout->id,
                ['subdirs' => 0, 'maxfiles' => 5]
            );

            $event = payout_updated::create([
                'context'       => $context,
                'objectid'      => $payout->id,
                'relateduserid' => $payout->teacherid,
            ]);
            $event->trigger();

            redirect(new moodle_url('/local/walletrevenue/payouts.php'), get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            $record = (object) [
                'teacherid'    => $data->teacherid,
                'amount'       => (float) $data->amount,
                'currency'     => (string) $data->currency,
                'method'       => (string) $data->method,
                'reference'    => (string) ($data->reference ?? ''),
                'notes'        => (string) ($data->notes ?? ''),
                'paidat'       => (int) $data->paidat,
                'usermodified' => $USER->id,
                'timecreated'  => $now,
                'timemodified' => $now,
            ];

            $newid = $DB->insert_record('local_walletrevenue_payout', $record);

            file_save_draft_area_files(
                $data->proof_filemanager,
                $context->id,
                'local_walletrevenue',
                'payout_proof',
                $newid,
                ['subdirs' => 0, 'maxfiles' => 5]
            );

            $event = payout_created::create([
                'context'       => $context,
                'objectid'      => $newid,
                'relateduserid' => $data->teacherid,
            ]);
            $event->trigger();

            redirect(new moodle_url('/local/walletrevenue/payouts.php'), get_string('payoutrecorded', 'local_walletrevenue'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }

    echo $OUTPUT->header();
    echo ui_helper::render_tabs('payouts');
    echo $OUTPUT->heading($id > 0 ? get_string('editpayout', 'local_walletrevenue') : get_string('recordpayout', 'local_walletrevenue'), 3);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// Standard Payouts List View.
echo $OUTPUT->header();
echo ui_helper::render_tabs('payouts');

echo html_writer::div(
    html_writer::link(new moodle_url('/local/walletrevenue/payouts.php', ['action' => 'add']), '+ ' . get_string('recordpayout', 'local_walletrevenue'), ['class' => 'btn btn-primary mb-3']),
    'text-end mb-2'
);

$sql = "SELECT p.*, u.firstname, u.lastname, u.email
          FROM {local_walletrevenue_payout} p
          JOIN {user} u ON u.id = p.teacherid
      ORDER BY p.paidat DESC";

$payouts = $DB->get_records_sql($sql);

$table = new html_table();
$table->head = [
    get_string('teacher', 'local_walletrevenue'),
    get_string('payoutamount', 'local_walletrevenue'),
    get_string('currency', 'local_walletrevenue'),
    get_string('paymentmethod', 'local_walletrevenue'),
    get_string('paymentreference', 'local_walletrevenue'),
    get_string('paidat', 'local_walletrevenue'),
    get_string('paymentproof', 'local_walletrevenue'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable';

$fs = get_file_storage();

if (empty($payouts)) {
    echo $OUTPUT->notification(get_string('none'), \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($payouts as $p) {
        $teacheruser = \core_user::get_user($p->teacherid);
        $teacherdisplay = ($teacheruser ? fullname($teacheruser) : '#' . $p->teacherid) .
            '<br><small class="text-muted">' . s($p->email) . '</small>';

        // Check for attached proof files.
        $files = $fs->get_area_files($context->id, 'local_walletrevenue', 'payout_proof', $p->id, 'filename', false);
        $prooflinks = [];
        foreach ($files as $file) {
            $fileurl = moodle_url::make_pluginfile_url(
                $context->id,
                'local_walletrevenue',
                'payout_proof',
                $p->id,
                '/',
                $file->get_filename(),
                true
            );
            $prooflinks[] = html_writer::link($fileurl, '📎 ' . s($file->get_filename()), ['target' => '_blank', 'class' => 'd-block text-truncate', 'style' => 'max-width:180px;']);
        }
        $proofdisplay = !empty($prooflinks) ? implode('', $prooflinks) : '<span class="text-muted">' . get_string('noreceipt', 'local_walletrevenue') . '</span>';

        $editurl = new moodle_url('/local/walletrevenue/payouts.php', ['action' => 'edit', 'id' => $p->id]);
        $deleteurl = new moodle_url('/local/walletrevenue/payouts.php', ['action' => 'delete', 'id' => $p->id, 'sesskey' => sesskey()]);

        $actions = html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-sm btn-outline-secondary me-1']) .
                   html_writer::link($deleteurl, get_string('delete'), [
                       'class'   => 'btn btn-sm btn-outline-danger',
                       'onclick' => 'return confirm("' . get_string('confirmpayoutdelete', 'local_walletrevenue') . '");',
                   ]);

        $table->data[] = [
            $teacherdisplay,
            '<strong>' . number_format($p->amount, 2) . '</strong>',
            s($p->currency),
            s($p->method),
            s($p->reference),
            userdate($p->paidat),
            $proofdisplay,
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
