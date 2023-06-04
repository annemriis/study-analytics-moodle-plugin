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

/**
 * @throws downgrade_exception
 * @throws ddl_field_missing_exception
 * @throws ddl_exception
 * @throws upgrade_exception
 * @throws ddl_table_missing_exception
 */
function xmldb_local_study_analytics_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2023050700) {
        $table = new xmldb_table('study_analytics_courses');

        $updatefrequencyfield = new xmldb_field('updatefrequency', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $updatefrequencyfield)) {
            $dbman->add_field($table, $updatefrequencyfield);
        }

        $autoupdatetimefield = new xmldb_field('autoupdatetime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $autoupdatetimefield)) {
            $dbman->add_field($table, $autoupdatetimefield);
        }

        $manualupdatetimefield = new xmldb_field('manualupdatetime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $manualupdatetimefield)) {
            $dbman->add_field($table, $manualupdatetimefield);
        }

        $cronfield = new xmldb_field('cron');
        if ($dbman->field_exists($table, $cronfield)) {
            $dbman->drop_field($table, $cronfield);
        }

        upgrade_plugin_savepoint(true, 2023050700, 'local', 'study_analytics');
    }

    if ($oldversion < 2023051000) {
        $table = new xmldb_table('study_analytics_courses');

        $timelastdatasentfield = new xmldb_field('timelastdatasent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $timelastdatasentfield)) {
            $dbman->add_field($table, $timelastdatasentfield);
        }
    }

    return true;
}
