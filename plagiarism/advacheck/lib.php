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
 * Functions for working with API Moodle.
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

use \plagiarism_advacheck\local\advacheck_constants;
use \plagiarism_advacheck\local\upload_start_check_manual;
use \plagiarism_advacheck\local\queue_log_manager;
use \plagiarism_advacheck\local\document_queue_manager;

// Get global class.
global $CFG;
require_once($CFG->dirroot . '/plagiarism/lib.php');
require_once($CFG->dirroot . '/plagiarism/advacheck/classes/local/constants.php');
require_once($CFG->dirroot . '/plagiarism/advacheck/classes/local/document_queue_manager.php');

/**
 * A class that adds verification results, a block with information, or a button to send for verification.
 */
class plagiarism_plugin_advacheck extends plagiarism_plugin
{

    /** @var mixed module plagiarism advacheck settings. */
    public static $cnfg = null;
    /** @var mixed Common lagiarism advacheck settings. */
    public static $plugin_cfg = null;
    private static $docrecords = [];
    /** @var bool flag - is first load or no*/
    private static $firstload = true;
    /** 
     * Course object
     * @var object
     */
    private static $course;
    /**
     * Id of course
     * @var int
     */
    private static $courseid;
    /**
     * Context object
     * @var object
     */
    private static $context;
    /**
     * Coursemodule object
     * @var object
     */
    private static $cm;
    /**
     * Id of coursemodule
     * @var int
     */
    private static $cmid;
    /**
     * Id of document`s user
     * @var int
     */
    private static $userid;
    /**
     * Is the site admin.
     * @var bool
     */
    private static $siteadmin;
    /**
     * Type of document
     * @var int
     */
    private static $doctype;
    /**
     * Require students to click the submit button
     * @var bool
     */
    private static $submissiondrafts;

