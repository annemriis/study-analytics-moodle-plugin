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

namespace classes\form;

use moodleform;

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for removing "Study analytics" from the course.
 *
 * @package     local_study_analytics
 * @copyright   2023 Annemari Riisimäe and Kaisa-Mari Veinberg
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_service extends moodleform {

    /** @var int id of the course */
    private $courseid;

    public function __construct(int $courseid) {
        $this->courseid = $courseid;
        parent::__construct();
    }

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid', $this->courseid);
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(false, "Remove Study Analytics");
    }

    public function validation($data, $files) {
        return array();
    }

}
