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
 * Resolver for hierarchical commission rates (Course -> Teacher -> Global).
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_resolver {
    /** @var string Scope identifier for course overrides. */
    public const SCOPE_COURSE = 'course';

    /** @var string Scope identifier for teacher overrides. */
    public const SCOPE_TEACHER = 'teacher';

    /**
     * Resolve the commission percentage for a course and teacher.
     *
     * Hierarchy: Course override -> Teacher override -> Global setting.
     *
     * @param int $courseid Course ID.
     * @param int $teacherid Teacher user ID (0 if unassigned).
     * @return float The resolved commission percentage (e.g. 80.00).
     */
    public static function resolve(int $courseid, int $teacherid = 0): float {
        $info = self::get_rate_info($courseid, $teacherid);
        return $info['percent'];
    }

    /**
     * Get detailed information about the resolved commission rate, including source.
     *
     * @param int $courseid Course ID.
     * @param int $teacherid Teacher user ID.
     * @return array Array with keys: 'percent' (float) and 'source' ('course'|'teacher'|'global').
     */
    public static function get_rate_info(int $courseid, int $teacherid = 0): array {
        global $DB;

        // 1. Check Course Override.
        if ($courseid > 0) {
            $record = $DB->get_record('local_walletrevenue_rate', [
                'scope'  => self::SCOPE_COURSE,
                'itemid' => $courseid,
            ]);
            if ($record) {
                return [
                    'percent' => (float) $record->percent,
                    'source'  => self::SCOPE_COURSE,
                ];
            }
        }

        // 2. Check Teacher Override.
        if ($teacherid > 0) {
            $record = $DB->get_record('local_walletrevenue_rate', [
                'scope'  => self::SCOPE_TEACHER,
                'itemid' => $teacherid,
            ]);
            if ($record) {
                return [
                    'percent' => (float) $record->percent,
                    'source'  => self::SCOPE_TEACHER,
                ];
            }
        }

        // 3. Fallback to Global Default.
        return [
            'percent' => self::get_global_default(),
            'source'  => 'global',
        ];
    }

    /**
     * Get the global default commission rate from plugin configuration.
     *
     * @return float The global default rate (defaults to 80.00).
     */
    public static function get_global_default(): float {
        $rate = get_config('local_walletrevenue', 'defaultrate');
        if ($rate === false || $rate === null || $rate === '') {
            return 80.00;
        }
        return (float) $rate;
    }

    /**
     * Set or update a commission rate override.
     *
     * @param string $scope 'course' or 'teacher'.
     * @param int $itemid Course ID or Teacher user ID.
     * @param float $percent Commission percentage (0.00 to 100.00).
     * @param int $usermodified User ID making the change (default current $USER).
     * @return bool True on success.
     */
    public static function set_rate(string $scope, int $itemid, float $percent, int $usermodified = 0): bool {
        global $DB, $USER;

        if ($percent < 0.0 || $percent > 100.0) {
            throw new \coding_exception('Commission percentage must be between 0 and 100');
        }

        $now = time();
        $userid = $usermodified ?: ($USER->id ?? 0);

        $existing = $DB->get_record('local_walletrevenue_rate', [
            'scope'  => $scope,
            'itemid' => $itemid,
        ]);

        if ($existing) {
            $existing->percent = $percent;
            $existing->usermodified = $userid;
            $existing->timemodified = $now;
            return $DB->update_record('local_walletrevenue_rate', $existing);
        }

        $record = (object) [
            'scope'        => $scope,
            'itemid'       => $itemid,
            'percent'      => $percent,
            'usermodified' => $userid,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        return (bool) $DB->insert_record('local_walletrevenue_rate', $record);
    }

    /**
     * Delete a commission rate override.
     *
     * @param string $scope 'course' or 'teacher'.
     * @param int $itemid Course ID or Teacher user ID.
     * @return bool True on success.
     */
    public static function delete_rate(string $scope, int $itemid): bool {
        global $DB;
        return $DB->delete_records('local_walletrevenue_rate', [
            'scope'  => $scope,
            'itemid' => $itemid,
        ]);
    }
}