    /**
     * Hook to allow plagiarism specific information to be displayed beside a submission.
     * @param array $linkarray all relevant information for the plugin to generate a link
     * @return string
     *
     */
    public function get_links($linkarray)
    {
        global $PAGE, $CFG, $DB;
        global $OUTPUT;

        if (!$linkarray['userid']) {
            return '';
        } else {
            self::$userid = $linkarray['userid'];
        }

        if (self::$firstload) {
            // Loaded the plugin settings and added JS to the page.
            $this->_preLoad($linkarray);
            self::$firstload = false;
        }

        // File object.
        $file = isset($linkarray['file']) ? $linkarray['file'] : null;
        // Clean text content.
        $content = isset($linkarray['content']) ? document_queue_manager::get_strip_text_content_hash($linkarray['content'], true) : null;

        // If the check is not enabled, it returns an empty string.
        if (empty(self::$cnfg->mode)) {
            return '';
        }
        // Let’s take the “can check?” rights once.
        $checkcap = has_capability('plagiarism/advacheck:checkadvacheck', self::$context);
        // If the current user is a student and the display of scan results is disabled.
        if (!self::$cnfg->disp_notices && !$checkcap && !self::$siteadmin) {
            return '';
        }

        // If the course module is an assignment, then we will check whether verification is enabled in the assignments.
        if (self::$doctype == advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN) {
            if (empty(self::$plugin_cfg->check_assign)) {
                return '';
            }
        }
        // If the course module is a forum, then let's check whether checking is enabled in forums.
        if (self::$doctype == advacheck_constants::PLAGIARISM_ADVACHECK_FORUM) {
            if (empty(self::$plugin_cfg->check_forum)) {
                return '';
            }
        }
        // If the course module is a workshop, then we will check whether verification is enabled in workshops.
        if (self::$doctype == advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP) {
            if (empty(self::$plugin_cfg->check_workshop)) {
                return '';
            }
        }
        // If the course module is a quiz, then we will check whether verification is enabled in quiz.
        if (self::$doctype == advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ) {
            if (empty(self::$plugin_cfg->check_quiz)) {
                return '';
            }
        }

        // If the content is a file, then we will check whether file checking is enabled.
        if ($file) {
            if (empty(self::$cnfg->checkfile)) {
                return '';
            }

            $doctype = advacheck_constants::PLAGIARISM_ADVACHECK_FILE;
            $typeid = $file->get_id();

        } else if ($content) {
            // If the content is text, then let's check whether text checking is enabled.
            if (empty(self::$cnfg->checktext)) {
                return '';
            }
            $doctype = self::$doctype;
            $typeid = document_queue_manager::get_strip_text_content_hash($linkarray['content']);
        } else {
            return '';
        }

        $data = isset(self::$docrecords["$doctype-$typeid-" . self::$userid]) ? self::$docrecords["$doctype-$typeid-" . self::$userid] : false;

        // If we have results with a hash that was calculated with the old algorithm.
        if (!$data && isset($content)) {
            $typeid2 = sha1($linkarray['content']);
            $data = isset(self::$docrecords["$doctype-$typeid2-" . self::$userid]) ? self::$docrecords["$doctype-$typeid2-" . self::$userid] : false;
            if ($data) {
                // Write new hash.
                $DB->set_field('plagiarism_advacheck_docs', 'typeid', $typeid, ['id' => $data->id]);
            }
        }

        // Variable for html.
        $output = '';
        if ($data) {
            switch ((int) $data->status) {
                // Waits for blocking - unless "Require students to click the submit button" is enabled in the task.
                case advacheck_constants::PLAGIARISM_ADVACHECK_WAITBLOCK:
                    if ($checkcap) {
                        // If Require students to click the submit button.
                        if (self::$submissiondrafts) {
                            // Info for the teacher: the student must submit an answer for verification.
                            $msg = get_string('wait_block_submissiondrafts_yes', 'plagiarism_advacheck');
                        } else {
                            // For the teacher: You should be prohibited from changing the answer.
                            $msg = get_string('wait_block_submissiondrafts_no', 'plagiarism_advacheck');
                        }
                        $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $msg]);
                    } else {
                        // Information for students: let's check the limit of checks and how many have been checked.
                        if ((int) $data->stud_check >= (int) self::$cnfg->check_stud_lim) {
                            $infostring = get_string('stud_not_check', 'plagiarism_advacheck');
                            $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                        } else {
                            $c = (int) self::$cnfg->check_stud_lim - (int) $data->stud_check;
                            $infostring = get_string('stud_check', 'plagiarism_advacheck', $c);
                            $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                            $output = html_writer::tag('p', $output);
                            // Receive html buttons for sending for verification.
                            $output .= $this->get_check_button_html(
                                true,
                                $doctype,
                                $content,
                                $typeid,
                                $data->id
                            );
                        }
                    }
                    break;
                // The document is waiting to be sent.
                case advacheck_constants::PLAGIARISM_ADVACHECK_WAITUPLOAD:
                    // We calculate the adding time.
                    $age = time() - (int) $data->timeadded;
                    // For forums, let's calculate whether the editing time has passed?
                    if (!empty($forum) && $age < $CFG->maxeditingtime) {
                        $a = new stdClass();
                        $a->t = time();
                        // We calculate the time to complete editing.
                        $t = ((int) $data->timeadded + (int) $CFG->maxeditingtime) - $a->t;
                        $a->i = intval($t / 60);
                        $a->s = $t - $a->i * 60;
                        $a->t = date('H:i', $a->t + (int) $CFG->maxeditingtime);
                        $infostring = get_string('edit_time', 'plagiarism_advacheck', $a);
                        $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                    } else if (!$checkcap && $data->workshop != 0) {
                        // At the workshop, just like in the assignment, if the check limit for a student has not been reached, then we display a button.
                        if ((int) $data->stud_check >= (int) self::$cnfg->check_stud_lim) {
                            $infostring = get_string('stud_not_check', 'plagiarism_advacheck');
                            $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                        } else {
                            $c = (int) self::$cnfg->check_stud_lim - (int) $data->stud_check;
                            $infostring = get_string('stud_check', 'plagiarism_advacheck', $c);
                            $addclass = "stud_check-$typeid";
                            $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring, 'addclass' => $addclass]);

                            // Receive html buttons for sending for verification.
                            $output .= $this->get_check_button_html(
                                true,
                                $doctype,
                                $content,
                                $typeid,
                                $data->id
                            );
                        }
                    } else { // If the editing time has expired and automatic checking is enabled, then display the corresponding information.                        
                        if (self::$cnfg->mode == advacheck_constants::PLAGIARISM_ADVACHECK_AUTOMODE) {
                            $class = "check_notice $typeid";
                            $infostring = get_string('wait_upload', 'plagiarism_advacheck', '');
                            $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring, 'addclass' => $class]);
                        } else if (!$checkcap) {
                            // Information for students about the end of the check limit.
                            $class = "check_notice $typeid";
                            $infostring = get_string('stud_not_check', 'plagiarism_advacheck');
                            $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring, 'addclass' => $class]);
                        }
                        if ($checkcap) {
                            $output .= $this->get_check_button_html(
                                $checkcap,
                                $doctype,
                                $content,
                                $typeid,
                                $data->id
                            );
                        }
                    }
                    break;
                // The document is in the process of being uploaded.
                case advacheck_constants::PLAGIARISM_ADVACHECK_UPLOADING:
                    $infostring = get_string('uploading', 'plagiarism_advacheck');
                    $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                    break;
                // The document has been uploaded.
                case advacheck_constants::PLAGIARISM_ADVACHECK_UPLOADED:
                    $infostring = get_string('uploaded', 'plagiarism_advacheck');
                    $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                    break;
                // The document is in the process of being reviewed.
                case advacheck_constants::PLAGIARISM_ADVACHECK_CHECKING:
                    // Animation of the verification process.
                    $data = [
                        'typeid' => $typeid,
                        'hidden' => '',
                        'infostring' => get_string('checking', 'plagiarism_advacheck')
                    ];
                    // Get loader animation.
                    $output .= $OUTPUT->render_from_template('plagiarism_advacheck/loaderimg', $data);
                    break;
                // Insufficient number of words.
                case advacheck_constants::PLAGIARISM_ADVACHECK_LESSNWORDS:
                    // Display a message at the time of queuing, because the number of words could have changed.                    
                    $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $data->error]);
                    break;
                // There is no right to be verified - we don’t show anything, for example, a teacher’s answer on the forum.
                case advacheck_constants::PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY:
                    $output = '';
                    break;
                case advacheck_constants::PLAGIARISM_ADVACHECK_INVALIDFILETYPE:
                    $infostring = get_string('uploaded', 'plagiarism_advacheck');
                    $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                    break;
                // Error when trying to upload.
                case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_UPLOADING:
                    $infostring = get_string('error_upload', 'plagiarism_advacheck', $data->error);
                    $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                    break;
                // Error when initiating document verification.
                case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_CHECKING:
                    $infostring = get_string('error_checking', 'plagiarism_advacheck', $data->error);
                    $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring,]);
                    break;
                // An error occurred during the verification process.   
                case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_CHECK:
                    $infostring = get_string('error_check', 'plagiarism_advacheck', $data->error);
                    $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                    break;
                // Error when trying to get status.
                case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_GET_STATUS:
                    $infostring = get_string('error_get_status', 'plagiarism_advacheck', $data->error);
                    $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                    break;
                // When trying to get a report.
                case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_GET_REPORT:
                    $infostring = get_string('error_get_report', 'plagiarism_advacheck', $data->error);
                    $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                    break;
                // Error when trying to add/remove to index.
                case advacheck_constants::PLAGIARISM_ADVACHECK_ERROR_INDEX:
                    $infostring = get_string('error_index', 'plagiarism_advacheck', $data->error);
                    $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
                    break;
                // In other cases, we display the results of the check.
                default:
                    $output = $this->get_result_html($data);
                    break;
            }
        } else {
            // The case when the document is not added to the queue.
            // We show the message only to those who have the right to check documents.
            if ($checkcap) {
                $infostring = get_string('not_in_queue', 'plagiarism_advacheck');
                $output = $OUTPUT->render_from_template('plagiarism_advacheck/blockinfoclear', ['infostring' => $infostring]);
            }
        }

        if ($output != '') {
            $output = html_writer::div($output);
        }

        return $output;
    }

    /**
     * Loads general plugin settings and validation settings for a given course module.
     * Adds JS to the page.
     *
     * @global moodle_page $PAGE
     * @global moodle_database $DB
     * @param array $linkarray
     */
    private function _preLoad($linkarray)
    {
        global $PAGE, $DB;

        self::$siteadmin = is_siteadmin($linkarray['userid']);
        if (isset($linkarray["component"])) {
            if ($linkarray["component"] == 'qtype_essay') {

                $sql =
                    "SELECT cm.id, cm.course
                       FROM {context} ctx
                       JOIN {course_modules} cm ON cm.id = ctx.instanceid
                      WHERE ctx.id = ?";
                $cm = $DB->get_record_sql($sql, [$linkarray['context']]);
                $linkarray['cmid'] = $cm->id;
                $linkarray['course'] = $cm->course;
            } else {
                return '';
            }
        }

        self::$course = $linkarray['course'];
        self::$courseid = is_object(static::$course) ? static::$course->id : static::$course;
        self::$context = context_module::instance($linkarray['cmid'], MUST_EXIST);
        self::$cmid = $linkarray['cmid'];
        $sql = "SELECT cm.*, m.name as modname, cm.instance 
                  FROM {course_modules} cm
                  JOIN {modules} m ON cm.module = m.id
                 WHERE cm.id = ? 
                 ";
        self::$cm = $DB->get_record_sql($sql, [self::$cmid]);
        self::$doctype = $this->get_module_type_by_name(self::$cm->modname);
        if (self::$doctype == advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN) {
            self::$submissiondrafts = $DB->get_field('assign', 'submissiondrafts', ['id' => self::$cm->instance]);
        }

        if (self::$plugin_cfg == null) {
            self::$plugin_cfg = get_config('plagiarism_advacheck');
        }

        if (self::$cnfg == null) {
            self::$cnfg = $DB->get_record('plagiarism_advacheck_course', ['cmid' => self::$cmid, 'courseid' => self::$courseid]);
        }
        // Get docrecords for this coursemodule
        $docrecrdsraw = $DB->get_records('plagiarism_advacheck_docs', ['cmid' => self::$cmid], 'timeadded ASC');
        foreach ($docrecrdsraw as $drr) {
            $key = "$drr->doctype-$drr->typeid-$drr->userid";
            if (empty(self::$docrecords[$key])) {
                self::$docrecords[$key] = $drr; // Select records
            }
        }
        $PAGE->requires->js_call_amd('plagiarism_advacheck/check', 'addPlagiarismCtrlButtons');
    }

    /**
     * Collects the html code of the verification results.
     *
     * @global object $OUTPUT
     * @param stdClass $data Structure with test results.
     * @return string
     */
    private function get_result_html($data)
    {
        global $OUTPUT, $USER;
        $output = '';

        $plagiarism = isset($data->plagiarism) ? $data->plagiarism : 0;
        $legal = isset($data->legal) ? $data->legal : 0;
        $selfcite = isset($data->selfcite) ? $data->selfcite : 0;

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

        $orig = 100 - $plagiarism - $legal - $selfcite;

        if ($orig == round($orig, 0)) {
            $orig = round($orig, 0);
        } else {
            $orig = round($orig, 2);
        }

        // Get the link according to the rights.
        $link = '';
        if (has_capability('plagiarism/advacheck:viewfullreport', self::$context)) {
            // If you have the right to view the full report
            if (!empty($data->reportedit)) {
                $link = self::$plugin_cfg->uri . $data->reportedit;
            }
        } else if (
            // If you have the right to view the full report or report output mode = view the full report or work by the student checking in the workshop.
            has_capability('plagiarism/advacheck:viewfullreadreport', self::$context) || (int) self::$cnfg->disp_notices == 2 ||
            ($data->discussion == 0 && $data->assignment == 0 && $USER->id != $data->userid)
        ) {
            if (!empty($data->reportread)) {
                $link = self::$plugin_cfg->uri . $data->reportread;
            }
        } else if (has_capability('plagiarism/advacheck:viewshortreport', self::$context) || (int) self::$cnfg->disp_notices == 1) {
            // If you have the right to view a short report or water notification mode = short report.
            if (!empty($data->shortreport)) {
                $link = self::$plugin_cfg->uri . $data->shortreport;
            }
        }

        // Get class to display or not suspicious icon
        $suspicioustoggleclass = '';
        if (!$data->issuspicious) {
            $suspicioustoggleclass = "$data->typeid advacheck-suspiciousoff";
        } else {
            $suspicioustoggleclass = "$data->typeid advacheck-suspiciouson";
        }

        // Get class to fill block with check result
        $class = "";
        $iconclass = "";
        $icontype = "";
        if ($orig >= self::$plugin_cfg->originality_limit) {
            $class .= " advacheck-green";
            $iconclass .= "advacheck-green_icon";
            $icontype .= "fa-solid fa-circle-check";
        } else {
            $class .= " advacheck-red";
            $iconclass .= "advacheck-red_icon";
            $icontype .= "fa-solid fa-circle-exclamation";
        }

        // Html of button to download certificate about check.
        // Optional functionality, check with the verification services subscription provider.
        $downloadcertificate = '';
        if (advacheck_constants::PLAGIARISM_ADVACHECK_VIEW_CERTIFICATE) {
            $data = [
                'downloadhref' => new moodle_url("/plagiarism/advacheck/downloadfile.php", ['userid' => $data->userid, 'docid' => $data->docid])
            ];
            $downloadcertificate = $OUTPUT->render_from_template('plagiarism_advacheck/downloadcertificate', $data);
        }
        $plagiarismresulthelp = $OUTPUT->help_icon('checkresult', 'plagiarism_advacheck');
        $data = [
            'typeid' => $data->typeid,
            'class' => $class,
            'plagiarism' => $plagiarism,
            'selfcite' => $selfcite,
            'legal' => $legal,
            'originality' => $orig,
            'reportlinkhref' => $link,
            'updatereporthref' => '#',
            'downloadcertificate' => $downloadcertificate,
            'suspiciouslnkhref' => $link,
            'suspicioustoggleclass' => $suspicioustoggleclass,
            'plagiarismresulthelp' => $plagiarismresulthelp,
            'coloricon' => $iconclass,
            'icontype' => $icontype,

        ];
        $output = $OUTPUT->render_from_template('plagiarism_advacheck/plagiarismresult', $data);
        return $output;
    }

    /**
     * Collects the html code of the submit button for review.
     *
     * @param bool $checkcap Rights for submit to check.
     * @param int $doctype Type of docs
     * @param string $content Content of text answer
     * @param string $typeid Sha1-hash or fileid
     * @param int $docid Id document in queue
     * @return string
     */
    private function get_check_button_html(
        $checkcap,
        $doctype,
        $content,
        $typeid,
        $docid
    ) {
        global $OUTPUT;
        $output = '';
        if ($checkcap) {
            // Html of button to download certificate about check.
            // Optional functionality, check with the verification services subscription provider.
            $downloadcertificate = '';
            if (advacheck_constants::PLAGIARISM_ADVACHECK_VIEW_CERTIFICATE) {
                $data = [
                    'downloadhref' => new moodle_url("/plagiarism/advacheck/downloadfile.php", ['userid' => self::$userid, 'docid' => $docid])
                ];
                $downloadcertificate = $OUTPUT->render_from_template('plagiarism_advacheck/downloadcertificate', $data);
            }
            $data = [
                'typeid' => $typeid,
                'hidden' => 'advacheck-hidden',
                'infostring' => get_string('checking', 'plagiarism_advacheck')
            ];
            // Get loader animation.
            $loaderhtml = $OUTPUT->render_from_template('plagiarism_advacheck/loaderimg', $data);
            $plagiarismresulthelp = $OUTPUT->help_icon('checkresult', 'plagiarism_advacheck');
            $data = [
                'typeid' => $typeid,
                'class' => 'advacheck-hidden',
                'plagiarism' => '-',
                'selfcite' => '-',
                'legal' => '-',
                'originality' => '-',
                'reportlinkhref' => '#',
                'updatereporthref' => '#',
                'downloadcertificate' => $downloadcertificate,
                'suspiciouslnkhref' => '#',
                'suspicioustoggleclass' => 'advacheck-suspiciousoff',
                'plagiarismresulthelp' => $plagiarismresulthelp,
                'hidden' => 'advacheck-hidden',
                'icontype' => 'invisibleicon',

            ];
            $plagiarismresult = $OUTPUT->render_from_template('plagiarism_advacheck/plagiarismresult', $data);
            $data = [
                'typeid' => $typeid,
                'loader' => $loaderhtml,
                'courseid' => self::$courseid,
                'doctype' => $doctype,
                'content' => $content,
                'userid' => self::$userid,
                'plagiarismresult' => $plagiarismresult,
            ];
            $output = $OUTPUT->render_from_template('plagiarism_advacheck/checkbutton', $data);
        }
        return $output;
    }

    /**
     * Matches string names of course module type and numeric constants and vice versa
     *
     * @param mixed $mod type of course module in int or string.
     * @param bool $rev Reversive.
     * @return mixed
     */
    private function get_module_type_by_name($mod, $rev = true)
    {
        if ($rev) {
            switch ($mod) {
                case 'forum':
                    return advacheck_constants::PLAGIARISM_ADVACHECK_FORUM;
                case 'assign':
                    return advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN;
                case 'workshop':
                    return advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP;
                case 'quiz':
                    return advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ;
            }
        } else {
            switch ($mod) {
                case advacheck_constants::PLAGIARISM_ADVACHECK_FORUM:
                    return 'forum';
                case advacheck_constants::PLAGIARISM_ADVACHECK_ASSIGN:
                    return 'assign';
                case advacheck_constants::PLAGIARISM_ADVACHECK_WORKSHOP:
                    return 'workshop';
                case advacheck_constants::PLAGIARISM_ADVACHECK_QUIZ:
                    return 'quiz';
            }
        }
    }
}

