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
 * Main revenue and payout summary report for administrators.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_walletrevenue\local\calculator;
use local_walletrevenue\local\ui_helper;

require_login();
$context = context_system::instance();
require_capability('local/walletrevenue:viewall', $context);

$view        = optional_param('view', 'summary', PARAM_ALPHA);
$teacherid   = optional_param('teacherid', 0, PARAM_INT);
$currency    = optional_param('currency', '', PARAM_ALPHANUM);
$source      = optional_param('source', '', PARAM_ALPHAEXT);
$timestart   = optional_param('timestart', 0, PARAM_INT);
$timeend     = optional_param('timeend', 0, PARAM_INT);

$url = new moodle_url('/local/walletrevenue/index.php');
if ($view !== 'summary') {
    $url->param('view', $view);
}
if ($teacherid) {
    $url->param('teacherid', $teacherid);
}
if ($currency) {
    $url->param('currency', $currency);
}
if (!empty($source)) {
    $url->param('source', $source);
}
if ($timestart) {
    $url->param('timestart', $timestart);
}
if ($timeend) {
    $url->param('timeend', $timeend);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reports', 'local_walletrevenue'));
$PAGE->set_heading(get_string('reports', 'local_walletrevenue'));

echo $OUTPUT->header();
echo ui_helper::render_tabs('reports');
echo ui_helper::render_unassigned_banner();

// Course breakdown drill-down view for a specific teacher.
if ($view === 'courses' && $teacherid >= 0) {
    $teachername = get_string('nopayeeassigned', 'local_walletrevenue');
    if ($teacherid > 0) {
        $u = \core_user::get_user($teacherid);
        if ($u) {
            $teachername = fullname($u);
        }
    }

    echo html_writer::div(
        html_writer::link(new moodle_url('/local/walletrevenue/index.php'), '← ' . get_string('back'), ['class' => 'btn btn-outline-secondary mb-3']),
        'mb-2'
    );
    echo $OUTPUT->heading(get_string('mycourses', 'local_walletrevenue') . ': ' . $teachername, 3);

    // Source filter buttons for course drill-down.
    $sourceoptions = [
        ''             => get_string('source_all', 'local_walletrevenue'),
        'wallet'       => get_string('source_wallet', 'local_walletrevenue'),
        'core_payment' => get_string('source_core_payment', 'local_walletrevenue'),
    ];
    echo html_writer::start_div('mb-3');
    echo html_writer::tag('span', get_string('source', 'local_walletrevenue') . ': ', ['class' => 'me-2 fw-bold text-muted']);
    echo html_writer::start_div('btn-group btn-group-sm', ['role' => 'group']);
    foreach ($sourceoptions as $optval => $optlabel) {
        $btnurl = new moodle_url($PAGE->url, ['source' => $optval]);
        $activeclass = ($source === $optval) ? 'btn-primary' : 'btn-outline-secondary';
        echo html_writer::link($btnurl, $optlabel, ['class' => 'btn ' . $activeclass]);
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    $courses = calculator::get_teacher_courses($teacherid, $currency, $timestart, $timeend, $source);

    $table = new html_table();
    $table->head = [
        get_string('course', 'local_walletrevenue'),
        get_string('listprice', 'local_walletrevenue'),
        get_string('paidstudents', 'local_walletrevenue'),
        get_string('totalsales', 'local_walletrevenue'),
        get_string('appliedrate', 'local_walletrevenue'),
        get_string('teachershare', 'local_walletrevenue'),
    ];
    $table->attributes['class'] = 'generaltable';

    if (empty($courses)) {
        echo $OUTPUT->notification(get_string('none'), \core\output\notification::NOTIFY_INFO);
    } else {
        foreach ($courses as $c) {
            $sourcestr = get_string_manager()->string_exists('ratesource_' . $c->ratesource, 'local_walletrevenue')
                ? get_string('ratesource_' . $c->ratesource, 'local_walletrevenue')
                : s($c->ratesource);
            $table->data[] = [
                format_string($c->coursename) . ' (' . s($c->courseshortname) . ')',
                number_format($c->listprice, 2) . ' ' . s($c->currency),
                $c->studentcount,
                number_format($c->totalsales, 2) . ' ' . s($c->currency),
                $c->appliedrate . '% <small class="text-muted">(' . $sourcestr . ')</small>',
                '<strong>' . number_format($c->teachershare, 2) . ' ' . s($c->currency) . '</strong>',
            ];
        }
        echo html_writer::table($table);
    }

    echo $OUTPUT->footer();
    exit;
}

// Main Teacher Summary Report.
echo $OUTPUT->heading(get_string('reports', 'local_walletrevenue'), 3);

// Source filter buttons for main summary.
$sourceoptions = [
    ''             => get_string('source_all', 'local_walletrevenue'),
    'wallet'       => get_string('source_wallet', 'local_walletrevenue'),
    'core_payment' => get_string('source_core_payment', 'local_walletrevenue'),
];
echo html_writer::start_div('mb-3');
echo html_writer::tag('span', get_string('source', 'local_walletrevenue') . ': ', ['class' => 'me-2 fw-bold text-muted']);
echo html_writer::start_div('btn-group btn-group-sm', ['role' => 'group']);
foreach ($sourceoptions as $optval => $optlabel) {
    $btnurl = new moodle_url($PAGE->url, ['source' => $optval]);
    $activeclass = ($source === $optval) ? 'btn-primary' : 'btn-outline-secondary';
    echo html_writer::link($btnurl, $optlabel, ['class' => 'btn ' . $activeclass]);
}
echo html_writer::end_div();
echo html_writer::end_div();

$summaries = calculator::get_teachers_summary($timestart, $timeend, $teacherid, $currency, $source);

$table = new html_table();
$table->head = [
    get_string('teacher', 'local_walletrevenue'),
    get_string('currency', 'local_walletrevenue'),
    get_string('totalsales', 'local_walletrevenue'),
    get_string('teachershare', 'local_walletrevenue'),
    get_string('transferred', 'local_walletrevenue'),
    get_string('balance', 'local_walletrevenue'),
    get_string('periodnetdelta', 'local_walletrevenue'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable';

if (empty($summaries)) {
    echo $OUTPUT->notification(get_string('none'), \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($summaries as $row) {
        $teacherdisplay = '<span class="badge bg-warning text-dark">' . get_string('nopayeeassigned', 'local_walletrevenue') . '</span>';
        if ($row->teacheruser) {
            $teacherdisplay = fullname($row->teacheruser) . '<br><small class="text-muted">' . s($row->teacheruser->email) . '</small>';
        }

        $balanceclass = $row->currentbalance > 0 ? 'text-success font-weight-bold' : '';
        $balancedisplay = '<span class="' . $balanceclass . '">' . number_format($row->currentbalance, 2) . ' ' . s($row->currency) . '</span>';

        // Action links.
        $actions = [];
        $viewurl = new moodle_url('/local/walletrevenue/index.php', [
            'view'      => 'courses',
            'teacherid' => $row->teacherid,
            'currency'  => $row->currency,
            'source'    => $source,
            'timestart' => $timestart,
            'timeend'   => $timeend,
        ]);
        $actions[] = html_writer::link($viewurl, get_string('viewcourses', 'local_walletrevenue'), ['class' => 'btn btn-sm btn-outline-info me-1']);

        if ($row->teacherid > 0) {
            $payouturl = new moodle_url('/local/walletrevenue/payouts.php', [
                'action'    => 'add',
                'teacherid' => $row->teacherid,
                'currency'  => $row->currency,
                'amount'    => max(0.0, $row->currentbalance),
            ]);
            $actions[] = html_writer::link($payouturl, get_string('recordpayout', 'local_walletrevenue'), ['class' => 'btn btn-sm btn-outline-success']);
        }

        $table->data[] = [
            $teacherdisplay,
            s($row->currency),
            number_format($row->totalsales, 2),
            number_format($row->teachershare, 2),
            number_format($row->transferred, 2),
            $balancedisplay,
            number_format($row->periodnetdelta, 2),
            implode(' ', $actions),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
