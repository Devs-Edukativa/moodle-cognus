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
 * Class for working with the Anti-Plagiarism API
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\local;

require_once "constants.php";

/**
 * A class that uploads and sends documents for verification in manual mode
 */
class upload_start_check_manual
{

    /**
     * @var mixed Object to connect external service 
     */
    private $api;
    /** 
     * @var mixed Record of document from db 
     * */
    private $docrecord;
    /** 
     * @var mixed Plugin configuration 
     */
    private $plugin_cfg;
    /**
     * @var mixed For id of uploaded document from external system*/
    private $ap_docid;
    /** @var mixed Structure for displaying test results on the course module page. */
    private $api_data;
    /** @var mixed Object with course context */
    private $context;
    private $courseid;
    /** @var mixed Content of answer`s text / file */
    private $content;
    private $userid;
    /** @var mixed Fileid from db / sha-hash of answer`s text */
    private $typeid;
    /** @var mixed Additional upload fields of document */
    private $data_attr;
    /** @var mixed Type of document file or text from assign submittion/forum post/workshop submittion/ quiz essay*/
    private $doctype;
    /** @var mixed Id of forum discussion */
    private $discussion;
    /** @var mixed Id of assignment */
    private $assignment;
    private $filename = '';
    /** @var mixed The length of the text/file content is longer than the minimum allowed. */
    private $islongstr;
    /** @var mixed Html of verify result */
    private $result;
    /** @var mixed Object to recording log */
    private $logobject;
    /** 
     * Saves settings and various data.
     * @var mixed $typeid Sha-1 hash or fileid
     * @var mixed $courseid Id of course
     * @var mixed $doctype Type of document
     * @var mixed $content Clear text content
     * @var mixed $userid Id of document`s user
     * @var mixed $assignment Id of assignment
     * @var mixed $discussion Id of discussion
     */

    public function __construct(
        $typeid,
        $courseid,
        $doctype,
        $content,
        $userid,
        $assignment,
        $discussion
    ) {
        global $DB;
        $this->api = new advacheck_api();
        $this->doctype = $doctype;
        $this->typeid = $typeid;
        // If it is not a file, then we will get the content values and decrypt it, as well as other parameters.
        if ($doctype != advacheck_constants::PLAGIARISM_ADVACHECK_FILE) {
            $this->content = hex2bin($content);
            $this->userid = $userid;
            $this->assignment = $assignment;
            $this->discussion = $discussion;
        }
        $this->plugin_cfg = get_config('plagiarism_advacheck');
        $this->api_data = new \stdClass();
        $this->context = \context_course::instance($courseid, MUST_EXIST);
        $this->data_attr = new \stdClass();
        $this->data_attr->coursefullname = '';
        $this->data_attr->userid = '';
        $this->data_attr->cm_name = '';
        $this->data_attr->d_name = '';
        $this->result = new \stdClass();
        $this->logobject = new queue_log_manager();
    }