/**
 * Hook to add plagiarism specific settings to a module settings page
 *
 * @param moodleform $formwrapper
 * @param MoodleQuickForm $mform
 */
function plagiarism_advacheck_coursemodule_standard_elements($formwrapper, $mform)
{
    global $DB, $COURSE, $CFG;

    $plugin_cfg = get_config('plagiarism_advacheck');

    if (empty($plugin_cfg->enabled)) {
        return;
    }

    $cmid = null;
    if ($cm = $formwrapper->get_coursemodule()) {
        $cmid = $cm->id;
    }

    $courseid = $formwrapper->get_course()->id;

    $courseid = $courseid ? $courseid : $COURSE->id;
    $coursecontext = context_course::instance($courseid, MUST_EXIST);

    $hasCapability = has_capability('plagiarism/advacheck:manage', $coursecontext);

    // Check in which modules we have document checking enabled.
    $allowmodules = [];

    $matches = [];
    preg_match('/^mod_([^_]+)_mod_form$/', get_class($formwrapper), $matches);
    $modulename = "mod_" . $matches[1];

    if (!empty($plugin_cfg->check_forum)) {
        $allowmodules[] = "mod_forum";
    }
    if (!empty($plugin_cfg->check_assign)) {
        $allowmodules[] = "mod_assign";
    }

    if (!empty($plugin_cfg->check_workshop)) {
        $allowmodules[] = "mod_workshop";
    }

    if ($CFG->version >= 2021051700) {
        if (!empty($plugin_cfg->check_quiz)) {
            $allowmodules[] = "mod_quiz";
        }
    }

    if (in_array($modulename, $allowmodules) && $hasCapability) {
        // Header of the settings block for checking in Anti-plagiarism.
        $mform->addElement(
            'header',
            'plugin_header',
            get_string('coursesettings', 'plagiarism_advacheck')
        );

        // Check mode.
        $options = [
            advacheck_constants::PLAGIARISM_ADVACHECK_DISABLEDMODE => get_string('disabledmode', 'plagiarism_advacheck'),
            advacheck_constants::PLAGIARISM_ADVACHECK_MANUALMODE => get_string('manualmode', 'plagiarism_advacheck'),
            advacheck_constants::PLAGIARISM_ADVACHECK_AUTOMODE => get_string('automode', 'plagiarism_advacheck'),
        ];
        $mod_settings = $DB->get_record('plagiarism_advacheck_course', ['cmid' => $cmid]);

        $mform->addElement('select', 'advacheck_mode', get_string('mode', 'plagiarism_advacheck'), $options);

        $mform->addElement(
            'advcheckbox',
            'advacheck_checktext',
            get_string('checktext', 'plagiarism_advacheck'),
            get_string('enable', 'plagiarism_advacheck'),
            [],
            [0, 1]
        );
        $mform->disabledIf('advacheck_checktext', 'advacheck_mode', 'eq', 0);

        $mform->addElement(
            'advcheckbox',
            'advacheck_checkfile',
            get_string('checkfile', 'plagiarism_advacheck'),
            get_string('enable', 'plagiarism_advacheck'),
            [],
            [0, 1]
        );
        $mform->disabledIf('advacheck_checkfile', 'advacheck_mode', 'eq', 0);

        // Add to index.
        $mform->addElement('selectyesno', 'add_to_index', get_string('add_to_index', 'plagiarism_advacheck'));
        $mform->disabledIf('add_to_index', 'advacheck_mode', 'eq', 0);
        $mform->addHelpButton('add_to_index', 'add_to_index_info', 'plagiarism_advacheck');
        $add_to_index = isset($mod_settings->add_to_index) ? $mod_settings->add_to_index : 1;
        $mform->setDefault('add_to_index', $add_to_index);

        $options_notes = [
            0 => get_string('not_display', 'plagiarism_advacheck'),
            1 => get_string('display_short', 'plagiarism_advacheck'),
            2 => get_string('display_full', 'plagiarism_advacheck'),
        ];
        // Mode for displaying test results.
        $mform->addElement('select', 'disp_notices', get_string('disp_notices', 'plagiarism_advacheck'), $options_notes);
        $mform->disabledIf('disp_notices', 'advacheck_mode', 'eq', 0);
        // Either as set in the general settings of the plugin or output as a short report.
        $disp_notices_default = isset($plugin_cfg->disp_notices_default) ? $plugin_cfg->disp_notices_default : 1;
        $disp_notices = isset($mod_settings->disp_notices) ? $mod_settings->disp_notices : $disp_notices_default;
        $mform->setDefault('disp_notices', $disp_notices);

        // In an assignment/workshop, there is a limit for checking drafts for a student.
        if ($modulename != 'mod_forum' && $modulename != 'mod_quiz') {
            $mform->addElement(
                'select',
                'check_stud_lim',
                get_string('check_stud_lim', 'plagiarism_advacheck'),
                [
                    0 => '0',
                    1 => '1',
                    2 => '2',
                    3 => '3',
                    5 => '5',
                    7 => '7',
                    10 => '10',
                ]
            );
            $mform->disabledIf('check_stud_lim', 'advacheck_mode', 'eq', 0);
            // By default, either from the general plugin settings or 0.
            $check_stud_lim_default_global = isset($plugin_cfg->check_stud_lim_default) ? $plugin_cfg->check_stud_lim_default : 0;
            $check_stud_lim_default = isset($mod_settings->check_stud_lim) ? $mod_settings->check_stud_lim : $check_stud_lim_default_global;
            $mform->setDefault('check_stud_lim', $check_stud_lim_default);
        }
        // Type of document being checked.
        $mform->addElement('select', 'works_types', get_string('works_types', 'plagiarism_advacheck'), advacheck_constants::get_advacheck_works_types());
        $mform->disabledIf('works_types', 'advacheck_mode', 'eq', 0);
        if (isset($mod_settings->works_types)) {
            $mform->setDefault('works_types', $mod_settings->works_types);
        }

        $header1 = html_writer::tag('h3', get_string('structuresectionsheadermod', 'plagiarism_advacheck'), ['class' => 'main']);
        $mform->addElement('html', $header1);

        $mform->addElement('advcheckbox', 'advacheck_docsecttitle', get_string('docsecttitle', 'plagiarism_advacheck'), '', [], [0, 1]);
        $mform->disabledIf('advacheck_docsecttitle', 'advacheck_mode', 'eq', 0);
        $docsecttitledefaultglobal = isset($plugin_cfg->docsecttitledefault) ? $plugin_cfg->docsecttitledefault : 1;
        $docsecttitledefault = isset($mod_settings->docsecttitle) ? $mod_settings->docsecttitle : $docsecttitledefaultglobal;
        $mform->setDefault('advacheck_docsecttitle', $docsecttitledefault);

        $mform->addElement('advcheckbox', 'advacheck_docsectcontent', get_string('docsectcontent', 'plagiarism_advacheck'), '', [], [0, 1]);
        $mform->disabledIf('advacheck_docsectcontent', 'advacheck_mode', 'eq', 0);
        $docsectcontentdefaultglobal = isset($plugin_cfg->docsectcontentdefault) ? $plugin_cfg->docsectcontentdefault : 1;
        $docsectcontentdefault = isset($mod_settings->docsectcontent) ? $mod_settings->docsectcontent : $docsectcontentdefaultglobal;
        $mform->setDefault('advacheck_docsectcontent', $docsectcontentdefault);

        $mform->addElement('advcheckbox', 'advacheck_docsectbibliography', get_string('docsectbibliography', 'plagiarism_advacheck'), '', [], [0, 1]);
        $mform->disabledIf('advacheck_docsectbibliography', 'advacheck_mode', 'eq', 0);
        $docsectbibliographydefaultglobal = isset($plugin_cfg->docsectbibliographydefault) ? $plugin_cfg->docsectbibliographydefault : 1;
        $docsectbibliographydefault = isset($mod_settings->docsectbibliography) ? $mod_settings->docsectbibliography : $docsectbibliographydefaultglobal;
        $mform->setDefault('advacheck_docsectbibliography', $docsectbibliographydefault);

        $mform->addElement('advcheckbox', 'advacheck_docsectappendix', get_string('docsectappendix', 'plagiarism_advacheck'), '', [], [0, 1]);
        $mform->disabledIf('advacheck_docsectappendix', 'advacheck_mode', 'eq', 0);
        $docsectappendixdefaultglobal = isset($plugin_cfg->docsectappendixdefault) ? $plugin_cfg->docsectappendixdefault : 1;
        $docsectappendix = isset($mod_settings->docsectappendix) ? $mod_settings->docsectappendix : $docsectappendixdefaultglobal;
        $mform->setDefault('advacheck_docsectappendix', $docsectappendix);

        $mform->addElement('advcheckbox', 'advacheck_docsectintroduction', get_string('docsectintroduction', 'plagiarism_advacheck'), '', [], [0, 1]);
        $mform->disabledIf('advacheck_docsectintroduction', 'advacheck_mode', 'eq', 0);
        $docsectintroductiondefaultglobal = isset($plugin_cfg->docsectintroductiondefault) ? $plugin_cfg->docsectintroductiondefault : 1;
        $docsectintroduction = isset($mod_settings->docsectintroduction) ? $mod_settings->docsectintroduction : $docsectintroductiondefaultglobal;
        $mform->setDefault('advacheck_docsectintroduction', $docsectintroduction);

        $mform->addElement('advcheckbox', 'advacheck_docsectmethod', get_string('docsectmethod', 'plagiarism_advacheck'), '', [], [0, 1]);
        $mform->disabledIf('advacheck_docsectmethod', 'advacheck_mode', 'eq', 0);
        $docsectmethoddefaultglobal = isset($plugin_cfg->docsectmethoddefault) ? $plugin_cfg->docsectmethoddefault : 1;
        $docsectmethod = isset($mod_settings->docsectmethod) ? $mod_settings->docsectmethod : $docsectmethoddefaultglobal;
        $mform->setDefault('advacheck_docsectmethod', $docsectmethod);

        $mform->addElement('advcheckbox', 'advacheck_docsectconclusion', get_string('docsectconclusion', 'plagiarism_advacheck'), '', [], [0, 1]);
        $mform->disabledIf('advacheck_docsectconclusion', 'advacheck_mode', 'eq', 0);
        $docsectconclusiondefaultglobal = isset($plugin_cfg->docsectconclusiondefault) ? $plugin_cfg->docsectconclusiondefault : 1;
        $docsectconclusion = isset($mod_settings->docsectconclusion) ? $mod_settings->docsectconclusion : $docsectconclusiondefaultglobal;
        $mform->setDefault('advacheck_docsectconclusion', $docsectconclusion);

        if ($mod_settings) {
            $mform->setDefault('advacheck_mode', $mod_settings->mode);
            if ($mod_settings->mode > 0) {
                $mform->setDefault('advacheck_checktext', $mod_settings->checktext);
                $mform->setDefault('advacheck_checkfile', $mod_settings->checkfile);
            }
        }
    }
}

