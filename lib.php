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
 * @var stdClass $plugin
 */

use classes\task\grade_adhoc_task;
use core\task\manager;

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot.'/grade/report/user/externallib.php');
require_once($CFG->dirroot.'/user/externallib.php');
require_once($CFG->dirroot.'/lib/filelib.php');
require_once(__DIR__.'/../../config.php');

/**
 * Study Analytics main class.
 */

class local_study_analytics {

    /**
     * Date format.
     */
    const DATE_FORMAT = 'd-m-Y H:i:s';
    private static $curl;

    /**
     * @param curl $curl
     */
    public static function set_curl($curl): void {
        self::$curl = $curl;
    }

    /**
     * @return curl mixed
     */
    public static function get_curl() {
        if (self::$curl == null) {
            return new curl();
        }
        return self::$curl;
    }

    /**
     * Run adhoc task.
     *
     * @param int $courseid Course id
     * @param int $userid User id
     * @param string $username Username
     * @return void
     */
    public static function run_grade_adhoc_task(int $courseid, int $userid, string $username) {
        $task = new grade_adhoc_task();
        $task->set_custom_data([
            'courseid' => $courseid,
            'userid' => $userid,
            'username' => $username,
        ]);

        manager::queue_adhoc_task($task);
    }

    /**
     * Returns the complete list of grade items for users in a course.
     *
     * @param int $courseid Course Id
     * @param int $userid   Only this user (optional)
     * @param int $groupid  Get users from this group only (optional)
     *
     * @return string A response from Logstash
     */
    public static function get_course_grades(int $courseid, int $userid, string $username): string {
        global $DB;
        $coursegrades = gradereport_user_external::get_grade_items($courseid, 0, 0);
        $usergrades = $coursegrades['usergrades'];
        $record = $DB->get_record('study_analytics_courses', ['userid' => $userid, 'courseid' => $courseid]);
        $lecturerusername = self::get_lecturer_username($username);
        $response = "";
        $data = [];
        for ($i = 0; $i < count($usergrades); $i++) {
            $user = $usergrades[$i];
            $userid = (int) $user['userid'];
            $userlist = [['userid' => $userid, 'courseid' => $courseid]];
            $profile = self::find_user_profile($userlist);

            $studentdata = self::create_student_data($user, $userid, $profile, $courseid, $lecturerusername);

            array_push($data, $studentdata);

            if (count($data) == 100) {
                $response = self::send_to_logstash($data);
                self::update_time_last_data_sent_in_study_analytics_courses_table($record, time());
                $data = [];
            }
        }
        if (count($data) > 0) {
            $response = self::send_to_logstash($data);
            self::update_time_last_data_sent_in_study_analytics_courses_table($record, time());
        }
        return $response;
    }

    /**
     * Get course participant's details.
     *
     * @param array $userlist an array of user id and according course id
     * @return array An array describing course participant
     */
    private static function find_user_profile(array $userlist) : array {
        return core_user_external::get_course_user_profiles($userlist)[0];
    }

    /**
     * Create student data array.
     *
     * @param array $user an array of student data and grade items
     * @param int $userid student id
     * @param array $profile an array describing course participant's Moodle account
     * @param int $courseid course id
     * @param string $lecturerusername lecturer's username
     *
     * @return array An array with data that is sent to Logstash
     */
    private static function create_student_data(array $user, int $userid, array $profile, int $courseid,
        string $lecturerusername): array {
        $studentdata = [
            'userid' => $userid,
            'firstname' => $profile['firstname'],
            'lastname' => $profile['lastname'],
            'UNI-ID' => self::get_username($profile['username']),
            'email' => $profile['email'],
            'lastaccess' => date(self::DATE_FORMAT, substr($profile['lastaccess'], 0, 10)),
            'courseid' => $courseid,
            'lecturerusername' => $lecturerusername,
        ];

        $gradeitems = $user['gradeitems'];
        return self::add_grades_to_student_data($studentdata, $gradeitems);
    }

    /**
     * Add grade names and grades to student data array.
     *
     * @param array $studentdata an array of student data
     * @param array $gradeitems an array of grade items
     *
     * @return array An array with grade_name and grade
     */
    private static function add_grades_to_student_data(array $studentdata, array $gradeitems): array {
        $gradename = '';
        $grades = [];
        for ($i = 0; $i < count($gradeitems); $i++) {
            $gradeitem = $gradeitems[$i];
            $name = $gradeitem['itemname'];
            if ($name == null) {
                $name = 'Kursus kokku';
            }
            $grade = floatval($gradeitem['gradeformatted']);
            $gradename .= self::replace_non_alphanumeric_characters($name) . ',';
            $grades[] = $grade;
        }

        $studentdata['gradename'] = $gradename;
        $studentdata['grade'] = $grades;
        return $studentdata;
    }

