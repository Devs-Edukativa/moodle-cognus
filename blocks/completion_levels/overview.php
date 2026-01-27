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
 * Completion Levels block overview page
 *
 * @package    block_completion_levels
 * @copyright  2016 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
global $CFG, $DB, $USER, $OUTPUT, $PAGE;
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('instanceid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);

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
$page     = optional_param('page', 0, PARAM_INT); // Which page to show.
$perpage  = optional_param('perpage', 20, PARAM_INT); // How many per page.
$group    = optional_param('group', isset($config->group) ? $config->group : 0, PARAM_INT); // Group selected.
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
$baseurl = new moodle_url('/blocks/completion_levels/overview.php', [ 'instanceid' => $id, 'courseid' => $courseid ]);
$fullurl = clone($baseurl);
$fullurl->params([ 'group' => $group, 'role' => $roleselected ]);

$PAGE->set_url($baseurl);
$blockname = $blockinstance->get_title();
$title = $blockname . ' - ' . get_string('overviewofstudents', 'block_completion_levels');
$PAGE->set_title($title);
$PAGE->set_heading(get_string('pluginname', 'block_completion_levels'));
$PAGE->navbar->add($blockname);
$PAGE->navbar->add(get_string('overview', 'block_completion_levels'), $baseurl);
$PAGE->set_pagelayout('report');

// Check user is logged in and capable of accessing the Overview.
require_login($course, false);
require_capability('block/completion_levels:overview', $blockcontext);

// Start page output.
echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2);
echo $OUTPUT->container_start('block_completion_levels');

block_completion_levels_print_view_tabs('overview', $id, $courseid, $group, $roleselected);

$users = block_completion_levels_get_users($courseid, $config, $group, $roleselected, $context->id, true);

$usersprogress = block_completion_levels_get_progress($config, array_keys($users), $courseid);

if (empty($usersprogress)) {
    echo get_string('noactivitiestracked', 'block_completion_levels');
    echo $OUTPUT->container_end();
    echo $OUTPUT->footer();
    die();
}

// Output group selector if there are groups in the course.
echo $OUTPUT->container_start('mb-3');
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
$table = new flexible_table('block_completion_levels-overview');
$table->define_columns([ 'fullname', 'lastonline', 'badge', 'progress' ]);
$table->define_headers([
        get_string('fullname'),
        get_string('lastcourseaccess'),
        get_string('badge', 'block_completion_levels'),
        get_string('progress', 'block_completion_levels'),
]);
$table->sortable(true);
$table->set_attribute('class', 'block_completion_levels-overview');

$table->no_sorting('badge');
$table->define_baseurl($fullurl);

$table->initialbars(true);

$table->setup();

// Style columns after setup, because from Moodle 3.9 onwards, width is overriden (for an unknown reason).
$table->column_style_all('vertical-align', 'middle');
$table->column_style('fullname', 'width', '18%');
$table->column_style('lastonline', 'width', '15%');
$table->column_style('badge', 'width', '10%');
$table->column_style('badge', 'text-align', 'center');
$table->column_style('progress', 'text-align', 'center');
$table->column_style('progress', 'width', '40%');
$table->column_style('progress', 'padding-right', '3em');

// Build array of user information.
$rows = [];
foreach ($users as $user) {

    if (($ifirst = $table->get_initial_first()) !== null) {
        if (!preg_match("/^$ifirst/i", $user->firstname)) {
            continue;
        }
    }
    if (($ilast = $table->get_initial_last()) !== null) {
        if (!preg_match("/^$ilast/i", $user->lastname)) {
            continue;
        }
    }

    $picture = $OUTPUT->user_picture($user, [ 'course' => $course->id ]);
    $namelink = html_writer::link($CFG->wwwroot.'/user/view.php?id='.$user->id.'&course='.$course->id, fullname($user));
    if (empty($user->lastonlinetime)) {
        $lastonline = get_string('never');
    } else {
        $timeelapsed = time() - $user->lastonlinetime;
        if ($timeelapsed > 0) {
            $formattedtime = format_time($timeelapsed);
        } else {
            $formattedtime = '0 ' . get_string('sec');
        }
        $lastonline = '<div title="' . userdate($user->lastonlinetime) . '">' .
                          get_string('ago', 'message', $formattedtime) .
                      '</div>';
    }

    $progress = $usersprogress[$user->id];

    $rows[] = [
            // Displayed data.
            'fullname' => $picture . $namelink,
            'lastonlinedisplay' => $lastonline,
            'badge' => $OUTPUT->render(new block_completion_levels\widget\badge($config, $blockcontext, $progress)),
            'progressbar' => $OUTPUT->render($progress->get_progress_bar()),
            // Data used to sort the table.
            'userid' => $user->id,
            'firstname' => strtoupper($user->firstname),
            'lastname' => strtoupper($user->lastname),
            'lastonline' => $user->lastonlinetime,
            'progress' => $progress->relativevalue,
    ];
}

$sortcolumns = $table->get_sort_columns() ?: [ 'userid' => SORT_DESC ];
usort($rows, function($a, $b) use ($sortcolumns) {
    foreach ($sortcolumns as $column => $direction) {
        if ($a[$column] > $b[$column]) {
            return $direction == SORT_DESC ? 1 : -1;
        } else if ($a[$column] < $b[$column]) {
            return $direction == SORT_DESC ? -1 : 1;
        }
    }
    return 0;
});

$numberofusers = count($users);
$paged = $numberofusers > $perpage;
if ($paged) {
    $table->pagesize($perpage, $numberofusers);
} else {
    $page = 0;
}

// Build the table content and output.
foreach (array_slice($rows, $page * $perpage, $perpage) as $row) {
    $table->add_data([ $row['fullname'], $row['lastonlinedisplay'], $row['badge'], $row['progressbar'] ]);
}
$table->finish_output();

// Output paging controls.
$perpageurl = clone($fullurl);
if ($paged) {
    $perpageurl->param('perpage', 5000);
    echo $OUTPUT->container(html_writer::link($perpageurl, get_string('showall', '', $numberofusers)));
} else if ($numberofusers > 20) {
    $perpageurl->param('perpage', 20);
    echo $OUTPUT->container(html_writer::link($perpageurl, get_string('showperpage', '', 20)));
}

echo $OUTPUT->container_end();
echo $OUTPUT->footer();
