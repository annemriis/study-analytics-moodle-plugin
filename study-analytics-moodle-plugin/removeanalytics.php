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

/**
 * @package     local_study_analytics
 * @copyright   2023 Annemari Riisimäe and Kaisa-Mari Veinberg
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use classes\form\delete_service;

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/local/study_analytics/classes/form/delete_service.php');
require_once($CFG->dirroot.'/local/study_analytics/lib.php');

// Course id.
$courseid = required_param('courseid', PARAM_INT);
$PAGE->set_url(new moodle_url('/local/study_analytics/updategrades.php', array('courseid' => $courseid)));
$username = $USER->username;

// Basic access checks.
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    throw new moodle_exception('invalidcourseid');
}
require_login($course);
$context = context_course::instance($course->id);
if (!has_capability('local/study_analytics:updategrades', $context)) {
    throw new moodle_exception('nopermissiontoviewpage');
}

// Check if user has already used Study analytics.
$userid = $USER->id;
$record = $DB->get_record('study_analytics_courses', array('userid' => $userid, 'courseid' => $courseid));
if (!$record) {
    throw new moodle_exception('invaliduserid');
}

$PAGE->set_title('Study Analytics');
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add('Study Analytics');
$PAGE->set_pagelayout('incourse');

// Process form submission.
$mform = new delete_service($courseid);

if ($fromform = $mform->get_data()) {
    // If the form was submitted and the data is valid.
    // Delete index.
    $response = local_study_analytics::send_delete_index_request_to_elasticsearch($courseid);
    // Remove record from our database table local_study_analytics_courses.
    try {
        local_study_analytics::delete_data_from_study_analytics_courses_table($record);
        redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, "Course was removed successfully", null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (dml_exception $e) {
        redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, $e->getMessage(), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();

if (has_capability('local/study_analytics:updategrades', $context)) {
    echo "<p>By pressing the button, the \"Study Analytics\" service will be permanently removed from the course, and all associated data in Kibana will be deleted</p>";
    $mform->display();
}

echo $OUTPUT->footer();
