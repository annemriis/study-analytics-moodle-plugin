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

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settingspage = new admin_settingpage('local_study_analytics', new lang_string('pluginname', 'local_study_analytics'));
    $ADMIN->add('localplugins', $settingspage);

    $settingspage->add(new admin_setting_configtext(
            'local_study_analytics/logstash_url',
            'Logstash URL',
            'The Study analytics Logstash url.',
            'https://study-analytics.ee/logstash',
            PARAM_TEXT,
            100)
    );
    $settingspage->add(new admin_setting_configtext(
            'local_study_analytics/kibana_url',
            'Kibana URL',
            'The Study analytics Kibana url.',
            'https://study-analytics.ee/kibana',
            PARAM_TEXT,
            100)
    );
    $settingspage->add(new admin_setting_configtext(
            'local_study_analytics/elasticsearch_url',
            'Elasticsearch URL',
            'The Study analytics Elasticsearch url.',
            'https://study-analytics.ee/elasticsearch',
            PARAM_TEXT,
            100)
    );
    $settingspage->add(new admin_setting_configtext(
            'local_study_analytics/kibana_api_key',
            'Kibana API key',
            'The Study analytics Kibana API key.',
            'API key',
            PARAM_TEXT,
            100)
    );

}
