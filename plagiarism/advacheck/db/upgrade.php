<?php
// This file is part of Moodle - http://moodle.org/
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
 *
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Updates and modifies the plugin's table structures.
 * @param mixed $oldversion
 * @return bool
 */
function xmldb_plagiarism_advacheck_upgrade($oldversion)
{
    global $DB, $CFG, $OUTPUT;
    $dbman = $DB->get_manager();
    if ($oldversion < 2023100600) {

        // Starting from version 2023091920, the API link must be entered differently.
        $settings = get_config('plagiarism_advacheck');
        if (!empty($settings->soap_wsdl) && !empty($settings->company)) {
            set_config('soap_wsdl', "$settings->soap_wsdl/apiCorp/$settings->company?singleWsdl", 'plagiarism_advacheck');
            // The company field is no longer used.
            set_config('company', null, 'plagiarism_advacheck');
        }
        if (substr($settings->uri, -1) == '/') {
            $value = substr($settings->uri, 0, -1);
            set_config('uri', $value, 'plagiarism_advacheck');
        }
        // Apgtru savepoint reached.
        upgrade_plugin_savepoint(true, 2023100600, 'plagiarism', 'advacheck');
    }

    if ($oldversion < 2024020902) {

        // Define field id to be added to plagiarism_advacheck_docs.
        $table = new xmldb_table('plagiarism_advacheck_docs');
        $field = new xmldb_field('attemptnumber', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'type');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Apgtru savepoint reached.
        upgrade_plugin_savepoint(true, 2024020902, 'plagiarism', 'advacheck');
    }

    if ($oldversion < 2024040904) {
        $table = new xmldb_table('plagiarism_advacheck_action_log');
        if ($dbman->table_exists($table)) {
            // Launch rename table for plagiarism_advacheck_act_log.
            $dbman->rename_table($table, 'plagiarism_advacheck_act_log');
        }
        $table = new xmldb_table('plagiarism_advacheck_act_log');
        if ($dbman->table_exists($table)) {
            $key = new xmldb_key('action_id', XMLDB_KEY_FOREIGN, ['action_id'], 'plagiarism_advacheck_action', ['id']);

            // Launch drop key action_id.
            $dbman->drop_key($table, $key);

            $field = new xmldb_field('action_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'reportedit');
            // Launch change of type for field action_id.
            $dbman->change_field_type($table, $field);
            // Launch rename field action_id.
            $dbman->rename_field($table, $field, 'action');
        }

        // Define table plagiarism_advacheck_action to be dropped.
        $table = new xmldb_table('plagiarism_advacheck_action');

        // Conditionally launch drop table for plagiarism_advacheck_action.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $table = new xmldb_table('plagiarism_advacheck_action_log');

        if ($dbman->table_exists($table)) {
            // Launch rename table for plagiarism_advacheck_act_log.
            $dbman->rename_table($table, 'plagiarism_advacheck_act_log');
        }

        // If exist view plagiarism_advacheck_log_view, then delete it so that there are no errors in the DB.
        if ($CFG->dbtype == 'sqlsrv') {
            $sql = "SELECT OBJECT_ID('{plagiarism_advacheck_log_view}')AS exist";
            $view = $DB->get_record_sql($sql);
            if ($view->exist) {
                $deleteview = "DROP VIEW {plagiarism_advacheck_log_view}";
                $DB->execute($deleteview);
            }
        } else {
            $deleteview = "DROP VIEW IF EXISTS {plagiarism_advacheck_log_view}";
            $DB->execute($deleteview);
        }

        // Apgtru savepoint reached.
        upgrade_plugin_savepoint(true, 2024040904, 'plagiarism', 'advacheck');
    }

    if ($oldversion < 2024121016) {

        // Define field docsecttitle to be added to plagiarism_advacheck_course.
        $table = new xmldb_table('plagiarism_advacheck_course');
        $field = new xmldb_field('docsecttitle', XMLDB_TYPE_BINARY, null, null, null, null, null, 'works_types');

        // Conditionally launch add field docsecttitle.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('docsectcontent', XMLDB_TYPE_BINARY, null, null, null, null, null, 'docsecttitle');

        // Conditionally launch add field docsectcontent.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('docsectbibliography', XMLDB_TYPE_BINARY, null, null, null, null, null, 'docsectcontent');

        // Conditionally launch add field docsectbibliography.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('docsectappendix', XMLDB_TYPE_BINARY, null, null, null, null, null, 'docsectbibliography');

        // Conditionally launch add field docsectappendix.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('docsectintroduction', XMLDB_TYPE_BINARY, null, null, null, null, null, 'docsectappendix');

        // Conditionally launch add field docsectappendix.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('docsectmethod', XMLDB_TYPE_BINARY, null, null, null, null, null, 'docsectintroduction');

        // Conditionally launch add field docsectappendix.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('docsectconclusion', XMLDB_TYPE_BINARY, null, null, null, null, null, 'docsectmethod');

        // Conditionally launch add field docsectappendix.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        set_config('docsecttitledefault', 1, 'plagiarism_advacheck');
        set_config('docsectcontentdefault', 1, 'plagiarism_advacheck');
        set_config('docsectbibliographydefault', 1, 'plagiarism_advacheck');
        set_config('docsectappendixdefault', 1, 'plagiarism_advacheck');
        set_config('docsectintroductiondefault', 1, 'plagiarism_advacheck');
        set_config('docsectmethoddefault', 1, 'plagiarism_advacheck');
        set_config('docsectconclusiondefault', 1, 'plagiarism_advacheck');

        // Apgtru savepoint reached.
        upgrade_plugin_savepoint(true, 2024121016, 'plagiarism', 'advacheck');
    }
    return true;
}
