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
 * Uploading and sending documents for verification.
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\task;

use \plagiarism_advacheck\local\advacheck_constants;
use \plagiarism_advacheck\local\upload_start_check_manual;
use \plagiarism_advacheck\local\queue_log_manager;
use \plagiarism_advacheck\local\advacheck_api;

require_once "$CFG->dirroot/plagiarism/advacheck/classes/local/constants.php";
require_once "$CFG->dirroot/plagiarism/advacheck/classes/local/queue_log_manager.php";
require_once "$CFG->dirroot/plagiarism/advacheck/classes/local/upload_start_check_manual.php";

class upload_and_check_advacheck extends \core\task\scheduled_task
{

    /**
     *
     * @var advacheck_api
     */
    private $api = null;
    private $config;
    private $s;
    /** */
    private $logobject;

    /**
     * Class constructor, where we load the plugin settings.
     */
    function __construct()
    {
        $this->config = get_config('plagiarism_advacheck');

    }

    /**
     * Task body.
     * Performs the task of downloading and sending documents for scheduled scanning.
     *
     * @global \stdClass $CFG
     * @global \moodle_database $DB
     * @return boolean
     */

    public function execute()
    {
        global $CFG, $DB;
        if (empty($this->config->enabled)) {
            return true;
        }

        $this->logobject = new queue_log_manager();

        // We went to the task of downloading and sending documents for verification.
        mtrace(PHP_EOL . get_string('upload_and_check_enter', 'plagiarism_advacheck', null));
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        if (empty($this->config->uri) || empty($this->config->login) || empty($this->config->password)) {
            // Connection details have not been entered! I'm leaving the task.
            mtrace(PHP_EOL . get_string('upload_and_check_nologindata', 'plagiarism_advacheck', null));
            return false;
        } else {
            $this->api = new advacheck_api();
        }

        $count = $this->config->cron_check_count;

        if (empty($this->config->enabled)) {
            return true;
        }

        if (empty($this->config->check_forum) && empty($this->config->check_assign) && empty($this->config->check_workshop) && empty($this->config->check_quiz)) {
            return true;
        }

        // Select responses awaiting automatic sending.
        $sql =
            "SELECT docs.*
               FROM {plagiarism_advacheck_docs} docs
               JOIN {plagiarism_advacheck_course} cfg ON (docs.courseid = cfg.courseid AND docs.cmid = cfg.cmid )
              WHERE (status = ? AND cfg.mode = ?) OR status = ?
           ORDER BY timeadded ";
        $data = $DB->get_records_sql($sql, [advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD, advacheck_constants::PLAGIARISM_ADVACHECK_AUTOMODE, advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_UPLOADING], 0, $count);
        // Number of documents to upload and send for verification: count($data).
        mtrace(get_string('upload_and_check_countdocs', 'plagiarism_advacheck', count($data)));

        // The cycle of uploading documents to Antiplagiarism.
        mtrace(get_string('upload_and_check_cycle', 'plagiarism_advacheck', null));
        mtrace(PHP_EOL);
        foreach ($data as $item) {

            // Document processing and attributes retrieving.
            $a = new \stdClass();
            $a->id = $item->id;
            $a->status = $item->status;
            $a->time = date('d:m:Y H:i:s');
            mtrace("    " . get_string('upload_and_check_docprocessing', 'plagiarism_advacheck', $a));

            // We keep an event log to investigate incidents.
            $this->logobject->add_log_record(
                $item->externalid,
                '',
                10,
                $item->courseid,
                $item->cmid,
                $item->assignment,
                $item->discussion,
                $item->userid,
                $item->answerid,
                $item->id,
                $item->status,
                'task\upload_and_check_advacheck'
            );

            // An array for document attributes.
            $data_attr = [];
            $assignment = 0;
            $discussion = 0;
            $userid = 0;
            $filename = '';
            $content = '';

            if ((int) $item->doctype == advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN) {
                // Processing text from the assignment.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                mtrace("        " . get_string('upload_and_check_assigntext', 'plagiarism_advacheck', $a));
                // Let's take the user's response by response ID.
                // We took the results of checks of the answers of previous attempts.

                $docsparams = [
                    'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN,
                    'userid' => $item->userid,
                    'status' => advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX,
                    'answerid' => $item->answerid,
                    'assignment' => $item->assignment,
                ];
                $checkeddocs = $DB->get_records('plagiarism_advacheck_docs', $docsparams);
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                $a->cnt = count($checkeddocs);
                mtrace("        " . get_string('upload_and_check_removefromindexcnt', 'plagiarism_advacheck', $a));
                $this->remove_from_index($checkeddocs);
                // We request the content and text of the response.
                $s_sql =
                    "SELECT txt.id AS id, txt.onlinetext AS text, ass.name AS cm_name, c.fullname AS course_name
                      FROM {assignsubmission_onlinetext} txt
                      JOIN {assign} ass ON ass.id = txt.assignment
                      JOIN {course} c ON c.id = ass.course
                     WHERE txt.submission = ? AND txt.assignment = ?";
                $text = $DB->get_record_sql($s_sql, [$item->answerid, $item->assignment]);
                if ($text) {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    $a->id = $text->id;
                    mtrace("        " . get_string('upload_and_check_gettextassign', 'plagiarism_advacheck', $a));
                    $data_attr['coursefullname'] = $text->course_name;
                    $data_attr['cm_name'] = $text->cm_name;
                    $content = strip_tags($text->text);
                    $filename .= upload_start_check_manual::assemble_filename($content);
                } else {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    mtrace("        " . get_string('upload_and_check_textassignnotfound', 'plagiarism_advacheck', $a));
                    $DB->set_field("plagiarism_advacheck_docs", "status", advacheck_constants::PLAGIARISM_ADVACHECK_NOTFOUND, ["id" => $item->id]);
                    continue;
                }
            } else if ((int) $item->doctype == advacheck_constants::PLAGIARISM_ADVACHECK_FORUM) {
                // Processing text from the forum.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                mtrace("        " . get_string('upload_and_check_forumtext', 'plagiarism_advacheck', $a));
                $docsparams = [
                    'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_FORUM,
                    'userid' => $item->userid,
                    'status' => advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX,
                    'answerid' => $item->answerid,
                    'discussion' => $item->discussion,
                ];
                $checkeddocs = $DB->get_records('plagiarism_advacheck_docs', $docsparams);
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                $a->cnt = count($checkeddocs);
                mtrace("        " . get_string('upload_and_check_removefromindexcnt', 'plagiarism_advacheck', $a));
                $this->remove_from_index($checkeddocs);
                $s_sql =
                    "SELECT fp.id AS id, fp.message AS text, c.fullname AS course_name, f.name AS cm_name, fd.name AS d_name
                      FROM {forum_posts} fp
                      JOIN {forum_discussions} fd ON fd.id = fp.discussion
                      JOIN {forum} f ON fd.forum = f.id
                      JOIN {course} c ON c.id = f.course
                     WHERE fp.id = ? AND fp.discussion = ?";

                $text = $DB->get_record_sql($s_sql, [$item->answerid, $item->discussion]);
                if ($text) {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    $a->id = $text->id;
                    mtrace("        " . get_string('upload_and_check_gettextforum', 'plagiarism_advacheck', $a));
                    $data_attr['coursefullname'] = $text->course_name;
                    $data_attr['cm_name'] = $text->cm_name;
                    $data_attr['d_name'] = $text->d_name;
                    $content = strip_tags($text->text);
                    $filename .= upload_start_check_manual::assemble_filename($content);
                } else {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    mtrace("        " . get_string('upload_and_check_textforumnotfound', 'plagiarism_advacheck', $a));
                    $DB->set_field("plagiarism_advacheck_docs", "status", advacheck_constants::PLAGIARISM_ADVACHECK_NOTFOUND, ["id" => $item->id]);
                    continue;
                }
            } else if ((int) $item->doctype == advacheck_constants::PLAGIARISM_ADVACHECK_FILE) {
                // Processing file from any course module : assign/forum/workshop/essey quiz.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                mtrace("        " . get_string('upload_and_check_file', 'plagiarism_advacheck', $a));
                $fs = get_file_storage();
                $file = $fs->get_file_by_id($item->typeid);
                if (!$file) {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    mtrace("        " . get_string('upload_and_check_filenotfound', 'plagiarism_advacheck', $a));
                    $DB->set_field("plagiarism_advacheck_docs", "status", advacheck_constants::PLAGIARISM_ADVACHECK_NOTFOUND, ["id" => $item->id]);
                    continue;
                }
                $content = $file->get_content();
                $filename = $file->get_filename();

                $docsparams = [
                    'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
                    'userid' => $item->userid,
                    'status' => advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX,
                    'answerid' => $item->answerid,
                ];


                $sql_p = "SELECT * 
                            FROM {plagiarism_advacheck_docs} 
                           WHERE doctype = :doctype 
                                 AND userid = :userid 
                                 AND status = :status 
                                 AND answerid = :answerid 
                               ";
                if ($file->get_component() == 'question') {
                    $sql_p .= "AND attemptnumber <> :attemptnumber";
                    $docsparams['attemptnumber'] = $item->attemptnumber;
                }

                $checkeddocs = $DB->get_records_sql(
                    $sql_p,
                    $docsparams
                );
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                $a->cnt = count($checkeddocs);
                mtrace("        " . get_string('upload_and_check_removefromindexcnt', 'plagiarism_advacheck', $a));

                $this->remove_from_index($checkeddocs);
                // We received the id of the course module from which the file was sent
                $itemid = $file->get_itemid();
                $params1 = [$itemid, $item->discussion, $itemid, $item->assignment, $itemid, $itemid, $item->userid,];
                $cm_name_sql =
                    "SELECT f.name AS cm_name , fd.name AS d_name, c.fullname AS course_name
                       FROM {forum_posts} fp
                       JOIN {forum_discussions} fd ON fd.id = fp.discussion
                       JOIN {forum} f ON f.id = fd.forum
                       JOIN {course} c ON f.course = c.id
                      WHERE fp.id = ? 
                            AND fp.discussion = ?

                      UNION

                     SELECT ass.name AS cm_name, '-' AS d_name, c.fullname AS course_name
                       FROM {assign_submission} sub
                       JOIN {assign} ass ON sub.assignment = ass.id
                       JOIN {course} c ON c.id = ass.course
                      WHERE sub.id = ? 
                            AND ass.id = ?

                      UNION

                     SELECT w.name AS cm_name, '-' AS d_name, c.fullname AS course_name
                       FROM {workshop_submissions} ws
                       JOIN {workshop} w ON w.id = ws.workshopid
                       JOIN {course} c ON c.id = w.course
                      WHERE ws.id = ?

                      UNION

                     SELECT CONCAT(quiz.name, ' - ', q.name) AS cm_name, '-' AS d_name, c.fullname AS course_name
                       FROM {question_attempt_steps} qas
                       JOIN {question_attempts} qa ON qas.questionattemptid = qa.id        
                       JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                       JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                       JOIN {quiz} quiz ON quiz.id = quiza.quiz
                       JOIN {course} c ON c.id = quiz.course
                      WHERE qas.id = ? 
                            AND qas.userid = ?
                        ";
                // We got the name of the course module.
                //var_dump($params1);
                $cm_name = $DB->get_record_sql($cm_name_sql, $params1);
                $data_attr['cm_name'] = $cm_name->cm_name;
                // If we have a forum, we will write down the name of the discussion.
                if (!is_numeric($cm_name->d_name)) {
                    $data_attr['d_name'] = $cm_name->d_name;
                }
                $data_attr['userid'] = $file->get_userid();
                $data_attr['coursefullname'] = $cm_name->course_name;
            } else if ((int) $item->doctype == advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP) {
                // Processing text from the workshop.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                mtrace("        " . get_string('upload_and_check_workshoptext', 'plagiarism_advacheck', $a));
                $checkeddocs = $DB->get_records_sql(
                    $sql,
                    [advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP, $item->userid, advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX, $item->answerid]
                );
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                $a->cnt = count($checkeddocs);
                mtrace("        " . get_string('upload_and_check_removefromindexcnt', 'plagiarism_advacheck', $a));

                $this->remove_from_index($checkeddocs);
                // Get content and response text.
                $s_sql =
                    "SELECT ws.id AS id, ws.content AS text, w.name AS cm_name, c.fullname AS course_name
                       FROM {workshop_submissions} ws
                       JOIN {workshop} w ON w.id = ws.workshopid       
                       JOIN {course} c ON c.id = w.course
                      WHERE ws.id = ?";
                $text = $DB->get_record_sql($s_sql, [$item->answerid]);
                if ($text) {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    $a->id = $text->id;
                    mtrace("        " . get_string('upload_and_check_gettextworkshop', 'plagiarism_advacheck', $a));

                    $data_attr['coursefullname'] = $text->course_name;
                    $data_attr['cm_name'] = $text->cm_name;
                    $content = strip_tags($text->text);
                    $filename .= upload_start_check_manual::assemble_filename($content);
                } else {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    mtrace("        " . get_string('upload_and_check_textworkshopnotfound', 'plagiarism_advacheck', $a));
                    mtrace(PHP_EOL);
                    $DB->set_field("plagiarism_advacheck_docs", "status", advacheck_constants::PLAGIARISM_ADVACHECK_NOTFOUND, ["id" => $item->id]);
                    continue;
                }
            } else if ((int) $item->doctype == advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ) {
                // Processing essays from the quiz.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                mtrace("        " . get_string('upload_and_check_quiztext', 'plagiarism_advacheck', $a));
                $p = $sql . ' AND attemptnumber != ?';
                $checkeddocs = $DB->get_records_sql(
                    $sql,
                    [advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ, $item->userid, advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX, $item->answerid, $item->attemptnumber]
                );
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                $a->cnt = count($checkeddocs);
                mtrace("        " . get_string('upload_and_check_removefromindexcnt', 'plagiarism_advacheck', $a));
                $this->remove_from_index($checkeddocs);
                // We request the text of the answer and the name of the test, question and course name.
                $s_sql =
                    "SELECT qas.id, CONCAT(quiz.name, ': ', q.name) AS cm_name, c.fullname AS course_name, qa.responsesummary as text
                       FROM {question_attempt_steps} qas
                       JOIN {question_attempt_step_data} asd ON qas.id = asd.attemptstepid AND asd.name = 'answer'
                       JOIN {question_attempts} qa ON qas.questionattemptid = qa.id
                       JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                       JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                       JOIN {quiz} quiz ON quiz.id = quiza.quiz
                       JOIN {course} c ON c.id = quiz.course
                      WHERE qas.id = ? 
                            AND qas.userid = ?";

                $text = $DB->get_record_sql($s_sql, [$item->answerid, $item->userid]);
                if ($text) {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    $a->id = $text->id;
                    mtrace("        " . get_string('upload_and_check_gettextquiz', 'plagiarism_advacheck', $a));
                    $data_attr['coursefullname'] = $text->course_name;
                    $data_attr['cm_name'] = $text->cm_name;
                    $content = $text->text;
                    $filename .= upload_start_check_manual::assemble_filename($content);
                } else {
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    mtrace("        " . get_string('upload_and_check_textquiznotfound', 'plagiarism_advacheck', $a));
                    mtrace(PHP_EOL);
                    $DB->set_field("plagiarism_advacheck_docs", "status", advacheck_constants::PLAGIARISM_ADVACHECK_NOTFOUND, ["id" => $item->id]);
                    continue;
                }
            }

            // Uploading documents to the AP and initiating verification.
            $externalid = null;
            $status = null;
            $data_attr['userid'] = $item->userid;
            if (mb_strlen($content) > 0) {
                // Processing document attributes.

                $works_types = $DB->get_field('plagiarism_advacheck_course', 'works_types', ['cmid' => $item->cmid, 'courseid' => $item->courseid]);
                $DB->set_field('plagiarism_advacheck_docs', 'type', $works_types, ['id' => $item->id]);
                $doc_attr = new \stdClass();
                $doc_attr->works_types = ["Type" => $works_types];
                // Let's get the name of the site.
                $site = $DB->get_record('course', ['id' => 1], 'fullname,  summary');
                $site_name = $site->fullname;
                $site_description = strip_tags($site->summary);

                $attr = [];
                if (!empty($this->plugin_cfg->add_attr_system)) {
                    $attr[] = ["AttrName" => get_string('add_attr_system', 'plagiarism_advacheck'), "AttrValue" => $site_name];
                }
                if (!empty($this->plugin_cfg->add_attr_descr)) {
                    $attr[] = ["AttrName" => get_string('add_attr_descr', 'plagiarism_advacheck'), "AttrValue" => $site_description];
                }
                if (!empty($this->plugin_cfg->add_attr_site)) {
                    $attr[] = ["AttrName" => get_string('add_attr_site', 'plagiarism_advacheck'), "AttrValue" => $CFG->wwwroot];
                }
                if (!empty($this->plugin_cfg->add_attr_course)) {
                    $attr[] = ["AttrName" => get_string('add_attr_course', 'plagiarism_advacheck'), "AttrValue" => $data_attr['coursefullname']];
                }
                if (!empty($this->plugin_cfg->add_attr_item)) {
                    $attr[] = ["AttrName" => get_string('add_attr_item', 'plagiarism_advacheck'), "AttrValue" => $data_attr['cm_name']];
                }
                if (isset($this->data_attr->d_name) && !empty($this->plugin_cfg->add_attr_discusname)) {
                    $attr[] = ["AttrName" => get_string('add_attr_discusname', 'plagiarism_advacheck'), "AttrValue" => $data_attr['d_name']];
                }
                if (!empty($this->plugin_cfg->add_attr_idauthor)) {
                    $attr[] = ["AttrName" => get_string('add_attr_idauthor', 'plagiarism_advacheck'), "AttrValue" => $data_attr['userid']];
                }

                $doc_attr->custom_attrs = ["Custom" => $attr];
                // Let's set the status to "in the process of downloading" so that the cron task does not download this document again.
                $status = advacheck_constants::PLAGIARISM_ADVACHECK_UPLOADING;
                $DB->set_field('plagiarism_advacheck_docs', 'timeupload_start', time(), ['id' => $item->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'error', '', ['id' => $item->id]);
                $this->logobject->add_log_record(
                    '',
                    '',
                    2,
                    $item->courseid,
                    $item->cmid,
                    $item->assignment,
                    $item->discussion,
                    $item->userid,
                    $item->answerid,
                    $item->id,
                    $status,
                    'task\upload_and_check_advacheck'
                );
                $a = new \stdClass();
                $a->id = $item->id;
                $a->time = date('d:m:Y H:i:s');
                mtrace("        " . get_string('upload_and_check_uploaddoc', 'plagiarism_advacheck', $a));
                $ap_docid = $this->api->upload_doc($filename, $content, $item->courseid, 'task\upload_and_check_advacheck', $conn_error, $doc_attr->works_types);

                // Handling loading documents errors.
                if (is_string($ap_docid)) {
                    if ($conn_error) {
                        $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_UPLOADING;
                    } else {
                        $status = advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE;
                    }
                    $a = new \stdClass();
                    $a->id = $item->id;
                    $a->error = $ap_docid;
                    $a->time = date('d:m:Y H:i:s');
                    mtrace("        " . get_string('upload_and_check_erroruploaddoc', 'plagiarism_advacheck', $a));
                    mtrace(PHP_EOL);

                    $s = $ap_docid;
                    $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $item->id]);
                    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                    $this->logobject->add_log_record(
                        $item->externalid,
                        $item->reportedit,
                        14,
                        $item->courseid,
                        $item->cmid,
                        $item->assignment,
                        $item->discussion,
                        $item->userid,
                        $item->answerid,
                        $item->id,
                        $status,
                        'task\upload_and_check_advacheck',
                        $s
                    );
                    continue;
                } else {
                    // Let's check whether Anti-Plagiarism considered the document erroneous.
                    if ($ap_docid->Reason !== 'NoError') {
                        $a = new \stdClass();
                        $a->id = $item->id;
                        $a->error = $ap_docid->FailDetails;
                        $a->time = date('d:m:Y H:i:s');
                        mtrace("        " . get_string('upload_and_check_errordoc', 'plagiarism_advacheck', $a));
                        mtrace(PHP_EOL);

                        $status = advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE;
                        $s = $ap_docid->FailDetails;
                        $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $item->id]);
                        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                        $this->logobject->add_log_record(
                            $item->externalid,
                            $item->reportedit,
                            14,
                            $item->courseid,
                            $item->cmid,
                            $item->assignment,
                            $item->discussion,
                            $item->userid,
                            $item->answerid,
                            $item->id,
                            $status,
                            'task\upload_and_check_advacheck',
                            $s
                        );
                        continue;
                    }
                    $externalid = $ap_docid->Id->Id;
                    $this->logobject->add_log_record(
                        $externalid,
                        '',
                        11,
                        $item->courseid,
                        $item->cmid,
                        $item->assignment,
                        $item->discussion,
                        $item->userid,
                        $item->answerid,
                        $item->id,
                        $status,
                        'task\upload_and_check_advacheck'
                    );
                    $doc_attr_res = $this->api->upload_doc_attr($externalid, $doc_attr->custom_attrs);
                    $this->logobject->add_log_record(
                        $externalid,
                        '',
                        12,
                        $item->courseid,
                        $item->cmid,
                        $item->assignment,
                        $item->discussion,
                        $item->userid,
                        $item->answerid,
                        $item->id,
                        $status,
                        'task\upload_and_check_advacheck'
                    );
                    // Handling loading documents errors.
                    if (is_string($doc_attr_res)) {
                        $a = new \stdClass();
                        $a->id = $item->id;
                        $a->error = $doc_attr_res;
                        $a->time = date('d:m:Y H:i:s');
                        mtrace("        " . get_string('upload_and_check_uploaddocattrerror', 'plagiarism_advacheck', $a));
                        mtrace(PHP_EOL);

                        $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_UPLOADING;
                        $s = $doc_attr_res;
                        $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $item->id]);
                        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                        $this->logobject->add_log_record(
                            $externalid,
                            $item->reportedit,
                            14,
                            $item->courseid,
                            $item->cmid,
                            $item->assignment,
                            $item->discussion,
                            $item->userid,
                            $item->answerid,
                            $item->id,
                            $status,
                            'task\upload_and_check_advacheck',
                            $s
                        );
                        continue;
                    }
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    mtrace("        " . get_string('upload_and_check_uploaddocsuccess', 'plagiarism_advacheck', $a));
                    mtrace(PHP_EOL);
                    $rec['timeupload_end'] = time();
                    $rec['status'] = advacheck_constants::PLAGIARISM_ADVACHECK_UPLOADED;
                    $rec['externalid'] = $externalid;
                    $rec['id'] = $item->id;
                    $DB->update_record('plagiarism_advacheck_docs', (object) $rec);
                    $this->logobject->add_log_record(
                        $externalid,
                        '',
                        3,
                        $item->courseid,
                        $item->cmid,
                        $item->assignment,
                        $item->discussion,
                        $item->userid,
                        $item->answerid,
                        $item->id,
                        advacheck_constants::PLAGIARISM_ADVACHECK_UPLOADED,
                        'task\upload_and_check_advacheck'
                    );
                    $a = new \stdClass();
                    $a->id = $item->id;
                    $a->time = date('d:m:Y H:i:s');
                    mtrace("        " . get_string('upload_and_check_startingdoccheck', 'plagiarism_advacheck', $a));
                    mtrace(PHP_EOL);
                    $cm_sett = $DB->get_record('plagiarism_advacheck_course', ['cmid' => $item->cmid, 'courseid' => $item->courseid]);
                    $ignoresections = [
                        "Title" => !$cm_sett->docsecttitle,
                        "Content" => !$cm_sett->docsectcontent,
                        "Bibliography" => !$cm_sett->docsectbibliography,
                        "Appendix" => !$cm_sett->docsectappendix,
                        "Introduction" => !$cm_sett->docsectintroduction,
                        "Method" => !$cm_sett->docsectmethod,
                        "Conclusion" => !$cm_sett->docsectconclusion,
                    ];
                    $m = $this->api->start_check($externalid, $ignoresections);
                    // The status is "in checking", because checks can take a long time.
                    $status = advacheck_constants::PLAGIARISM_ADVACHECK_CHECKING;
                    $DB->set_field('plagiarism_advacheck_docs', 'timecheck_start', time(), ['id' => $item->id]);
                    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                    $this->logobject->add_log_record(
                        $externalid,
                        '',
                        4,
                        $item->courseid,
                        $item->cmid,
                        $item->assignment,
                        $item->discussion,
                        $item->userid,
                        $item->answerid,
                        $item->id,
                        $status,
                        'task\upload_and_check_advacheck'
                    );
                    // Handling start check errors.
                    if ($m !== true) {
                        $a = new \stdClass();
                        $a->id = $item->id;
                        $a->error = $m;
                        $a->time = date('d:m:Y H:i:s');
                        mtrace("        " . get_string('upload_and_check_startingdoccheckerror', 'plagiarism_advacheck', $a));
                        mtrace(PHP_EOL);
                        $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_CHECKING;
                        $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $item->id]);
                        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $item->id]);
                        $this->logobject->add_log_record(
                            $item->externalid,
                            $item->reportedit,
                            14,
                            $item->courseid,
                            $item->cmid,
                            $item->assignment,
                            $item->discussion,
                            $item->userid,
                            $item->answerid,
                            $item->id,
                            $status,
                            'task\upload_and_check_advacheck',
                            $m
                        );
                    }
                }
            } else {
                // The document content was not found, we do not process it, we set the status to advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS.
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                mtrace("        " . get_string('upload_and_check_emptydoc', 'plagiarism_advacheck', $a));
                mtrace(PHP_EOL);
                $DB->set_field("plagiarism_advacheck_docs", "status", advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS, ["id" => $item->id]);
            }
            unset($content);
            // End of document processing.
            $this->logobject->add_log_record(
                $item->externalid,
                $item->reportedit,
                13,
                $item->courseid,
                $item->cmid,
                $item->assignment,
                $item->discussion,
                $item->userid,
                $item->answerid,
                $item->id,
                $item->status,
                'task\upload_and_check_advacheck'
            );
            mtrace(PHP_EOL);
        }
        mtrace(PHP_EOL);
        return true;
    }

    /**
     * Returns a string with the name of the task.
     * @return string
     */
    public function get_name()
    {
        global $CFG;
        return get_string('upload_and_check_advacheck', 'plagiarism_advacheck', null);
    }

    /**
     * Function Remove from index.
     * @global \moodle_database $DB
     * @param array $checkeddocs An array of documents from the queue.
     * @return void
     */
    private function remove_from_index($checkeddocs)
    {
        global $DB, $CFG;
        foreach ($checkeddocs as $doc) {

            $docid = $doc->externalid;
            // If there is no ID in the anti-plagiarism, then this document was not uploaded. There is no need to remove it from the index.
            if (empty($docid)) {
                $a = new \stdClass();
                $a->id = $docid;
                $a->externalid = $doc->externalid;
                $a->time = date('d:m:Y H:i:s');
                mtrace("            " . get_string('upload_and_check_emptydocid', 'plagiarism_advacheck', $a));
                mtrace(PHP_EOL);
                continue;
            }
            $m = $this->api->set_index_status($docid, false);
            if ($m !== true) {
                // Handling remove from index errors.
                $a = new \stdClass();
                $a->id = $doc->id;
                $a->error = $m;
                $a->time = date('d:m:Y H:i:s');
                mtrace("        " . get_string('upload_and_check_errorfromindex', 'plagiarism_advacheck', $a));
                $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_INDEX;
                $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $doc->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $doc->id]);
                $this->logobject->add_log_record(
                    $doc->externalid,
                    $doc->reportedit,
                    14,
                    $doc->courseid,
                    $doc->cmid,
                    $doc->assignment,
                    $doc->discussion,
                    $doc->userid,
                    $doc->answerid,
                    $doc->id,
                    $status,
                    'task\upload_and_check_advacheck',
                    $m
                );
                continue;
            }
            // Let's write down what we started to remove from the index.
            $this->logobject->add_log_record(
                $doc->externalid,
                $doc->reportedit,
                6,
                $doc->courseid,
                $doc->cmid,
                $doc->assignment,
                $doc->discussion,
                $doc->userid,
                $doc->answerid,
                $doc->id,
                $doc->status,
                'task\upload_and_check_advacheck'
            );
            do {
                $a = new \stdClass();
                $a->time = date('d:m:Y H:i:s');
                mtrace("                " . get_string('upload_and_check_cyclefromindex', 'plagiarism_advacheck', $a));
                $di = $this->api->get_document_info($docid);
                if (is_string($di)) {
                    // Handling remove from index errors.
                    $a = new \stdClass();
                    $a->id = $doc->id;
                    $a->error = $di;
                    $a->time = date('d:m:Y H:i:s');
                    mtrace("                " . get_string('upload_and_check_errorfromindex', 'plagiarism_advacheck', $a));
                    $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_INDEX;
                    $DB->set_field('plagiarism_advacheck_docs', 'error', $di, ['id' => $doc->id]);
                    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $doc->id]);
                    $this->logobject->add_log_record(
                        $doc->externalid,
                        $doc->reportedit,
                        14,
                        $doc->courseid,
                        $doc->cmid,
                        $doc->assignment,
                        $doc->discussion,
                        $doc->userid,
                        $doc->answerid,
                        $doc->id,
                        $status,
                        'task\upload_and_check_advacheck',
                        $di
                    );
                    return;
                }
                if (time_nanosleep(0, 400000000) === true) {
                    // Delay of 0.4. This is the minimum possible delay for polling the status of the document index.
                    $a = new \stdClass();
                    $a->time = date('d:m:Y H:i:s');
                    mtrace("                " . get_string('upload_and_check_fromindextimeout', 'plagiarism_advacheck', $a));
                }
            } while (isset($di->AddedToIndex));
            $a = new \stdClass();
            $a->id = $doc->id;
            $a->time = date('d:m:Y H:i:s');
            mtrace("                " . get_string('upload_and_check_fromindexsuccess', 'plagiarism_advacheck', $a));
            $DB->set_field("plagiarism_advacheck_docs", "status", advacheck_constants::PLAGIARISM_ADVACHECK_CHECKED, ["id" => $doc->id]);
            // Let's record the end of deletion from the index.
            $this->logobject->add_log_record(
                $doc->externalid,
                $doc->reportedit,
                7,
                $doc->courseid,
                $doc->cmid,
                $doc->assignment,
                $doc->discussion,
                $doc->userid,
                $doc->answerid,
                $doc->id,
                advacheck_constants::PLAGIARISM_ADVACHECK_CHECKED,
                'task\upload_and_check_advacheck'
            );
        }
    }

}
