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
 * Partner and agency management page for administrators.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_walletrevenue\form\partner_assign_form;
use local_walletrevenue\form\partner_form;
use local_walletrevenue\local\calculator;
use local_walletrevenue\local\partner_manager;
use local_walletrevenue\local\ui_helper;

require_login();
$context = context_system::instance();
require_capability('local/walletrevenue:manageconfig', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
if ($id <= 0) {
    $id = optional_param('partnerid', 0, PARAM_INT);
}

// Normalize action if posted from form.
if ($action === 'save') {
    $action = $id > 0 ? 'edit' : 'add';
} else if ($action === 'saveassign') {
    $action = 'assign';
}

$pageurl = new moodle_url('/local/walletrevenue/partners.php');
$url = new moodle_url('/local/walletrevenue/partners.php');
if ($action !== '') {
    $url->param('action', $action);
}
if ($id > 0) {
    $url->param('id', $id);
}
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('partners', 'local_walletrevenue'));
$PAGE->set_heading(get_string('partners', 'local_walletrevenue'));

// Helper query to load eligible teachers.
$userfields = \core_user\fields::for_name()->get_sql('u')->selects;
$teachersql = "SELECT DISTINCT u.id, u.email {$userfields}
                 FROM {user} u
                WHERE u.deleted = 0
                  AND u.id <> :guestid
                  AND (
                      EXISTS (
                          SELECT 1
                            FROM {local_walletrevenue_sale} s
                           WHERE s.teacherid = u.id
                      )
                      OR EXISTS (
                          SELECT 1
                            FROM {role_assignments} ra
                            JOIN {context} ctx ON ctx.id = ra.contextid
                            JOIN {role} r ON r.id = ra.roleid
                           WHERE ra.userid = u.id
                             AND ctx.contextlevel = " . CONTEXT_COURSE . "
                             AND r.archetype IN ('editingteacher', 'teacher')
                      )
                  )
             ORDER BY u.firstname, u.lastname";
$eligibleteachers = $DB->get_records_sql($teachersql, ['guestid' => (int) $CFG->siteguest]);

// All active non-guest users for partner accounts.
$alluserssql = "SELECT DISTINCT u.id, u.email {$userfields}
                  FROM {user} u
                 WHERE u.deleted = 0
                   AND u.id <> :guestid
              ORDER BY u.firstname, u.lastname";
$allusers = $DB->get_records_sql($alluserssql, ['guestid' => (int) $CFG->siteguest], 0, 500);

