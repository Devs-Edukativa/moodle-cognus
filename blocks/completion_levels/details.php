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
 * Completion Levels block details page
 *
 * @package    block_completion_levels
 * @copyright  2018 Florent Paccalet
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
global $CFG, $DB, $USER, $OUTPUT, $PAGE;
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('instanceid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$downloadformat = optional_param('downloadformat', '', PARAM_RAW);
$isdownloading = $downloadformat > '';

// Determine course and context.
$course = get_course($courseid);
$context = context_course::instance($courseid);
$PAGE->set_course($course);

// Get specific block config and context.
$blockrecord = $DB->get_record('block_instances', [ 'id' => $id ]);
block_completion_levels_check_instance($blockrecord, $context, $course->fullname);
$blockinstance = block_instance('completion_levels', $blockrecord);
$config = $blockinstance->config;
$blockcontext = context_block::instance($id);

// Filters.
$group = optional_param('group', isset($config->group) ? $config->group : 0, PARAM_INT); // Group selected.
$roleselected = optional_param('role', -1, PARAM_INT);
if ($roleselected == -1) {
    $roleselected = block_completion_levels_get_student_role_id($context->id);
}

// Clean group param.
$groupuserid = $USER->id;
if (has_capability('moodle/site:accessallgroups', $context)) {
    $groupuserid = 0;
}
$accessiblegroups = array_keys(groups_get_all_groups($courseid, $groupuserid, 0, 'g.id'));
if (!in_array($group, $accessiblegroups)) {
    $group = 0;
}

// Set up page parameters.
$PAGE->set_course($course);
$baseurl = new moodle_url('/blocks/completion_levels/details.php', [ 'instanceid' => $id, 'courseid' => $courseid ]);
$fullurl = clone($baseurl);
$fullurl->params([ 'group' => $group, 'role' => $roleselected ]);

$PAGE->set_url($baseurl);
$blockname = $blockinstance->get_title();
$title = $blockname . ' - ' . get_string('details', 'block_completion_levels');
$PAGE->set_title($title);
$PAGE->set_heading(get_string('pluginname', 'block_completion_levels'));
$PAGE->navbar->add($blockname);
$PAGE->navbar->add(get_string('details', 'block_completion_levels'), $baseurl);
$PAGE->set_pagelayout('report');

// Check user is logged in and capable of accessing the Overview.
require_login($course, false);
require_capability('block/completion_levels:overview', $blockcontext);

// Set headers by activities available for the course.
$activities = block_completion_levels_get_tracked_activities($courseid, $config);

$tablecolumns = [ 'lastname' ];
$tableheaders = $isdownloading ? [ 'userid', get_string('name'), get_string('email') ] : [ get_string('name') ];

$modinfo = get_fast_modinfo($courseid, -1);
$i = 0;
foreach ($activities as $activity) {
    $cm = $modinfo->get_cm($activity->id);
    $activity->name = $cm->get_formatted_name() . ' (' . $cm->get_module_type_name() . ')';

    $tablecolumns[] = 'activity_' . ($i++);

    $header = $activity->name;
    if (!$isdownloading) {
        $text = html_writer::div($header, 'text-truncate');
        $header = html_writer::div($text, 'activity-name-table-header', [ 'title' => $activity->name ]);
        $header .= html_writer::div($activity->weight . '&nbsp;<i class="fa fa-balance-scale"></i>', 'weight-indicator',
                [ 'title' => get_string('weighta', 'block_completion_levels', $activity->weight) ]);
    }
    $tableheaders[] = $header;
}

$tablecolumns[] = 'progress';
$tableheaders[] = get_string('progress', 'block_completion_levels');

$users = block_completion_levels_get_users($courseid, $config, $group, $roleselected, $context->id);

$usersprogress = block_completion_levels_get_progress($config, array_keys($users), $courseid);

$rows = [];
foreach ($usersprogress as $userid => $progress) {
    $user = $users[$userid];
    $row = new stdClass();
    $row->display = $isdownloading ? [ $user->id, fullname($user), $user->email ] : [ fullname($user) ];

    foreach ($activities as $activity) {
        $completion = $progress->completion_info($activity->id);

        if ($isdownloading) {
            $scoredisplay = block_completion_levels_format_user_activity_completion($completion, $config);
        } else {
            $scoredisplay = $OUTPUT->render(new \block_completion_levels\widget\completionicon($completion, $activity->name));
        }

        $row->display[] = $scoredisplay;
    }
    $row->display[] = $progress->display();
    $row->data = [
            'userid' => $user->id,
            'lastname' => strtoupper($user->lastname),
            'progress' => $progress->relativevalue,
    ];
    $rows[] = $row;
}

if ($isdownloading) {
    // Add a row for total progress and activity weights.
    $row = new stdClass();
    $row->display = [ '', '', 'weights' ];
    foreach ($activities as $activity) {
        $row->display[] = $activity->weight;
    }
    $row->display[] = '';
    $rows[] = $row;
}

if ($isdownloading) {
    // This is a request to download the table.
    confirm_sesskey();

    $file = $CFG->dirroot . '/dataformat/' . $downloadformat . '/classes/writer.php';
    if (is_readable($file)) {
        include_once($file);
    }
    $writerclass = 'dataformat_' . $downloadformat. '\writer';
    if (!class_exists($writerclass)) {
        throw new moodle_exception('invalidparameter', 'debug');
    }

    $writer = new $writerclass();

    $time = userdate(time(), '%Y-%m-%d-%H%M%S');
    $writer->set_filename(clean_filename('block_completion_levels_export_' . $course->shortname . '_' . $time));
    $writer->send_http_headers();
    $writer->set_sheettitle($course->shortname);
    $writer->start_output();

    $writer->start_sheet($tableheaders);

    foreach ($rows as $rownum => $row) {
        $writer->write_record($row->display, $rownum + 1);
    }

    $writer->close_sheet($tableheaders);

    $writer->close_output();
    exit();
}

// Start page output.
echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2);
echo $OUTPUT->container_start('block_completion_levels');

