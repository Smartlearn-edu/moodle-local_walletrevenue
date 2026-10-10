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
 * Partner and agency financial statement portal.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_walletrevenue\local\calculator;
use local_walletrevenue\local\partner_manager;

require_login();
if (isguestuser()) {
    throw new \moodle_exception('noguest');
}

$context = context_system::instance();
require_capability('local/walletrevenue:viewpartner', $context);

$partnerid = optional_param('partnerid', 0, PARAM_INT);
$canviewall = has_capability('local/walletrevenue:viewall', $context);

if ($canviewall && $partnerid > 0) {
    $partner = partner_manager::get_partner($partnerid);
} else {
    $partner = partner_manager::get_partner_by_user((int) $USER->id);
}

$PAGE->set_url(new moodle_url('/local/walletrevenue/partner.php', $partnerid > 0 ? ['partnerid' => $partnerid] : []));
$PAGE->set_context($context);

if (!$partner) {
    $PAGE->set_title(get_string('partnerportal', 'local_walletrevenue'));
    $PAGE->set_heading(get_string('partnerportal', 'local_walletrevenue'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notapartner', 'local_walletrevenue'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

$PAGE->set_title(get_string('partnerportal', 'local_walletrevenue') . ' - ' . s($partner->name));
$PAGE->set_heading(get_string('partnerportal', 'local_walletrevenue'));

echo $OUTPUT->header();

// Partner Header Banner.
$partneruser = \core_user::get_user($partner->userid);
$useremail = $partneruser ? s($partneruser->email) : '';

echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
echo html_writer::start_div();
echo html_writer::tag('h2', s($partner->name) . ' <small class="badge bg-info text-dark">' . s($partner->code) . '</small>', ['class' => 'mb-1']);
echo html_writer::tag('p', get_string('partneraccount', 'local_walletrevenue') . ': ' . $useremail, ['class' => 'text-muted mb-0']);
echo html_writer::end_div();

if ($canviewall) {
    echo html_writer::link(
        new moodle_url('/local/walletrevenue/partners.php'),
        '← ' . get_string('backtopartners', 'local_walletrevenue'),
        ['class' => 'btn btn-outline-secondary']
    );
}
echo html_writer::end_div();

// Informative Notice.
echo $OUTPUT->notification(get_string('partnerportalnotice', 'local_walletrevenue'), \core\output\notification::NOTIFY_INFO);

// 1. Balance Overview Cards (per currency).
$summaries = calculator::get_partner_summary($partner->id);
echo $OUTPUT->heading(get_string('myearnings', 'local_walletrevenue'), 4);

if (empty($summaries)) {
    echo $OUTPUT->notification(get_string('none'), \core\output\notification::NOTIFY_INFO);
} else {
    echo '<div class="row mb-4">';
    foreach ($summaries as $s) {
        $balancecolor = $s->currentbalance > 0 ? 'border-success' : 'border-secondary';
        echo '<div class="col-md-4 mb-3">';
        echo '<div class="card shadow-sm ' . $balancecolor . '">';
        echo '<div class="card-body">';
        echo '<h5 class="card-title text-muted">' . get_string('currencybalance', 'local_walletrevenue', s($s->currency)) . '</h5>';
        echo '<h2 class="card-text text-success font-weight-bold">' . number_format($s->currentbalance, 2) . ' ' . s($s->currency) . '</h2>';
        echo '<hr>';
        echo '<div class="d-flex justify-content-between text-muted small">';
        echo '<span>' . get_string('totalsales', 'local_walletrevenue') . ':</span>';
        echo '<strong>' . number_format($s->totalsales, 2) . ' ' . s($s->currency) . '</strong>';
        echo '</div>';
        echo '<div class="d-flex justify-content-between text-muted small mt-1">';
        echo '<span>' . get_string('teachershare', 'local_walletrevenue') . ':</span>';
        echo '<strong>' . number_format($s->lifetimeshare, 2) . ' ' . s($s->currency) . '</strong>';
        echo '</div>';
        echo '<div class="d-flex justify-content-between text-muted small mt-1">';
        echo '<span>' . get_string('transferred', 'local_walletrevenue') . ':</span>';
        echo '<strong>' . number_format($s->lifetimetransfer, 2) . ' ' . s($s->currency) . '</strong>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

// 2. Affiliated Teachers Breakdown Table.
echo $OUTPUT->heading(get_string('assignedteachers', 'local_walletrevenue'), 4);
$teachers = calculator::get_partner_teachers($partner->id);

$teachertable = new html_table();
$teachertable->head = [
    get_string('teacher', 'local_walletrevenue'),
    get_string('courses', 'local_walletrevenue'),
    get_string('paidstudents', 'local_walletrevenue'),
    get_string('totalsales', 'local_walletrevenue'),
    get_string('teachershare', 'local_walletrevenue'),
];
$teachertable->attributes['class'] = 'generaltable';

if (empty($teachers)) {
    echo $OUTPUT->notification(get_string('noteachersassigned', 'local_walletrevenue'), \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($teachers as $t) {
        $tuser = $t->teacheruser;
        $name = $tuser ? fullname($tuser) : '#' . $t->teacherid;
        $email = $tuser ? '<br><small class="text-muted">' . s($tuser->email) . '</small>' : '';

        $teachertable->data[] = [
            $name . $email,
            $t->coursecount,
            $t->studentcount,
            number_format($t->totalsales, 2) . ' ' . s($t->currency),
            '<strong>' . number_format($t->teachershare, 2) . ' ' . s($t->currency) . '</strong>',
        ];
    }
    echo html_writer::table($teachertable);
}

// 3. Monetized Courses Table.
echo $OUTPUT->heading(get_string('courses', 'local_walletrevenue'), 4);
$courses = calculator::get_partner_courses($partner->id);

$coursetable = new html_table();
$coursetable->head = [
    get_string('course', 'local_walletrevenue'),
    get_string('teacher', 'local_walletrevenue'),
    get_string('listprice', 'local_walletrevenue'),
    get_string('paidstudents', 'local_walletrevenue'),
    get_string('totalsales', 'local_walletrevenue'),
    get_string('appliedrate', 'local_walletrevenue'),
    get_string('teachershare', 'local_walletrevenue'),
];
$coursetable->attributes['class'] = 'generaltable';

if (empty($courses)) {
    echo $OUTPUT->notification(get_string('none'), \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($courses as $c) {
        $tuser = $c->teacheruser;
        $tname = $tuser ? fullname($tuser) : '#' . $c->teacherid;

        $coursetable->data[] = [
            format_string($c->coursename) . '<br><small class="text-muted">' . s($c->courseshortname) . '</small>',
            $tname,
            number_format($c->listprice, 2) . ' ' . s($c->currency),
            $c->studentcount,
            number_format($c->totalsales, 2) . ' ' . s($c->currency),
            $c->appliedrate . '%',
            '<strong>' . number_format($c->teachershare, 2) . ' ' . s($c->currency) . '</strong>',
        ];
    }
    echo html_writer::table($coursetable);
}

// 4. Partner Payout History & Downloadable Receipts.
echo $OUTPUT->heading(get_string('mypayouts', 'local_walletrevenue'), 4);
$payouts = $DB->get_records_select(
    'local_walletrevenue_payout',
    'partnerid = :pid AND recipienttype = :rt',
    ['pid' => $partner->id, 'rt' => 'partner'],
    'paidat DESC'
);

$payouttable = new html_table();
$payouttable->head = [
    get_string('paidat', 'local_walletrevenue'),
    get_string('payoutamount', 'local_walletrevenue'),
    get_string('currency', 'local_walletrevenue'),
    get_string('paymentmethod', 'local_walletrevenue'),
    get_string('paymentreference', 'local_walletrevenue'),
    get_string('paymentnotes', 'local_walletrevenue'),
    get_string('paymentproof', 'local_walletrevenue'),
];
$payouttable->attributes['class'] = 'generaltable';

$fs = get_file_storage();

if (empty($payouts)) {
    echo $OUTPUT->notification(get_string('none'), \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($payouts as $p) {
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
            $prooflinks[] = html_writer::link(
                $fileurl,
                '📎 ' . s($file->get_filename()),
                ['target' => '_blank', 'class' => 'd-block text-truncate', 'style' => 'max-width:180px;']
            );
        }
        $proofdisplay = !empty($prooflinks) ? implode('', $prooflinks) :
            '<span class="text-muted">' . get_string('noreceipt', 'local_walletrevenue') . '</span>';

        $methodstr = s($p->method);
        $methodlangkey = 'method_' . $p->method;
        if (get_string_manager()->string_exists($methodlangkey, 'local_walletrevenue')) {
            $methodstr = get_string($methodlangkey, 'local_walletrevenue');
        }

        $payouttable->data[] = [
            userdate($p->paidat),
            '<strong>' . number_format($p->amount, 2) . '</strong>',
            s($p->currency),
            $methodstr,
            s($p->reference),
            !empty($p->notes) ? s($p->notes) : '<span class="text-muted">-</span>',
            $proofdisplay,
        ];
    }
    echo html_writer::table($payouttable);
}

echo $OUTPUT->footer();
