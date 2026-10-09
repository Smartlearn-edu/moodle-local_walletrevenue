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
use local_walletrevenue\local\rate_resolver;

/**
 * Unit tests for rate_resolver hierarchical commission calculations.
 *
 * @package    local_walletrevenue
 * @category   test
 * @copyright  2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rate_resolver_test extends advanced_testcase {
    public function test_global_default_fallback(): void {
        $this->resetAfterTest(true);

        set_config('defaultrate', '80.00', 'local_walletrevenue');

        $resolved = rate_resolver::resolve(10, 20);
        $this->assertEquals(80.00, $resolved);

        $info = rate_resolver::get_rate_info(10, 20);
        $this->assertEquals('global', $info['source']);
        $this->assertEquals(80.00, $info['percent']);
    }

    public function test_teacher_override(): void {
        $this->resetAfterTest(true);

        set_config('defaultrate', '80.00', 'local_walletrevenue');
        $teacher = $this->getDataGenerator()->create_user();

        rate_resolver::set_rate(rate_resolver::SCOPE_TEACHER, $teacher->id, 70.00);

        $resolved = rate_resolver::resolve(10, $teacher->id);
        $this->assertEquals(70.00, $resolved);

        $info = rate_resolver::get_rate_info(10, $teacher->id);
        $this->assertEquals('teacher', $info['source']);
    }

    public function test_course_override_priority(): void {
        $this->resetAfterTest(true);

        set_config('defaultrate', '80.00', 'local_walletrevenue');
        $teacher = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        rate_resolver::set_rate(rate_resolver::SCOPE_TEACHER, $teacher->id, 70.00);
        rate_resolver::set_rate(rate_resolver::SCOPE_COURSE, $course->id, 60.00);

        // Course override must take precedence over teacher override and global default.
        $resolved = rate_resolver::resolve($course->id, $teacher->id);
        $this->assertEquals(60.00, $resolved);

        $info = rate_resolver::get_rate_info($course->id, $teacher->id);
        $this->assertEquals('course', $info['source']);
    }
}
