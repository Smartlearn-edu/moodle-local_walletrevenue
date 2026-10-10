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

namespace local_walletrevenue;

use advanced_testcase;
use local_walletrevenue\local\sale_sync;
use local_walletrevenue\local\payee_resolver;
use local_walletrevenue\local\rate_resolver;
use local_walletrevenue\local\calculator;

/**
 * Unit tests for sale_sync and calculator.
 *
 * @package    local_walletrevenue
 * @category   test
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sale_sync_test extends advanced_testcase {
    public function test_sale_sync_and_unassigned_recalculation(): void {
        global $DB;
        $this->resetAfterTest(true);

        set_config('defaultrate', '80.00', 'local_walletrevenue');
        set_config('installfloor', 0, 'local_walletrevenue');

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        // 1. Manually insert an unassigned sale record.
        $saleid = $DB->insert_record('local_walletrevenue_sale', [
            'transactionid' => 101,
            'type'          => 'sale',
            'sourceop'      => 'enrol_instance',
            'courseid'      => $course->id,
            'instanceid'    => 1,
            'studentid'     => $student->id,
            'teacherid'     => 0, // Unassigned
            'listprice'     => 100.00,
            'amount'        => 100.00,
            'percent'       => 80.00,
            'teachershare'  => 80.00,
            'currency'      => 'USD',
            'freecut'       => 0.00,
            'timecreated'   => time(),
            'timesynced'    => time(),
        ]);

        $this->assertEquals(1, payee_resolver::get_unassigned_courses_count());

        // Set teacher override to 70%.
        rate_resolver::set_rate(rate_resolver::SCOPE_TEACHER, $teacher->id, 70.00);

        // Assign payee teacher.
        payee_resolver::set_payee($course->id, $teacher->id);

        $this->assertEquals(0, payee_resolver::get_unassigned_courses_count());

        // Verify the sale was recalculated to 70% ($70.00).
        $updatedsale = $DB->get_record('local_walletrevenue_sale', ['id' => $saleid]);
        $this->assertEquals($teacher->id, $updatedsale->teacherid);
        $this->assertEquals(70.00, (float) $updatedsale->percent);
        $this->assertEquals(70.00, (float) $updatedsale->teachershare);

        // Test calculator summary.
        $summaries = calculator::get_teachers_summary(0, 0, $teacher->id, 'USD');
        $this->assertCount(1, $summaries);
        $summary = $summaries[0];
        $this->assertEquals(100.00, $summary->totalsales);
        $this->assertEquals(70.00, $summary->teachershare);
        $this->assertEquals(70.00, $summary->currentbalance);
    }

    /**
     * Test core payment gateway ingestion, exclusion of enrol_wallet payments, and source filtering.
     */
    public function test_sync_pending_core_payments(): void {
        global $DB;
        $this->resetAfterTest(true);

        set_config('enablepaymentsync', 1, 'local_walletrevenue');
        set_config('defaultrate', '80.00', 'local_walletrevenue');
        set_config('installfloor_payment', 0, 'local_walletrevenue');

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        // Assign payee teacher for this course.
        payee_resolver::set_payee($course->id, $teacher->id);

        // 1. Create an enrol_fee instance in {enrol}.
        $enrolid = $DB->insert_record('enrol', [
            'enrol'        => 'fee',
            'status'       => 0, // ENROL_INSTANCE_ENABLED
            'courseid'     => $course->id,
            'sortorder'    => 1,
            'cost'         => 150.00,
            'currency'     => 'USD',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        // 2. Insert a valid enrol_fee payment in {payments}.
        $paymentid = $DB->insert_record('payments', [
            'component'    => 'enrol_fee',
            'paymentarea'  => 'fee',
            'itemid'       => $enrolid,
            'userid'       => $student->id,
            'amount'       => 150.00,
            'currency'     => 'USD',
            'gateway'      => 'paypal',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        // 3. Insert an enrol_wallet payment in {payments} (should be ignored to prevent double-counting).
        $walletpaymentid = $DB->insert_record('payments', [
            'component'    => 'enrol_wallet',
            'paymentarea'  => 'fee',
            'itemid'       => $enrolid,
            'userid'       => $student->id,
            'amount'       => 200.00,
            'currency'     => 'USD',
            'gateway'      => 'wallet',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        // 4. Insert an unrelated component payment in {payments} (should be skipped).
        $otherpaymentid = $DB->insert_record('payments', [
            'component'    => 'mod_customcert',
            'paymentarea'  => 'fee',
            'itemid'       => 999,
            'userid'       => $student->id,
            'amount'       => 50.00,
            'currency'     => 'USD',
            'gateway'      => 'stripe',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        // Run sync for core payments.
        $syncedcount = sale_sync::sync_pending_core_payments();
        $this->assertEquals(1, $syncedcount);

        // Verify the ingested core payment sale record.
        $salerecord = $DB->get_record('local_walletrevenue_sale', [
            'source'        => 'core_payment',
            'transactionid' => $paymentid,
        ]);
        $this->assertNotEmpty($salerecord);
        $this->assertEquals($course->id, (int) $salerecord->courseid);
        $this->assertEquals($teacher->id, (int) $salerecord->teacherid);
        $this->assertEquals($student->id, (int) $salerecord->studentid);
        $this->assertEquals(150.00, (float) $salerecord->amount);
        $this->assertEquals('paypal', $salerecord->gateway);
        $this->assertEquals('core_payment', $salerecord->source);
        $this->assertEquals(80.00, (float) $salerecord->percent);
        $this->assertEquals(120.00, (float) $salerecord->teachershare);

        // Verify enrol_wallet payment was NOT ingested in core_payment.
        $walletdup = $DB->get_record('local_walletrevenue_sale', [
            'source'        => 'core_payment',
            'transactionid' => $walletpaymentid,
        ]);
        $this->assertFalse($walletdup);

        // Verify unrelated payment was NOT ingested.
        $otherrecord = $DB->get_record('local_walletrevenue_sale', [
            'source'        => 'core_payment',
            'transactionid' => $otherpaymentid,
        ]);
        $this->assertFalse($otherrecord);

        // Test idempotency: syncing again should return 0 new records.
        $syncedagain = sale_sync::sync_pending_core_payments();
        $this->assertEquals(0, $syncedagain);

        // Test calculator source filtering.
        $allsummary = calculator::get_teachers_summary(0, 0, $teacher->id, 'USD', '');
        $this->assertCount(1, $allsummary);
        $this->assertEquals(150.00, $allsummary[0]->totalsales);
        $this->assertEquals(120.00, $allsummary[0]->teachershare);

        $coresummary = calculator::get_teachers_summary(0, 0, $teacher->id, 'USD', 'core_payment');
        $this->assertCount(1, $coresummary);
        $this->assertEquals(150.00, $coresummary[0]->totalsales);
        $this->assertEquals(120.00, $coresummary[0]->teachershare);

        $walletsummary = calculator::get_teachers_summary(0, 0, $teacher->id, 'USD', 'wallet');
        $this->assertEmpty($walletsummary);

        // Test toggle disable: when enablepaymentsync is 0, no core payments are processed.
        set_config('enablepaymentsync', 0, 'local_walletrevenue');
        $this->assertEquals(0, sale_sync::sync_pending_core_payments());
    }
}
