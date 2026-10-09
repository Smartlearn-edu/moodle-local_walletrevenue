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
 * CLI script to backfill historical wallet transactions created before plugin installation.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_walletrevenue\local\sale_sync;

[$options, $unrecognized] = cli_get_params([
    'help'    => false,
    'execute' => false,
], [
    'h' => 'help',
]);

if ($options['help']) {
    $help = <<<EOF
CLI tool to import historical enrol_wallet transactions created prior to local_walletrevenue installation.

Options:
 -h, --help            Print out this help.
 --execute             Execute the backfill. Without this flag, script only previews.

Example:
\$ php local/walletrevenue/cli/backfill.php --execute

EOF;
    echo $help;
    exit(0);
}

cli_heading('local_walletrevenue Historical Data Backfill');

if (!$options['execute']) {
    cli_writeln("Dry run mode: Specify --execute to import historical transactions.");
    exit(0);
}

cli_writeln("Starting historical backfill starting from transaction ID 0...");
$count = sale_sync::sync_all_pending(0);
cli_writeln("Backfill complete. Imported {$count} transaction record(s).");