/**
 * Hook to save plagiarism specific settings on a module settings page
 *
 * @param stdClass $data
 * @param stdClass $course
 */
function plagiarism_advacheck_coursemodule_edit_post_actions($data, $course)
{
    global $DB;
    $plugin_cfg = get_config('plagiarism_advacheck');

    if (empty($plugin_cfg->enabled)) {
        return $data;
    }

    $allowmodules = [];
    $instance = $data->instance;
    $modulename = $data->modulename;
    $cmid = $data->coursemodule;
    if (!empty($plugin_cfg->check_forum)) {
        $allowmodules[] = "forum";
    }
    if (!empty($plugin_cfg->check_assign)) {
        $allowmodules[] = "assign";
    }
    if (!empty($plugin_cfg->check_workshop)) {
        $allowmodules[] = "workshop";
    }
    if (!empty($plugin_cfg->check_quiz)) {
        $allowmodules[] = "quiz";
    }

    $coursecontext = context_course::instance($data->course, MUST_EXIST);
    $hasCapability = has_capability('plagiarism/advacheck:manage', $coursecontext);

    if ($hasCapability && in_array($data->modulename, $allowmodules)) {
        $row = new stdClass();
        $row->courseid = $data->course;
        $row->cmid = $data->coursemodule;
        $row->mode = 0;
        $row->checktext = 0;
        $row->checkfile = 0;

        if (isset($data->advacheck_mode)) {
            $row->mode = $data->advacheck_mode;
        }

        if (isset($data->advacheck_checktext)) {
            $row->checktext = $data->advacheck_checktext;
        }

        if (isset($data->advacheck_checkfile)) {
            $row->checkfile = $data->advacheck_checkfile;
        }

        if (isset($data->check_stud_lim)) {
            $row->check_stud_lim = $data->check_stud_lim;
        }

        if (isset($data->add_to_index)) {
            $row->add_to_index = $data->add_to_index;
        }

        if (isset($data->disp_notices)) {
            $row->disp_notices = $data->disp_notices;
        }

        if (isset($data->advacheck_docsecttitle)) {
            $row->docsecttitle = $data->advacheck_docsecttitle;
        }

        if (isset($data->advacheck_docsectcontent)) {
            $row->docsectcontent = $data->advacheck_docsectcontent;
        }

        if (isset($data->advacheck_docsectbibliography)) {
            $row->docsectbibliography = $data->advacheck_docsectbibliography;
        }

        if (isset($data->advacheck_docsectappendix)) {
            $row->docsectappendix = $data->advacheck_docsectappendix;
        }

        if (isset($data->advacheck_docsectintroduction)) {
            $row->docsectintroduction = $data->advacheck_docsectintroduction;
        }

        if (isset($data->advacheck_docsectmethod)) {
            $row->docsectmethod = $data->advacheck_docsectmethod;
        }

        if (isset($data->advacheck_docsectconclusion)) {
            $row->docsectconclusion = $data->advacheck_docsectconclusion;
        }

        if ($r = $DB->get_record('plagiarism_advacheck_course', ['courseid' => $row->courseid, 'cmid' => $row->cmid])) {
            $row->id = $r->id;
            $DB->update_record('plagiarism_advacheck_course', $row);
        } else {
            $DB->insert_record('plagiarism_advacheck_course', $row);
        }
        // Adding answers to the queue if checking was enabled after students added answers.
        if (!empty($data->advacheck_mode)) {
            $modcontext = context_module::instance($cmid);
            if ($modulename == 'forum') {
                $postscnt = $DB->count_records('forum_discussions', ['forum' => $instance]);
                $docscnt = $DB->count_records('plagiarism_advacheck_docs', ['cmid' => $cmid]);
                if ($postscnt != $docscnt) {
                    unset($posts);
                    $sql =
                        "SELECT fp.id, fp.message, fp.userid, fp.attachment, fp.discussion, fp.modified AS time
                           FROM {forum_posts} fp
                           JOIN {forum_discussions} fd ON fp.discussion = fd.id
                          WHERE fd.forum = ?";
                    $posts = $DB->get_records_sql($sql, [$instance]);
                    foreach ($posts as $p) {
                        if (!empty($data->advacheck_checktext) && !empty($p->message)) {
                            $addtodocqueue = new document_queue_manager($coursecontext, $modcontext, $p, $cmid, $data->course);
                            $addtodocqueue->add_to_queue_forum_text();
                        }
                        if (!empty($data->advacheck_checkfile) && !empty($p->attachment)) {
                            $fs = get_file_storage();
                            $files = $fs->get_area_files($modcontext->id, 'mod_forum', 'attachment', $p->id);
                            $addtodocqueue = new document_queue_manager($coursecontext, $modcontext, $p, $cmid, $data->course);
                            $addtodocqueue->add_to_queue_file($files, $modulename);
                        }
                    }
                }
            } else if ($modulename == "assign") {
                unset($subs);
                $sql =
                    "SELECT sub.id, sub_text.onlinetext AS txt, sub.userid, sub.assignment AS assign, sub.status, sub_file.numfiles, sub.timemodified AS time                
                       FROM {assign_submission} sub
                  LEFT JOIN {assignsubmission_onlinetext} sub_text ON sub.id = sub_text.submission
                  LEFT JOIN {assignsubmission_file} sub_file ON sub.id = sub_file.submission
                      WHERE sub.assignment = ?";
                $subs = $DB->get_records_sql($sql, [$instance]);
                foreach ($subs as $s) {
                    if (!empty($data->advacheck_checktext) && !empty($s->txt)) {
                        $addtodocqueue = new document_queue_manager($coursecontext, $modcontext, $s, $cmid, $data->course);
                        $addtodocqueue->add_to_queue_assign_text();
                    }
                    if (!empty($data->advacheck_checkfile) && !empty($s->numfiles)) {
                        $fs = get_file_storage();
                        $files = $fs->get_area_files($modcontext->id, 'assignsubmission_file', ASSIGNSUBMISSION_FILE_FILEAREA, $s->id);
                        $addtodocqueue = new document_queue_manager($coursecontext, $modcontext, $s, $cmid, $data->course);
                        $addtodocqueue->add_to_queue_file($files, $modulename);
                    }
                }
            } else if ($modulename == 'workshop') {
                $sql =
                    "SELECT sub.id,  sub.content AS txt, sub.authorid AS userid,  sub.workshopid, sub.published, sub.timemodified AS time
                       FROM {workshop_submissions} sub
                      WHERE sub.workshopid = ?";
                $subs = $DB->get_records_sql($sql, [$instance]);
                foreach ($subs as $s) {
                    if (!empty($data->advacheck_checktext) && !empty($s->txt)) {
                        $addtodocqueue = new document_queue_manager($coursecontext, $modcontext, $s, $cmid, $data->course);
                        $addtodocqueue->add_to_queue_workshop_text();
                    }
                    if (!empty($data->advacheck_checkfile)) {
                        $fs = get_file_storage();
                        $files = $fs->get_area_files($modcontext->id, 'mod_workshop', 'submission_attachment', $s->id);
                        if (count($files) != 0) {
                            $addtodocqueue = new document_queue_manager($coursecontext, $modcontext, $s, $cmid, $data->course);
                            $addtodocqueue->add_to_queue_file($files, $modulename);
                        }
                    }
                }
            } else if ($modulename == 'quiz') {
                if (!empty($data->advacheck_checktext)) {
                    $sql =
                        "SELECT DISTINCT qas.id, qa.responsesummary as txt, qa.timemodified as time, qas.userid
                           FROM {question_attempts} qa 
                           JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                           JOIN {question_attempt_step_data} asd ON qas.id = asd.attemptstepid AND asd.name = 'answer'
                           JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                           JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                          WHERE quiza.quiz = ?";
                    $attempts_t = $DB->get_records_sql($sql, [$instance]);
                    foreach ($attempts_t as $at) {
                        if (empty($at->txt)) {
                            continue;
                        }
                        $addtodocqueue = new document_queue_manager($coursecontext, $modcontext, $at, $cmid, $data->course);
                        $addtodocqueue->add_to_queue_quiz_text();
                    }
                }
                if (!empty($data->advacheck_checkfile)) {
                    $sql =
                        "SELECT DISTINCT qas.id, qa.responsesummary as txt, qa.timemodified as time, qas.userid
                           FROM {question_attempts} qa 
                           JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                           JOIN {question_attempt_step_data} asd ON qas.id = asd.attemptstepid AND asd.name = 'attachments'
                           JOIN {question} q ON qa.questionid = q.id AND q.qtype = 'essay'
                           JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                          WHERE quiza.quiz = ?";
                    $attempts_f = $DB->get_records_sql($sql, [$instance]);
                    foreach ($attempts_f as $af) {
                        $attempt_files = get_file_storage()->get_area_files($modcontext->id, 'question', 'response_attachments', $af->id);
                        if (count($attempt_files) != 0) {
                            $addtodocqueue = new document_queue_manager($coursecontext, $modcontext, $af, $cmid, $data->course);
                            $addtodocqueue->add_to_queue_file($attempt_files, $modulename);
                        }
                    }
                }
            }
        }
    }

    return $data;
}

