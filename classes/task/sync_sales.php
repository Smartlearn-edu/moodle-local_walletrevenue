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

namespace local_walletrevenue\task;

use core\task\scheduled_task;
use local_walletrevenue\local\sale_sync;

/**
 * Scheduled task to synchronize wallet sales and refunds.
 *
 * @package    local_walletrevenue
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_sales extends scheduled_task {
    /**
     * Return the task's localized name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_sync_sales', 'local_walletrevenue');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        mtrace('Starting local_walletrevenue sales synchronization...');
        $walletcount = sale_sync::sync_pending_wallet_transactions();
        $paymentcount = sale_sync::sync_pending_core_payments();
        $total = $walletcount + $paymentcount;
        mtrace("Synchronization complete. Processed {$total} transaction(s) (Wallet: {$walletcount}, Core payment: {$paymentcount}).");
    }
}
