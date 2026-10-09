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
}
