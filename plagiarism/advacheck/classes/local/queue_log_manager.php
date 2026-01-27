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
 * Class for working recording log of action with documents
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\local;

/**
 * A class that records verification events to investigate incidents.
 */

class queue_log_manager
{

    /**
     * We keep an event log to investigate incidents.
     * Adds a record of an event to the queue log.
     *
     * @global \moodle_database $DB
     * @param string $externalid ID of the document in the AP system.
     * @param string $reportedit Link to inspection report.
     * @param int $action_id ID of the action in the table with actions.
     * @param int $courseid
     * @param int $cmid
     * @param int $assignment assignment id
     * @param int $discussion discussion id
     * @param int $userid ID of the user being checked.
     * @param int $objectid Message/response ID to search for.
     * @param int $docid ID of the document in the queue.
     * @param int $status The status of the document at the time of the current action.
     * @param string|bool $auto False - manual mode, take the ID of the person who pressed the button. in Atoremode we get the name of the task according to the schedule.
     * @param string $errormessage Error text.
     * @return void
     */
    function add_log_record(
        $externalid,
        $reportedit,
        $action_id,
        $courseid,
        $cmid,
        $assignment,
        $discussion,
        $userid,
        $objectid,
        $docid,
        $status,
        $auto = false,
        $errormessage = ''
    ) {
        global $DB, $USER, $CFG;
        $sm = get_string_manager();
        if (!get_config('plagiarism_advacheck', 'log_actions')) {
            return;
        }
        if (!$auto) {
            $initid = $USER->id;
        } else {
            $initid = $auto;
        }
        // We enhance human-readable time with microseconds.
        $mt = microtime(true);
        $mt_int = intval($mt);
        $ms = round($mt - $mt_int, 4);
        $k = explode('.', $ms . '');
        $k[1] = isset($k[1]) ? $k[1] : 0;
        // Receiving settings for checking the current course module.
        $cm_sett = $DB->get_record('plagiarism_advacheck_course', ['cmid' => $cmid, 'courseid' => $courseid]);

        if ($cm_sett->mode == advacheck_constants::PLAGIARISM_ADVACHECK_AUTOMODE) {
            $mode = $sm->get_string('action_log_modeauto', 'plagiarism_advacheck', null, $CFG->lang);
        } else if ($cm_sett->mode == advacheck_constants::PLAGIARISM_ADVACHECK_MANUALMODE) {
            $mode = $sm->get_string('action_log_modeman', 'plagiarism_advacheck', null, $CFG->lang);
        } else {
            $mode = $sm->get_string('action_log_modeoff', 'plagiarism_advacheck', null, $CFG->lang);
        }
        $text = '-';
        $file = '-';
        if ($cm_sett->checktext) {
            $text = $sm->get_string('action_log_typetext', 'plagiarism_advacheck', null, $CFG->lang);
        }
        if ($cm_sett->checkfile) {
            $file = $sm->get_string('action_log_typefile', 'plagiarism_advacheck', null, $CFG->lang);
        }
        $doctype = "$text:$file";

        if ($cm_sett->add_to_index) {
            $inindex = $sm->get_string('action_log_indexyes', 'plagiarism_advacheck', null, $CFG->lang);
        } else {
            $inindex = $sm->get_string('action_log_indexno', 'plagiarism_advacheck', null, $CFG->lang);
        }

        if ($cm_sett->check_stud_lim) {
            $check_stud_lim = $sm->get_string('action_log_studcancheck', 'plagiarism_advacheck', null, $CFG->lang);
        } else {
            $check_stud_lim = $sm->get_string('action_log_studcanotcheck', 'plagiarism_advacheck', null, $CFG->lang);
        }

        $context = \context_course::instance($courseid, MUST_EXIST);
        if (has_capability('plagiarism/advacheck:checkadvacheck', $context)) {
            $checker = $sm->get_string('action_log_checkerteach', 'plagiarism_advacheck', null, $CFG->lang);
        } else {
            $checker = $sm->get_string('action_log_checkerstud', 'plagiarism_advacheck', null, $CFG->lang);
        }
        // If there is a task, get the answer parameters, the number of checks available for students.
        // If forum, then get the forum type.
        $a = $DB->get_record('assign', ['id' => $assignment], 'submissiondrafts');
        $mod_settings = '';
        if ($a) {
            if ($a->submissiondrafts) {
                $mod_settings = $sm->get_string('action_log_submissiondraftsno', 'plagiarism_advacheck', null, $CFG->lang);
            } else {
                $mod_settings = $sm->get_string('action_log_submissiondraftsyes', 'plagiarism_advacheck', null, $CFG->lang);
            }
            $mod_settings = $sm->get_string('action_log_assignsett', 'plagiarism_advacheck', null, $CFG->lang) . $mod_settings;
        }

        $sql = "SELECT f.type
              FROM {forum} f
              JOIN {forum_discussions} fd ON fd.forum = f.id
             WHERE fd.id = ?";
        $f = $DB->get_record_sql($sql, [$discussion]);

        if ($f) {
            $mod_settings = $sm->get_string('action_log_forumtype', 'plagiarism_advacheck', null, $CFG->lang) . $f->type;
        }

        $a = new \stdClass();
        $a->mode = $mode;
        $a->doctype = $doctype;
        $a->inindex = $inindex;
        $a->checker = $checker;
        $a->mod_settings = $mod_settings;
        $a->check_stud_lim = $check_stud_lim;

        $m = $sm->get_string('action_log_cmsettings', 'plagiarism_advacheck', $a, $CFG->lang);

        $action_str = '';
        switch ($action_id) {
            case 1:
                $action_str = $sm->get_string("action_add_to_queue", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 2:
                $action_str = $sm->get_string("action_start_download", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 3:
                $action_str = $sm->get_string("action_end_download", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 4:
                $action_str = $sm->get_string("action_start_verification", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 5:
                $action_str = $sm->get_string("action_end_verification", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 6:
                $action_str = $sm->get_string("action_start_removing_from_index", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 7:
                $action_str = $sm->get_string("action_end_removing_from_index", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 8:
                $action_str = $sm->get_string("action_placement_to_index", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 9:
                $action_str = $sm->get_string("action_result_updated", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 10:
                $action_str = $sm->get_string("action_start_doc_processing", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 11:
                $action_str = $sm->get_string("action_start_loading_doc_fields", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 12:
                $action_str = $sm->get_string("action_end_loading_doc_fields", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 13:
                $action_str = $sm->get_string("action_end_doc_processing", 'plagiarism_advacheck', null, $CFG->lang);
                break;
            case 14:
                $action_str = $sm->get_string("action_error_received", 'plagiarism_advacheck', null, $CFG->lang);
                break;
        }

        $row['docid'] = $docid;
        $row['externalid'] = empty($externalid) ? '' : $externalid;
        $row['reportedit'] = $reportedit;
        $row['time_action'] = $mt;
        $row['time_action_hr'] = date('d-m-Y H:i:s', $mt_int) . ".$k[1]";
        $row['status'] = $status;
        $row['action'] = $action_str;
        $row['courseid'] = $courseid;
        $row['cmid'] = $cmid;
        $row['assignment'] = $assignment;
        $row['answerid'] = $objectid;
        $row['discussion'] = $discussion;
        $row['userid'] = $userid;
        $row['verifier_initiator'] = $initid;
        $row['errormessage'] = $errormessage;
        $row['cmsettings'] = $m;
        $DB->insert_record('plagiarism_advacheck_act_log', (object) $row);
    }
}