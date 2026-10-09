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
 * Teacher personal revenue, balance, and payout statement portal.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_walletrevenue\local\calculator;

require_login();
$context = context_system::instance();
require_capability('local/walletrevenue:viewown', $context);

$PAGE->set_url(new moodle_url('/local/walletrevenue/my.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('myrevenue', 'local_walletrevenue'));
$PAGE->set_heading(get_string('myrevenue', 'local_walletrevenue'));

echo $OUTPUT->header();

$teacherid = (int) $USER->id;

// Check if current user is payee of any course or has recorded earnings.
$haspayeecourses = $DB->record_exists('local_walletrevenue_payee', ['teacherid' => $teacherid]);
$hassales = $DB->record_exists('local_walletrevenue_sale', ['teacherid' => $teacherid]);
$haspayouts = $DB->record_exists('local_walletrevenue_payout', ['teacherid' => $teacherid]);

if (!$haspayeecourses && !$hassales && !$haspayouts) {
    echo $OUTPUT->notification(get_string('none'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

// 1. Balance Overview Cards (per currency).
$summaries = calculator::get_teachers_summary(0, 0, $teacherid);

echo $OUTPUT->heading(get_string('myearnings', 'local_walletrevenue'), 3);

echo '<div class="row mb-4">';
foreach ($summaries as $s) {
    $balancecolor = $s->currentbalance > 0 ? 'border-success' : 'border-secondary';
    echo '<div class="col-md-4 mb-3">';
    echo '<div class="card shadow-sm ' . $balancecolor . '">';
    echo '<div class="card-body">';
    echo '<h5 class="card-title text-muted">' . s($s->currency) . ' Balance</h5>';
    echo '<h2 class="card-text text-success font-weight-bold">' . number_format($s->currentbalance, 2) . ' ' . s($s->currency) . '</h2>';
    echo '<hr>';
    echo '<div class="d-flex justify-content-between text-muted small">';
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

// 2. Monetized Courses Table.
echo $OUTPUT->heading(get_string('mycourses', 'local_walletrevenue'), 4);
$courses = calculator::get_teacher_courses($teacherid);

$coursetable = new html_table();
$coursetable->head = [
    get_string('course', 'local_walletrevenue'),
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
        $coursetable->data[] = [
            format_string($c->coursename) . ' (' . s($c->courseshortname) . ')',
            number_format($c->listprice, 2) . ' ' . s($c->currency),
            $c->studentcount,
            number_format($c->totalsales, 2) . ' ' . s($c->currency),
            $c->appliedrate . '%',
            '<strong>' . number_format($c->teachershare, 2) . ' ' . s($c->currency) . '</strong>',
        ];
    }
    echo html_writer::table($coursetable);
}

// 3. Payout History & Downloadable Receipts.
echo $OUTPUT->heading(get_string('mypayouts', 'local_walletrevenue'), 4);
$payouts = $DB->get_records('local_walletrevenue_payout', ['teacherid' => $teacherid], 'paidat DESC');

$payouttable = new html_table();
$payouttable->head = [
    get_string('paidat', 'local_walletrevenue'),
    get_string('payoutamount', 'local_walletrevenue'),
    get_string('currency', 'local_walletrevenue'),
    get_string('paymentmethod', 'local_walletrevenue'),
    get_string('paymentreference', 'local_walletrevenue'),
    get_string('paymentproof', 'local_walletrevenue'),
];
$payouttable->attributes['class'] = 'generaltable';

$fs = get_file_storage();

if (empty($payouts)) {
    echo $OUTPUT->notification(get_string('none'), \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($payouts as $p) {
        $files = $fs->get_area_files($context->id, 'local_walletrevenue', 'payout_proof', $p->id, 'filename', false);
        $receiptlinks = [];
        foreach ($files as $f) {
            $furl = moodle_url::make_pluginfile_url(
                $context->id,
                'local_walletrevenue',
                'payout_proof',
                $p->id,
                '/',
                $f->get_filename(),
                true
            );
            $receiptlinks[] = html_writer::link($furl, '📄 ' . s($f->get_filename()), ['target' => '_blank', 'class' => 'btn btn-sm btn-outline-primary mb-1 d-block text-truncate']);
        }

        $receiptdisplay = !empty($receiptlinks) ? implode('', $receiptlinks) : '<span class="text-muted">' . get_string('noreceipt', 'local_walletrevenue') . '</span>';

        $payouttable->data[] = [
            userdate($p->paidat),
            '<strong>' . number_format($p->amount, 2) . '</strong>',
            s($p->currency),
            s($p->method),
            s($p->reference),
            $receiptdisplay,
        ];
    }
    echo html_writer::table($payouttable);
}

// 4. Anonymized Recent Sales Ledger (GDPR privacy compliant).
echo $OUTPUT->heading(get_string('recordsale', 'local_walletrevenue'), 4);
$sales = $DB->get_records_select(
    'local_walletrevenue_sale',
    'teacherid = :tid',
    ['tid' => $teacherid],
    'timecreated DESC',
    'id, courseid, studentid, type, amount, teachershare, currency, timecreated',
    0,
    50
);

$salestable = new html_table();
$salestable->head = [
    get_string('date'),
    get_string('course', 'local_walletrevenue'),
    get_string('user'),
    get_string('totalsales', 'local_walletrevenue'),
    get_string('teachershare', 'local_walletrevenue'),
];
$salestable->attributes['class'] = 'generaltable';

if (empty($sales)) {
    echo $OUTPUT->notification(get_string('none'), \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($sales as $s) {
        $cname = '#' . $s->courseid;
        $course = $DB->get_record('course', ['id' => $s->courseid], 'fullname');
        if ($course) {
            $cname = format_string($course->fullname);
        }

        // Anonymized student representation for GDPR.
        $studentmask = get_string('anonymizedstudent', 'local_walletrevenue', $s->studentid);

        $salestable->data[] = [
            userdate($s->timecreated),
            $cname,
            $studentmask,
            number_format($s->amount, 2) . ' ' . s($s->currency),
            '<strong>' . number_format($s->teachershare, 2) . ' ' . s($s->currency) . '</strong>',
        ];
    }
    echo html_writer::table($salestable);
}

echo $OUTPUT->footer();
