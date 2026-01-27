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
 * Completion Levels external services implementation.
 *
 * @package    block_completion_levels
 * @copyright  2022 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Completion Levels external services implementation class.
 *
 * @package    block_completion_levels
 * @copyright  2022 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_completion_levels_external extends external_api {

    /**
     * Parameters definition for delete_custom_pix.
     */
    public static function delete_custom_pix_parameters() {
        return new external_function_parameters([
                'contextid' => new external_value(PARAM_INT, 'Block context ID', VALUE_REQUIRED),
                'draftitemid' => new external_value(PARAM_INT, 'Draft file area ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Delete custom badges for a block instance.
     *
     * @param int $contextid Block context ID, in which files are stored.
     * @param int $draftitemid Draft area ID.
     * @return boolean true on success
     */
    public static function delete_custom_pix($contextid, $draftitemid) {
        global $USER;
        self::validate_parameters(self::delete_custom_pix_parameters(), [
                'contextid' => $contextid,
                'draftitemid' => $draftitemid,
        ]);
        $context = context::instance_by_id($contextid);
        self::validate_context($context);
        require_capability('moodle/block:edit', $context);
        $fs = get_file_storage();
        $success = $fs->delete_area_files($contextid, 'block_completion_levels', 'levels_pix');
        $success = $success && $fs->delete_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $draftitemid);
        return $success;
    }

    /**
     * Return true on success.
     */
    public static function delete_custom_pix_returns() {
        return new external_value(PARAM_BOOL, 'Whether operation was successful', VALUE_REQUIRED);
    }

    /**
     * Parameters definition for get_progress.
     */
    public static function get_progress_parameters() {
        return new external_function_parameters([
                'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
                'blockid' => new external_value(PARAM_INT, 'Block ID', VALUE_REQUIRED),
                'userid' => new external_value(PARAM_INT, 'User ID, 0: current user, -1: all users', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Compute and return progress of one or all users for a block.
     * @param int $courseid Course ID.
     * @param int $blockid Block ID.
     * @param int $userid User ID. 0 means current user, -1 means all users. Default 0.
     */
    public static function get_progress($courseid, $blockid, $userid) {
        global $DB, $USER;
        list($courseid, $blockid, $userid) = array_values(self::validate_parameters(self::get_progress_parameters(), [
                'courseid' => $courseid,
                'blockid' => $blockid,
                'userid' => $userid,
        ]));

        $context = context_block::instance($blockid);
        self::validate_context($context);

        $blockrecord = $DB->get_record('block_instances', [ 'id' => $blockid, 'blockname' => 'completion_levels' ]);
        if (!$blockrecord) {
            throw new moodle_exception('blockcannotread', '', '', $blockid);
        }
        $block = block_instance('completion_levels', $blockrecord);
        if ($userid == 0 || $userid == $USER->id) {
            if (!is_enrolled(context_course::instance($courseid), $USER->id, '', true)) {
                throw new moodle_exception('notenroled', 'completion');
            }
            $users = [ $USER->id => $USER ];
        } else if ($userid > 0) {
            require_capability('block/completion_levels:overview', $block->context);
            if (!is_enrolled(context_course::instance($courseid), $userid, '', true)) {
                throw new moodle_exception('usernotenroled', 'completion');
            }
            $fields = \core_user\fields::for_userpic()->get_sql('', false, '', '', false)->selects;
            $users = [ $userid => core_user::get_user($userid, $fields, MUST_EXIST) ];
        } else {
            require_capability('block/completion_levels:overview', $block->context);
            $users = block_completion_levels_get_users($courseid, $block->config);
        }
        $progresses = block_completion_levels_get_progress($block->config, array_keys($users), $courseid);

        $activities = block_completion_levels_get_tracked_activities($courseid, $block->config);
        $modinfo = get_fast_modinfo($courseid, -1);

        $result = [];
        foreach ($progresses as $uid => $progress) {
            $foruser = [
                    'userid' => $uid,
                    'fullname' => fullname($users[$uid]),
                    'overall_completion' => strip_tags($progress->display()),
            ];
            $foruser['activities'] = [];
            foreach ($activities as $activity) {
                $cm = $modinfo->get_cm($activity->id);
                $completion = $progress->completion_info($activity->id);
                $foruser['activities'][] = [
                        'cmid' => $activity->id,
                        'name' => $cm->get_formatted_name(),
                        'type' => $cm->modname,
                        'completion' => $completion,
                ];
            }
            $result[] = $foruser;
        }
        return $result;
    }

    /**
     * Structure returned by get_progress.
     */
    public static function get_progress_returns() {
        return new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'fullname' => new external_value(PARAM_RAW, 'User name'),
                'overall_completion' => new external_value(PARAM_RAW, 'User overall completion for this block instance'),
                'activities' => new external_multiple_structure(new external_single_structure([
                        'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                        'name' => new external_value(PARAM_RAW, 'Course module name'),
                        'type' => new external_value(PARAM_RAW, 'Course module type'),
                        'completion' => new external_value(PARAM_RAW, 'User completion status for this course module'),
                ])),
        ]));
    }
}
