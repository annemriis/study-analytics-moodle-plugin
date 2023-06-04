<?php
// This file is part of Moodle Study Analytics Plugin
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

namespace local_study_analytics\task;

use local_study_analytics;

require_once($CFG->dirroot.'/local/study_analytics/lib.php');

/**
 * Scheduled task for sending course grades to Logstash.
 *
 * @package     local_study_analytics
 * @copyright   2023 Annemari Riisimäe and Kaisa-Mari Veinberg
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_update_task extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string Name of the task.
     */
    public function get_name(): string {
        return 'Study analytics grades auto update task';
    }

    /**
     * Send grades to Logstash.
     */
    public function execute() {
        global $DB;

        // Retrieve the list of courses that need to be updated.
        $courses = $DB->get_records('study_analytics_courses');

        foreach ($courses as $course) {
            $frequency = $course->updatefrequency;
            if ($this->is_no_auto_update($frequency)) {
                continue;
            }
            $lastupdatetime = $course->autoupdatetime;
            if ($this->has_time_interval_passed($lastupdatetime, $frequency)) {
                $courseid = $course->courseid;
                $userid = $course->userid;
                $username = $course->username;
                // Update grades.
                local_study_analytics::run_grade_adhoc_task($courseid, $userid, $username);
                // Update the last update time for the course in the database.
                local_study_analytics::update_auto_update_time_in_study_analytics_courses_table($course, time());
            }
        }
    }

    /**
     * Check if frequency is "no auto update".
     *
     * @param int $frequency grades update frequency
     * @return bool true if frequency is "no auto update" false otherwise
     */
    private function is_no_auto_update(int $frequency): bool {
        if ($frequency == 0) {
            return true;
        }
        return false;
    }

    /**
     * Check if time interval has passed.
     *
     * @param int $lastupdatetime unix epoch of the last update
     * @param int $frequency grades update frequency
     *
     * @return bool true if time interval has passed false otherwise
     */
    private function has_time_interval_passed(int $lastupdatetime, int $frequency): bool {
        $timeinterval = $this->get_time_interval($frequency);
        // Calculate the number of seconds that have passed since $last_update_time.
        $secondspassed = time() - $lastupdatetime;
        // Check if the appropriate time interval has passed since $last_update_time.
        if ($secondspassed >= $timeinterval) {
            return true;
        }
        return false;
    }

    /**
     * Determine the appropriate time interval based on the value of frequency.
     *
     * @param int $frequency grades update frequency
     * @return int time interval in unix epoch
     */
    private function get_time_interval(int $frequency): int {
        if ($frequency == 1) {
            return 86400; // 1 day.
        } else if ($frequency == 2) {
            return 604800; // 1 week.
        }
        return 2592000; // 1 month.
    }

}
