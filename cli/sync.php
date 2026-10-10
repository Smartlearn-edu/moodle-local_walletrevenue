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
 * CLI script to manually trigger synchronization of wallet transactions.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_walletrevenue\local\sale_sync;

cli_heading('local_walletrevenue Sync');
cli_writeln("Synchronizing pending revenue transactions...");

$walletcount = sale_sync::sync_pending_wallet_transactions();
$paymentcount = sale_sync::sync_pending_core_payments();
$total = $walletcount + $paymentcount;

cli_writeln("Sync complete. Processed {$total} transaction record(s).");
cli_writeln(" - Wallet transactions: {$walletcount}");
cli_writeln(" - Core payment gateway transactions: {$paymentcount}");
