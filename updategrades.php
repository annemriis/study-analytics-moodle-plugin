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

use classes\form\automatic_update_grades;
use classes\form\update_grades;

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/local/study_analytics/classes/form/update_grades.php');
require_once($CFG->dirroot.'/local/study_analytics/classes/form/automatic_update_grades.php');
require_once($CFG->dirroot.'/local/study_analytics/lib.php');

// Course id.
$courseid = required_param('courseid', PARAM_INT);
$moodleurl = new moodle_url('/local/study_analytics/updategrades.php', array('courseid' => $courseid));
$PAGE->set_url($moodleurl);
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

$mform = new update_grades($courseid);
$automaticupdatemform = new automatic_update_grades($record->updatefrequency, $courseid);

// Button "Update grades".
if ($fromform = $mform->get_data()) {
    // If the form was submitted and the data is valid, get the course ID and pass it to the function.
    $data = $fromform->courseid;

    local_study_analytics::run_grade_adhoc_task($data, $userid, $username);
    local_study_analytics::update_manual_update_time_in_study_analytics_courses_table($record, time());
    redirect($moodleurl, "Updating grades", null, \core\output\notification::NOTIFY_SUCCESS);
}

// Form "Grades update frequency".
if ($fromform = $automaticupdatemform->get_data()) {
    // If the form was submitted then insert data into our database table local_study_analytics_courses.
    $updatefrequency = $fromform->updatefrequency;
    $response = local_study_analytics::update_update_frequency_in_study_analytics_courses_table($record, $updatefrequency);
    if ($response) {
        redirect($moodleurl, "Updated \"Grades update frequency\" successfully", null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($moodleurl, "\"Grades update frequency\" was not updated", null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();

if (has_capability('local/study_analytics:updategrades', $context)) {
    echo "<h5>Update grades</h5>";
    echo "<p>Update grades in Kibana</p>";
    $mform->display();
    echo "<p>Last automatic update: " . date("d-m-Y H:i", substr($record->autoupdatetime, 0, 10)) . "</p>";
    echo "<p>Last manual update: " . date("d-m-Y H:i", substr($record->manualupdatetime, 0, 10)) . "</p>";
    echo "<p>Time last data sent: " . date("d-m-Y H:i", substr($record->timelastdatasent, 0, 10)) . "</p>";
    echo "<h5>Automatic update frequency</h5>";
    echo "<p>Here, you have the option to choose the frequency for automatically updating grades by selecting your preferred interval from the dropdown menu</p>";
    $automaticupdatemform->display();
}

if (isset($_FILES["csvfile"])) {
    $file = $_FILES["csvfile"]["tmp_name"];

    // Check file extension.
    $allowedextension = array("csv");
    $fileextension = strtolower(pathinfo($_FILES["csvfile"]["name"], PATHINFO_EXTENSION));
    if (!in_array($fileextension, $allowedextension)) {
        \core\notification::add("Only CSV files are allowed.", \core\output\notification::NOTIFY_ERROR);
    } else {
        // Check file MIME type.
        $allowedmime = array("text/csv", "application/csv", "application/vnd.ms-excel", "text/plain");
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $filemime = finfo_file($finfo, $file);
        finfo_close($finfo);
        if (!in_array($filemime, $allowedmime)) {
            \core\notification::add("Invalid file type.", \core\output\notification::NOTIFY_ERROR);
        } else {
            // Send declaration = false.
            local_study_analytics::get_course_participants($context, $username, $courseid);
            // Send declarations.
            $data = array();
            if (($handle = fopen($file, "r")) !== false) {
                $header = fgetcsv($handle, 0, ";", '"', "\n");
                // Replace characters with "" in the header row.
                $header = array_map(function($value) {
                    return preg_replace('/[^A-Za-z0-9ÕÄÖÜõäöüŠš -]/u', '', $value);
                }, $header);
                while (($row = fgetcsv($handle, 0, ";", '"', "\n")) !== false) {
                    $data[] = array_combine($header, $row);
                    $data[count($data) - 1]['courseid'] = $courseid;
                    $data[count($data) - 1]['lecturerusername'] = local_study_analytics::get_lecturer_username($USER->username);
                    $data[count($data) - 1]['declaration'] = true;
                }
                fclose($handle);

                $response = local_study_analytics::send_to_logstash($data);
                if ($response === "ok") {
                    \core\notification::add("Declarations were updated", \core\output\notification::NOTIFY_SUCCESS);
                } else {
                    \core\notification::add("Declarations were not updated. " . $response, \core\output\notification::NOTIFY_ERROR);
                }
            }
        }
    }
}

echo "<h5>Upload ÕIS CSV file</h5>";
echo "<p>Here you can upload your course declarations CSV-file, which should include the required field \"UNI-ID\" in the header</p>";
?>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="csvfile">
    <input type="submit" class="btn btn-primary" value="Submit">
</form>

<?php
echo $OUTPUT->footer();
