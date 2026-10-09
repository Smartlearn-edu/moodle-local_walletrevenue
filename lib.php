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
 * Standard plugin library functions for local_walletrevenue.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serves plugin files (payout proof screenshots and receipts).
 *
 * @param stdClass $course Course settings or null.
 * @param stdClass $cm Course module settings or null.
 * @param context $context Context object.
 * @param string $filearea File area ('payout_proof').
 * @param array $args Extra arguments (itemid, filepath, filename).
 * @param bool $forcedownload Whether or not force download.
 * @param array $options Additional options affecting file serving.
 * @return bool False if file not found, does not return if found.
 */
function local_walletrevenue_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    require_login();

    if ($filearea !== 'payout_proof') {
        return false;
    }

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $payout = $DB->get_record('local_walletrevenue_payout', ['id' => $itemid]);
    if (!$payout) {
        return false;
    }

    // Permission check: Admin/manager or the specific payee teacher.
    $hasmanage = has_capability('local/walletrevenue:managepayouts', $context)
        || has_capability('local/walletrevenue:viewall', $context);
    $isteacher = ($USER->id == $payout->teacherid)
        && has_capability('local/walletrevenue:viewown', $context);

    if (!$hasmanage && !isteacher) {
        return false;
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_walletrevenue', 'payout_proof', $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
