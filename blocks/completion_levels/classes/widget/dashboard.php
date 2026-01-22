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

namespace block_completion_levels\widget;

use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable widget for the block contents on the dashboard.
 *
 * @package    block_completion_levels
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements renderable, templatable {

    /**
     * {@inheritDoc}
     * @param renderer_base $output
     * @see templatable::export_for_template()
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER;

        // Show a message when the user is not enrolled in any courses.
        $mycourses = enrol_get_my_courses();
        if (empty($mycourses)) {
            return [ 'rawcontent' => get_string('notenrolled', 'grades') ];
        }

        $sql = "SELECT bi.*,
                       c.id AS courseid,
                       COALESCE(bp.region, bi.defaultregion) AS region,
                       COALESCE(bp.weight, bi.defaultweight) AS weight
                  FROM {block_instances} bi
                  JOIN {context} ctx ON ctx.id = bi.parentcontextid
                  JOIN {course} c ON c.id = ctx.instanceid
             LEFT JOIN {block_positions} bp ON bp.blockinstanceid = bi.id
                 WHERE bi.blockname = :blockname
                   AND ctx.contextlevel = :contextcourse
                   AND COALESCE(bp.visible, 1) = 1
              ORDER BY c.sortorder ASC, region DESC, weight ASC, bi.id";

        $params = [ 'blockname' => 'completion_levels', 'contextcourse' => CONTEXT_COURSE ];

        $rawrecords = $DB->get_records_sql($sql, $params);

        $courseblockrecords = [];
        foreach ($rawrecords as $record) {
            if (!isset($mycourses[$record->courseid])) {
                // This is not a course the user is enrolled in.
                continue;
            }
            if (!isset($courseblockrecords[$record->courseid])) {
                $courseblockrecords[$record->courseid] = [];
            }
            $courseblockrecords[$record->courseid][] = $record;
        }

        $templatedata = [ 'courses' => [] ];

        foreach ($courseblockrecords as $courseid => $blockrecords) {
            $coursebadges = [];

            foreach ($blockrecords as $blockrecord) {
                $blockinstance = block_instance('completion_levels', $blockrecord);
                $config = $blockinstance->config;
                $blockcontext = \context_block::instance($blockrecord->id);

                if (!empty($config->group)
                        && !has_capability('moodle/site:accessallgroups', $blockcontext)
                        && !groups_is_member($config->group, $USER->id)) {
                    continue;
                }

                $progress = block_completion_levels_get_progress($config, $USER->id, $courseid);

                if ($progress === null) {
                    continue;
                }

                $coursebadges[] = [
                        'title' => $blockinstance->get_title(),
                        'instanceid' => $blockrecord->id,
                        'badge' => (new badge($config, $blockcontext, $progress))->export_for_template($output),
                        'progressbar' => $progress->get_progress_bar()->export_for_template($output),
                ];
            }

            if (!empty($coursebadges)) {
                $templatedata['courses'][] = [
                        'courseid' => $courseid,
                        'fullname' => format_string($mycourses[$courseid]->fullname),
                        'badges' => $coursebadges,
                ];
            }
        }

        if (empty($templatedata['courses'])) {
            $templatedata['rawcontent'] = get_string('no_blocks', 'block_completion_levels');
        }

        return $templatedata;
    }
}
