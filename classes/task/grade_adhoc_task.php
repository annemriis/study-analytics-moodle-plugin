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

namespace classes\task;

require_once($CFG->dirroot.'/local/study_analytics/lib.php');

use core\task\adhoc_task;
use local_study_analytics;

/**
 * Adhoc task for sending course grades to Logstash.
 *
 * @package     local_study_analytics
 * @copyright   2023 Annemari Riisimäe and Kaisa-Mari Veinberg
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_adhoc_task extends adhoc_task {

    /**
     * Send grades to Logstash.
     */
    public function execute() {
        $data = $this->get_custom_data();
        $courseid = $data->courseid;
        $userid = $data->userid;
        $username = $data->username;

        local_study_analytics::get_course_grades($courseid, $userid, $username);
    }

}
