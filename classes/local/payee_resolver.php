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

namespace local_walletrevenue\local;

use context_course;

/**
 * Resolver and manager for course teacher assignments.
 *
 * Automatically detects the course teacher from course enrolments,
 * while allowing optional manual overrides by the administrator.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payee_resolver {
    /**
     * Get the teacher ID for a course.
     *
     * First checks for an explicit manual override. If none is set,
     * it automatically detects the enrolled editing teacher of the course.
     *
     * @param int $courseid Course ID.
     * @return int Teacher user ID, or 0 if no teacher found.
     */
    public static function get_payee(int $courseid): int {
        $info = self::get_teacher_info($courseid);
        return $info['teacherid'];
    }

    /**
     * Get detailed teacher assignment info for a course.
     *
     * @param int $courseid Course ID.
     * @return array Array with keys: 'teacherid' (int) and 'source' ('manual'|'auto'|'none').
     */
    public static function get_teacher_info(int $courseid): array {
        global $DB;

        if ($courseid <= 0) {
            return ['teacherid' => 0, 'source' => 'none'];
        }

        // 1. Check for explicit manual override.
        $record = $DB->get_record('local_walletrevenue_payee', ['courseid' => $courseid]);
        if ($record && !empty($record->teacherid)) {
            return [
                'teacherid' => (int) $record->teacherid,
                'source'    => 'manual',
            ];
        }

        // 2. Automatically detect teacher from course enrolments.
        $autoid = self::auto_detect_teacher($courseid);
        if ($autoid > 0) {
            return [
                'teacherid' => $autoid,
                'source'    => 'auto',
            ];
        }

        return ['teacherid' => 0, 'source' => 'none'];
    }

    /**
     * Automatically detect the primary teacher enrolled in a course.
     *
     * @param int $courseid Course ID.
     * @return int Teacher user ID, or 0 if none found.
     */
    public static function auto_detect_teacher(int $courseid): int {
        global $DB, $CFG;

        if ($courseid <= 0) {
            return 0;
        }

        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return 0;
        }

        // 1. Check users with capability moodle/course:update (editing teachers).
        $teachers = get_enrolled_users($context, 'moodle/course:update', 0, 'u.id, u.firstname, u.lastname', 'u.id ASC');
        if (!empty($teachers)) {
            $first = reset($teachers);
            return (int) $first->id;
        }

        // 2. Check course contacts (configured roles in $CFG->coursecontact).
        if (!empty($CFG->coursecontact)) {
            $contactroles = explode(',', $CFG->coursecontact);
            [$insql, $params] = $DB->get_in_or_equal($contactroles, SQL_PARAMS_NAMED, 'r');
            $params['contextid'] = $context->id;

            $sql = "SELECT u.id
                      FROM {role_assignments} ra
                      JOIN {user} u ON u.id = ra.userid
                     WHERE ra.contextid = :contextid AND ra.roleid $insql AND u.deleted = 0
                  ORDER BY ra.id ASC";
            $contact = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
            if ($contact) {
                return (int) $contact->id;
            }
        }

        // 3. Fallback: check any user with teacher archetype roles in the course.
        $teacherroles = $DB->get_records_select('role', "archetype IN ('editingteacher', 'teacher')", null, '', 'id');
        if (!empty($teacherroles)) {
            $roleids = array_keys($teacherroles);
            [$rinsql, $rparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'ar');
            $rparams['contextid'] = $context->id;

            $sql = "SELECT u.id
                      FROM {role_assignments} ra
                      JOIN {user} u ON u.id = ra.userid
                     WHERE ra.contextid = :contextid AND ra.roleid $rinsql AND u.deleted = 0
                  ORDER BY ra.id ASC";
            $res = $DB->get_record_sql($sql, $rparams, IGNORE_MULTIPLE);
            if ($res) {
                return (int) $res->id;
            }
        }

        return 0;
    }

    /**
     * Assign or change the manual teacher override for a course.
     *
     * @param int $courseid Course ID.
     * @param int $teacherid Teacher user ID (0 to remove override and use auto-detect).
     * @param int $usermodified User ID making the change (default current $USER).
     * @return bool True on success.
     */
    public static function set_payee(int $courseid, int $teacherid, int $usermodified = 0): bool {
        global $DB, $USER;

        $now = time();
        $userid = $usermodified ?: ($USER->id ?? 0);

        $existing = $DB->get_record('local_walletrevenue_payee', ['courseid' => $courseid]);

        if ($existing) {
            $existing->teacherid = $teacherid;
            $existing->usermodified = $userid;
            $existing->timemodified = $now;
            $DB->update_record('local_walletrevenue_payee', $existing);
        } else {
            $record = (object) [
                'courseid'     => $courseid,
                'teacherid'    => $teacherid,
                'usermodified' => $userid,
                'timecreated'  => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('local_walletrevenue_payee', $record);
        }

        // Effective teacher is either the manual choice or auto-detected.
        $effective = $teacherid > 0 ? $teacherid : self::auto_detect_teacher($courseid);
        if ($effective > 0) {
            self::recalculate_unassigned_sales($courseid, $effective);
        }

        return true;
    }

    /**
     * Automatically scan and resolve any sales with teacherid = 0.
     *
     * @return int Number of resolved sales.
     */
    public static function resolve_all_unassigned_sales(): int {
        global $DB;

        $unassigned = $DB->get_records('local_walletrevenue_sale', ['teacherid' => 0]);
        if (empty($unassigned)) {
            return 0;
        }

        $resolved = 0;
        foreach ($unassigned as $sale) {
            $teacherid = self::get_payee((int) $sale->courseid);
            if ($teacherid > 0) {
                $rate = rate_resolver::resolve((int) $sale->courseid, $teacherid);
                $sale->teacherid = $teacherid;
                $sale->percent = $rate;
                $sale->teachershare = round(((float) $sale->amount * $rate) / 100.0, 4);
                $DB->update_record('local_walletrevenue_sale', $sale);
                $resolved++;
            }
        }

        return $resolved;
    }

    /**
     * Recalculate historical unassigned sales for a course.
     *
     * @param int $courseid Course ID.
     * @param int $teacherid Assigned teacher ID.
     * @return int Number of updated sales.
     */
    public static function recalculate_unassigned_sales(int $courseid, int $teacherid): int {
        global $DB;

        $unassigned = $DB->get_records('local_walletrevenue_sale', [
            'courseid'  => $courseid,
            'teacherid' => 0,
        ]);

        if (empty($unassigned)) {
            return 0;
        }

        $rate = rate_resolver::resolve($courseid, $teacherid);
        $updated = 0;

        foreach ($unassigned as $sale) {
            $sale->teacherid = $teacherid;
            $sale->percent = $rate;
            $sale->teachershare = round(((float) $sale->amount * $rate) / 100.0, 4);
            $DB->update_record('local_walletrevenue_sale', $sale);
            $updated++;
        }

        return $updated;
    }

    /**
     * Get count of courses that have recorded wallet sales but no teacher found.
     *
     * @return int Number of unassigned courses with sales.
     */
    public static function get_unassigned_courses_count(): int {
        global $DB;

        // Auto-heal any unassigned sales first.
        self::resolve_all_unassigned_sales();

        $sql = "SELECT COUNT(DISTINCT courseid)
                  FROM {local_walletrevenue_sale}
                 WHERE teacherid = 0";

        return (int) $DB->count_records_sql($sql);
    }

    /**
     * Get list of eligible teachers for a course.
     *
     * @param int $courseid Course ID.
     * @return array Array of user objects.
     */
    public static function get_eligible_teachers(int $courseid): array {
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }

        $userfields = 'u.id, u.email' . \core_user\fields::for_name()->get_sql('u')->selects;
        $teachers = get_enrolled_users($context, 'moodle/course:update', 0, $userfields);
        if (empty($teachers)) {
            $teachers = get_enrolled_users($context, '', 0, $userfields);
        }

        return $teachers;
    }
}
