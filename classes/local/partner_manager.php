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

use stdClass;

/**
 * Partner and agency management and synchronization engine.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class partner_manager {
    /**
     * Get a partner by ID.
     *
     * @param int $id Partner ID.
     * @return stdClass|null
     */
    public static function get_partner(int $id): ?stdClass {
        global $DB;

        $record = $DB->get_record('local_walletrevenue_partner', ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Get a partner by user ID.
     *
     * @param int $userid Moodle user ID.
     * @return stdClass|null
     */
    public static function get_partner_by_user(int $userid): ?stdClass {
        global $DB;

        $record = $DB->get_record('local_walletrevenue_partner', ['userid' => $userid]);
        return $record ?: null;
    }

    /**
     * Check if a user is registered as a partner.
     *
     * @param int $userid Moodle user ID.
     * @return bool
     */
    public static function is_partner(int $userid): bool {
        global $DB;

        return $DB->record_exists('local_walletrevenue_partner', ['userid' => $userid]);
    }

    /**
     * Get all partners.
     *
     * @return array Array of partner records.
     */
    public static function get_all_partners(): array {
        global $DB;

        return $DB->get_records('local_walletrevenue_partner', [], 'name ASC');
    }

    /**
     * Get partner assigned to a specific teacher.
     *
     * @param int $teacherid Moodle user ID of the teacher.
     * @return stdClass|null Partner record or null.
     */
    public static function get_partner_for_teacher(int $teacherid): ?stdClass {
        global $DB;

        $sql = "SELECT p.*
                  FROM {local_walletrevenue_partner} p
                  JOIN {local_walletrevenue_partner_teacher} pt ON pt.partnerid = p.id
                 WHERE pt.teacherid = :teacherid";

        $partner = $DB->get_record_sql($sql, ['teacherid' => $teacherid]);
        return $partner ?: null;
    }

    /**
     * Get all teacher IDs assigned to a partner.
     *
     * @param int $partnerid Partner ID.
     * @return array Array of teacher user IDs.
     */
    public static function get_teacher_ids_for_partner(int $partnerid): array {
        global $DB;

        return $DB->get_fieldset_select(
            'local_walletrevenue_partner_teacher',
            'teacherid',
            'partnerid = :pid',
            ['pid' => $partnerid]
        );
    }

    /**
     * Create a new partner.
     *
     * @param string $name Organization name.
     * @param int $userid Moodle user ID of the partner.
     * @param string $code Unique code/slug.
     * @return int Created partner ID.
     */
    public static function create_partner(string $name, int $userid, string $code = ''): int {
        global $DB, $USER;

        $now = time();
        $record = (object) [
            'name'         => trim($name),
            'userid'       => $userid,
            'code'         => trim($code) !== '' ? trim($code) : 'P' . $userid,
            'usermodified' => (int) $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        return (int) $DB->insert_record('local_walletrevenue_partner', $record);
    }

    /**
     * Update an existing partner.
     *
     * @param int $id Partner ID.
     * @param string $name Organization name.
     * @param int $userid Moodle user ID.
     * @param string $code Unique code/slug.
     * @return bool
     */
    public static function update_partner(int $id, string $name, int $userid, string $code = ''): bool {
        global $DB, $USER;

        $record = (object) [
            'id'           => $id,
            'name'         => trim($name),
            'userid'       => $userid,
            'code'         => trim($code) !== '' ? trim($code) : 'P' . $userid,
            'usermodified' => (int) $USER->id,
            'timemodified' => time(),
        ];

        return $DB->update_record('local_walletrevenue_partner', $record);
    }

    /**
     * Delete a partner and unassign all affiliated teachers.
     *
     * @param int $id Partner ID.
     * @return bool
     */
    public static function delete_partner(int $id): bool {
        global $DB;

        $teacherids = self::get_teacher_ids_for_partner($id);
        foreach ($teacherids as $tid) {
            self::unassign_teacher((int) $tid);
        }

        return $DB->delete_records('local_walletrevenue_partner', ['id' => $id]);
    }

    /**
     * Assign a teacher to a partner.
     *
     * @param int $partnerid Partner ID.
     * @param int $teacherid Teacher user ID.
     * @return bool
     */
    public static function assign_teacher(int $partnerid, int $teacherid): bool {
        global $DB, $USER;

        $partner = self::get_partner($partnerid);
        if (!$partner) {
            return false;
        }

        $existing = $DB->get_record('local_walletrevenue_partner_teacher', ['teacherid' => $teacherid]);
        if ($existing) {
            if ((int) $existing->partnerid === $partnerid) {
                return true;
            }
            $existing->partnerid = $partnerid;
            $existing->usermodified = (int) $USER->id;
            $DB->update_record('local_walletrevenue_partner_teacher', $existing);
        } else {
            $record = (object) [
                'partnerid'    => $partnerid,
                'teacherid'    => $teacherid,
                'usermodified' => (int) $USER->id,
                'timecreated'  => time(),
            ];
            $DB->insert_record('local_walletrevenue_partner_teacher', $record);
        }

        // Sync with Moodle custom profile field.
        self::update_teacher_profile_field($teacherid, $partner->name);

        return true;
    }

    /**
     * Unassign a teacher from any partner.
     *
     * @param int $teacherid Teacher user ID.
     * @return bool
     */
    public static function unassign_teacher(int $teacherid): bool {
        global $DB;

        $DB->delete_records('local_walletrevenue_partner_teacher', ['teacherid' => $teacherid]);
        self::update_teacher_profile_field($teacherid, '');
        return true;
    }

    /**
     * Set the exact list of teachers assigned to a partner.
     *
     * @param int $partnerid Partner ID.
     * @param array $teacherids Array of teacher user IDs.
     */
    public static function set_partner_teachers(int $partnerid, array $teacherids): void {
        $current = self::get_teacher_ids_for_partner($partnerid);
        $toassign = array_map('intval', $teacherids);

        // Remove teachers not in the new list.
        foreach ($current as $cid) {
            if (!in_array((int) $cid, $toassign, true)) {
                self::unassign_teacher((int) $cid);
            }
        }

        // Add new teachers.
        foreach ($toassign as $tid) {
            if ($tid > 0) {
                self::assign_teacher($partnerid, $tid);
            }
        }
    }

    /**
     * Get the configured shortname of the custom user profile field.
     *
     * @return string
     */
    public static function get_profile_field_shortname(): string {
        $shortname = get_config('local_walletrevenue', 'partner_profile_field');
        return !empty($shortname) ? trim($shortname) : 'walletrevenue_partner';
    }

    /**
     * Ensure the custom user profile field exists in Moodle.
     *
     * @return int Field ID in user_info_field.
     */
    public static function ensure_profile_field_exists(): int {
        global $DB;

        $shortname = self::get_profile_field_shortname();
        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
        if ($field) {
            return (int) $field->id;
        }

        // Ensure category exists.
        $cat = $DB->get_record('user_info_category', ['name' => 'Wallet Revenue']);
        if (!$cat) {
            $catid = $DB->insert_record('user_info_category', (object) [
                'name'      => 'Wallet Revenue',
                'sortorder' => 1,
            ]);
        } else {
            $catid = $cat->id;
        }

        // Create the profile field.
        $newfield = (object) [
            'shortname'   => $shortname,
            'name'        => get_string('partner_profile_field_label', 'local_walletrevenue'),
            'datatype'    => 'text',
            'description' => get_string('partner_profile_field_desc', 'local_walletrevenue'),
            'categoryid'  => $catid,
            'sortorder'   => 1,
            'required'    => 0,
            'locked'      => 0,
            'visible'     => 2, // Visible to teachers and admins.
            'forceunique' => 0,
            'signup'      => 0,
            'defaultdata' => '',
            'param1'      => 50,
            'param2'      => 255,
        ];

        return (int) $DB->insert_record('user_info_field', $newfield);
    }

    /**
     * Update the teacher's custom profile field value.
     *
     * @param int $teacherid Teacher user ID.
     * @param string $value Value to set (empty string to clear).
     */
    public static function update_teacher_profile_field(int $teacherid, string $value): void {
        global $DB;

        $fieldid = self::ensure_profile_field_exists();
        if ($fieldid <= 0) {
            return;
        }

        $existing = $DB->get_record('user_info_data', [
            'userid'  => $teacherid,
            'fieldid' => $fieldid,
        ]);

        if ($existing) {
            if ($existing->data !== $value) {
                $existing->data = $value;
                $DB->update_record('user_info_data', $existing);
            }
        } else if ($value !== '') {
            $record = (object) [
                'userid'  => $teacherid,
                'fieldid' => $fieldid,
                'data'    => $value,
            ];
            $DB->insert_record('user_info_data', $record);
        }
    }

    /**
     * Find a partner by name, code, ID, or user email/username.
     *
     * @param string $identifier Partner name, code, ID, or user email.
     * @return stdClass|null
     */
    public static function find_partner_by_identifier(string $identifier): ?stdClass {
        global $DB;

        $val = trim($identifier);
        if ($val === '') {
            return null;
        }

        // 1. Match by partner name (case-insensitive).
        $partner = $DB->get_record_select(
            'local_walletrevenue_partner',
            $DB->sql_equal('name', ':name', false),
            ['name' => $val]
        );
        if ($partner) {
            return $partner;
        }

        // 2. Match by partner code (case-insensitive).
        $partner = $DB->get_record_select(
            'local_walletrevenue_partner',
            $DB->sql_equal('code', ':code', false),
            ['code' => $val]
        );
        if ($partner) {
            return $partner;
        }

        // 3. Match by numeric ID.
        if (is_numeric($val)) {
            $partner = $DB->get_record('local_walletrevenue_partner', ['id' => (int) $val]);
            if ($partner) {
                return $partner;
            }
        }

        // 4. Match by partner user email or username (case-insensitive).
        $sql = "SELECT p.*
                  FROM {local_walletrevenue_partner} p
                  JOIN {user} u ON u.id = p.userid
                 WHERE " . $DB->sql_equal('u.email', ':email', false) . "
                    OR " . $DB->sql_equal('u.username', ':uname', false);
        $partner = $DB->get_record_sql($sql, ['email' => $val, 'uname' => $val]);
        if ($partner) {
            return $partner;
        }

        return null;
    }

    /**
     * Synchronize teacher assignments from Moodle user custom profile fields.
     *
     * @return array Array with counts: ['new' => int, 'total' => int]
     */
    public static function sync_from_profile_fields(): array {
        global $DB;

        $shortname = self::get_profile_field_shortname();
        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
        if (!$field) {
            return ['new' => 0, 'total' => 0];
        }

        $datas = $DB->get_records('user_info_data', ['fieldid' => $field->id]);
        if (empty($datas)) {
            return ['new' => 0, 'total' => 0];
        }

        $newcount = 0;
        $totalmatched = 0;

        foreach ($datas as $d) {
            $val = trim((string) ($d->data ?? ''));
            $teacherid = (int) $d->userid;

            // If profile field is cleared, remove existing assignment if any.
            if ($val === '') {
                $existing = $DB->get_record('local_walletrevenue_partner_teacher', [
                    'teacherid' => $teacherid,
                ]);
                if ($existing) {
                    self::unassign_teacher($teacherid);
                }
                continue;
            }

            $partner = self::find_partner_by_identifier($val);
            if (!$partner) {
                continue;
            }

            // A partner account is the organization owner/manager, not an affiliated teacher under themselves.
            if ($teacherid === (int) $partner->userid) {
                $existing = $DB->get_record('local_walletrevenue_partner_teacher', [
                    'teacherid' => $teacherid,
                    'partnerid' => (int) $partner->id,
                ]);
                if ($existing) {
                    self::unassign_teacher($teacherid);
                }
                continue;
            }

            $totalmatched++;
            $existing = $DB->get_record('local_walletrevenue_partner_teacher', [
                'teacherid' => $teacherid,
            ]);

            if (!$existing || (int) $existing->partnerid !== (int) $partner->id) {
                self::assign_teacher((int) $partner->id, $teacherid);
                $newcount++;
            }
        }

        return ['new' => $newcount, 'total' => $totalmatched];
    }
}
