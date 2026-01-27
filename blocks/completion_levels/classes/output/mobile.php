<?php
// This file is part of Moodle - https://moodle.org/
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

namespace block_completion_levels\output;

use context_course;

/**
 * Callbacks class for mobile app.
 *
 * @package    block_completion_levels
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Callback to render the block contents on mobile app.
     * @param array $args Data provided by standard CoreBlockDelegate.
     */
    public static function get_block_content($args) {
        global $PAGE;

        // For some reason, global $COURSE is not properly set. We need to search the course we are in ourselves.
        $courseid = $args['contextlevel'] === 'course' ? $args['instanceid'] : 1;

        if ($courseid == 1) {
            // For Dashboard, block is not configurable, so block instance does not matter.
            $renderableblock = new \block_completion_levels\widget\dashboard();
        } else {
            $blockinstance = block_instance_by_id($args['blockid']);
            $renderableblock = new \block_completion_levels\widget\course($blockinstance, $courseid);
        }

        $renderer = $PAGE->get_renderer('block_completion_levels', 'mobile');

        return [ 'templates' => [ [ 'id' => 'main', 'html' => $renderer->render($renderableblock) ] ] ];
    }

    /**
     * Callback to render student overview table on mobile app.
     * @param array $args Data provided by core-site-plugins-new-content ion-button. Should contain courseid and instanceid fields.
     */
    public static function student_overview($args) {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/blocks/completion_levels/locallib.php');

        $course = get_course($args['courseid']);
        $context = context_course::instance($course->id);

        $blockrecord = $DB->get_record('block_instances', [ 'id' => $args['instanceid'] ]);
        block_completion_levels_check_instance($blockrecord, $context, $course->fullname);
        $blockinstance = block_instance('completion_levels', $blockrecord);

        $renderer = $PAGE->get_renderer('block_completion_levels', 'mobile');
        $table = new \block_completion_levels\widget\studentoverviewtable($blockinstance, $course->id);

        return [ 'templates' => [ [ 'id' => 'main', 'html' => $renderer->render($table) ] ] ];
    }
}