/**
 * Turn on/off the module for different versions of moodle
 *
 * @global stdClass $CFG
 * @param bool $set_get true- get; false - write down.
 * @param int $val on/off value
 * @return mixed int | bool
 */
function plagiarism_advacheck_enable_plug($set_get, $val = 0)
{
    global $CFG;
    if ($set_get) {
        if ($CFG->version < 2020061500) {
            return get_config('plagiarism', 'advacheck_use');
        }
        return get_config('plagiarism_advacheck', 'enabled');
    } else {
        if ($CFG->version < 2020061500) {
            set_config('advacheck_use', $val, 'plagiarism');
        }
        set_config('enabled', $val, 'plagiarism_advacheck');
    }

}

/**
 * Adds a link to the navigation block.
 *
 * @param object $navigation
 * @param stdClass $course
 * @param context $context
 */
function plagiarism_advacheck_extend_navigation_course($navigation, $course, $context)
{
    $antiplugiat_enable = plagiarism_advacheck_enable_plug(true);
    if (has_capability('plagiarism/advacheck:manage', $context) && $antiplugiat_enable) {
        $url = new moodle_url('/plagiarism/advacheck/coursesettings/', ['courseid' => $course->id]);
        $navigation->add(
            get_string('coursesettings', 'plagiarism_advacheck'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/report', '')
        );
    }
}
