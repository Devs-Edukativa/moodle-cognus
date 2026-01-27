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
 * Check settings page for the course for all modules.
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once ('../../../config.php');
require_once ($CFG->dirroot . '/plagiarism/advacheck/lib.php');

use plagiarism_advacheck\local\advacheck_constants;

$id = optional_param('courseid', 0, PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);

if (!has_capability('plagiarism/advacheck:manage', $context)) {
    redirect(new moodle_url("/course/view.php", ['id' => $course->id]));
}

$antiplugiat_enable = plagiarism_advacheck_enable_plug(true);
if (!$antiplugiat_enable) {
    notice(get_string('notice_plug_disable', 'plagiarism_advacheck'), new moodle_url("/course/view.php", ['id' => $course->id]));
}

$PAGE->set_url('/plagiarism/advacheck/coursesettings/index.php', ['courseid' => $id]);
$PAGE->set_pagelayout('course');

$modinfo = get_fast_modinfo($course);
$mods = $modinfo->get_cms();

$sql =
    "SELECT cmid, ac.* 
       FROM {plagiarism_advacheck_course} ac 
      WHERE courseid = ?";
$course_settings = $DB->get_records_sql($sql, [$course->id]);

$table = new html_table();
$table->head = [
    get_string('modname', 'plagiarism_advacheck'),
    get_string('automode', 'plagiarism_advacheck'),
    get_string('manualmode', 'plagiarism_advacheck'),
    get_string('disabledmode', 'plagiarism_advacheck'),
    get_string('checktext', 'plagiarism_advacheck'),
    get_string('checkfile', 'plagiarism_advacheck'),
    get_string('add_to_index_cln', 'plagiarism_advacheck'),
    get_string('disp_notices_cln', 'plagiarism_advacheck'),
    get_string('stud_limit_cln', 'plagiarism_advacheck'),
    get_string('works_types', 'plagiarism_advacheck'),
];
$table->data = [];

$allowmodules = [];

$plugin_cfg = get_config('plagiarism_advacheck');
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
$options_notes = [
    0 => get_string('not_display', 'plagiarism_advacheck'),
    1 => get_string('display_short', 'plagiarism_advacheck'),
    2 => get_string('display_full', 'plagiarism_advacheck'),
];
$options_limits = [0 => '0', 1 => '1', 2 => '2', 3 => '3', 5 => '5', 7 => '7', 10 => '10'];

foreach ($mods as $mod) {

    if (!in_array($mod->modname, $allowmodules)) {
        continue;
    }

    $modname = html_writer::link($mod->url, html_writer::img($mod->get_icon_url(), '') . " " . $mod->name);

    $mode = 0;
    $checktext = 0;
    $checkfile = 0;
    $add_to_index = 0;
    $disp_notices = 0;
    $limit_check = 0;
    $works_types = 'none';

    $settings = isset($course_settings[$mod->id]) ? $course_settings[$mod->id] : null;
    if (!empty($settings)) {
        $mode = (int) $settings->mode;
        $checktext = $settings->checktext;
        $checkfile = $settings->checkfile;
        $add_to_index = $settings->add_to_index;
        $disp_notices = $settings->disp_notices;
        $limit_check = $settings->check_stud_lim;
        $works_types = $settings->works_types;
    }

    $automodeval = '';
    $manualmodeval = '';
    $disabledmodeval = '';
    switch ($mode) {
        case advacheck_constants::PLAGIARISM_ADVACHECK_DISABLEDMODE:
            $disabledmodeval = 'checked';
            break;
        case advacheck_constants::PLAGIARISM_ADVACHECK_MANUALMODE:
            $manualmodeval = 'checked';
            break;
        case advacheck_constants::PLAGIARISM_ADVACHECK_AUTOMODE:
            $automodeval = 'checked';
            break;
        default:
            $disabledmodeval = 'checked';
            break;
    }

    $automode = html_writer::empty_tag('input
                                             ',
        [
            'type' => 'radio',
            'class' => 'changemode',
            'name' => $mod->id,
            'value' => advacheck_constants::PLAGIARISM_ADVACHECK_AUTOMODE,
            $automodeval => $automodeval
        ]
    );
    $manualmode = html_writer::empty_tag(
        'input',
        [
            'type' => 'radio',
            'class' => 'changemode',
            'name' => $mod->id,
            'value' => advacheck_constants::PLAGIARISM_ADVACHECK_MANUALMODE,
            $manualmodeval => $manualmodeval
        ]
    );
    $disabledmode = html_writer::empty_tag(
        'input',
        [
            'type' => 'radio',
            'class' => 'changemode',
            'name' => $mod->id,
            'value' => advacheck_constants::PLAGIARISM_ADVACHECK_DISABLEDMODE,
            $disabledmodeval => $disabledmodeval
        ]
    );

    $checktextBox = html_writer::checkbox('checktext', $mod->id, $checktext, '', ['class' => 'changetype']);
    $checkfileBox = html_writer::checkbox('checkfile', $mod->id, $checkfile, '', ['class' => 'changetype']);
    $add_to_indexBox = html_writer::checkbox('add_to_index', $mod->id, $add_to_index, '', ['class' => 'changetype']);
    $disp_noticesBox = html_writer::select(
        $options_notes,
        'disp_notices',
        $disp_notices,
        false,
        ['class' => "changetype $mod->id"]
    );
    if ($mod->modname == 'forum' || $mod->modname == 'quiz') {
        $limit_checkBox = '-';
    } else {
        $limit_checkBox = html_writer::select(
            $options_limits,
            'check_stud_lim',
            $limit_check,
            false,
            ['class' => "changetype $mod->id"]
        );
    }

    $works_typesBox = html_writer::select(
        advacheck_constants::get_advacheck_works_types(),
        'works_types',
        $works_types,
        false,
        ['class' => "changetype $mod->id"]
    );

    $table->data[] = [
        $modname,
        $automode,
        $manualmode,
        $disabledmode,
        $checktextBox,
        $checkfileBox,
        $add_to_indexBox,
        $disp_noticesBox,
        $limit_checkBox,
        $works_typesBox
    ];
}


echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursesettings', 'plagiarism_advacheck'));

if (empty($allowmodules)) {
    $html = html_writer::div(get_string('notice_cm_not_allowed', 'plagiarism_advacheck'), "alert alert-warning");
    notice($html, new moodle_url('/plagiarism/advacheck/settings.php'));
}
echo html_writer::table($table);

$PAGE->requires->js_call_amd('plagiarism_advacheck/check', 'changeMode   ');
echo $OUTPUT->footer();
