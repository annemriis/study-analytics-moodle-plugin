# study-analytics-moodle-plugin

The Moodle plugin called "Study Analytics" is a local plugin for Moodle that sends the main functionality of a course grade book, teacher, and enrolled student information to the Elastic Stack.

On a Moodle course, a user with at least the built-in `editingteacher` role has four functionalities that they can use:
1. The user can add the "Study Analytics" service to their course. 
2. Once the "Study Analytics" service is added, the user can manually or automatically send course data to the Elastic Stack. 
3. Additionally, the user can add a CSV file from the Student Information System (ÕIS) that contains the declared students for the course and send the declared students' data to the Elastic Stack. 
4. The user can remove the "Study Analytics" service from their course.

During the plugin download process, the Moodle administrator has the ability to modify four configurations of the plugin:
1. `logstash_url` - The network address of the Logstash software to which Moodle data is sent. 
2. `kibana_url` - The network address of the Kibana software to which API queries are sent. 
3. `elasticsearch_url` - The network address of the Elasticsearch software to which API queries are sent. 
4. `kibana_api_key` - An API key that grants the right to send API queries to Kibana and Elasticsearch.

## Installation

The plugin can be installed up to Moodle version 4.0. Download the .zip file, where the topmost folder is study_analytics. 
In the Moodle administrator view, go to `install plugins` and select the plugin type as `Local`. The plugin is installed in the Moodle `local` folder.

A table called `study_analytics_courses` is created for the plugin.
