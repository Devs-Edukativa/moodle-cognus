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
 * @package   plagiarism_advacheck
 * @category  plagiarism
 * @copyright © 2023 onwards Advacheck OU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

if (interface_exists('\core_privacy\local\request\userlist')) {
    interface my_userlist extends \core_privacy\local\request\userlist
    {
    }
} else {
    interface my_userlist
    {
    }
    ;
}

/**
 * Class provider
 *
 * @package plagiarism_advacheck\privacy
 */
class provider implements
    my_userlist,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider
{

    // This trait must be included.
    use \core_privacy\local\legacy_polyfill;

    /**
     * @param collection $collection
     * @return collection
     */
    public static function _get_metadata(collection $collection)
    {

        $collection->add_subsystem_link(
            'core_files',
            array(),
            'privacy:metadata:core_files'
        );

        $collection->add_database_table(
            'plagiarism_advacheck_docs',
            array(
                'doctype' => 'privacy:metadata:plagiarism_advacheck_docs:doctype',
                'typeid' => 'privacy:metadata:plagiarism_advacheck_docs:typeid',
                'answerid' => 'privacy:metadata:plagiarism_advacheck_docs:answerid',
                'error' => 'privacy:metadata:plagiarism_advacheck_docs:error',
                'assignment' => 'privacy:metadata:plagiarism_advacheck_docs:assignment',
                'discussion' => 'privacy:metadata:plagiarism_advacheck_docs:discussion',
                'workshop' => 'privacy:metadata:plagiarism_advacheck_docs:workshop',
                'userid' => 'privacy:metadata:plagiarism_advacheck_docs:userid',
                'plagiarism' => 'privacy:metadata:plagiarism_advacheck_docs:plagiarism',
                'legal' => 'privacy:metadata:plagiarism_advacheck_docs:legal',
                'selfcite' => 'privacy:metadata:plagiarism_advacheck_docs:selfcite',
                'issuspicious' => 'privacy:metadata:plagiarism_advacheck_docs:issuspicious',
                'reportedit' => 'privacy:metadata:plagiarism_advacheck_docs:reportedit',
                'reportread' => 'privacy:metadata:plagiarism_advacheck_docs:reportread',
                'shortreport' => 'privacy:metadata:plagiarism_advacheck_docs:shortreport',
                'externalid' => 'privacy:metadata:plagiarism_advacheck_docs:externalid',
                'status' => 'privacy:metadata:plagiarism_advacheck_docs:status',
                'timeadded' => 'privacy:metadata:plagiarism_advacheck_docs:timeadded',
                'courseid' => 'privacy:metadata:plagiarism_advacheck_docs:courseid',
                'cmid' => 'privacy:metadata:plagiarism_advacheck_docs:cmid',
                'timeupload_start' => 'privacy:metadata:plagiarism_advacheck_docs:timeupload_start',
                'timeupload_end' => 'privacy:metadata:plagiarism_advacheck_docs:timeupload_end',
                'timecheck_start' => 'privacy:metadata:plagiarism_advacheck_docs:timecheck_start',
                'timecheck_end' => 'privacy:metadata:plagiarism_advacheck_docs:timecheck_end',
                'teacherid' => 'privacy:metadata:plagiarism_advacheck_docs:teacherid',
                'stud_check' => 'privacy:metadata:plagiarism_advacheck_docs:stud_check',
                'type' => 'privacy:metadata:plagiarism_advacheck_docs:type',
                'attemptnumber' => 'privacy:metadata:plagiarism_advacheck_docs:attemptnumber',

            ),
            'privacy:metadata:plagiarism_advacheck_docs'
        );

        $collection->add_database_table(
            'plagiarism_advacheck_course',
            array(
                'courseid' => 'privacy:metadata:plagiarism_advacheck_course:courseid',
                'cmid' => 'privacy:metadata:plagiarism_advacheck_course:cmid',
                'mode' => 'privacy:metadata:plagiarism_advacheck_course:mode',
                'checktext' => 'privacy:metadata:plagiarism_advacheck_course:checktext',
                'checkfile' => 'privacy:metadata:plagiarism_advacheck_course:checkfile',
                'check_stud_lim' => 'privacy:metadata:plagiarism_advacheck_course:check_stud_lim',
                'add_to_index' => 'privacy:metadata:plagiarism_advacheck_course:add_to_index',
                'disp_notices' => 'privacy:metadata:plagiarism_advacheck_course:disp_notices',
                'works_types' => 'privacy:metadata:plagiarism_advacheck_course:works_types',
            ),
            'privacy:metadata:plagiarism_advacheck_course'
        );

        $collection->add_database_table(
            'plagiarism_advacheck_act_log',
            array(
                'docid' => 'privacy:metadata:plagiarism_advacheck_act_log:docid',
                'time_action' => 'privacy:metadata:plagiarism_advacheck_act_log:time_action',
                'time_action_hr' => 'privacy:metadata:plagiarism_advacheck_act_log:time_action_hr',
                'reportedit' => 'privacy:metadata:plagiarism_advacheck_act_log:reportedit',
                'action' => 'privacy:metadata:plagiarism_advacheck_act_log:actions',
                'status' => 'privacy:metadata:plagiarism_advacheck_act_log:status',
                'courseid' => 'privacy:metadata:plagiarism_advacheck_act_log:courseid',
                'cmid' => 'privacy:metadata:plagiarism_advacheck_act_log:cmid',
                'assignment' => 'privacy:metadata:plagiarism_advacheck_act_log:assignment',
                'discussion' => 'privacy:metadata:plagiarism_advacheck_act_log:discussion',
                'userid' => 'privacy:metadata:plagiarism_advacheck_act_log:userid',
                'verifier_initiator' => 'privacy:metadata:plagiarism_advacheck_act_log:verifier_initiator',
                'errormessage' => 'privacy:metadata:plagiarism_advacheck_act_log:errormessage',
                'cmsettings' => 'privacy:metadata:plagiarism_advacheck_act_log:cmsettings',
            ),
            'privacy:metadata:plagiarism_advacheck_act_log'
        );

        $collection->add_external_location_link(
            'plagiarism_advacheck',
            array(
                'file' => 'privacy:metadata:plagiarism_advacheck:file',
            ),
            'privacy:metadata:plagiarism_advacheck'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int         $userid     The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist
    {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT cmid FROM {plagiarism_advacheck_docs} WHERE userid = :userid";
        $params = [
            'userid' => $userid
        ];
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT DISTINCT cmid FROM {plagiarism_advacheck_act_log} WHERE userid = :userid";
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist)
    {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $params = [
            'cmid' => $context->instanceid,
        ];
        $sql = "SELECT DISTINCT userid FROM {plagiarism_advacheck_docs} WHERE cmid = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT DISTINCT userid FROM {plagiarism_advacheck_act_log} WHERE cmid = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist)
    {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $userdatadocs = $DB->get_records('plagiarism_advacheck_docs', ['userid' => $userid]);
        foreach ($userdatadocs as $udd) {
            $context = \context_module::instance($udd->cmid);
            writer::with_context($context)->export_data([], (object) $udd);
        }

        $userdatalogs = $DB->get_records('plagiarism_advacheck_act_log', ['userid' => $userid]);
        foreach ($userdatalogs as $udl) {
            $context = \context_module::instance($udl->cmid);
            writer::with_context($context)->export_data([], (object) $udl);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist)
    {
        global $DB;

        $context = $userlist->get_context();
        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['cmid' => $context->instanceid], $userinparams);
        $sql = "cmid = :cmid AND userid {$userinsql}";

        $DB->delete_records_select('plagiarism_advacheck_docs', $sql, $params);
        $DB->delete_records_select('plagiarism_advacheck_act_log', $sql, $params);

    }


    /**
     * Export all user preferences for the plugin.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid)
    {
        global $DB;
        $DB->get_records('plagiarism_advacheck_docs', ['userid' => $userid]);
        $DB->get_records('plagiarism_advacheck_act_log', ['userid' => $userid]);
    }


    /**
     * Delete all data for all users in the specified context.
     *
     * @param   \context         $context   The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context)
    {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $DB->delete_records('plagiarism_advacheck_docs', ['cmid' => $context->instanceid]);
        $DB->delete_records('plagiarism_advacheck_act_log', ['cmid' => $context->instanceid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist)
    {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('plagiarism_advacheck_docs', ['cmid' => $context->instanceid, 'userid' => $userid]);
            $DB->delete_records('plagiarism_advacheck_act_log', ['cmid' => $context->instanceid, 'userid' => $userid]);
        }
    }
}
