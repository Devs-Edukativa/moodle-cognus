<?php
// This file is part of Moodle - https://moodle.org/
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
 * Observer for completion changes in course.
 * @package    block_completion_levels
 * @copyright  2022 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_levels;
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/completion_levels/locallib.php');

/**
 * Observer definition.
 * @copyright  2022 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_observer {
    /**
     * Observer called every time an activity completion is updated.
     * @param \core\event\course_module_completion_updated $event
     */
    public static function user_completion_updated(\core\event\course_module_completion_updated $event) {
        $eventdata = $event->get_record_snapshot('course_modules_completion', $event->objectid);

        $userid = $event->relateduserid;

        $module = get_coursemodule_from_id(null, $eventdata->coursemoduleid);
        $course = get_course($module->course);
        $completedcm = get_fast_modinfo($course, -1)->get_cm($module->id);

        self::process_user_data_updated($userid, $completedcm, $course->id, 0);
    }

    /**
     * Observer called every time an activity grade or grade item changes.
     * @param \core\event\user_graded $event
     */
    public static function user_grade_updated(\core\event\user_graded $event) {
        $grade = $event->get_grade();
        $gradeitem = $grade->grade_item;

        if ($gradeitem->itemtype != 'mod') {
            return;
        }

        $courseid = $gradeitem->courseid;
        $userid = $grade->userid;

        $gradedcm = get_fast_modinfo($courseid, -1)->get_instances_of($gradeitem->itemmodule)[$gradeitem->iteminstance];

        self::process_user_data_updated($userid, $gradedcm, $courseid, 1);
    }

    /**
     * Process that user completion or grade changed on a given course module.
     * @param number $userid
     * @param \cm_info $cm
     * @param number $courseid
     * @param number $trackingmethod Only blocks with this tracking method will be affected by the update:
     *  0 means completion mode (i.e. this user's completion has been updated),
     *  1 means grade mode (i.e. this user's grade has been updated).
     */
    protected static function process_user_data_updated($userid, $cm, $courseid, $trackingmethod) {
        global $DB;
        $coursecontext = \context_course::instance($courseid);
        $blockrecords = $DB->get_records('block_instances',
                [ 'blockname' => 'completion_levels', 'parentcontextid' => $coursecontext->id ]);

        $user = \core_user::get_user($userid);
        $course = get_course($courseid);

        foreach ($blockrecords as $blockrecord) {
            if (!empty($blockrecord->configdata)) {
                $block = block_instance('completion_levels', $blockrecord);
                if (isset($block->config->trackingmethod) && $block->config->trackingmethod == $trackingmethod) {
                    if (!empty($block->config->activity[$cm->id]['checkbox'])) {
                        // Completion updated for an activity that is tracked by this block.
                        // Check if the user has achieved 100% progress.
                        $progress = block_completion_levels_get_progress($block->config, $userid, $courseid);
                        if ($progress->percentage == 100) {
                            if (!empty($block->config->sendcompletionnotifications)) {
                                $notifiableusers = block_completion_levels_get_notifiable_users($courseid);
                                foreach ($notifiableusers as $notifiableuser) {
                                    if (!empty($block->config->sendnotificationsto[$notifiableuser->id])) {
                                        // Send a notification.
                                        // TODO check if notification has not already been sent recently?
                                        self::send_blockcompleted_notification($user, $cm, $course, $block, $notifiableuser);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Send a notification telling that a user has reached 100% on a completion_levels block.
     * @param \stdClass $subjectuser User database record of subject (will be the sender).
     * @param \cm_info $subjectcm
     * @param \stdClass $subjectcourse Course database record.
     * @param \block_base $subjectblock
     * @param \stdClass $touser User database record of recipient.
     */
    protected static function send_blockcompleted_notification($subjectuser, $subjectcm, $subjectcourse, $subjectblock, $touser) {
        // Set language to the recipient's preferred language.
        $oldforcelang = force_current_language($touser->lang);

        // Retrieve and format subject user name.
        $username = fullname($subjectuser);
        $userurl = new \moodle_url('/user/view.php', [ 'id' => $subjectuser->id ]);
        if (is_enrolled(\context_course::instance($subjectcourse->id), $subjectuser)) {
            $userurl->param('course', $subjectcourse->id);
        }
        $usernameurl = '[' . $username . '](' . ($userurl)->out(false) . ')';

        // Retrieve and format subject block name.
        $blockname = format_string($subjectblock->title);

        // Retrieve and format subject course name.
        $coursename = format_string($subjectcourse->fullname);
        $courseurl = course_get_url($subjectcourse);
        $coursenameurl = '[' . $coursename . '](' . $courseurl->out(false) . ')';

        // Retrieve and format subject cm name.
        $cmnameurl = $subjectcm->get_formatted_name();
        if ($subjectcm->url !== null) {
            $cmnameurl = '[' . $cmnameurl . '](' . $subjectcm->url->out(false) . ')';
        }

        // Build notification message (title, short message and full message).
        $a = [ 'coursename' => $coursename, 'blockname' => $blockname, 'username' => $username ];
        $title = get_string('message:blockcompleted:title', 'block_completion_levels', $a);
        $shortmessage = get_string('message:blockcompleted:shortmessage', 'block_completion_levels', $a);

        $a = [
                'coursename' => $coursenameurl,
                'blockname' => $blockname,
                'username' => $usernameurl,
                'modname' => $subjectcm->modfullname,
                'cmname' => $cmnameurl,
        ];
        $str = 'message:blockcompleted:fullmessage:' . ($subjectblock->config->trackingmethod == 0 ? 'completion' : 'grade');
        $fullmessage = get_string($str, 'block_completion_levels', $a);

        $message = new \core\message\message();
        $message->courseid          = $subjectcourse->id;
        $message->notification      = 1;
        $message->component         = 'block_completion_levels';
        $message->name              = 'blockcompleted'; // As declared in db/messages.php.
        $message->userfrom          = $subjectuser;
        $message->userto            = $touser;
        $message->subject           = $title;
        $message->fullmessage       = $fullmessage;
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml   = markdown_to_html($message->fullmessage);
        $message->smallmessage      = $shortmessage;
        $message->contexturlname    = $coursename;
        $message->contexturl        = $courseurl;

        // Send the notification.
        message_send($message);

        // Set language back to original setting.
        force_current_language($oldforcelang);
    }
}