// Handle Sync from profile fields.
if ($action === 'sync') {
    require_sesskey();
    partner_manager::sync_from_profile_fields();
    redirect($pageurl, get_string('syncsuccess', 'local_walletrevenue'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle Delete.
if ($action === 'delete' && $id > 0) {
    require_sesskey();
    partner_manager::delete_partner($id);
    redirect($pageurl, get_string('partnerdeleted', 'local_walletrevenue'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle Add / Edit form.
if ($action === 'add' || $action === 'edit') {
    $existing = null;
    if ($action === 'edit' && $id > 0) {
        $existing = partner_manager::get_partner($id);
        if (!$existing) {
            throw new moodle_exception('invalidpartnerid', 'local_walletrevenue');
        }
    }

    $formurl = new moodle_url('/local/walletrevenue/partners.php', ['action' => $action]);
    if ($id > 0) {
        $formurl->param('id', $id);
    }
    $form = new partner_form($formurl, [
        'users'  => $allusers,
        'action' => $action,
        'id'     => $id,
    ]);
    if ($existing) {
        $form->set_data($existing);
    }

    if ($form->is_cancelled()) {
        redirect($pageurl);
    } else if ($data = $form->get_data()) {
        $targetid = (int) ($data->id ?? $id);
        if ($targetid > 0) {
            partner_manager::update_partner($targetid, $data->name, (int) $data->userid, $data->code ?? '');
            $msg = get_string('partnerupdated', 'local_walletrevenue');
        } else {
            partner_manager::create_partner($data->name, (int) $data->userid, $data->code ?? '');
            $msg = get_string('partnercreated', 'local_walletrevenue');
        }
        redirect($pageurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo ui_helper::render_tabs('partners');
    $title = $existing
        ? get_string('editpartner', 'local_walletrevenue') . ': ' . s($existing->name)
        : get_string('addpartner', 'local_walletrevenue');
    echo $OUTPUT->heading($title, 3);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// Handle Assign Teachers.
if ($action === 'assign' && $id > 0) {
    $partner = partner_manager::get_partner($id);
    if (!$partner) {
        throw new moodle_exception('invalidpartnerid', 'local_walletrevenue');
    }

    $formurl = new moodle_url('/local/walletrevenue/partners.php', ['action' => 'assign', 'id' => $id]);
    $form = new partner_assign_form($formurl, [
        'partnerid' => $id,
        'teachers'  => $eligibleteachers,
        'action'    => 'assign',
    ]);

    $currentteachers = partner_manager::get_teacher_ids_for_partner($id);
    $form->set_data([
        'partnerid' => $id,
        'teachers'  => $currentteachers,
    ]);

    if ($form->is_cancelled()) {
        redirect($pageurl);
    } else if ($data = $form->get_data()) {
        $assigned = !empty($data->teachers) ? (array) $data->teachers : [];
        partner_manager::set_partner_teachers($id, $assigned);
        redirect(
            $pageurl,
            get_string('teachersassignedsuccess', 'local_walletrevenue'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    echo ui_helper::render_tabs('partners');
    echo $OUTPUT->heading(get_string('assignteacherstopartner', 'local_walletrevenue') . ': ' . s($partner->name), 3);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// Default View: List Partners.
echo $OUTPUT->header();
echo ui_helper::render_tabs('partners');
echo ui_helper::render_unassigned_banner();

// Top Action Bar.
$addurl = new moodle_url('/local/walletrevenue/partners.php', ['action' => 'add']);
$syncurl = new moodle_url('/local/walletrevenue/partners.php', ['action' => 'sync', 'sesskey' => sesskey()]);

$actionbar = html_writer::div(
    html_writer::link($addurl, '+ ' . get_string('addpartner', 'local_walletrevenue'), ['class' => 'btn btn-primary me-2']) .
    html_writer::link($syncurl, get_string('syncfromprofiles', 'local_walletrevenue'), ['class' => 'btn btn-outline-secondary']),
    'd-flex justify-content-between align-items-center mb-3'
);
echo $actionbar;

$partners = partner_manager::get_all_partners();

$table = new html_table();
$table->head = [
    get_string('partnername', 'local_walletrevenue'),
    get_string('partneruser', 'local_walletrevenue'),
    get_string('partnercode', 'local_walletrevenue'),
    get_string('assignedteachers', 'local_walletrevenue'),
    get_string('balance', 'local_walletrevenue'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable';

if (empty($partners)) {
    echo $OUTPUT->notification(get_string('nopartnersfound', 'local_walletrevenue'), \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($partners as $p) {
        $partneruser = \core_user::get_user($p->userid);
        $userdisplay = $partneruser
            ? fullname($partneruser) . '<br><small class="text-muted">' . s($partneruser->email) . '</small>'
            : '#' . $p->userid;

        $tids = partner_manager::get_teacher_ids_for_partner($p->id);
        $tcount = count($tids);
        $teachernames = [];
        foreach ($tids as $tid) {
            $u = \core_user::get_user($tid);
            if ($u) {
                $teachernames[] = s(fullname($u));
            }
        }
        $teacherdisplay = '<strong>' . $tcount . ' ' . get_string('teachers', 'local_walletrevenue') . '</strong>';
        if (!empty($teachernames)) {
            $preview = array_slice($teachernames, 0, 3);
            $more = count($teachernames) > 3 ? ' +' . (count($teachernames) - 3) . ' ' . get_string('more') : '';
            $teacherdisplay .= '<br><small class="text-muted">' . implode(', ', $preview) . $more . '</small>';
        }

        // Summary balances per currency.
        $summaries = calculator::get_partner_summary($p->id);
        $balancelines = [];
        if (empty($summaries)) {
            $balancelines[] = '<span class="text-muted">0.00</span>';
        } else {
            foreach ($summaries as $s) {
                $balclass = $s->currentbalance > 0 ? 'text-success font-weight-bold' : 'text-muted';
                $balancelines[] = '<span class="' . $balclass . '">' .
                    number_format($s->currentbalance, 2) . ' ' . s($s->currency) .
                    '</span> <small class="text-muted">(' . get_string('earned', 'local_walletrevenue') . ': ' .
                    number_format($s->lifetimeshare, 2) . ')</small>';
            }
        }
        $balancedisplay = implode('<br>', $balancelines);

        // Action links.
        $assignurl = new moodle_url('/local/walletrevenue/partners.php', ['action' => 'assign', 'id' => $p->id]);
        $viewurl = new moodle_url('/local/walletrevenue/partner.php', ['partnerid' => $p->id]);
        $payouturl = new moodle_url('/local/walletrevenue/payouts.php', [
            'action'        => 'add',
            'recipienttype' => 'partner',
            'partnerid'     => $p->id,
        ]);
        $editurl = new moodle_url('/local/walletrevenue/partners.php', ['action' => 'edit', 'id' => $p->id]);
        $delurl = new moodle_url('/local/walletrevenue/partners.php', [
            'action'  => 'delete',
            'id'      => $p->id,
            'sesskey' => sesskey(),
        ]);

        $actions = [
            html_writer::link($assignurl, get_string('assignteachers', 'local_walletrevenue'), ['class' => 'btn btn-sm btn-outline-primary me-1']),
            html_writer::link($viewurl, get_string('viewstatement', 'local_walletrevenue'), ['class' => 'btn btn-sm btn-outline-info me-1']),
            html_writer::link($payouturl, get_string('payout', 'local_walletrevenue'), ['class' => 'btn btn-sm btn-outline-success me-1']),
            html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-sm btn-outline-secondary me-1']),
            html_writer::link($delurl, get_string('delete'), [
                'class'   => 'btn btn-sm btn-outline-danger',
                'onclick' => 'return confirm("' . get_string('confirmdeletepartner', 'local_walletrevenue') . '");',
            ]),
        ];

        $table->data[] = [
            '<strong>' . s($p->name) . '</strong>',
            $userdisplay,
            '<code>' . s($p->code) . '</code>',
            $teacherdisplay,
            $balancedisplay,
            implode(' ', $actions),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