    /**
     * Replace non-alphanumeric charaters.
     *
     * @param string $string String with non-alphanumeric characters
     * @return string
     */
    private static function replace_non_alphanumeric_characters(string $string): string {
        return preg_replace('/[^A-Za-z0-9ÕÄÖÜõäöüŠš ]/u', ' ', $string);
    }

    /**
     * Send data to Logstash
     *
     * @param array $data an array that is going to be sent to Logstash
     * @return string A response from Logstash
     */
    public static function send_to_logstash(array $data): string {
        $logstashurl = self::get_logstash_url();
        $url = $logstashurl;
        $jsondata = json_encode($data);

        $options = [
            'RETURNTRANSFER' => 1,
            'HEADER' => 0,
            'FAILONERROR' => 1,
        ];

        $header = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsondata),
            'Accept: application/json',
        ];

        $curl = self::get_curl();
        $curl->setHeader($header);
        return $curl->post($url, $jsondata, $options);
    }

    /**
     * Send create space post request to Kibana
     *
     * @return bool true if space was created false otherwise
     */
    public static function send_create_space_request_to_kibana(): bool {
        $kibanaurl = self::get_kibana_url();
        $url = $kibanaurl . '/api/spaces/space';
        $data = self::create_space_request_body();
        $jsondata = json_encode($data);

        $options = [
            'RETURNTRANSFER' => 1,
            'HEADER' => 0,
            'FAILONERROR' => 1,
        ];

        $header = [
            'Content-Type: application/json;charset=UTF-8',
            'kbn-xsrf: true',
            'Authorization: ApiKey ' . self::get_api_key(),
        ];

        $curl = self::get_curl();
        $curl->setHeader($header);
        $response = $curl->post($url, $jsondata, $options);
        return $response;
    }

    /**
     * Create space request body
     *
     * @return array An array with data that is sent to Kibana to create a new space
     */
    private static function create_space_request_body(): array {
        global $USER;
        $lecturerusername = self::get_lecturer_username($USER->username);
        $requestbody = [
            'id' => $lecturerusername,
            'name' => $lecturerusername,
            'description' => "This is the $lecturerusername Space",
            'disabledFeatures' => [
                'maps',
                'ml',
                'enterpriseSearch',
                'logs',
                'infrastructure',
                'apm',
                'uptime',
                'observabilityCases',
                'siem',
                'securitySolutionCases',
                'osquery',
                'actions',
                'generalCases',
                'rulesSettings',
                'stackAlerts',
                'fleetv2',
                'monitoring',
            ]
        ];
        return $requestbody;
    }

    /**
     * Send create role put request to Kibana
     *
     * @return bool true if role was created false otherwise
     */
    public static function send_create_role_request_to_kibana(): bool {
        global $USER;
        $lecturerusername = self::get_lecturer_username($USER->username);
        $kibanaurl = self::get_kibana_url();
        $url = $kibanaurl . '/api/security/role/' . $lecturerusername;
        $data = self::create_role_request_body($lecturerusername);
        $jsondata = json_encode($data);

        $options = [
            'RETURNTRANSFER' => 1,
            'HEADER' => 0,
            'FAILONERROR' => 1,
        ];

        $header = [
            'Content-Type: application/json;charset=UTF-8',
            'kbn-xsrf: true',
            'Authorization: ApiKey ' . self::get_api_key(),
        ];

        $curl = self::get_curl();
        $curl->setHeader($header);
        $response = $curl->put($url, $jsondata, $options);
        return $response;
    }

    /**
     * Create role request body
     *
     * @param string $lecturerusername lecturer's username
     * @return array An array with data that is sent to Kibana to create a new role
     */
    private static function create_role_request_body(string $lecturerusername): array {
        $indicesnames = $lecturerusername . '_*';
        return [
            'metadata' => [
                'version' => 1
            ],
            'elasticsearch' => [
                'cluster' => ['manage_index_templates', 'manage_pipeline'],
                'indices' => [
                    [
                        'names' => [$indicesnames],
                        'privileges' => ['all']
                    ],
                    [
                        "names" => array("example_data"),
                        "privileges" => array("read")
                    ]
                ]
            ],
            'kibana' => [
                [
                    'base' => [],
                    'feature' => [
                        'discover' => ['all'],
                        'visualize' => ['all'],
                        'dashboard' => ['all'],
                        'indexPatterns' => ['all'],
                        'fleet' => ['all'],
                        'canvas' => ['all'],
                        'dev_tools' => ['all'],
                        'advancedSettings' => ['all'],
                        'filesManagement' => ['all'],
                        'filesSharedImage' => ['all'],
                        'savedObjectsManagement' => ['all'],
                        'savedObjectsTagging' => ['all'],
                    ],
                    'spaces' => [$lecturerusername]
                ]
            ]
        ];

    }

    /**
     * Send create user post request to Elasticsearch
     *
     * @param string $password user's password
     * @return bool true if user was created false otherwise
     */
    public static function send_create_user_request_to_elasticsearch(string $password): bool {
        global $USER;
        $lecturerusername = self::get_lecturer_username($USER->username);
        $elasticsearchurl = self::get_elasticsearch_url();
        $url = $elasticsearchurl . '/_security/user/' . $lecturerusername;
        $data = self::create_user_request_body($password, $lecturerusername);
        $jsondata = json_encode($data);

        $options = [
            'RETURNTRANSFER' => 1,
            'HEADER' => 0,
            'FAILONERROR' => 1,
        ];

        $header = [
            'Content-Type: application/json;charset=UTF-8',
            'Authorization: ApiKey ' . self::get_api_key(),
        ];

        $curl = self::get_curl();
        $curl->setHeader($header);
        return $curl->post($url, $jsondata, $options);
    }

    /**
     * Create user request body
     *
     * @param string $password user's password
     * @param string $lecturerusername lecturer's username
     * @return array An array with data that is sent to Kibana to create a new user
     */
    private static function create_user_request_body(string $password, string $lecturerusername): array {
        return [
            'password_hash' => self::hash_password($password),
            'roles' => [$lecturerusername],
        ];
    }

    /**
     * Hash password using bcrypt algorithm
     *
     * @param string $password to be hashed
     * @return string A hashed password
     */
    private static function hash_password(string $password): string {
        $options = [
            'cost' => 12,
        ];
        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    /**
     * Check if user already exists in Elasticsearch
     *
     * @return bool true if user exists false otherwise
     */
    public static function user_exists_in_elasticsearch(): bool {
        global $USER;
        $lecturerusername = self::get_lecturer_username($USER->username);
        $elasticsearchurl = self::get_elasticsearch_url();
        $url = $elasticsearchurl . '/_security/user/' . $lecturerusername;

        $options = [
            'RETURNTRANSFER' => 1,
            'HEADER' => 0,
            'FAILONERROR' => 1,
        ];

        $header = [
            'Authorization: ApiKey ' . self::get_api_key(),
        ];

        $curl = self::get_curl();
        $curl->setHeader($header);
        $response = $curl->get($url, '', $options);
        $responsearray = json_decode($response, true);
        if (isset($responsearray["$lecturerusername"])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Send delete index request to Elasticsearch
     *
     * @param int $courseid course id
     * @return bool true if index was deleted false otherwise
     */
    public static function send_delete_index_request_to_elasticsearch(int $courseid): bool {
        global $USER;
        $lecturerusername = self::get_lecturer_username($USER->username);
        $elasticsearchurl = self::get_elasticsearch_url();
        $index = $lecturerusername . '_' . $courseid;
        $url = $elasticsearchurl . '/' . $index;

        $options = [
            'RETURNTRANSFER' => 1,
            'HEADER' => 0,
            'FAILONERROR' => 1,
        ];

        $header = [
            'Authorization: ApiKey ' . self::get_api_key(),
        ];

        $curl = self::get_curl();
        $curl->setHeader($header);
        return $curl->delete($url, '', $options);
    }

    public static function get_lecturer_username(string $username): string {
        return str_replace('.', '_', self::get_username($username));
    }

    private static function get_username(string $username): string {
        return explode('@', $username)[0];
    }

    private static function get_logstash_url(): string {
        return get_config('local_study_analytics', 'logstash_url');
    }

    private static function get_kibana_url(): string {
        return get_config('local_study_analytics', 'kibana_url');
    }

    private static function get_elasticsearch_url(): string {
        return get_config('local_study_analytics', 'elasticsearch_url');
    }

    private static function get_api_key(): string {
        return get_config('local_study_analytics', 'kibana_api_key');
    }

    /**
     * Insert the data into database table local_study_analytics_courses.
     *
     * @param int $courseid
     * @return bool
     */
    public static function insert_data_to_study_analytics_courses_table(int $courseid): bool {
        global $DB, $USER;
        $recordtoinsert = new stdClass();
        $recordtoinsert->courseid = $courseid;
        $recordtoinsert->userid = $USER->id;
        $recordtoinsert->username = self::get_lecturer_username($USER->username);

        return $DB->insert_record('study_analytics_courses', $recordtoinsert);
    }

    public static function delete_data_from_study_analytics_courses_table(stdClass $record): bool {
        global $DB;
        return $DB->delete_records('study_analytics_courses', array('id' => $record->id));
    }

    public static function update_update_frequency_in_study_analytics_courses_table(stdClass $record, int $updatefrequency): bool {
        global $DB;
        $record->updatefrequency = $updatefrequency;
        return $DB->update_record('study_analytics_courses', $record);
    }

    public static function update_auto_update_time_in_study_analytics_courses_table(stdClass $record, int $updatetime): bool {
        // Insert the data into our database table local_study_analytics_courses.
        global $DB;
        $record->autoupdatetime = $updatetime;
        return $DB->update_record('study_analytics_courses', $record);
    }

    public static function update_manual_update_time_in_study_analytics_courses_table(stdClass $record, int $updatetime): bool {
        // Insert the data into our database table local_study_analytics_courses.
        global $DB;
        $record->manualupdatetime = $updatetime;
        return $DB->update_record('study_analytics_courses', $record);
    }

    private static function update_time_last_data_sent_in_study_analytics_courses_table(stdClass $record, int $updatetime): bool {
        // Insert the data into our database table local_study_analytics_courses.
        global $DB;
        $record->timelastdatasent = $updatetime;
        return $DB->update_record('study_analytics_courses', $record);
    }

    /**
     * Get course participants.
     *
     * @param context_course $context
     * @param string $lecturerusername
     * @param int $courseid
     * @return bool
     */
    public static function get_course_participants(context_course $context, string $lecturerusername, int $courseid): bool {
        $enrolledusers = get_enrolled_users($context);
        $teachers = get_enrolled_users($context, 'local/study_analytics:updategrades');
        $response = '';
        $data = [];
        foreach ($enrolledusers as $user) {
            if (in_array($user, $teachers)) {
                continue;
            }
            $participantdata = self::create_participant_data($user, $lecturerusername, $courseid);

            array_push($data, $participantdata);
            if (count($data) == 100) {
                $response = self::send_to_logstash($data);
                $data = [];
            }
        }
        if (count($data) > 0) {
            $response = self::send_to_logstash($data);
        }
        return $response;
    }

    private static function create_participant_data($user, string $lecturerusername, int $courseid): array {
        return [
            'UNI-ID' => self::get_username($user->username),
            'declaration' => false,
            'courseid' => $courseid,
            'lecturerusername' => self::get_lecturer_username($lecturerusername),
        ];
    }

}

function local_study_analytics_extend_navigation_course(navigation_node $parentnode, stdClass $course,
        context_course $context) {
    $addnode = $context->contextlevel === 50;
    $addnode = $addnode && has_capability('local/study_analytics:updategrades', $context);
    if ($addnode) {
        global $DB, $USER;
        $courseid = $context->instanceid;
        $nodeheading = $parentnode->add('Study Analytics');

        // Check if the user has used Study analytics.
        if ($DB->get_record('study_analytics_courses', ['userid' => $USER->id, 'courseid' => $courseid])) {
            $urltext = 'Study Analytics';
            $url = new moodle_url('/local/study_analytics/updategrades.php', [
                'courseid' => $courseid,
            ]);
            $removeurltext = 'Remove Study Analytics';
            $removeurl = new moodle_url('/local/study_analytics/removeanalytics.php', [
                'courseid' => $courseid,
            ]);
            $nodeheading->add($urltext, $url, navigation_node::NODETYPE_LEAF);
            $nodeheading->add($removeurltext, $removeurl, navigation_node::NODETYPE_LEAF);
        } else {
            $urltext = 'Add Study Analytics';
            $url = new moodle_url('/local/study_analytics/addanalytics.php', [
                'courseid' => $courseid,
            ]);
            $nodeheading->add($urltext, $url, navigation_node::NODETYPE_LEAF);
        }
    }
}
