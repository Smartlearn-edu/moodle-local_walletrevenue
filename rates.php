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
 * Commission rates management page.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_walletrevenue\local\rate_resolver;
use local_walletrevenue\local\ui_helper;
use local_walletrevenue\form\rate_form;

require_login();
$context = context_system::instance();
require_capability('local/walletrevenue:manageconfig', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/walletrevenue/rates.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('rates', 'local_walletrevenue'));
$PAGE->set_heading(get_string('rates', 'local_walletrevenue'));

// Handle delete.
if ($action === 'delete' && $id > 0) {
    require_sesskey();
    $DB->delete_records('local_walletrevenue_rate', ['id' => $id]);
    redirect(new moodle_url('/local/walletrevenue/rates.php'), get_string('deleted'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle add override form.
$form = new rate_form();
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/walletrevenue/rates.php'));
} else if ($data = $form->get_data()) {
    rate_resolver::set_rate($data->scope, $data->itemid, (float) $data->percent, $USER->id);
    redirect(new moodle_url('/local/walletrevenue/rates.php'), get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo ui_helper::render_tabs('rates');

// Show global default box.
$globaldefault = rate_resolver::get_global_default();
echo $OUTPUT->box(
    '<strong>' . get_string('defaultrate', 'local_walletrevenue') . ':</strong> ' .
    $globaldefault . '% ' .
    html_writer::link(new moodle_url('/admin/settings.php?section=local_walletrevenue'), ' (' . get_string('edit') . ')'),
    'alert alert-info mb-4'
);

echo $OUTPUT->heading(get_string('addrateoverride', 'local_walletrevenue'), 4);
$form->display();

echo $OUTPUT->heading(get_string('rates', 'local_walletrevenue'), 4);

// List existing overrides.
$overrides = $DB->get_records('local_walletrevenue_rate', [], 'scope ASC, timecreated DESC');

$table = new html_table();
$table->head = [
    get_string('rate_scope', 'local_walletrevenue'),
    get_string('name'),
    get_string('rate_percent', 'local_walletrevenue'),
    get_string('timemodified'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable mt-3';

if (empty($overrides)) {
    echo $OUTPUT->notification(get_string('none'), \core\output\notification::NOTIFY_INFO);
} else {
    foreach ($overrides as $ov) {
        $name = '#' . $ov->itemid;
        if ($ov->scope === rate_resolver::SCOPE_COURSE) {
            $course = $DB->get_record('course', ['id' => $ov->itemid]);
            if ($course) {
                $name = format_string($course->fullname) . '<br><small class="text-muted">' . s($course->shortname) . '</small>';
            }
            $scopestr = get_string('rate_scope_course', 'local_walletrevenue');
        } else {
            $user = $DB->get_record('user', ['id' => $ov->itemid]);
            if ($user) {
                $name = fullname($user) . '<br><small class="text-muted">' . s($user->email) . '</small>';
            }
            $scopestr = get_string('rate_scope_teacher', 'local_walletrevenue');
        }

        $deleteurl = new moodle_url('/local/walletrevenue/rates.php', [
            'action'  => 'delete',
            'id'      => $ov->id,
            'sesskey' => sesskey(),
        ]);
        $deleteaction = html_writer::link(
            $deleteurl,
            get_string('delete'),
            ['class' => 'btn btn-sm btn-outline-danger', 'onclick' => 'return confirm("' . get_string('confirmdeleterate', 'local_walletrevenue') . '");']
        );

        $table->data[] = [
            $scopestr,
            $name,
            $ov->percent . '%',
            userdate($ov->timemodified ?: $ov->timecreated),
            $deleteaction,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
