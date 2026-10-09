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

/**
 * Resolver and manager for course payee teacher assignments.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payee_resolver {
    /**
     * Get the assigned payee teacher ID for a course.
     *
     * @param int $courseid Course ID.
     * @return int Teacher user ID, or 0 if unassigned.
     */
    public static function get_payee(int $courseid): int {
        global $DB;
        $record = $DB->get_record('local_walletrevenue_payee', ['courseid' => $courseid]);
        return $record ? (int) $record->teacherid : 0;
    }

    /**
     * Assign or change the payee teacher for a course.
     *
     * Automatically retroactively recalculates any past unassigned sales (teacherid = 0)
     * for this course using the newly resolved commission rate hierarchy.
     *
     * @param int $courseid Course ID.
     * @param int $teacherid Teacher user ID (0 to unassign).
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

        // If a real teacher is assigned, recalculate past unassigned sales.
        if ($teacherid > 0) {
            self::recalculate_unassigned_sales($courseid, $teacherid);
        }

        return true;
    }

    /**
     * Recalculate historical unassigned sales for a course once a payee is designated.
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
     * Get count of courses that have recorded wallet sales but no payee teacher assigned.
     *
     * @return int Number of unassigned courses with sales.
     */
    public static function get_unassigned_courses_count(): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT courseid)
                  FROM {local_walletrevenue_sale}
                 WHERE teacherid = 0";

        return (int) $DB->count_records_sql($sql);
    }

    /**
     * Get list of eligible teachers for a course.
     *
     * Retrieves users enrolled with editingteacher or teacher role in the course context.
     *
     * @param int $courseid Course ID.
     * @return array Array of user objects (id, firstname, lastname, email).
     */
    public static function get_eligible_teachers(int $courseid): array {
        global $DB;

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }

        // Fetch users with capabilities in course context.
        $teachers = get_enrolled_users($context, 'moodle/course:update', 0, 'u.id, u.firstname, u.lastname, u.email');
        if (empty($teachers)) {
            $teachers = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email');
        }

        return $teachers;
    }
}