    /**
     * Checks files by clicking on a button from the course module page.
     *
     * @global \moodle_database $DB
     * @return \stdClass
     */
    public function start_file_verify()
    {
        global $DB;
        // Get document`s record from db
        $this->docrecord = $DB->get_record('plagiarism_advacheck_docs', ['doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_FILE, 'typeid' => $this->typeid]);
        // return info about missing record
        if (!$this->docrecord) {
            return $this->get_error_structure(get_string('error_index', 'plagiarism_advacheck', get_string('not_in_queue', 'plagiarism_advacheck')));
        }

        $this->logobject->add_log_record(
            $this->docrecord->externalid,
            '',
            10,
            $this->docrecord->courseid,
            $this->docrecord->cmid,
            $this->docrecord->assignment,
            $this->docrecord->discussion,
            $this->docrecord->userid,
            $this->docrecord->answerid,
            $this->docrecord->id,
            $this->docrecord->status
        );

        $docsparams = [
            'doctype' => advacheck_constants::PLAGIARISM_ADVACHECK_FILE,
            'userid' => $this->docrecord->userid,
            'status' => advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX,
            'answerid' => $this->docrecord->answerid,
            'cmid' => $this->docrecord->cmid,
        ];
        $sql = "SELECT *
              FROM {plagiarism_advacheck_docs}
             WHERE doctype = ?
                   AND userid = ?
                   AND status = ?
                   AND answerid <> ?
                   AND cmid = ?
           ";
        // If there is a discussion ID or a task ID, then we will indicate it to search for documents.
        if ($this->docrecord->assignment != 0) {
            $docsparams[] = $this->docrecord->assignment;
            $sql .= "AND assignment = ?
        ";
        } else if ($this->docrecord->discussion != 0) {
            $docsparams[] = $this->docrecord->discussion;
            $sql .= "AND discussion = ?
        ";
        }

        $fs = get_file_storage();
        $file = $fs->get_file_by_id($this->typeid);

        $this->content = $file->get_content();

        if (mb_strlen($this->content) == 0) {
            $this->islongstr = false;
        }
        $this->filename = $file->get_filename();
        // Let's determine which course module the file is from.
        $component = $file->get_component();
        $itemid = $file->get_itemid();
        // We will receive the response documents from the previous attempt.
        if ($component == 'question') {
            $sql .= " AND attemptnumber <> ?";
            $docsparams[] = $this->docrecord->attemptnumber;
        }
        $prevfiles = $DB->get_records_sql($sql, $docsparams);

        foreach ($prevfiles as $f) {
            $this->remove_from_index($f);
        }
        // We form the document attributes.
        $this->data_attr->coursefullname = $DB->get_field('course', 'fullname', ['id' => $this->courseid]);

        $sql_params = [];
        $sql_params[] = $itemid;
        if ($component == 'assignsubmission_file') {
            $cm_name_sql =
                "SELECT ass.name AS cm_name, sub.id
                   FROM {assign_submission} sub
                   JOIN {assign} ass ON sub.assignment = ass.id
                  WHERE sub.id = ?";
        } else if ($component == 'mod_forum') {
            $cm_name_sql =
                "SELECT f.name AS cm_name , fd.name AS d_name
                   FROM {forum_posts} fp
                   JOIN {forum_discussions} fd ON fd.id = fp.discussion
                   JOIN {forum} f ON f.id = fd.forum
                  WHERE fp.id = ?";
        } else if ($component == 'mod_workshop') {
            $cm_name_sql =
                "SELECT w.name AS cm_name
                   FROM {workshop} w 
                   JOIN {workshop_submissions} ws ON ws.workshopid = w.id 
                  WHERE ws.id = ?";
        } else if ($component == 'question') {
            $cm_name_sql =
                "SELECT CONCAT(quiz.name, ': ', q.name) AS cm_name, '-' AS d_name, c.fullname AS course_name
                   FROM {question_attempt_steps} qas 
                   JOIN {question_attempts} qa ON qas.questionattemptid = qa.id                    
                   JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                   JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                   JOIN {quiz} quiz ON quiz.id = quiza.quiz
                   JOIN {course} c ON c.id = quiz.course
                  WHERE qas.id = ? 
                        AND qas.userid = ?";
            $sql_params[] = $this->docrecord->userid;
        }
        // Let's get the name of the course module.
        $cm_name = $DB->get_record_sql($cm_name_sql, $sql_params);
        $this->data_attr->cm_name = $cm_name->cm_name;
        // If the file is from a forum, then write down the name of the topic.
        if ($this->docrecord->discussion != 0) {
            $this->data_attr->d_name = $cm_name->d_name;
        }
        $this->data_attr->userid = $file->get_userid();

        return $this->start_doc_verify();
    }

    /**
     * Checks the text by clicking on a button from the forum page or evaluating answers to a task.
     *
     * @global \moodle_database $DB
     * @return \stdClass
     */
    public function start_text_verify()
    {
        global $DB;

        $params = ['userid' => $this->userid];
        if ($this->discussion != 0) {
            $params["discussion"] = $this->discussion;
        }
        if ($this->assignment != 0) {
            $params["assignment"] = $this->assignment;
        }

        $params['doctype'] = $this->doctype;
        $params['typeid'] = $this->typeid;
        $this->docrecord = $DB->get_record('plagiarism_advacheck_docs', $params);

        if (!$this->docrecord) {
            return $this->get_error_structure(get_string('not_in_queue', 'plagiarism_advacheck') . var_export($params, true));
        }

        $this->islongstr = count_words($this->content) < (int) $this->plugin_cfg->min_len_str;

        $this->data_attr->coursefullname = $DB->get_field('course', 'fullname', ['id' => $this->courseid]);
        $this->data_attr->userid = $this->userid;

        if (!$this->islongstr) {
            // Select documents from previous responses to remove them from the index.
            $sql =
                "SELECT *
                   FROM {plagiarism_advacheck_docs}
                  WHERE doctype = ?
                        AND userid = ?
                        AND status = ?
                        AND answerid <> ?";
            if ($this->doctype == advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN) {
                if ($this->docrecord) {
                    // A record of the start of document processing.
                    $this->logobject->add_log_record(
                        $this->docrecord->externalid,
                        '',
                        10,
                        $this->docrecord->courseid,
                        $this->docrecord->cmid,
                        $this->docrecord->assignment,
                        $this->docrecord->discussion,
                        $this->docrecord->userid,
                        $this->docrecord->answerid,
                        $this->docrecord->id,
                        $this->docrecord->status
                    );
                    // If there is data from previous responses, we will remove it from the index.
                    $sql_p = $sql . ' AND assignment = ?';
                    $checkedSubs = $DB->get_records_sql($sql_p, [advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN, $this->docrecord->userid, advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX, $this->docrecord->answerid, $this->assignment]);

                    foreach ($checkedSubs as $sub) {
                        $this->remove_from_index($sub);
                    }
                }
                $this->data_attr->cm_name = $DB->get_field('assign', 'name', ['id' => $this->assignment]);
            }

            if ($this->doctype == advacheck_constants::PLAGIARISM_ADVACHECK_FORUM) {
                if ($this->docrecord) {
                    // A record of the start of document processing.
                    $this->logobject->add_log_record(
                        $this->docrecord->externalid,
                        '',
                        10,
                        $this->docrecord->courseid,
                        $this->docrecord->cmid,
                        $this->docrecord->assignment,
                        $this->docrecord->discussion,
                        $this->docrecord->userid,
                        $this->docrecord->answerid,
                        $this->docrecord->id,
                        $this->docrecord->status
                    );
                    // If there is data from previous responses, we will remove it from the index.
                    $sql_p = $sql . ' AND discussion = ?';
                    $checkedPosts = $DB->get_records_sql($sql_p, [advacheck_constants::PLAGIARISM_ADVACHECK_FORUM, $this->docrecord->userid, advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX, $this->docrecord->answerid, $this->discussion]);

                    foreach ($checkedPosts as $post) {
                        $this->remove_from_index($post);
                    }
                }
                $sql_info =
                    "SELECT fd.name AS d_name, f.name AS f_name
                       FROM {forum_discussions} fd
                       JOIN {forum} f ON fd.forum = f.id
                      WHERE fd.id = ?";
                $res = $DB->get_record_sql($sql_info, [$this->discussion]);
                if ($res) {
                    $this->data_attr->cm_name = $res->f_name;
                    $this->data_attr->d_name = $res->d_name;
                } else {
                    $sql_info =
                        "SELECT f.name AS f_name
                           FROM {forum} f
                           JOIN {course_modules} cm ON cm.instance = f.id
                          WHERE cm.id = ?";
                    $res = $DB->get_record_sql($sql_info, [$this->docrecord->cmid]);
                    $this->data_attr->cm_name = $res->f_name;
                }
            }

            if ($this->doctype == advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP) {
                if ($this->docrecord) {
                    // A record of the start of document processing.
                    $this->logobject->add_log_record(
                        $this->docrecord->externalid,
                        '',
                        10,
                        $this->docrecord->courseid,
                        $this->docrecord->cmid,
                        0,
                        0,
                        $this->docrecord->userid,
                        $this->docrecord->answerid,
                        $this->docrecord->id,
                        $this->docrecord->status
                    );

                    $checkedSubs = $DB->get_records_sql($sql, [advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP, $this->docrecord->userid, advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX, $this->docrecord->answerid]);

                    foreach ($checkedSubs as $sub) {
                        $this->remove_from_index($sub);
                    }
                }
                $sql_wname =
                    "SELECT w.name 
                       FROM {workshop} w 
                       JOIN {workshop_submissions} ws ON ws.workshopid = w.id 
                      WHERE ws.id = ?";
                $this->data_attr->cm_name = $DB->get_record_sql($sql_wname, [$this->docrecord->answerid])->name;
            }
            if ($this->doctype == advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ) {
                if ($this->docrecord) {
                    // A record of the start of document processing.
                    $this->logobject->add_log_record(
                        $this->docrecord->externalid,
                        '',
                        10,
                        $this->docrecord->courseid,
                        $this->docrecord->cmid,
                        0,
                        0,
                        $this->docrecord->userid,
                        $this->docrecord->answerid,
                        $this->docrecord->id,
                        $this->docrecord->status
                    );

                    $sql_p = $sql . ' AND attemptnumber != ?';

                    $checkedSubs = $DB->get_records_sql($sql_p, [advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ, $this->docrecord->userid, advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX, $this->docrecord->answerid, $this->docrecord->attemptnumber]);

                    foreach ($checkedSubs as $sub) {
                        $this->remove_from_index($sub);
                    }

                    $sql_qname =
                        "SELECT CONCAT(quiz.name, ': ', q.name) AS cm_name, '-' AS d_name, c.fullname AS course_name
                           FROM {question_attempt_steps} qas
                           JOIN {question_attempts} qa ON qas.questionattemptid = qa.id
                           JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                           JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                           JOIN {quiz} quiz ON quiz.id = quiza.quiz
                           JOIN {course} c ON c.id = quiz.course
                          WHERE qas.id = ? 
                                AND qas.userid = ? ";

                    $this->data_attr->cm_name = $DB->get_record_sql($sql_qname, [$this->docrecord->answerid, $this->docrecord->userid])->cm_name;
                }
            }

            $this->filename .= $this->assemble_filename($this->content);
        }
        return $this->start_doc_verify();
    }

    /**
     * Removes a document from the AP index for manual mode.
     *
     * @global \moodle_database $DB
     * @param object $doc
     */
    public static function remove_from_index($doc)
    {
        global $DB;

        if (empty($doc->externalid)) {
            return;
        }
        $docid = $doc->externalid;
        $api = new advacheck_api();
        $logobject = new queue_log_manager();
        $m = $api->set_index_status($doc->externalid, false);
        if ($m !== true) {
            // Processing error when trying to get the status of deleting a document from the index.
            $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_INDEX;
            $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $doc->id]);
            $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $doc->id]);
            $logobject->add_log_record(
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
                false,
                $m
            );
        } else {
            // A record indicating the start of deletion from the index.
            $logobject->add_log_record(
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
                $doc->status
            );
            // cycle while waiting for deletion from index.
            do {
                $di = $api->get_document_info($docid);
                if (is_string($di)) {
                    $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_INDEX;
                    $DB->set_field('plagiarism_advacheck_docs', 'error', $di, ['id' => $doc->id]);
                    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $doc->id]);
                    $logobject->add_log_record(
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
                        false,
                        $di
                    );
                }
                // Minimum possible AP server polling frequency.
                time_nanosleep(0, 400000000);
            } while (isset($di->AddedToIndex));
            // Set the status to deleted from the index.
            $DB->set_field("plagiarism_advacheck_docs", "status", advacheck_constants::PLAGIARISM_ADVACHECK_CHECKED, ["id" => $doc->id]);
            // A record indicating the completion of deletion from the index.
            $logobject->add_log_record(
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
                advacheck_constants::PLAGIARISM_ADVACHECK_CHECKED
            );
        }
    }

    /**
     * Prepares an array with document attributes.
     *
     * @global \moodle_database $DB
     * 
     */
    private function prepare_doc_attr()
    {
        global $DB, $CFG;

        $works_types = $DB->get_field('plagiarism_advacheck_course', 'works_types', ['cmid' => $this->docrecord->cmid, 'courseid' => $this->courseid]);
        $DB->set_field('plagiarism_advacheck_docs', 'type', $works_types, ['id' => $this->docrecord->id]);
        $this->data_attr->works_types = ["Type" => $works_types];
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
            $attr[] = ["AttrName" => get_string('add_attr_course', 'plagiarism_advacheck'), "AttrValue" => $this->data_attr->coursefullname];
        }
        if (!empty($this->plugin_cfg->add_attr_item)) {
            $attr[] = ["AttrName" => get_string('add_attr_item', 'plagiarism_advacheck'), "AttrValue" => $this->data_attr->cm_name];
        }
        if (isset($this->data_attr->d_name) && !empty($this->plugin_cfg->add_attr_discusname)) {
            $attr[] = ["AttrName" => get_string('add_attr_discusname', 'plagiarism_advacheck'), "AttrValue" => $this->data_attr->d_name];
        }
        if (!empty($this->plugin_cfg->add_attr_idauthor)) {
            $attr[] = ["AttrName" => get_string('add_attr_idauthor', 'plagiarism_advacheck'), "AttrValue" => $this->data_attr->userid];
        }

        $this->data_attr->custom_attrs = ["Custom" => $attr];
    }

    /**
     * Sends a document for verification and processes the result of checking texts and files.
     *
     * @global \moodle_database $DB
     * @return \stdClass
     */
    private function start_doc_verify()
    {
        global $DB, $USER;
        $this->ap_docid = null;
        $originality = 0;
        $this->api_data->error = '';
        $this->api_data->plagiarism = 0;
        $this->api_data->legal = 0;
        $this->api_data->issuspicious = 0;
        $this->api_data->selfcite = 0;
        $this->api_data->reportedit = '';
        $this->api_data->reportread = '';
        $this->api_data->shortreport = '';

        if (!$this->islongstr) {
            $this->prepare_doc_attr();
            if (!has_capability('plagiarism/advacheck:checkadvacheck', $this->context)) {
                // Increment check counter for student and record.
                $stud_check = (int) $this->docrecord->stud_check + 1;
                $DB->set_field(
                    'plagiarism_advacheck_docs',
                    'stud_check',
                    $stud_check,
                    ['cmid' => $this->docrecord->cmid, 'userid' => $this->docrecord->userid, 'doctype' => $this->docrecord->doctype]
                );
            }
            // If it has already been downloaded, but the scan results have not been uploaded.
            if ((int) $this->docrecord->status == advacheck_constants::PLAGIARISM_ADVACHECK_UPLOADED || (int) $this->docrecord->status == advacheck_constants::PLAGIARISM_ADVACHECK_CHECKING) {
                $this->ap_docid = $this->docrecord->externalid;
                $this->api_data->externalid = $this->docrecord->externalid;
            } else if ((int) $this->docrecord->status == advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD || (int) $this->docrecord->status == advacheck_constants::PLAGIARISM_ADVACHECK_WAITBLOCK) {
                // Requires unloading and running the verify.
                // Start uploading.
                $this->upload_document_manual();
                // Output information in case of errors.
                if (empty($this->ap_docid)) {
                    return $this->result;
                }
                // Start checking.
                $m = $this->start_check_manual();
                if (!$m) {
                    return $this->result;
                }
            }

            if (isset($this->ap_docid)) {
                // Let's wait 5 seconds and ask for the verification status; if the answer is not large, then it could have been verified.
                sleep(5);
                // Query verification status. 
                $st_curr = $this->api->get_current_check_status($this->ap_docid);

                if (isset($st_curr->error)) {
                    // Error handling when trying to request a check status.
                    $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_GET_STATUS;
                    $DB->set_field('plagiarism_advacheck_docs', 'error', $st_curr->error, ['id' => $this->docrecord->id]);
                    $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $this->docrecord->id]);
                    $this->logobject->add_log_record(
                        $this->docrecord->externalid,
                        $this->docrecord->reportedit,
                        14,
                        $this->docrecord->courseid,
                        $this->docrecord->cmid,
                        $this->docrecord->assignment,
                        $this->docrecord->discussion,
                        $this->docrecord->userid,
                        $this->docrecord->answerid,
                        $this->docrecord->id,
                        $status,
                        false,
                        $st_curr->error
                    );
                    return $this->get_error_structure($st_curr->error);
                } else {
                    if ($st_curr->status === 'Failed') {
                        // Handling errors during document verification.
                        $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_CHECK;
                        $DB->set_field('plagiarism_advacheck_docs', 'error', $st_curr->msg, ['id' => $this->docrecord->id]);
                        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $this->docrecord->id]);
                        $this->logobject->add_log_record(
                            $this->docrecord->externalid,
                            $this->docrecord->reportedit,
                            14,
                            $this->docrecord->courseid,
                            $this->docrecord->cmid,
                            $this->docrecord->assignment,
                            $this->docrecord->discussion,
                            $this->docrecord->userid,
                            $this->docrecord->answerid,
                            $this->docrecord->id,
                            $status,
                            false,
                            $st_curr->msg
                        );
                        return $this->get_error_structure($st_curr->msg);
                    } else if ($st_curr->status === 'InProgress' || $st_curr->status === 'None') {
                        // If the document was downloaded, but the check failed to run, we will run it again.
                        if ($st_curr->status === 'None') {
                            $m = $this->start_check_manual();
                            if (!$m) {
                                return $this->result;
                            }
                        }
                    }
                    // If the check completion status is "ready".
                    if ($st_curr->status === 'Ready') {
                        // Rounding values.
                        $this->calc_orig($st_curr->plagiarism, $st_curr->legal, $st_curr->selfcite, $originality);
                        $this->api_data->plagiarism = $st_curr->plagiarism;
                        $this->api_data->legal = $st_curr->legal;
                        $this->api_data->issuspicious = $st_curr->issuspicious;
                        $this->api_data->selfcite = $st_curr->selfcite;
                        $this->api_data->reportedit = $st_curr->reportedit;
                        $this->api_data->reportread = $st_curr->reportread;
                        $this->api_data->shortreport = $st_curr->shortreport;
                        $this->api_data->timecheck_end = $st_curr->timecheck_end;
                        $this->api_data->status = advacheck_constants::PLAGIARISM_ADVACHECK_CHECKED;
                        $this->api_data->id = $this->docrecord->id;
                        // Let's write down the results.
                        $DB->update_record('plagiarism_advacheck_docs', $this->api_data);
                        $this->logobject->add_log_record(
                            $this->api_data->externalid,
                            $this->api_data->reportedit,
                            5,
                            $this->docrecord->courseid,
                            $this->docrecord->cmid,
                            $this->docrecord->assignment,
                            $this->docrecord->discussion,
                            $this->docrecord->userid,
                            $this->docrecord->answerid,
                            $this->docrecord->id,
                            advacheck_constants::PLAGIARISM_ADVACHECK_CHECKED
                        );

                        // Getting course module settings: add to index or not?
                        $i = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $this->docrecord->courseid, 'cmid' => $this->docrecord->cmid], 'add_to_index');
                        if ($i->add_to_index != 0) {
                            $m = $this->api->set_index_status($this->ap_docid, true);
                            if ($m !== true) {
                                $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_INDEX;
                                $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $this->docrecord->id]);
                                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $this->docrecord->id]);
                                $this->logobject->add_log_record(
                                    $this->docrecord->externalid,
                                    $this->docrecord->reportedit,
                                    14,
                                    $this->docrecord->courseid,
                                    $this->docrecord->cmid,
                                    $this->docrecord->assignment,
                                    $this->docrecord->discussion,
                                    $this->docrecord->userid,
                                    $this->docrecord->answerid,
                                    $this->docrecord->id,
                                    $status,
                                    false,
                                    $m
                                );
                                return $this->get_error_structure($m);
                            }
                            $this->api_data->status = advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX;
                            $DB->set_field('plagiarism_advacheck_docs', 'status', advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX, ['id' => $this->docrecord->id]);
                            // A record of adding a document to the index.
                            $this->logobject->add_log_record(
                                $this->api_data->externalid,
                                $this->api_data->reportedit,
                                8,
                                $this->docrecord->courseid,
                                $this->docrecord->cmid,
                                $this->docrecord->assignment,
                                $this->docrecord->discussion,
                                $this->docrecord->userid,
                                $this->docrecord->answerid,
                                $this->docrecord->id,
                                advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX
                            );
                        }
                        // A record of the completion of document processing.
                        $this->logobject->add_log_record(
                            $this->api_data->externalid,
                            $this->api_data->reportedit,
                            13,
                            $this->docrecord->courseid,
                            $this->docrecord->cmid,
                            $this->docrecord->assignment,
                            $this->docrecord->discussion,
                            $this->docrecord->userid,
                            $this->docrecord->answerid,
                            $this->docrecord->id,
                            $this->api_data->status
                        );
                    } elseif (!empty($st_curr->msg)) {
                        // Any status other than the above is an error in the verification process.
                        $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_CHECK;
                        $DB->set_field('plagiarism_advacheck_docs', 'error', $st_curr->msg, ['id' => $this->docrecord->id]);
                        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $this->docrecord->id]);
                        $this->logobject->add_log_record(
                            $this->docrecord->externalid,
                            $this->docrecord->reportedit,
                            14,
                            $this->docrecord->courseid,
                            $this->docrecord->cmid,
                            $this->docrecord->assignment,
                            $this->docrecord->discussion,
                            $this->docrecord->userid,
                            $this->docrecord->answerid,
                            $this->docrecord->id,
                            $status,
                            false,
                            $st_curr->msg
                        );
                        return $this->get_error_structure($st_curr->msg);
                    }
                }
            } else {
                // Displays the current state of the document.
                switch ((int) $this->docrecord->status) {
                    case advacheck_constants::PLAGIARISM_ADVACHECK_UPLOADING:
                        return $this->get_error_structure(get_string('uploading', 'plagiarism_advacheck'), 'advacheck-green');

                    case advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE:
                        return $this->get_error_structure(get_string('error_filetype', 'plagiarism_advacheck', $this->docrecord->error), 'advacheck-gray');

                    case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_UPLOADING:
                        return $this->get_error_structure(get_string('error_upload', 'plagiarism_advacheck', $this->docrecord->error));
                    // Error when initiating the checking.
                    case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_CHECKING:
                        return $this->get_error_structure(get_string('error_checking', 'plagiarism_advacheck', $this->docrecord->error));
                    // An error occurred during the check process.
                    case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_CHECK:
                        return $this->get_error_structure(get_string('error_check', 'plagiarism_advacheck', $this->docrecord->error));

                    case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_GET_STATUS:
                        return $this->get_error_structure(get_string('error_get_status', 'plagiarism_advacheck', $this->docrecord->error));

                    case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_GET_REPORT:
                        return $this->get_error_structure(get_string('error_get_report', 'plagiarism_advacheck', $this->docrecord->error));

                    case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_INDEX:
                        return $this->get_error_structure(get_string('error_index', 'plagiarism_advacheck', $this->docrecord->error));
                    // In other cases, we display the results of the check.
                    default:
                        $this->calc_orig($this->docrecord->plagiarism, $this->docrecord->legal, $this->docrecord->selfcite, $originality);
                        $this->api_data->plagiarism = $this->docrecord->plagiarism;
                        $this->api_data->legal = $this->docrecord->legal;
                        $this->api_data->issuspicious = (bool) $this->docrecord->issuspicious;
                        $this->api_data->selfcite = $this->docrecord->selfcite;
                        $this->api_data->reportedit = $this->docrecord->reportedit;
                        $this->api_data->reportread = $this->docrecord->reportread;
                        $this->api_data->shortreport = $this->docrecord->shortreport;
                        $this->api_data->externalid = $this->docrecord->externalid;
                        break;
                }
            }
        } else {
            $DB->set_field('plagiarism_advacheck_docs', 'status', advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS, ['id' => $this->docrecord->id]);
        }

        if ($this->islongstr) {
            $this->result->class = "advacheck-gray";
            $this->result->originality = get_string('min_len_str_info', 'plagiarism_advacheck', (int) $this->plugin_cfg->min_len_str);
            $this->result->report = '#';
        } else {
            $this->result->plagiarism = $this->api_data->plagiarism . '%';
            $this->result->legal = $this->api_data->legal . '%';
            $this->result->selfcite = $this->api_data->selfcite . '%';
            $this->result->originality = $originality . "%";
            $this->result->docid = $this->api_data->externalid;
            $this->result->issuspicious = $this->api_data->issuspicious;

            // Taking the module settings on the course.
            $cm_sett = $DB->get_record('plagiarism_advacheck_course', ['cmid' => $this->docrecord->cmid, 'courseid' => $this->docrecord->courseid]);
            // Summary report display mode is enabled.
            if ((int) $cm_sett->disp_notices == 1) {
                $this->result->report = $this->plugin_cfg->uri . $this->api_data->shortreport;
            }
            // If you have the right to view the full report or the mode for displaying the full report is turned on, or the results are viewed by the student checking at the workshop.
            if (
                has_capability('plagiarism/advacheck:viewfullreadreport', $this->context) ||
                ((int) $cm_sett->disp_notices == 2 && !has_capability('plagiarism/advacheck:checkadvacheck', $this->context)) ||
                ($this->docrecord->discussion == 0 && $this->docrecord->assignment == 0 && $USER->id != $this->docrecord->userid)
            ) {
                $this->result->report = $this->plugin_cfg->uri . $this->api_data->reportread;
            } // If you have permission to view the full report with editing.
            if (has_capability('plagiarism/advacheck:viewfullreport', $this->context)) {
                $this->result->report = $this->plugin_cfg->uri . $this->api_data->reportedit;
            }
            // If you don’t have the rights to check, then we’ll add a block with the number of checks in the course module.
            if (!has_capability('plagiarism/advacheck:checkadvacheck', $this->context)) {
                // We received a limit for draft checks in a assignment.
                $check_stud_lim = (int) $cm_sett->check_stud_lim;

                $c = $check_stud_lim - $stud_check;

                if ($c == 0) {
                    // If there are no checks left, then we do not output anything.
                    $this->result->check_studs = 'none';
                } else {
                    $this->result->check_studs = get_string('stud_check_after_edit', 'plagiarism_advacheck', $c);
                }
            }
            if ($originality >= $this->plugin_cfg->originality_limit) {
                $this->result->class = "advacheck-green";
                $this->result->iconclass = "advacheck-green_icon";
                $this->result->icontype = "fa-circle-check";
            } else {
                $this->result->class = "advacheck-red";
                $this->result->iconclass = "advacheck-red_icon";
                $this->result->icontype = "fa-circle-exclamation";
            }
        }
        $this->result->status = $DB->get_field('plagiarism_advacheck_docs', 'status', ['id' => $this->docrecord->id]);
        return $this->result;
    }

    /**
     * Returns a structure to display the error-block or info-block.
     *
     * @param string $msg
     * @param string $class Class of displaied block.
     * @return \stdClass
     */
    private function get_error_structure($msg, $class = "advacheck-red")
    {
        $result = new \stdClass();
        $this->result->class = $class;
        $this->result->error = $msg;
        return $result;
    }

    /**
     * Returns html labels with info with an error or explanation.
     *
     * @param string $string
     * @param string $class
     * @return string
     */
    private function get_html_block_info($string, $class)
    {
        global $OUTPUT;
        $infostring = get_string('error_index', 'plagiarism_advacheck', $string);
        $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfo', ['infostring' => $infostring, 'infoclass' => $class]);
        return $output;
    }

    /**
     * Uploads files, writes to the log, records statuses, and document ID from the AP.
     * Error handling PLAGIARISM_ADVACHECK_ERROR_UPLOADING/
     *
     * @global \moodle_database $DB
     * 
     */
    function upload_document_manual()
    {
        global $DB, $USER;
        // Let's set the status to "PLAGIARISM_ADVACHECK_UPLOADING" so that the cron task does not upload this document again when the upload starts.
        $status = advacheck_constants::PLAGIARISM_ADVACHECK_UPLOADING;
        $DB->set_field('plagiarism_advacheck_docs', 'timeupload_start', time(), ['id' => $this->docrecord->id]);
        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $this->docrecord->id]);
        $this->logobject->add_log_record(
            '',
            '',
            2,
            $this->docrecord->courseid,
            $this->docrecord->cmid,
            $this->docrecord->assignment,
            $this->docrecord->discussion,
            $this->docrecord->userid,
            $this->docrecord->answerid,
            $this->docrecord->id,
            $status
        );

        if ($this->islongstr) {
            $DB->set_field("plagiarism_advacheck_docs", "status", advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS, ["id" => $this->docrecord->id]);
            $s = '';
            $this->result = $this->get_html_block_info(
                get_string('min_len_str_info', 'plagiarism_advacheck', get_config('plagiarism_advacheck', 'min_len_str')),
                'advacheck-gray'
            );
            $this->ap_docid = null;
            return;
        }
        // Let's write down the ID of the person who started the check, this is needed to download the pdf certificate.
        $DB->set_field('plagiarism_advacheck_docs', 'teacherid', $USER->id, ['id' => $this->docrecord->id]);
        $conn_error = '';
        $ap_docid = $this->api->upload_doc($this->filename, $this->content, $this->docrecord->courseid, false, $conn_error, $this->data_attr->custom_attrs);
        // Error handling during unloading.
        if (is_string($ap_docid)) {
            $s = $ap_docid;
            if ($conn_error) {
                $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_UPLOADING;
                $this->result = $this->get_error_structure(get_string('error_upload', 'plagiarism_advacheck', $s));
            } else {
                $status = advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE;
                $this->result = $this->get_error_structure(get_string('error_filetype', 'plagiarism_advacheck', $s));
            }
            $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $this->docrecord->id]);
            $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $this->docrecord->id]);
            $this->logobject->add_log_record(
                '',
                '',
                14,
                $this->docrecord->courseid,
                $this->docrecord->cmid,
                $this->docrecord->assignment,
                $this->docrecord->discussion,
                $this->docrecord->userid,
                $this->docrecord->answerid,
                $this->docrecord->id,
                $status,
                false,
                $s
            );
            $this->ap_docid = null;
            return;
        } else {
            // Let's check whether Anti-Plagiarism considered the document erroneous.
            if ($ap_docid->Reason !== 'NoError') {
                $status = advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE;
                $s = $ap_docid->FailDetails;
                $this->result = $this->get_error_structure(get_string('error_filetype', 'plagiarism_advacheck', $s));
                $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $this->docrecord->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $this->docrecord->id]);
                $this->logobject->add_log_record(
                    '',
                    '',
                    14,
                    $this->docrecord->courseid,
                    $this->docrecord->cmid,
                    $this->docrecord->assignment,
                    $this->docrecord->discussion,
                    $this->docrecord->userid,
                    $this->docrecord->answerid,
                    $this->docrecord->id,
                    $status,
                    false,
                    $s
                );
                $this->ap_docid = null;
                return;
            }
            $this->ap_docid = $ap_docid->Id->Id;
            $this->logobject->add_log_record(
                $this->ap_docid,
                '',
                11,
                $this->docrecord->courseid,
                $this->docrecord->cmid,
                $this->docrecord->assignment,
                $this->docrecord->discussion,
                $this->docrecord->userid,
                $this->docrecord->answerid,
                $this->docrecord->id,
                $status
            );
            // Let's load the document attributes.
            $doc_attr_res = $this->api->upload_doc_attr($this->ap_docid, $this->data_attr->custom_attrs);
            $this->logobject->add_log_record(
                $this->ap_docid,
                '',
                12,
                $this->docrecord->courseid,
                $this->docrecord->cmid,
                $this->docrecord->assignment,
                $this->docrecord->discussion,
                $this->docrecord->userid,
                $this->docrecord->answerid,
                $this->docrecord->id,
                $status
            );
            // Error while trying to load attributes.
            if (is_string($doc_attr_res)) {
                $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_UPLOADING;
                $s = $doc_attr_res;
                $this->result = $this->get_error_structure(get_string('error_upload', 'plagiarism_advacheck', $s));
                $DB->set_field('plagiarism_advacheck_docs', 'error', $s, ['id' => $this->docrecord->id]);
                $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $this->docrecord->id]);
                $this->logobject->add_log_record(
                    $this->ap_docid,
                    '',
                    14,
                    $this->docrecord->courseid,
                    $this->docrecord->cmid,
                    $this->docrecord->assignment,
                    $this->docrecord->discussion,
                    $this->docrecord->userid,
                    $this->docrecord->answerid,
                    $this->docrecord->id,
                    $status,
                    false,
                    $s
                );
                $this->ap_docid = null;
                return;
            }
            // Let's rewrite it so that it matches the further logic of the code.        
            $this->api_data->externalid = $this->ap_docid;
            $rec['timeupload_end'] = time();
            $rec['status'] = advacheck_constants::PLAGIARISM_ADVACHECK_UPLOADED;
            $rec['externalid'] = $this->ap_docid;
            $rec['id'] = $this->docrecord->id;
            $DB->update_record('plagiarism_advacheck_docs', (object) $rec);
            $this->logobject->add_log_record(
                $this->api_data->externalid,
                '',
                3,
                $this->docrecord->courseid,
                $this->docrecord->cmid,
                $this->docrecord->assignment,
                $this->docrecord->discussion,
                $this->docrecord->userid,
                $this->docrecord->answerid,
                $this->docrecord->id,
                advacheck_constants::PLAGIARISM_ADVACHECK_UPLOADED
            );
        }
    }

    /**
     * Initiates a scan, records the status "being checked", the start time of the scan, and makes a log entry.
     * Handles an error PLAGIARISM_ADVACHECK_ERROR_CHECKING
     *
     * @global \moodle_database $DB
     * @return boolean true - in case of success, false - in case of errors
     */
    function start_check_manual()
    {
        global $DB;
        $cm_sett = $DB->get_record('plagiarism_advacheck_course', ['cmid' => $this->docrecord->cmid, 'courseid' => $this->docrecord->courseid]);
        $ignoresections = [
            "Title" => !$cm_sett->docsecttitle,
            "Content" => !$cm_sett->docsectcontent,
            "Bibliography" => !$cm_sett->docsectbibliography,
            "Appendix" => !$cm_sett->docsectappendix,
            "Introduction" => !$cm_sett->docsectintroduction,
            "Method" => !$cm_sett->docsectmethod,
            "Conclusion" => !$cm_sett->docsectconclusion,
        ];

        $m = $this->api->start_check($this->ap_docid, $ignoresections);

        // The status is “PLAGIARISM_ADVACHECK_CHECKING”, because checks can take a long time.
        $status = advacheck_constants::PLAGIARISM_ADVACHECK_CHECKING;
        $DB->set_field('plagiarism_advacheck_docs', 'timecheck_start', time(), ['id' => $this->docrecord->id]);
        $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $this->docrecord->id]);
        $this->logobject->add_log_record(
            $this->api_data->externalid,
            '',
            4,
            $this->docrecord->courseid,
            $this->docrecord->cmid,
            $this->docrecord->assignment,
            $this->docrecord->discussion,
            $this->docrecord->userid,
            $this->docrecord->answerid,
            $this->docrecord->id,
            $status
        );
        // Handling an error in obtaining the verification status.
        if ($m !== true) {
            $status = advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_CHECKING;
            $DB->set_field('plagiarism_advacheck_docs', 'error', $m, ['id' => $this->docrecord->id]);
            $DB->set_field('plagiarism_advacheck_docs', 'status', $status, ['id' => $this->docrecord->id]);
            $this->logobject->add_log_record(
                $this->docrecord->externalid,
                $this->docrecord->reportedit,
                14,
                $this->docrecord->courseid,
                $this->docrecord->cmid,
                $this->docrecord->assignment,
                $this->docrecord->discussion,
                $this->docrecord->userid,
                $this->docrecord->answerid,
                $this->docrecord->id,
                $status,
                false,
                $m
            );
            $this->result = $this->get_error_structure($m);
            return false;
        } else {
            return true;
        }
    }

    /**
     * Updates the check result.
     *
     * @global \moodle_database $DB
     * @param mixed $typeid Sha-2 hash | fileid.
     * @return \stdClass
     */
    public static function update_advacheck_report($typeid)
    {
        global $DB, $USER;
        $plugin_cfg = get_config('plagiarism_advacheck');
        $docrecord = $DB->get_record('plagiarism_advacheck_docs', ['typeid' => $typeid]);
        $result = new \stdClass();
        $result->status = $DB->get_field('plagiarism_advacheck_docs', 'status', ['id' => $docrecord->id]);
        $docid = $docrecord->externalid;
        $logobject = new queue_log_manager();
        $api = new advacheck_api();
        if ($result->status == advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX || $result->status == advacheck_constants::PLAGIARISM_ADVACHECK_CHECKED) {
            $api_data = $api->get_check_report($docid);
            $api_data->status = "Ready";
        } else {
            $api_data = $api->get_current_check_status($docid);
        }
        if (!empty($docrecord->error)) {
            $result->error = $docrecord->error;
        }

        $originality = 0;
        if (!isset($api_data->error) && $api_data->status === "Ready") {
            self::calc_orig($api_data->plagiarism, $api_data->legal, $api_data->selfcite, $originality);
            $docrecord->plagiarism = $api_data->plagiarism;
            $docrecord->legal = $api_data->legal;
            $docrecord->issuspicious = $api_data->issuspicious;
            $docrecord->selfcite = $api_data->selfcite;
            $docrecord->originality = $originality;
            $docrecord->timecheck_end = $api_data->timecheck_end;
            $docrecord->reportedit = $api_data->reportedit;
            $docrecord->reportread = $api_data->reportread;
            $docrecord->shortreport = $api_data->shortreport;
            $docrecord->timecheck_end = $api_data->timecheck_end;
            $DB->update_record('plagiarism_advacheck_docs', $docrecord);
            $logobject->add_log_record(
                $docrecord->externalid,
                $docrecord->reportedit,
                9,
                $docrecord->courseid,
                $docrecord->cmid,
                $docrecord->assignment,
                $docrecord->discussion,
                $docrecord->userid,
                $docrecord->answerid,
                $docrecord->id,
                $docrecord->status
            );
        } elseif (!empty($api_data->error)) {
            $result->error = $api_data->error;
            $DB->set_field("plagiarism_advacheck_docs", "error", $api_data->error, ["id" => $docrecord->id]);
            $logobject->add_log_record(
                $docrecord->externalid,
                $docrecord->reportedit,
                14,
                $docrecord->courseid,
                $docrecord->cmid,
                $docrecord->assignment,
                $docrecord->discussion,
                $docrecord->userid,
                $docrecord->answerid,
                $docrecord->id,
                (int) $docrecord->status,
                false,
                get_string('updatereporterror', 'plagiarism_advacheck', $api_data->error),
            );
        }
        if (($result->status == advacheck_constants::PLAGIARISM_ADVACHECK_ININDEX || $result->status == advacheck_constants::PLAGIARISM_ADVACHECK_CHECKED)) {
            $result->plagiarism = $docrecord->plagiarism . '% ';
            $result->selfcite = $docrecord->selfcite . '%';
            $result->legal = $docrecord->legal . '% ';
            $result->originality = $docrecord->originality . '% ';
            $result->issuspicious = $api_data->issuspicious;

            $context = \context_course::instance($docrecord->courseid, MUST_EXIST);
            if (
                has_capability('plagiarism/advacheck:viewfullreadreport', $context) ||
                ($docrecord->discussion == 0 && $docrecord->assignment == 0 && $USER->id != $docrecord->userid)
            ) {
                $result->report = $plugin_cfg->uri . $api_data->reportread;
            }
            if (has_capability('plagiarism/advacheck:viewfullreport', $context)) {
                $result->report = $plugin_cfg->uri . $api_data->reportedit;
            }

            if ($docrecord->originality >= $plugin_cfg->originality_limit) {
                $result->class = "advacheck-green";
                $result->iconclass = "advacheck-green_icon";
                $result->icontype = "fa-circle-check";
            } else {
                $result->class = "advacheck-red";
                $result->iconclass = "advacheck-red_icon";
                $result->icontype = "fa-circle-exclamation";
            }
        } else if (!$docrecord) {
            $result->error = get_string('docnotchecked', 'plagiarism_advacheck');
        }
        return $result;
    }

    /**
     * Collects the filename from the first three words of the content, up to a maximum of 30 characters.
     *
     * @param string $content
     * @return string
     */
    public static function assemble_filename($content)
    {
        $filename = '';
        $str_name = mb_substr($content, 0, 30);
        $arr = preg_split('/[^\w]+/u', $str_name);
        if (empty($arr[1])) {
            $arr[1] = '';
        }
        if (empty($arr[2])) {
            $arr[2] = '';
        }
        if ((mb_strlen($arr[0]) + mb_strlen($arr[1]) + mb_strlen($arr[2])) > 16) {
            if ((mb_strlen($arr[0]) + mb_strlen($arr[1])) > 16) {
                $filename = $arr[0];
            } else {
                $filename = $arr[0] . '_' . $arr[1];
            }
        } else {
            $filename = $arr[0] . '_' . $arr[1] . '_' . $arr[2];
        }
        return $filename . '.txt';
    }

    /**
     * Rounds the % of originality in the same way as in the report on the Anti-Plagiarism page.
     *
     * @param float $plagiarism
     * @param float $legal
     * @param float $selfcite
     * @param float $originality
     */
    public static function calc_orig(&$plagiarism, &$legal, &$selfcite, &$originality)
    {

        if ($plagiarism == round($plagiarism, 0)) {
            $plagiarism = round($plagiarism, 0);
        } else {
            $plagiarism = round($plagiarism, 2);
        }

        if ($legal == round($legal, 0)) {
            $legal = round($legal, 0);
        } else {
            $legal = round($legal, 2);
        }

        if ($selfcite == round($selfcite, 0)) {
            $selfcite = round($selfcite, 0);
        } else {
            $selfcite = round($selfcite, 2);
        }

        $originality = 100 - $plagiarism - $legal - $selfcite;

        if ($originality == round($originality, 0)) {
            $originality = round($originality, 0);
        } else {
            $originality = round($originality, 2);
        }
    }
}