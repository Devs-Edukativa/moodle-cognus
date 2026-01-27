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
 * The class responsible for adding documents to the queue for upload 
 * for verification in the case when the verification plugin was enabled 
 * after students added their answers to the course module.
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\local;

use plagiarism_advacheck\eventobservers;

require_once "constants.php";
require_once "$CFG->dirroot/plagiarism/advacheck/classes/eventobservers.php";

/**
 * A class that adds documents to the verification queue.
 */
class document_queue_manager
{
    /**For course context */
    private $crs_ctxt;
    /**For course module context */
    private $mod_ctxt;
    /**For config settings plugin */
    private $plugin_cfg;
    /**For object of forum post/assign submition/ workshop submittion/ quiz essay text*/
    private $answertextobject;
    /**For course id */
    private $courseid;
    /**For course module id */
    private $cmid;

    /**
     * Get parametrs and settings
     * @param mixed $crs_ctxt Course context
     * @param mixed $mod_ctxt Coursemodule context
     * @param mixed $answertextobject Object with answer text
     * @param mixed $cmid Id of coursemodule
     * @param mixed $courseid Id of course
     */
    public function __construct($crs_ctxt, $mod_ctxt, $answertextobject, $cmid, $courseid)
    {
        $this->crs_ctxt = $crs_ctxt;
        $this->mod_ctxt = $mod_ctxt;
        $this->cmid = $cmid;
        $this->courseid = $courseid;
        $this->answertextobject = $answertextobject;
        $this->plugin_cfg = get_config('plagiarism_advacheck');

    }
    /**
     * Adds the message text to the queue for verify for forums.
     * After saving the module settings, if the check was enabled after students added answers.
     *
     * @global \moodle_database $DB
     * @return void
     */
    public function add_to_queue_forum_text()
    {
        global $DB;
        $hash = self::get_strip_text_content_hash($this->answertextobject->message);
        $recparams = [
            'typeid' => $hash,
            'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_FORUM,
            'userid' => $this->answertextobject->userid,
            'answerid' => $this->answertextobject->id,
        ];
        // Checking if there is a record with this hash.
        $rec = $DB->get_record(
            'plagiarism_advacheck_docs',
            $recparams
        );

        if ($rec) {
            return;
        }

        // Checking if there is a record with this hash by old algorithm
        $content = file_rewrite_pluginfile_urls($this->answertextobject->message, 'pluginfile.php', $this->mod_ctxt->id, 'mod_forum', 'post', $this->answertextobject->id);
        $content = trim($content);
        $rec = $DB->get_record(
            'plagiarism_advacheck_docs',
            ['typeid' => sha1($content), 'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_FORUM, 'userid' => $this->answertextobject->userid, 'answerid' => $this->answertextobject->id]
        );
        if ($rec) {
            // Write new hash only and return from function
            $DB->set_field('plagiarism_advacheck_docs', 'typeid', $hash, ['id' => $rec->id]);
            return;
        }

        // Checking whether this message (from a teacher or administrator) needs to be checked in AP?
        if (eventobservers::can_not_checked_by($this->crs_ctxt, $this->answertextobject->userid)) {
            // Add to the queue with the status - no right to be verified.
            eventobservers::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_FORUM,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                $this->answertextobject->id,
                0,
                $this->answertextobject->userid,
                0,
                $this->answertextobject->discussion,
                $this->courseid,
                $this->cmid
            );
            return;
        }
        if (count_words($this->answertextobject->message) < (int) $this->plugin_cfg->min_len_str) {
            eventobservers::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_FORUM,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                $this->answertextobject->id,
                0,
                $this->answertextobject->userid,
                0,
                $this->answertextobject->discussion,
                $this->courseid,
                $this->cmid
            );
            return;
        }
        eventobservers::add_to_queue(
            advacheck_constants::PLAGIARISM_ADVACHECK_FORUM,
            $hash,
            advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD,
            $this->answertextobject->id,
            $this->answertextobject->time,
            $this->answertextobject->userid,
            0,
            $this->answertextobject->discussion,
            $this->courseid,
            $this->cmid
        );
    }

    /**
     * Adds the essay response text to the review queue.
     *
     * @global \stdClass $CFG
     * @global \moodle_database $DB
     * @return void
     */
    public function add_to_queue_quiz_text()
    {
        global $CFG, $DB;

        if (!isset($this->answertextobject->txt)) {
            $this->answertextobject->txt = '';
        }

        $hash = self::get_strip_text_content_hash($this->answertextobject->txt);
        $recparams = [
            'typeid' => $hash,
            'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ,
            'userid' => $this->answertextobject->userid,
            'answerid' => $this->answertextobject->id
        ];
        // Checking if there is a record with this hash.
        $rec = $DB->get_record(
            'plagiarism_advacheck_docs',
            $recparams
        );

        if ($rec) {
            return;
        }

        // Checking if there is a record with hash of old algorithm.
        $rec = $DB->get_record(
            'plagiarism_advacheck_docs',
            ['typeid' => sha1($this->answertextobject->txt), 'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ, 'userid' => $this->answertextobject->userid, 'answerid' => $this->answertextobject->id]
        );
        if ($rec) {
            // Write new hash only and return from function
            $DB->set_field('plagiarism_advacheck_docs', 'typeid', $hash, ['id' => $rec->id]);
            return;
        }

        // Checking whether this message (from a teacher or administrator) needs to be checked in AP?
        if (eventobservers::can_not_checked_by($this->crs_ctxt, $this->answertextobject->userid)) {
            // Add to the queue with the status - no right to be verified.
            eventobservers::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                $this->answertextobject->id,
                0,
                $this->answertextobject->userid,
                0,
                0,
                $this->courseid,
                $this->cmid
            );
            return;
        }
        if (count_words($this->answertextobject->txt) < (int) $this->plugin_cfg->min_len_str) {
            // Adding to the queue with status - few words.
            eventobservers::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                $this->answertextobject->id,
                0,
                $this->answertextobject->userid,
                0,
                0,
                $this->courseid,
                $this->cmid
            );
            return;
        }

        $status = advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD;
        eventobservers::add_to_queue(
            advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ,
            $hash,
            $status,
            $this->answertextobject->id,
            $this->answertextobject->time,
            $this->answertextobject->userid,
            0,
            0,
            $this->courseid,
            $this->cmid
        );
    }

    /**
     * Adds the text of the workshop response to the queue for review
     *
     * @global \puserfield_form $CFG
     * @global \moodle_database $DB
     * @return void
     */
    public function add_to_queue_workshop_text()
    {
        global $CFG, $DB;

        $hash = self::get_strip_text_content_hash($this->answertextobject->txt);
        // Checking if there is a record with this hash.
        $rec = $DB->get_record(
            'plagiarism_advacheck_docs',
            [
                'typeid' => $hash,
                'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP,
                'userid' => $this->answertextobject->userid,
                'answerid' => $this->answertextobject->id
            ]
        );

        if ($rec) {
            return;
        }

        // Checking if there is a record with hash of old algorithm.
        $rec = $DB->get_record(
            'plagiarism_advacheck_docs',
            [
                'typeid' => sha1($this->answertextobject->txt),
                'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP,
                'userid' => $this->answertextobject->userid,
                'answerid' => $this->answertextobject->id
            ]
        );
        if ($rec) {
            // Write new hash only and return from function
            $DB->set_field('plagiarism_advacheck_docs', 'typeid', $hash, ['id' => $rec->id]);
            return;
        }

        // Checking whether this message (from a teacher or administrator) needs to be checked in AP?
        if (eventobservers::can_not_checked_by($this->crs_ctxt, $this->answertextobject->userid)) {
            // Add to the queue with the status - no right to be verified.
            eventobservers::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                $this->answertextobject->id,
                0,
                $this->answertextobject->userid,
                0,
                0,
                $this->courseid,
                $this->cmid
            );
            return;
        }
        if (count_words($this->answertextobject->txt) < (int) $this->plugin_cfg->min_len_str) {
            // Adding to the queue with status - few words.
            eventobservers::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                $this->answertextobject->id,
                0,
                $this->answertextobject->userid,
                0,
                0,
                $this->courseid,
                $this->cmid
            );
            return;
        }

        $status = advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD;
        eventobservers::add_to_queue(
            advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP,
            $hash,
            $status,
            $this->answertextobject->id,
            $this->answertextobject->time,
            $this->answertextobject->userid,
            0,
            0,
            $this->courseid,
            $this->cmid
        );
    }

    /**
     * Adds the text of the response to the task to the queue for verification.
     *
     * @global \stdClass $CFG
     * @global \moodle_database $DB
     * @return mixed
     */
    public function add_to_queue_assign_text()
    {
        global $CFG, $DB;

        $hash = self::get_strip_text_content_hash($this->answertextobject->txt);
        // Checking if there is a record with this hash.
        $rec = $DB->get_record(
            'plagiarism_advacheck_docs',
            ['typeid' => $hash, 'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN, 'userid' => $this->answertextobject->userid, 'answerid' => $this->answertextobject->id]
        );

        if ($rec) {
            return;
        }

        // Checking if there is a record with hash of old algorithm.
        // If Moodle 3.3.3 and below.
        if ($CFG->version < 2017051504) {
            require_once ($CFG->dirroot . '/mod/assign/locallib.php');
            list($course, $cm) = get_course_and_cm_from_cmid($this->cmid, 'assign');
            $assign = new \assign($this->mod_ctxt, $cm, $course);
            $content = $assign->render_editor_content(
                ASSIGNSUBMISSION_ONLINETEXT_FILEAREA,
                $this->answertextobject->id,
                'onlinetext',
                'onlinetext',
                'assignsubmission_onlinetext'
            );
            $content = trim($content);
        } else {
            // If Moodle 3.3.4 and higher
            if (!isset($this->answertextobject->txt)) {
                $this->answertextobject->txt = '';
            }
            $content = trim($this->answertextobject->txt);
        }
        // Checking if there is a record with this hash.
        $rec = $DB->get_record(
            'plagiarism_advacheck_docs',
            [
                'typeid' => sha1($content),
                'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN,
                'userid' => $this->answertextobject->userid,
                'answerid' => $this->answertextobject->id
            ]
        );

        if ($rec) {
            // Write new hash only and return from function
            $DB->set_field('plagiarism_advacheck_docs', 'typeid', $hash, ['id' => $rec->id]);
            return;
        }

        // Checking whether this message (from a teacher or administrator) needs to be checked in AP?
        if (eventobservers::can_not_checked_by($this->crs_ctxt, $this->answertextobject->userid)) {
            // Add to the queue with the status - no right to be verified.
            eventobservers::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                $this->answertextobject->id,
                0,
                $this->answertextobject->userid,
                $this->answertextobject->assign,
                0,
                $this->courseid,
                $this->cmid
            );
            return;
        }
        if (count_words($this->answertextobject->txt) < (int) $this->plugin_cfg->min_len_str) {
            // Adding to the queue with status - few words.
            eventobservers::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN,
                $hash,
                advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS,
                $this->answertextobject->id,
                0,
                $this->answertextobject->userid,
                $this->answertextobject->assign,
                0,
                $this->courseid,
                $this->cmid
            );
            return;
        }
        // Draft submitted.
        switch ($this->answertextobject->status) {
            case 'draft':
                $status = advacheck_constants::PLAGIARISM_ADVACHECK_WAITBLOCK;
                break;
            case 'submitted':
                $status = advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD;
                break;
        }
        eventobservers::add_to_queue(
            advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN,
            $hash,
            $status,
            $this->answertextobject->id,
            $this->answertextobject->time,
            $this->answertextobject->userid,
            $this->answertextobject->assign,
            0,
            $this->courseid,
            $this->cmid
        );
    }

    /**
     * Adds files to the queue for sending.
     *
     * @global \moodle_database $DB
     * @param array $files Array of files
     * @param string $modname Course module name
     */
    public function add_to_queue_file($files, $modname)
    {
        global $DB;
        $assignment = 0;
        $discussion = 0;
        $cond = [];
        switch ($modname) {
            case 'assign':
                $assignment = $this->answertextobject->assign;
                $cond['assignment'] = $assignment;
                break;
            case 'forum':
                $discussion = $this->answertextobject->discussion;
                $cond['discussion'] = $discussion;
                break;
            case 'workshop':
                $cond['discussion'] = $discussion;
                $cond['assignment'] = $assignment;
                break;
            case 'quiz':
                $cond['discussion'] = $discussion;
                $cond['assignment'] = $assignment;
                break;
        }
        foreach ($files as $f) {
            if (!$f || $f->is_directory()) {
                continue;
            }

            // If an entry with such a file ID, such a user, and such a document type is already in the queue, then we will not add it.
            $rec = $DB->get_record(
                'plagiarism_advacheck_docs',
                array_merge($cond, ['typeid' => $f->get_id(), 'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_FILE, 'userid' => $f->get_userid()])
            );
            if ($rec) {
                continue;
            }
            file_put_contents(__DIR__ . '/debug.log', 'kot15' . PHP_EOL, FILE_APPEND);
            $filetype = substr(strrchr($f->get_filename(), "."), 1) . ',';
            // Check the file extension for valid file types to scan.
            if (eventobservers::is_allow_file_type($filetype) === false) {
                // Add to the queue with the status invalid file type.
                eventobservers::add_to_queue(
                    advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                    $f->get_id(),
                    advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE,
                    $this->answertextobject->id,
                    0,
                    $f->get_userid(),
                    $assignment,
                    $discussion,
                    $this->courseid,
                    $this->cmid
                );
                file_put_contents(__DIR__ . '/debug.log', 'kot2' . PHP_EOL, FILE_APPEND);
                continue;
            }
            if (eventobservers::can_not_checked_by($this->crs_ctxt, $f->get_userid())) {
                // Add to the queue with the status - no right to be verified.
                eventobservers::add_to_queue(
                    advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                    $f->get_id(),
                    advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY,
                    $this->answertextobject->id,
                    0,
                    $f->get_userid(),
                    $assignment,
                    $discussion,
                    $this->courseid,
                    $this->cmid
                );
                continue;
            }
            $status = advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD;
            if ($assignment) {
                // Draft submitted.
                if ($this->answertextobject->status == 'draft') {
                    $status = advacheck_constants::PLAGIARISM_ADVACHECK_WAITBLOCK;
                }
            }
            eventobservers::add_to_queue(
                advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                $f->get_id(),
                $status,
                $this->answertextobject->id,
                $this->answertextobject->time,
                $f->get_userid(),
                $assignment,
                $discussion,
                $this->courseid,
                $this->cmid
            );
        }
    }
    /**
     * Clear text of html tags and special characters. Trims extra spaces. Line break tags are replaced with end-of-line (EOL) characters.
     * Calculates Sha1-hash. 
     * @param string $textcontent Text from the editor.
     * @param bool $getcleartext Get clear text. 
     * @return string Sha1-hash of clear text, if $getcleartext is false, else clear text, converted binary data into hexadecimal representation
     */
    public static function get_strip_text_content_hash($textcontent, $getcleartext = false)
    {
        $cleartext = trim(str_replace("\n", ' ', html_entity_decode(strip_tags(nl2br($textcontent)))));
        if ($getcleartext) {
            return bin2hex($cleartext);
        } else {
            return sha1($cleartext);
        }
    }


}
