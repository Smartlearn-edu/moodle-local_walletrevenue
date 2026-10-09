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
 * Course payee management page.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_walletrevenue\local\payee_resolver;
use local_walletrevenue\local\rate_resolver;
use local_walletrevenue\local\ui_helper;
use local_walletrevenue\form\payee_form;

require_login();
$context = context_system::instance();
require_capability('local/walletrevenue:manageconfig', $context);

$courseid = optional_param('courseid', 0, PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHA);

$url = new moodle_url('/local/walletrevenue/payees.php');
if ($courseid) {
    $url->param('courseid', $courseid);
}
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('payees', 'local_walletrevenue'));
$PAGE->set_heading(get_string('payees', 'local_walletrevenue'));

// Handle form processing if assigning payee.
if ($courseid && $action === 'edit') {
    $course = get_course($courseid);
    $teachers = payee_resolver::get_eligible_teachers($courseid);
    $currentteacher = payee_resolver::get_payee($courseid);

    $form = new payee_form(null, ['courseid' => $courseid, 'teachers' => $teachers]);
    $form->set_data(['courseid' => $courseid, 'teacherid' => $currentteacher]);

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/walletrevenue/payees.php'));
    } else if ($data = $form->get_data()) {
        payee_resolver::set_payee($data->courseid, $data->teacherid, $USER->id);
        redirect(new moodle_url('/local/walletrevenue/payees.php'), get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo ui_helper::render_tabs('payees');
    echo $OUTPUT->heading(get_string('assignpayee', 'local_walletrevenue') . ': ' . format_string($course->fullname), 3);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo ui_helper::render_tabs('payees');
echo ui_helper::render_unassigned_banner();

// Query all courses with wallet enrolment instances or sales.
$sql = "SELECT DISTINCT c.id, c.fullname, c.shortname, p.teacherid, u.firstname, u.lastname, u.email
          FROM {course} c
     LEFT JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'wallet')
     LEFT JOIN {local_walletrevenue_sale} s ON s.courseid = c.id
     LEFT JOIN {local_walletrevenue_payee} p ON p.courseid = c.id
     LEFT JOIN {user} u ON u.id = p.teacherid
         WHERE (e.id IS NOT NULL OR s.id IS NOT NULL) AND c.id <> :siteid
      ORDER BY c.fullname ASC";

$courses = $DB->get_records_sql($sql, ['siteid' => SITEID]);

$table = new html_table();
$table->head = [
    get_string('course', 'local_walletrevenue'),
    get_string('shortnamecourse'),
    get_string('payeeteacher', 'local_walletrevenue'),
    get_string('appliedrate', 'local_walletrevenue'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable';

if (empty($courses)) {
    echo $OUTPUT->notification(get_string('nocoursesfound', 'moodle'), \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($courses as $c) {
        $teacherid = (int) ($c->teacherid ?? 0);
        if ($teacherid > 0) {
            $userobj = (object) ['id' => $teacherid, 'firstname' => $c->firstname, 'lastname' => $c->lastname, 'email' => $c->email];
            $teacherdisplay = fullname($userobj) . ' (' . s($c->email) . ')';
        } else {
            $teacherdisplay = '<span class="badge bg-warning text-dark">' . get_string('nopayeeassigned', 'local_walletrevenue') . '</span>';
        }

        $rateinfo = rate_resolver::get_rate_info($c->id, $teacherid);
        $ratedisplay = $rateinfo['percent'] . '% <small class="text-muted">(' . $rateinfo['source'] . ')</small>';

        $editurl = new moodle_url('/local/walletrevenue/payees.php', [
            'courseid' => $c->id,
            'action'   => 'edit',
        ]);
        $actions = html_writer::link($editurl, get_string('assignpayee', 'local_walletrevenue'), ['class' => 'btn btn-sm btn-outline-primary']);

        $table->data[] = [
            format_string($c->fullname),
            s($c->shortname),
            $teacherdisplay,
            $ratedisplay,
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
