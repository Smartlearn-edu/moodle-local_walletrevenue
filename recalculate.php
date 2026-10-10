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
 * Commission rate recalculation tool.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_walletrevenue\local\rate_resolver;
use local_walletrevenue\local\ui_helper;
use local_walletrevenue\form\recalculate_form;
use local_walletrevenue\event\commission_recalculated;

require_login();
$context = context_system::instance();
require_capability('local/walletrevenue:recalculate', $context);

$PAGE->set_url(new moodle_url('/local/walletrevenue/recalculate.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('recalculatetool', 'local_walletrevenue'));
$PAGE->set_heading(get_string('recalculatetool', 'local_walletrevenue'));

$form = new recalculate_form();

echo $OUTPUT->header();
echo ui_helper::render_tabs('recalculate');
echo $OUTPUT->heading(get_string('recalculatetool', 'local_walletrevenue'), 3);

if ($data = $form->get_data()) {
    $where = ["type = 'sale'"];
    $params = [];

    if (!empty($data->courseid)) {
        $where[] = "courseid = :cid";
        $params['cid'] = $data->courseid;
    }
    if (!empty($data->teacherid)) {
        $where[] = "teacherid = :tid";
        $params['tid'] = $data->teacherid;
    }
    if (!empty($data->timefrom)) {
        $where[] = "timecreated >= :tfrom";
        $params['tfrom'] = $data->timefrom;
    }
    if (!empty($data->timeto)) {
        $where[] = "timecreated <= :tto";
        $params['tto'] = $data->timeto;
    }

    $sales = $DB->get_records_select('local_walletrevenue_sale', implode(' AND ', $where), $params, 'timecreated DESC');

    $previewrows = [];
    $totaldiff = 0.0;
    $updatedcount = 0;

    foreach ($sales as $s) {
        $newrate = rate_resolver::resolve((int) $s->courseid, (int) $s->teacherid);
        $newshare = round(((float) $s->amount * $newrate) / 100.0, 4);
        $diff = round($newshare - (float) $s->teachershare, 4);

        if (abs($diff) > 0.0001 || abs($newrate - (float) $s->percent) > 0.01) {
            $previewrows[] = [
                'sale'     => $s,
                'oldrate'  => (float) $s->percent,
                'newrate'  => $newrate,
                'oldshare' => (float) $s->teachershare,
                'newshare' => $newshare,
                'diff'     => $diff,
            ];
            $totaldiff += $diff;

            if (empty($data->previewonly)) {
                $s->percent = $newrate;
                $s->teachershare = $newshare;
                $DB->update_record('local_walletrevenue_sale', $s);
                $updatedcount++;
            }
        }
    }

    if (!empty($data->previewonly)) {
        echo $OUTPUT->heading(get_string('previewdiff', 'local_walletrevenue'), 4);

        if (empty($previewrows)) {
            echo $OUTPUT->notification(get_string('none'), \core\output\notification::NOTIFY_INFO);
        } else {
            $table = new html_table();
            $table->head = [
                get_string('date'),
                get_string('course', 'local_walletrevenue'),
                get_string('amount', 'local_walletrevenue'),
                get_string('oldrate', 'local_walletrevenue'),
                get_string('newrate', 'local_walletrevenue'),
                get_string('oldshare', 'local_walletrevenue'),
                get_string('newshare', 'local_walletrevenue'),
                get_string('difference', 'local_walletrevenue'),
            ];
            $table->attributes['class'] = 'generaltable';

            foreach ($previewrows as $r) {
                $table->data[] = [
                    userdate($r['sale']->timecreated),
                    '#' . $r['sale']->courseid,
                    number_format($r['sale']->amount, 2),
                    $r['oldrate'] . '%',
                    '<strong>' . $r['newrate'] . '%</strong>',
                    number_format($r['oldshare'], 2),
                    number_format($r['newshare'], 2),
                    ($r['diff'] >= 0 ? '+' : '') . number_format($r['diff'], 2),
                ];
            }
            echo html_writer::table($table);

            $impactformatted = ($totaldiff >= 0 ? '+' : '') . number_format($totaldiff, 2);
            echo $OUTPUT->box(
                '<strong>' . get_string('totalnetimpact', 'local_walletrevenue', $impactformatted) . '</strong>',
                'alert alert-warning mb-3'
            );
        }
    } else {
        $event = commission_recalculated::create([
            'context' => $context,
            'other'   => ['updatedcount' => $updatedcount, 'totaldiff' => $totaldiff],
        ]);
        $event->trigger();

        echo $OUTPUT->notification(
            get_string('recalculatesuccess', 'local_walletrevenue', $updatedcount),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$form->display();
echo $OUTPUT->footer();
