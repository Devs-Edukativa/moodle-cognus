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

use block_completion_levels;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable widget for the block contents within a course.
 *
 * @package    block_completion_levels
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course implements renderable, templatable {

    /**
     * @var block_completion_levels The block instance.
     */
    public $block;

    /**
     * @var number Course ID.
     */
    public $courseid;

    /**
     * Constructor.
     * @param block_completion_levels $block The block instance.
     * @param number $courseid The course id (defaults to global $COURSE id).
     */
    public function __construct(block_completion_levels $block, $courseid = null) {
        global $COURSE;
        $this->block = $block;
        $this->courseid = $courseid ?? $COURSE->id;
    }

    /**
     * {@inheritDoc}
     * @param renderer_base $output
     * @see templatable::export_for_template()
     */
    public function export_for_template(renderer_base $output) {
        global $USER;

        // Check if user is in group for block.
        if (!empty($this->block->config->group)
                && !has_capability('moodle/site:accessallgroups', $this->block->context)
                && !groups_is_member($this->block->config->group, $USER->id)) {
            return [];
        }

        $progress = block_completion_levels_get_progress($this->block->config, $USER->id, $this->courseid);

        if ($progress === null) {
            if (has_capability('moodle/block:edit', $this->block->context)) {
                return [ 'title' => $this->block->get_title(),  'noactivitiestrackedmessage' => true ];
            } else {
                return [];
            }
        }

        $templatedata = [
                'title' => $this->block->get_title(),
                'badge' => (new badge($this->block->config, $this->block->context, $progress))->export_for_template($output),
                'progressbar' => $progress->get_progress_bar()->export_for_template($output),
        ];

        // Allow teachers to access the overview page.
        if (has_capability('block/completion_levels:overview', $this->block->context)) {
            $templatedata['showlinks'] = true;
            $templatedata['links'] = [];
            foreach ([ 'overview', 'details'] as $link) {
                $templatedata['links'][] = [
                        'identifier' => $link,
                        'name' => get_string($link, 'block_completion_levels'),
                ];
            }
        }

        $templatedata['instanceid'] = $this->block->instance->id;
        $templatedata['courseid'] = $this->courseid;
        $templatedata['walloffame'] = (new walloffame($this->block, $this->courseid))->export_for_template($output);
        return $templatedata;
    }
}