block_completion_levels_print_view_tabs('details', $id, $courseid, $group, $roleselected);

if (empty($usersprogress)) {
    echo get_string('noactivitiestracked', 'block_completion_levels');
    echo $OUTPUT->container_end();
    echo $OUTPUT->footer();
    die();
}

// Output group selector if there are groups in the course.
echo $OUTPUT->container_start();
if (!empty($accessiblegroups)) {
    $groupmenuurl = clone($baseurl);
    $groupmenuurl->param('role', $roleselected);
    $groupmenu = groups_allgroups_course_menu($course, $groupmenuurl, true, $group);
    if ($groupmenu > '') {
        echo '<div class="d-inline-block mr-4">' . $groupmenu . '</div>';
    }
}

// Output the roles menu.
$sql = "SELECT DISTINCT r.id, r.name, r.shortname
          FROM {role} r, {role_assignments} a
         WHERE a.contextid = :contextid
           AND r.id = a.roleid";
$params = [ 'contextid' => $context->id ];
$roles = role_fix_names($DB->get_records_sql($sql, $params), $context);
$rolestodisplay = [ 0 => get_string('showallroles', 'role') ];
foreach ($roles as $role) {
    $rolestodisplay[$role->id] = $role->localname;
}
$rolemenuurl = clone($baseurl);
$rolemenuurl->param('group', $group);
echo $OUTPUT->single_select($rolemenuurl, 'role', $rolestodisplay, $roleselected, null, 'selectrole',
        [ 'label' => get_string('role') ]);
echo $OUTPUT->container_end();

// Setup submissions table.
$table = new flexible_table('block_completion_levels-details');
$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->sortable(true);
$table->set_attribute('class', 'block-completion_levels-details mt-2');
$table->column_style_all('padding', '5px'); // Make this table compact, as it holds lots of information.

for ($i = 0; $i < count($activities); $i++) {
    $table->no_sorting('activity_'.$i);
}

$table->define_baseurl($fullurl);

$table->setup();

$sortcolumns = $table->get_sort_columns() ?: [ 'userid' => SORT_DESC ];
usort($rows, function($a, $b) use ($sortcolumns) {
    foreach ($sortcolumns as $column => $direction) {
        if ($a->data[$column] > $b->data[$column]) {
            return $direction == SORT_DESC ? 1 : -1;
        } else if ($a->data[$column] < $b->data[$column]) {
            return $direction == SORT_DESC ? -1 : 1;
        }
    }
    return 0;
});

foreach ($rows as $row) {
    $table->add_data($row->display);
}
$table->finish_output();

echo $OUTPUT->download_dataformat_selector(
        get_string('downloadas', 'table'),
        $PAGE->url->out_omit_querystring(),
        'downloadformat',
        $fullurl->params()
);

echo $OUTPUT->container_end();
echo $OUTPUT->footer();
