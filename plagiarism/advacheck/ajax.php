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
 * Processes ajax requests
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('AJAX_SCRIPT');
use \plagiarism_advacheck\local\upload_start_check_manual;
use \plagiarism_advacheck\local\advacheck_constants;

require_once ('../../config.php');
require_once 'classes/local/constants.php';
// Setting the internal encoding to which the HTTP request input data will be converted.
mb_internal_encoding("UTF-8");

$action = optional_param('action', '', PARAM_TEXT);
$type = optional_param('doctype', '', PARAM_TEXT);
$typeid = optional_param('typeid', 0, PARAM_TEXT);

$courseid = optional_param('courseid', 1, PARAM_INT);
$content = optional_param('content', '', PARAM_TEXT);
$doctype = optional_param('doctype', '', PARAM_TEXT);

$assignment = optional_param("assignment", 0, PARAM_INT);
$discussion = optional_param("discussion", 0, PARAM_INT);
$userid = optional_param("userid", 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid]);
$coursecontext = context_course::instance($courseid, MUST_EXIST);

require_login($course, false);

if (!confirm_sesskey()) {
    throw new \moodle_exception('confirmsesskeybad');
}

// Checking the files.
if (($action == "checkfile") && !empty($typeid) && (has_capability('plagiarism/advacheck:checkedby', $coursecontext) || has_capability('plagiarism/advacheck:checkadvacheck', $coursecontext))) {
    $docverfyobj = new upload_start_check_manual(
        $typeid,
        $courseid,
        $doctype,
        $content,
        $userid,
        $assignment,
        $discussion
    );
    $result = $docverfyobj->start_file_verify();
    echo json_encode($result);
    exit;
}
// Checking the text.
if (($action == "checktext") && !empty($typeid) && !empty($content) && !empty($typeid) && (has_capability('plagiarism/advacheck:checkedby', $coursecontext) || has_capability('plagiarism/advacheck:checkadvacheck', $coursecontext))) {

    $docverfyobj = new upload_start_check_manual(
        $typeid,
        $courseid,
        $doctype,
        $content,
        $userid,
        $assignment,
        $discussion
    );
    $result = $docverfyobj->start_text_verify();
    echo json_encode($result);
    exit;
}

// Changing the verification mode.
if ($action == "changeMode" && has_capability('plagiarism/advacheck:manage', $coursecontext)) {
    $cm = optional_param('cm', 0, PARAM_INT);
    $mode = optional_param('mode', advacheck_constants::PLAGIARISM_ADVACHECK_DISABLEDMODE, PARAM_INT);
    $course = $DB->get_field('course_modules', 'course', ['id' => $cm]);
    if ($cm && $course) {
        if ($DB->record_exists('plagiarism_advacheck_course', ['courseid' => $course, 'cmid' => $cm])) {
            $DB->set_field('plagiarism_advacheck_course', 'mode', $mode, ['courseid' => $course, 'cmid' => $cm]);
        } else {
            $row = new stdClass();
            $row->courseid = $course;
            $row->cmid = $cm;
            $row->mode = $mode;
            $DB->insert_record('plagiarism_advacheck_course', $row);
        }
    }
    exit;
}
// Changing other module settings.
if ($action == "changetype" && has_capability('plagiarism/advacheck:manage', $coursecontext)) {
    $type = optional_param('doctype', '', PARAM_TEXT);
    if (in_array($type, array_keys($DB->get_columns('plagiarism_advacheck_course')))) {
        $cm = optional_param('cm', 0, PARAM_INT);
        $value = optional_param('value', 0, PARAM_TEXT);
        $course = $DB->get_field('course_modules', 'course', ['id' => $cm]);
        if ($cm && $course && $type) {
            if ($DB->record_exists('plagiarism_advacheck_course', ['courseid' => $course, 'cmid' => $cm])) {
                $DB->set_field('plagiarism_advacheck_course', $type, $value, ['courseid' => $course, 'cmid' => $cm]);
            } else {
                $row = new stdClass();
                $row->courseid = $course;
                $row->cmid = $cm;
                $row->{$type} = $value;
                $DB->insert_record('plagiarism_advacheck_course', $row);
            }
        }
    } else {

    }

    exit;
}
// Checking the tariff.
if ($action == 'checktarif' && has_capability('plagiarism/advacheck:manage', $coursecontext)) {
    $login = optional_param('login', '', PARAM_TEXT);
    $password = optional_param('password', '', PARAM_TEXT);
    $soap_wsdl = optional_param('soap_wsdl', '', PARAM_TEXT);
    $url = optional_param('uri', '', PARAM_TEXT);
    if ($login == '' || $password == '' || $soap_wsdl == '' || $url == '') {
        $data = [
            'info' => get_string('error_login', 'plagiarism_advacheck'),
        ];
        echo json_encode($OUTPUT->render_from_template('plagiarism_advacheck/tarifinfoerror', $data));
        exit;
    } else {
        // Structure for sending a response to a page.
        $tariff_info_html = new stdClass();
        $tariff_info = plagiarism_advacheck\local\advacheck_api::check_tarif($login, $password, $soap_wsdl);

        if (!empty($tariff_info->error)) {
            $a = $tariff_info->error;
            $data = [
                'info' => get_string('error_check', 'plagiarism_advacheck', $a),
            ];
            echo json_encode($OUTPUT->render_from_template('plagiarism_advacheck/tarifinfoerror', $data));
            exit;
        }

        if ($tariff_info->tarif->TotalChecksCount == '') {
            $tariff_info->tarif->TotalChecksCount = get_string('totalcheckscount_unlimited', 'plagiarism_advacheck');
        }

        if ($tariff_info->tarif->RemainedChecksCount == '') {
            $tariff_info->tarif->RemainedChecksCount = get_string('remainedcheckscount_unlimited', 'plagiarism_advacheck');
        }

        $data = [
            'tarif' => $tariff_info->tarif,
            'url' => $url,
            'serviceslist' => $tariff_info->tarif->CheckServices->CheckServiceInfo,
        ];

        echo json_encode($OUTPUT->render_from_template('plagiarism_advacheck/tarifinfo', $data));
    }
    exit;
}
// Updated the link to the report.
if ($action == 'update_report' && has_capability('plagiarism/advacheck:updatereport', $coursecontext)) {
    $typeid = optional_param('typeid', '', PARAM_TEXT);
    $link = upload_start_check_manual::update_advacheck_report($typeid);
    echo json_encode($link);
    exit;
}

