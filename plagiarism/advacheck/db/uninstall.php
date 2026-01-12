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
defined('MOODLE_INTERNAL') || die();

require_once ($CFG->dirroot . "/plagiarism/advacheck/lib.php");
/**
 * Deletes all tables and plugin settings.
 * @return bool
 */
function xmldb_plagiarism_advacheck_uninstall()
{
    global $DB, $CFG;

    // First, let's delete all the tables that the view depends on.
    if ($CFG->dbtype == 'sqlsrv') {
        $sql = "SELECT OBJECT_ID('{plagiarism_advacheck_act_log}') AS exist";
        $view = $DB->get_field_sql($sql);
        if ($view) {
            $DB->execute("DROP VIEW {plagiarism_advacheck_act_log}");
        }
        $sql = "SELECT OBJECT_ID('{plagiarism_advacheck_docs}') AS exist";
        $view = $DB->get_field_sql($sql);
        if ($view) {
            $DB->execute("DROP VIEW {plagiarism_advacheck_docs}");
        }
        $sql = "SELECT OBJECT_ID('{plagiarism_advacheck_action}') AS exist";
        $view = $DB->get_field_sql($sql);
        if ($view) {
            $DB->execute("DROP VIEW {plagiarism_advacheck_action}");
        }
    } else {
        $DB->execute('DROP TABLE IF EXISTS {plagiarism_advacheck_act_log}');
        $DB->execute('DROP TABLE IF EXISTS {plagiarism_advacheck_docs}');
        $DB->execute('DROP TABLE IF EXISTS {plagiarism_advacheck_action}');
    }

    // Delete plugin settings.
    $DB->delete_records('config_plugins', ['plugin' => 'plagiarism_advacheck']);

    purge_caches();

    return true;
}
