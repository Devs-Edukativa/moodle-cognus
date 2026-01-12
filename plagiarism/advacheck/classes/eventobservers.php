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
 * Event handlers for plagiarism_advacheck.
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck;


use context;
use context_course;
use plagiarism_advacheck\local\advacheck_constants;

require_once "local/constants.php";

defined('MOODLE_INTERNAL') || die();
class eventobservers
{
    public static function is_allow_file_type($filetype)
    {
        $allowfiletypes = get_config('plagiarism_advacheck', 'allow_file_types');
        $allowfiletypes = str_replace(' ', '', mb_strtolower($allowfiletypes));
        return mb_strpos($allowfiletypes, mb_strtolower($filetype));
    }
    /**
     * Handling the response sending event in the quiz.
     *
     * @param \mod_quiz\event\attempt_submitted $event Quiz response event object.
     */
    public static function quiz_essay_submit(\mod_quiz\event\attempt_submitted $event)
    {

        global $DB;
        // We took the plugin settings.
        $plugin_cfg = get_config('plagiarism_advacheck');
        // Is the plugin enabled?
        if (empty($plugin_cfg->enabled)) {
            return true;
        }
        // Is quiz validation enabled?
        if (empty($plugin_cfg->check_quiz)) {
            return true;
        }
        // We took the plugin settings from the course for this lesson.
        $course_cfg = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $event->courseid, 'cmid' => $event->contextinstanceid], '*');
        // If the check is disabled, we exit.
        if (empty($course_cfg->mode)) {
            return true;
        }

        // Let's delete previous editions.
        self::delete_prev($event->component, 0, $event->objectid, $event->userid, advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ, $event->other['quizid']);
        self::delete_prev($event->component, 0, $event->objectid, $event->userid, advacheck_constants::PLAGIARISM_ADVACHECK_FILE, $event->other['quizid']);
        // Let's delete previous editions; the type of document is not important for the forum. delete_prev is called only once for it.
        // Let's get the course context.
        $context_course = context_course::instance($event->courseid, MUST_EXIST);
        // If file checking is enabled.
        if (!empty($course_cfg->checkfile)) {
            $sql =
                "SELECT DISTINCT qas.id, att.attempt
                   FROM {question_attempt_steps} qas 
                   JOIN {question_attempt_step_data} asd ON qas.id = asd.attemptstepid AND asd.name = 'attachments'
                   JOIN {question_attempts} qa ON qas.questionattemptid = qa.id
                   JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                   JOIN {quiz_attempts} att ON qa.questionusageid = att.uniqueid
                  WHERE att.id = ? 
                        AND qas.userid = ?";

            $essay_files = $DB->get_records_sql($sql, [$event->objectid, $event->userid]);
            foreach ($essay_files as $ef) {

                // We receive attachment files for the response.
                $attempt_files = get_file_storage()->get_area_files($event->contextid, 'question', 'response_attachments', $ef->id);

                // There may be several of them, so we work in a cycle.
                foreach ($attempt_files as $f) {
                    // If there is no file or it is a folder, then move on to the next one. file.
                    if (!$f || $f->is_directory()) {
                        continue;
                    }
                    // Let's check the length of the content in the file.
                    $content = $f->get_content();
                    if (mb_strlen($content) == 0) {
                        // Add to the queue with the status - insufficient number of words PLAGIARISM_ADVACHECK_LESSNWORDS.
                        self::add_to_queue(
                            advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                            $f->get_id(),
                            advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                            $ef->id,
                            0,
                            $f->get_userid(),
                            0,
                            0,
                            $event->courseid,
                            $event->contextinstanceid
                        );
                        continue;
                    }
                    // Retrieving the file type.
                    $filetype = substr(strrchr($f->get_filename(), "."), 1) . ',';
                    // Checking the file extension.
                    if (self::is_allow_file_type($filetype) === false) {
                        // Add to the queue with the status invalid file type.
                        self::add_to_queue(
                            advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                            $f->get_id(),
                            advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE,
                            $ef->id,
                            0,
                            $f->get_userid(),
                            0,
                            0,
                            $event->courseid,
                            $event->contextinstanceid
                        );
                        continue;
                    }
                    if (self::can_not_checked_by($context_course, $event->userid)) {
                        // Add to the queue with the status - no right to be verified.
                        self::add_to_queue(
                            advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                            $f->get_id(),
                            advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                            $ef->id,
                            0,
                            $f->get_userid(),
                            0,
                            0,
                            $event->courseid,
                            $event->contextinstanceid
                        );
                        continue;
                    }
                    // Add it to the queue with the status - awaiting unloading.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                        $f->get_id(),
                        advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD,
                        $ef->id,
                        $event->timecreated,
                        $f->get_userid(),
                        0,
                        0,
                        $event->courseid,
                        $event->contextinstanceid,
                        0,
                        $ef->attempt
                    );
                }
            }
        }

        // If text checking is enabled.
        if (!empty($course_cfg->checktext)) {
            // Based on the test attempt ID, we get the ID of the answer to the essay type question and the text that we will receive in get_links.
            $sql =
                "SELECT DISTINCT qas.id, qa.responsesummary, att.attempt
                   FROM {question_attempt_steps} qas 
                   JOIN {question_attempt_step_data} asd ON qas.id = asd.attemptstepid AND asd.name = 'answer'
                   JOIN {question_attempts} qa ON qas.questionattemptid = qa.id
                   JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                   JOIN {quiz_attempts} att ON qa.questionusageid = att.uniqueid
                  WHERE att.id = ?
                        AND qas.userid = ?";
            $essay_texts = $DB->get_records_sql($sql, [$event->objectid, $event->userid]);
            foreach ($essay_texts as $et) {
                // Let's take usa1-hash.
                $hash = \plagiarism_advacheck\local\document_queue_manager::get_strip_text_content_hash($et->responsesummary);
                if ($et->responsesummary == '') {
                    // Let's add it to the queue with the status - no right to be checked, so that the message Without checking (number of words <n) is not displayed.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ,
                        $hash,
                        advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                        $et->id,
                        0,
                        $event->userid,
                        0,
                        0,
                        $event->courseid,
                        $event->contextinstanceid
                    );
                    continue;
                }
                // Let's check whether this message (from a teacher or administrator) needs to be checked in the AP.
                if (self::can_not_checked_by($context_course, $event->userid)) {
                    // Add to the queue with the status - no right to be verified.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ,
                        $hash,
                        advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                        $et->id,
                        0,
                        $event->userid,
                        0,
                        0,
                        $event->courseid,
                        $event->contextinstanceid
                    );
                    continue;
                }
                // Let's check the number of words in the answer.
                if (count_words(strip_tags($et->responsesummary)) < (int) $plugin_cfg->min_len_str) {
                    // Add to queue with status - insufficient number of words.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ,
                        $hash,
                        advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                        $et->id,
                        0,
                        $event->userid,
                        0,
                        0,
                        $event->courseid,
                        $event->contextinstanceid
                    );
                    continue;
                }
                // Add it to the queue with status 2 - awaiting unloading.
                self::add_to_queue(
                    advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ,
                    $hash,
                    advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD,
                    $et->id,
                    $event->timecreated,
                    $event->userid,
                    0,
                    0,
                    $event->courseid,
                    $event->contextinstanceid,
                    0,
                    $et->attempt
                );
            }
        }

        return true;
    }

    /**
     * Handling the event of submitting work at a workshop.
     *
     * @param \mod_workshop\event\assessable_uploaded $event Workshop response event object.
     */
    public static function workshop_save_answer(\mod_workshop\event\assessable_uploaded $event)
    {
        global $DB;
        // We took the plugin settings.
        $plugin_cfg = get_config('plagiarism_advacheck');

        if (empty($plugin_cfg->enabled)) {
            return true;
        }
        if (empty($plugin_cfg->check_workshop)) {
            return true;
        }
        // We took the course check settings for this lesson.
        $course_cfg = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $event->courseid, 'cmid' => $event->contextinstanceid], '*');
        // If the check is disabled, we exit.
        if (empty($course_cfg->mode)) {
            return true;
        }
        // We remembered how many times we checked the work for the files.
        $cf = $DB->get_record(
            'plagiarism_advacheck_docs',
            ['cmid' => $event->contextinstanceid, 'userid' => $event->userid, 'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_FILE],
            'stud_check',
            IGNORE_MULTIPLE
        );
        $cf = $cf ? $cf->stud_check : 0;
        // We remembered how many times we checked the work for the text.
        $ct = $DB->get_record(
            'plagiarism_advacheck_docs',
            ['cmid' => $event->contextinstanceid, 'userid' => $event->userid, 'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP],
            'stud_check',
            IGNORE_MULTIPLE
        );
        $ct = $ct ? $ct->stud_check : 0;

        // Let's delete previous editions.
        self::delete_prev($event->component, 0, $event->objectid, $event->userid, advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP);
        self::delete_prev($event->component, 0, $event->objectid, $event->userid, advacheck_constants::PLAGIARISM_ADVACHECK_FILE);
        // Let's get the course context.
        $context_course = context_course::instance($event->courseid, MUST_EXIST);

        // If file checking is enabled.
        if (!empty($course_cfg->checkfile)) {
            // We look at the hashes of the paths to the downloaded files.
            // There can be several of them - that's why it works in a cycle.
            foreach ($event->other['pathnamehashes'] as $hash) {
                // We get the file by hash.
                $f = get_file_storage()->get_file_by_hash($hash);
                // If there is no file or it is a folder, then move on to the next file.
                if (!$f || $f->is_directory()) {
                    continue;
                }
                // Let's check the length of the content in the file.
                $content = $f->get_content();
                if (mb_strlen($content) == 0) {
                    // Add to the queue with the status - insufficient number of words PLAGIARISM_ADVACHECK_LESSNWORDS.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                        $f->get_id(),
                        advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                        $event->objectid,
                        0,
                        $f->get_userid(),
                        0,
                        0,
                        $event->courseid,
                        $event->contextinstanceid,
                        $cf
                    );
                    continue;
                }
                // Retrieving the file type.
                $filetype = substr(strrchr($f->get_filename(), "."), 1) . ',';
                // Checking the file extension.
                if (self::is_allow_file_type($filetype) === false) {
                    // Add to the queue with the status invalid file type.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                        $f->get_id(),
                        advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE,
                        $event->objectid,
                        0,
                        $f->get_userid(),
                        0,
                        0,
                        $event->courseid,
                        $event->contextinstanceid,
                        $cf
                    );
                    continue;
                }
                if (self::can_not_checked_by($context_course, $event->userid)) {
                    // Add to the queue with the status - no right to be verified.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                        $f->get_id(),
                        advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                        $event->objectid,
                        0,
                        $f->get_userid(),
                        0,
                        0,
                        $event->courseid,
                        $event->contextinstanceid,
                        $cf
                    );
                    continue;
                }
                // Add it to the queue with the status - awaiting unloading.
                self::add_to_queue(
                    advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                    $f->get_id(),
                    advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD,
                    $event->objectid,
                    $event->timecreated,
                    $f->get_userid(),
                    0,
                    0,
                    $event->courseid,
                    $event->contextinstanceid,
                    $cf
                );
            }
        }
        // If text checking is not enabled, then we simply exit.
        if (empty($course_cfg->checktext)) {
            return true;
        }
        // We check for a written response.
        if (empty($event->other['content'])) {
            return true;
        }
        // Convert all relative links to absolute ones.
        // We perform the same text transformations as before calling get__links.
        $content = file_rewrite_pluginfile_urls(
            $event->other['content'],
            'pluginfile.php',
            $event->contextid,
            'mod_workshop',
            'post',
            $event->contextinstanceid
        );
        // Let's take usa1-hash.
        $hash = \plagiarism_advacheck\local\document_queue_manager::get_strip_text_content_hash($event->other['content']);
        // Let's check whether this message (from a teacher or administrator) needs to be checked in the AP.
        if (self::can_not_checked_by($context_course, $event->userid)) {
            // Add to the queue with the status - no right to be verified.
            self::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                $event->objectid,
                0,
                $event->userid,
                0,
                0,
                $event->courseid,
                $event->contextinstanceid,
                $ct
            );
            return;
        }
        // Let's check the number of words in the answer.
        if (count_words(strip_tags($event->other['content'])) < (int) $plugin_cfg->min_len_str) {
            // Add to queue with status - insufficient number of words.
            self::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                $event->objectid,
                0,
                $event->userid,
                0,
                0,
                $event->courseid,
                $event->contextinstanceid,
                $ct
            );
            return;
        }
        // Add it to the queue with status 2 - awaiting unloading.
        self::add_to_queue(
            advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP,
            $hash,
            advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD,
            $event->objectid,
            $event->timecreated,
            $event->userid,
            0,
            0,
            $event->courseid,
            $event->contextinstanceid,
            $ct
        );
    }

    /**
     * Handling the event of sending a response on the forum.
     *
     * @param \mod_forum\event\assessable_uploaded $event Forum response event object.
     */
    public static function forum_post_upload(\mod_forum\event\assessable_uploaded $event)
    {
        global $DB;
        // We took the plugin settings.
        $plugin_cfg = get_config('plagiarism_advacheck');

        if (empty($plugin_cfg->enabled)) {
            return true;
        }
        if (empty($plugin_cfg->check_forum)) {
            return true;
        }
        // We took the plugin settings from the course for this lesson.
        $course_cfg = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $event->courseid, 'cmid' => $event->contextinstanceid], '*');
        // If the check is disabled, we exit.
        if (empty($course_cfg->mode)) {
            return true;
        }
        // Let's delete previous editions.
        self::delete_prev($event->component, $event->other['discussionid'], $event->objectid, $event->userid, advacheck_constants::PLAGIARISM_ADVACHECK_FORUM);
        self::delete_prev($event->component, $event->other['discussionid'], $event->objectid, $event->userid, advacheck_constants::PLAGIARISM_ADVACHECK_FILE);
        // Let's break down the context of the course.
        $context_course = context_course::instance($event->courseid, MUST_EXIST);
        // Let's request the modification date of the message; you can also use $event->timecreated, but it's better to take it from the message itself.
        $timepost = $DB->get_record('forum_posts', ['id' => $event->objectid], 'modified')->modified;
        // If file checking is enabled.
        if (!empty($course_cfg->checkfile)) {
            // We look at the hashes of the paths to the downloaded files.
            // There can be several of them - that’s why it works in a loop.
            foreach ($event->other['pathnamehashes'] as $hash) {
                // get the file by hash.
                $f = get_file_storage()->get_file_by_hash($hash);
                // If there is no file or it is a folder, then move on to the next file.
                if (!$f || $f->is_directory()) {
                    continue;
                }
                // Let's check the length of the content in the file.
                $content = $f->get_content();
                if (mb_strlen($content) == 0) {
                    // Add to the queue with the status - insufficient number of words PLAGIARISM_ADVACHECK_LESSNWORDS.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                        $f->get_id(),
                        advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                        $event->objectid,
                        0,
                        $f->get_userid(),
                        0,
                        $event->other['discussionid'],
                        $event->courseid,
                        $event->contextinstanceid
                    );
                    continue;
                }
                // Retrieving the file type.
                $filetype = substr(strrchr($f->get_filename(), "."), 1) . ',';
                // Check file extension.
                if (self::is_allow_file_type($filetype) === false) {
                    // Add to the queue with the status invalid file type.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                        $f->get_id(),
                        advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE,
                        $event->objectid,
                        0,
                        $f->get_userid(),
                        0,
                        $event->other['discussionid'],
                        $event->courseid,
                        $event->contextinstanceid
                    );
                    continue;
                }
                if (self::can_not_checked_by($context_course, $event->userid)) {
                    // Add to the queue with the status - no right to be verified.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                        $f->get_id(),
                        advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                        $event->objectid,
                        0,
                        $f->get_userid(),
                        0,
                        $event->other['discussionid'],
                        $event->courseid,
                        $event->contextinstanceid
                    );
                    continue;
                }
                // Add it to the queue with the status - awaiting unloading.
                self::add_to_queue(
                    advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                    $f->get_id(),
                    advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD,
                    $event->objectid,
                    $timepost,
                    $f->get_userid(),
                    0,
                    $event->other['discussionid'],
                    $event->courseid,
                    $event->contextinstanceid
                );
            }
        }
        // If text checking is not enabled, then we simply exit.
        if (empty($course_cfg->checktext)) {
            return true;
        }
        // We check for a written response.
        if (empty($event->other['content'])) {
            return true;
        }
        // Let's take usa1-hash.
        $hash = \plagiarism_advacheck\local\document_queue_manager::get_strip_text_content_hash($event->other['content']);
        // Let's check whether this message (from a teacher or administrator) needs to be checked in the AP.
        if (self::can_not_checked_by($context_course, $event->userid)) {
            // Add to the queue with the status - no right to be verified.
            self::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_FORUM,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                $event->objectid,
                0,
                $event->userid,
                0,
                $event->other['discussionid'],
                $event->courseid,
                $event->contextinstanceid
            );
            return;
        }
        // Let's check the number of words in the answer.
        if (count_words(strip_tags($event->other['content'])) < (int) $plugin_cfg->min_len_str) {
            // Add to queue with status - insufficient number of words.
            self::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_FORUM,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                $event->objectid,
                0,
                $event->userid,
                0,
                $event->other['discussionid'],
                $event->courseid,
                $event->contextinstanceid
            );
            return;
        }
        // Add it to the queue with status 2 - awaiting unloading.
        self::add_to_queue(
            advacheck_constants::PLAGIARISM_ADVACHECK_FORUM,
            $hash,
            advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD,
            $event->objectid,
            $timepost,
            $event->userid,
            0,
            $event->other['discussionid'],
            $event->courseid,
            $event->contextinstanceid
        );
    }

    /**
     * Processing the event of loading a text response in a task.
     *
     * @param \assignsubmission_onlinetext\event\assessable_uploaded $event Event object.
     */
    public static function assign_text_save(\assignsubmission_onlinetext\event\assessable_uploaded $event)
    {
        global $DB;
        // We took the plugin settings.
        $plugin_cfg = get_config('plagiarism_advacheck');
        // Let's check if the plugin is enabled.
        if (empty($plugin_cfg->enabled)) {
            return true;
        }
        // Is task checking enabled?
        if (empty($plugin_cfg->check_assign)) {
            return true;
        }
        // We took the plugin settings from the course for this lesson.
        $course_cfg = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $event->courseid, 'cmid' => $event->contextinstanceid], '*');
        // If the check is disabled, we exit.
        if (empty($course_cfg->mode)) {
            return true;
        }
        // If text checking is not enabled, then we simply exit.
        if (empty($course_cfg->checktext)) {
            return true;
        }
        // We check for a written response.
        if (empty($event->other['content'])) {
            return true;
        }
        // Get the course context.
        $context_course = context_course::instance($event->courseid, MUST_EXIST);
        // Let's ask for information about the response.
        $ass_sub = $DB->get_record('assign_submission', ['id' => $event->objectid]);
        $ass_sub->attemptnumber;

        // We remembered how many times the student checked the draft.
        $c = $DB->get_record(
            'plagiarism_advacheck_docs',
            ['cmid' => $event->contextinstanceid, 'userid' => $event->userid, 'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN],
            'stud_check',
            IGNORE_MULTIPLE
        );
        $c = $c ? $c->stud_check : 0;

        // Let's delete previous editions of the text response.
        self::delete_prev($event->component, $ass_sub->assignment, $event->objectid, $event->userid, advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN);
        // Let's take a hash.
        $hash = \plagiarism_advacheck\local\document_queue_manager::get_strip_text_content_hash($event->other['content']);
        // Let's check whether this message (from a teacher or administrator) needs to be checked in the AP.
        if (self::can_not_checked_by($context_course, $event->userid)) {
            // Add to the queue with the status - no right to be verified.
            self::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                $event->objectid,
                0,
                $event->userid,
                $ass_sub->assignment,
                0,
                $event->courseid,
                $event->contextinstanceid
            );
            return;
        }
        // Let's check the number of words in the answer.
        if (count_words(strip_tags($event->other['content'])) < (int) $plugin_cfg->min_len_str) {
            // Add to queue with status - insufficient number of words.
            self::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                $event->objectid,
                0,
                $event->userid,
                $ass_sub->assignment,
                0,
                $event->courseid,
                $event->contextinstanceid
            );
            return;
        }
        // Add to queue with status 1 - waiting for response to be blocked.
        self::add_to_queue(
            advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN,
            $hash,
            advacheck_constants::PLAGIARISM_ADVACHECK_WAITBLOCK,
            $event->objectid,
            $ass_sub->timemodified,
            $event->userid,
            $ass_sub->assignment,
            0,
            $event->courseid,
            $event->contextinstanceid,
            $c,
            $ass_sub->attemptnumber
        );
    }

    /**
     * Handling the file download event in a task.
     *
     * @param \assignsubmission_file\event\assessable_uploaded $event Event object.
     */
    public static function assign_file_upload(\assignsubmission_file\event\assessable_uploaded $event)
    {
        global $DB;
        // We took the plugin settings.
        $plugin_cfg = get_config('plagiarism_advacheck');
        // Is the plugin enabled?
        if (empty($plugin_cfg->enabled)) {
            return true;
        }
        // Is task checking enabled?
        if (empty($plugin_cfg->check_assign)) {
            return true;
        }
        // We took the plugin settings from the course for this lesson.
        $course_cfg = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $event->courseid, 'cmid' => $event->contextinstanceid]);
        // If the check is disabled, we exit.
        if (empty($course_cfg->mode)) {
            return true;
        }
        // Get the course context.
        $context_course = context_course::instance($event->courseid, MUST_EXIST);

        // Let's ask for information about the response.
        $ass_sub = $DB->get_record('assign_submission', ['id' => $event->objectid]);

        // We remembered how many times the student checked the draft.
        $c = $DB->get_record(
            'plagiarism_advacheck_docs',
            ['cmid' => $event->contextinstanceid, 'userid' => $event->userid, 'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_FILE],
            'stud_check',
            IGNORE_MULTIPLE
        );
        $c = $c ? $c->stud_check : 0;
        // We will delete previous editions.
        self::delete_prev($event->component, $ass_sub->assignment, $event->objectid, $event->userid, advacheck_constants::PLAGIARISM_ADVACHECK_FILE);
        // If file checking is enabled.
        if (!empty($course_cfg->checkfile)) {
            // We look at the hashes of the paths to the downloaded files.
            // There can be several of them - that’s why it works in a loop.
            foreach ($event->other['pathnamehashes'] as $hash) {
                // Get file by hash.
                $f = get_file_storage()->get_file_by_hash($hash);
                // If there is no file or it is a folder, then move on to the next one. file.
                if (!$f || $f->is_directory()) {
                    continue;
                }
                // Let's check the length of the content in the file.
                $content = $f->get_content();
                if (mb_strlen($content) == 0) {
                    // Add to the queue with the status - insufficient number of words PLAGIARISM_ADVACHECK_LESSNWORDS.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                        $f->get_id(),
                        advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                        $event->objectid,
                        0,
                        $f->get_userid(),
                        $ass_sub->assignment,
                        0,
                        $event->courseid,
                        $event->contextinstanceid,
                    );
                    continue;
                }
                // Retrieving the file type.
                $filetype = substr(strrchr($f->get_filename(), "."), 1) . ',';
                // Check file extension.
                if (self::is_allow_file_type($filetype) === false) {
                    // Add to the queue with the status invalid file type.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                        $f->get_id(),
                        advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE,
                        $event->objectid,
                        0,
                        $f->get_userid(),
                        $ass_sub->assignment,
                        0,
                        $event->courseid,
                        $event->contextinstanceid
                    );
                    continue;
                }
                if (self::can_not_checked_by($context_course, $event->userid)) {
                    // Add to the queue with the status - no right to be verified.
                    self::add_to_queue(
                        advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                        $f->get_id(),
                        advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                        $event->objectid,
                        0,
                        $f->get_userid(),
                        $ass_sub->assignment,
                        0,
                        $event->courseid,
                        $event->contextinstanceid
                    );
                    continue;
                }
                // Let's add it to the queue with the status - awaiting blocking.
                self::add_to_queue(
                    advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                    $f->get_id(),
                    advacheck_constants::PLAGIARISM_ADVACHECK_WAITBLOCK,
                    $event->objectid,
                    $ass_sub->timemodified,
                    $f->get_userid(),
                    $ass_sub->assignment,
                    0,
                    $event->courseid,
                    $event->contextinstanceid,
                    $c,
                    $ass_sub->attemptnumber
                );
            }
        }
    }

    /**
     * Processing the response confirmation event.
     *
     * @param \mod_assign\event\assessable_submitted $event Event object.
     */
    public static function assign_submission_submit(\mod_assign\event\assessable_submitted $event)
    {
        global $DB;
        // We took the plugin settings.
        $plugin_cfg = get_config('plagiarism_advacheck');
        // Is the plugin enabled?.
        if (empty($plugin_cfg->enabled)) {
            return true;
        }
        // Is task checking enabled?
        if (empty($plugin_cfg->check_assign)) {
            return true;
        }
        // Take the course check settings for this assignment.
        $course_cfg = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $event->courseid, 'cmid' => $event->contextinstanceid]);
        // If the check is disabled, we exit.
        if (empty($course_cfg->mode)) {
            return true;
        }
        // Let's ask for information about the response.
        $ass_sub = $DB->get_record('assign_submission', ['id' => $event->objectid]);
        // If required, click the send button.
        $submissiondrafts = $DB->get_record('assign', ['id' => $ass_sub->assignment], 'submissiondrafts')->submissiondrafts;
        // Check if the answer is blocked.
        $locked = $DB->get_record(
            'assign_user_flags',
            ['assignment' => $ass_sub->assignment, 'userid' => $event->userid],
            'locked'
        );
        // If you do not need to press the send button and the response is not blocked.
        if (empty($submissiondrafts) && empty($locked->locked)) {
            return true;
        }
        // We change from “waiting for blocking” to “waiting for unloading” and indicate the status in the condition because students can check their draft.
        // We do this so that the teacher does not check an already verified answer.        
        $cond = ['assignment' => $ass_sub->assignment, 'userid' => $event->userid, 'answerid' => $event->objectid, 'status' => advacheck_constants::PLAGIARISM_ADVACHECK_WAITBLOCK];

        // Let's write down that they are waiting for loading.
        $DB->set_field('plagiarism_advacheck_docs', 'status', advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD, $cond);
        // Because the status has already changed.
        $cond['status'] = advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD;
        $DB->set_field('plagiarism_advacheck_docs', 'error', '', $cond);
    }

    /**
     * Handling a response blocking event.
     *
     * @param \mod_assign\event\submission_locked $event Event object.
     */
    public static function assign_submission_locked(\mod_assign\event\submission_locked $event)
    {
        global $DB;
        // We took the plugin settings.
        $plugin_cfg = get_config('plagiarism_advacheck');
        // Is the plugin enabled?.
        if (empty($plugin_cfg->enabled)) {
            return true;
        }
        // Is task checking enabled?
        if (empty($plugin_cfg->check_assign)) {
            return true;
        }
        // We took the plugin settings from the course for this forum.
        $course_cfg = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $event->courseid, 'cmid' => $event->contextinstanceid]);
        // If the check is disabled, we exit.
        if (empty($course_cfg->mode)) {
            return true;
        }
        // We took information about the user's response. sorted by number of attempts.
        $sql =
            "SELECT *
               FROM {assign_submission} ass
              WHERE ass.assignment =:assignment 
                    AND ass.userid =:userid
           ORDER BY ass.attemptnumber";
        $cond = ['assignment' => $event->objectid, 'userid' => $event->relateduserid];
        // Let's ask for information about the response.
        $ass_sub = $DB->get_record_sql($sql, $cond, IGNORE_MULTIPLE);

        // If the documents were in the “awaiting blocking” status, then we do not change the status so that the teacher does not check an already verified answer.    
        $conds = ['assignment' => $ass_sub->assignment, 'userid' => $event->relateduserid, 'cmid' => $event->contextinstanceid, 'status' => advacheck_constants::PLAGIARISM_ADVACHECK_WAITBLOCK];
        $DB->set_field('plagiarism_advacheck_docs', 'status', advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD, $conds);

        // Under the specified conditions, both the response file and the response text are marked.
        $cond = ['assignment' => $ass_sub->assignment, 'userid' => $event->relateduserid, 'cmid' => $event->contextinstanceid];
        $DB->set_field('plagiarism_advacheck_docs', 'error', '', $cond);
    }

    /**
     * Handling the response unlock event.
     *
     * @param \mod_assign\event\submission_unlocked $event Event object.
     */
    public static function assign_submission_unlocked(\mod_assign\event\submission_unlocked $event)
    {
        global $DB;
        // We took the plugin settings.
        $plugin_cfg = get_config('plagiarism_advacheck');
        // Is the plugin enabled?.
        if (empty($plugin_cfg->enabled)) {
            return true;
        }
        // Is task checking enabled?
        if (empty($plugin_cfg->check_assign)) {
            return true;
        }
        // We took the course verification settings for this assignment.
        $course_cfg = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $event->courseid, 'cmid' => $event->contextinstanceid]);
        // If the check is disabled, we exit.
        if (empty($course_cfg->mode)) {
            return true;
        }
        // We took information about the user's response. sorted by number of attempts.
        $sql =
            "SELECT *
               FROM {assign_submission} ass
              WHERE ass.assignment =:assignment 
                    AND ass.userid =:userid
           ORDER BY ass.attemptnumber";
        $cond = ['assignment' => $event->objectid, 'userid' => $event->relateduserid];
        // Let's ask for information about the response.
        $ass_sub = $DB->get_record_sql($sql, $cond, IGNORE_MULTIPLE);
        // Under the specified conditions, both the response file and the response text are marked.
        $cond = ['assignment' => $ass_sub->assignment, 'userid' => $event->relateduserid, 'cmid' => $event->contextinstanceid];
        // Let's write down what is waiting for blocking.
        $DB->set_field('plagiarism_advacheck_docs', 'status', advacheck_constants::PLAGIARISM_ADVACHECK_WAITBLOCK, $cond);
        $DB->set_field('plagiarism_advacheck_docs', 'error', '', $cond);
    }

    /**
     * Looks for a message or reply where the creation time was earlier than the editing time.
     * If the document is being edited, it removes previous entries for this response from the queue.
     *
     * @global \moodle_database $DB driver object.
     * @param string $component component name.
     * @param int $compid component id.
     * @param int $objectid message/reply ID.
     * @param int $userid
     * @param string $doctype Document type: file/assignment/forum/essay/workshop.
     * @param int $quizid Id of quiz.
     */
    private static function delete_prev($component, $compid, $objectid, $userid, $doctype, $quizid = 0)
    {
        global $DB;
        $params = [];
        $params[] = $objectid;
        if ($component == 'mod_forum') {
            // We took information about a message where the editing time is different from the creation time.
            $sql =
                "SELECT *
                   FROM {forum_posts} fp
                  WHERE fp.modified > fp.created 
                        AND fp.id = ?";
            $cond = ['discussion' => $compid];
        } else if ($component == 'assignsubmission_onlinetext' || $component == 'assignsubmission_file') {
            // We took information about the answer, where the editing time is different from the creation time.
            $sql =
                "SELECT *
                   FROM {assign_submission} ass
                  WHERE ass.timemodified > ass.timecreated 
                        AND ass.id = ?";
            $cond = ['assignment' => $compid];
        } else if ($component == 'mod_workshop') {
            // We took information about the answer, where the editing time is different from the creation time.
            $sql =
                "SELECT *
                   FROM {workshop_submissions} ws
                  WHERE ws.timemodified > ws.timecreated 
                        AND ws.id = ?";
        } else if ($component == 'mod_quiz') {
            $sql =
                "SELECT DISTINCT qas.id, qa.responsesummary as txt, qa.timemodified as time, qas.userid
                   FROM {question_attempts} qa 
                   JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                   JOIN {question_attempt_step_data} asd ON qas.id = asd.attemptstepid AND (asd.name = 'attachments' OR asd.name = 'answer')
                   JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                   JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                  WHERE quiza.quiz = ? 
                        AND qas.userid = ? ";
            $params[] = $quizid;
            $params[] = $userid;
        }
        // If the response was changed, then we will delete all entries from the queue with the ID of this message.
        if ($DB->record_exists_sql($sql, $params)) {
            $cond['answerid'] = $objectid;
            $cond['userid'] = $userid;
            $cond['doctype'] = $doctype;
            // Let's take a document that needs to be removed from the index.
            $doc = $DB->get_record('plagiarism_advacheck_docs', array_merge($cond, ['status' => advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX]));

            if ($doc) {
                \plagiarism_advacheck\local\upload_start_check_manual::remove_from_index($doc);
            }
            $DB->delete_records('plagiarism_advacheck_docs', $cond);
        }
    }

    /**
     * Checks whether a document can be sent for review from a given user in a given context?
     *
     * @param context $context context.
     * @param int $userid user id.
     * @return bool
     */
    public static function can_not_checked_by($context, $userid)
    {
        // Is the message from the admin?
        $isAdmin = is_siteadmin($userid);
        // Let's check whether this message (from a teacher or administrator) needs to be checked in the AP.
        $res = has_capability('plagiarism/advacheck:checkedby', $context, $userid);
        return !$res || $isAdmin;
    }

    /**
     * Adds a document to the queue with the specified status.
     *
     * @global \moodle_database $DB driver object
     * @param string $doctype Document type: file/assignment/forum/essay/workshop.
     * @param string $typeid File id/response hash
     * @param int $status Status of document
     * @param int $objectid Id of answer
     * @param int $timemodified Time answer modified
     * @param int $userid Id of document`s user
     * @param int $assign Id of assign
     * @param int $discussion Id of discussion
     * @param int $courseid Id of course
     * @param int $cmid Id of course module
     * @param int $stud_check Count of student checks
     * @param int $attempt Attempt number
     */
    public static function add_to_queue(
        $doctype,
        $typeid,
        $status,
        $objectid,
        $timemodified,
        $userid,
        $assign,
        $discussion,
        $courseid,
        $cmid,
        $stud_check = 0,
        $attempt = 0
    ) {
        global $DB;
        $sql =
            "SELECT cm.id, cm.instance
               FROM {course_modules} cm
               JOIN {modules} m ON cm.module = m.id
              WHERE cm.id = ? 
                    AND m.name = 'workshop'";
        $cm_wokshop = $DB->get_record_sql($sql, [$cmid]);
        if ($cm_wokshop) {
            $row['workshop'] = $cm_wokshop->instance;
        }

        $row['assignment'] = $assign;
        $row['discussion'] = $discussion;

        $error = '';
        switch ($status) {
            case advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE:
                $error = '-';
                break;
            case advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY:
                $error = "";
                break;
            case advacheck_constants::PLAGIARISM_ADVACHECK_WAITBLOCK:
                $error = '';
                break;
            case advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS:
                $error = get_string('min_len_str_info', 'plagiarism_advacheck', get_config('plagiarism_advacheck', 'min_len_str'));
                break;
        }
        $row['userid'] = $userid;
        $row['doctype'] = $doctype;
        $row['typeid'] = $typeid;
        $row['status'] = $status;
        // Message/response ID to search for.
        $row['answerid'] = $objectid;
        // Change time for sorting.
        $row['timeadded'] = $timemodified;
        $row['error'] = $error;
        $row['courseid'] = $courseid;
        $row['cmid'] = $cmid;
        $row['stud_check'] = $stud_check;
        $row['attemptnumber'] = $attempt;
        $DB->insert_record('plagiarism_advacheck_docs', (object) $row);
    }

}
