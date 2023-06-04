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

use classes\form\add_service;
use classes\form\add_user;

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/local/study_analytics/classes/form/add_service.php');
require_once($CFG->dirroot.'/local/study_analytics/classes/form/add_user.php');
require_once($CFG->dirroot.'/local/study_analytics/lib.php');

// Course id.
$courseid = required_param('courseid', PARAM_INT);
$currentpageurl = new moodle_url('/local/study_analytics/addanalytics.php', array('courseid' => $courseid));
$PAGE->set_url($currentpageurl);

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
if ($DB->get_record('study_analytics_courses', array('userid' => $USER->id, 'courseid' => $courseid))) {
    throw new moodle_exception('invaliduserid');
}

// Setup page.
$PAGE->set_title('Study Analytics');
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add('Study Analytics');
$PAGE->set_pagelayout('incourse');

// Process form submission.
$adduserform = new add_user($courseid);

if ($fromform = $adduserform->get_data()) {
    $password = $fromform->userpassword;
    // Create user.
    $response = local_study_analytics::send_create_user_request_to_elasticsearch($password);
    if ($response) {
        // Create space.
        local_study_analytics::send_create_space_request_to_kibana();
        local_study_analytics::send_create_role_request_to_kibana();
        local_study_analytics::send_copy_example_dashboard_request_to_kibana();
        redirect($currentpageurl, "User was created successfully", null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($currentpageurl, "User was not created", null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Process form submission.
$mform = new add_service($courseid);

if ($fromform = $mform->get_data()) {
    // If the form was submitted and the data is valid, get the course ID and pass it to the function.
    // Insert the data into our database table local_study_analytics_courses.
    try {
        local_study_analytics::insert_data_to_study_analytics_courses_table($courseid);
        redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, "Course was added successfully", null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (dml_exception $e) {
        redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid, $e->getMessage(), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();

if (has_capability('local/study_analytics:updategrades', $context)) {
    if (local_study_analytics::user_exists_in_elasticsearch()) {
        echo "<p>Add Study Analytics to your course.</p>";
        $mform->display();
    } else {
        echo "<p>Here you can create your Kibana account. With Kibana, you can effortlessly visualize and analyze data from your Moodle course.</p>";
        echo "<p>Your username is " . local_study_analytics::get_lecturer_username($USER->username) . "</p>";
        echo "<p>Please enter your desired password to create your Kibana account</p>";
        $adduserform->display();
    }
}

echo $OUTPUT->footer();
