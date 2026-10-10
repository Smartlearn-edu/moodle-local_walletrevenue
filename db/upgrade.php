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
 * Upgrade code for local_walletrevenue.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_walletrevenue upgrade tasks between versions.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_walletrevenue_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026101004) {
        // Define table local_walletrevenue_partner to be created.
        $table = new xmldb_table('local_walletrevenue_partner');

        // Adding fields to table local_walletrevenue_partner.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('code', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_walletrevenue_partner.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_uniq', XMLDB_KEY_UNIQUE, ['userid']);

        // Adding indexes to table local_walletrevenue_partner.
        $table->add_index('code_idx', XMLDB_INDEX_NOTUNIQUE, ['code']);

        // Conditionally launch create table for local_walletrevenue_partner.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table local_walletrevenue_partner_teacher to be created.
        $teachertable = new xmldb_table('local_walletrevenue_partner_teacher');

        // Adding fields to table local_walletrevenue_partner_teacher.
        $teachertable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $teachertable->add_field('partnerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $teachertable->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $teachertable->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $teachertable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_walletrevenue_partner_teacher.
        $teachertable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $teachertable->add_key('teacherid_uniq', XMLDB_KEY_UNIQUE, ['teacherid']);

        // Adding indexes to table local_walletrevenue_partner_teacher.
        $teachertable->add_index('partnerid_idx', XMLDB_INDEX_NOTUNIQUE, ['partnerid']);

        // Conditionally launch create table for local_walletrevenue_partner_teacher.
        if (!$dbman->table_exists($teachertable)) {
            $dbman->create_table($teachertable);
        }

        // Define fields to be added to local_walletrevenue_payout.
        $payouttable = new xmldb_table('local_walletrevenue_payout');
        $fieldrecip = new xmldb_field('recipienttype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'teacher');
        if (!$dbman->field_exists($payouttable, $fieldrecip)) {
            $dbman->add_field($payouttable, $fieldrecip);
        }

        $fieldpartner = new xmldb_field('partnerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($payouttable, $fieldpartner)) {
            $dbman->add_field($payouttable, $fieldpartner);
        }

        $indexpartner = new xmldb_index('partnerid_idx', XMLDB_INDEX_NOTUNIQUE, ['partnerid']);
        if (!$dbman->index_exists($payouttable, $indexpartner)) {
            $dbman->add_index($payouttable, $indexpartner);
        }

        // Walletrevenue savepoint reached.
        upgrade_plugin_savepoint(true, 2026101004, 'local', 'walletrevenue');
    }

    if ($oldversion < 2026101006) {
        $table = new xmldb_table('local_walletrevenue_sale');

        // Add source field.
        $fieldsource = new xmldb_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'wallet');
        if (!$dbman->field_exists($table, $fieldsource)) {
            $dbman->add_field($table, $fieldsource);
        }

        // Add gateway field.
        $fieldgateway = new xmldb_field('gateway', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'wallet');
        if (!$dbman->field_exists($table, $fieldgateway)) {
            $dbman->add_field($table, $fieldgateway);
        }

        // Drop old unique key on transactionid if it exists.
        $oldkey = new xmldb_key('transactionid_uniq', XMLDB_KEY_UNIQUE, ['transactionid']);
        if ($dbman->key_exists($table, $oldkey)) {
            $dbman->drop_key($table, $oldkey);
        }

        // Add new compound unique key on (source, transactionid).
        $newkey = new xmldb_key('source_tx_uniq', XMLDB_KEY_UNIQUE, ['source', 'transactionid']);
        if (!$dbman->key_exists($table, $newkey)) {
            $dbman->add_key($table, $newkey);
        }

        // Add index on source.
        $indexsource = new xmldb_index('source_idx', XMLDB_INDEX_NOTUNIQUE, ['source']);
        if (!$dbman->index_exists($table, $indexsource)) {
            $dbman->add_index($table, $indexsource);
        }

        // Walletrevenue savepoint reached.
        upgrade_plugin_savepoint(true, 2026101006, 'local', 'walletrevenue');
    }

    return true;
}
