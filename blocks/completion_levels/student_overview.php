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
 * Completion Levels block student overview function
 *
 * @package    block_completion_levels
 * @copyright  2018 Florent Paccalet, 2021 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
global $CFG;
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('instanceid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);

global $DB, $OUTPUT, $PAGE;

$course = get_course($courseid);
$context = context_course::instance($courseid);
$PAGE->set_course($course);

// Get specific block config and context.
$blockrecord = $DB->get_record('block_instances', [ 'id' => $id ]);
block_completion_levels_check_instance($blockrecord, $context, $course->fullname);
$blockinstance = block_instance('completion_levels', $blockrecord);

// Set up page parameters.
$PAGE->set_url('/blocks/completion_levels/student_overview.php', [ 'instanceid' => $id, 'courseid' => $courseid ]);
$title = $blockinstance->get_title() . ' - ' . get_string('activitiescompletion', 'block_completion_levels');
$PAGE->set_title($title);
$PAGE->set_heading(get_string('pluginname', 'block_completion_levels'));
$PAGE->navbar->add($title);
$PAGE->set_pagelayout('report');

// Start page output.
echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2);
echo $OUTPUT->container_start('block_completion_levels');

echo $OUTPUT->render(new \block_completion_levels\widget\studentoverviewtable($blockinstance, $courseid));

echo $OUTPUT->container_end();
echo $OUTPUT->footer();
