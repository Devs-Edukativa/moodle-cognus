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
use html_table;
use html_writer;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable widget of the overview table for each student.
 *
 * @package    block_completion_levels
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class studentoverviewtable implements renderable, templatable {

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

        $table = new html_table();

        $table->head = [
                get_string('type', 'block_completion_levels'),
                get_string('name'),
                get_string('score', 'block_completion_levels'),
                get_string('weight', 'block_completion_levels'),
                get_string('completion', 'block_completion_levels'),
        ];
        $table->align = [
                'right',
                'left',
                'right',
                'right',
                'center',
        ];
        $table->size = [
                null,
                '50%',
                '10em',
                null,
                null,
        ];

        $config = $this->block->config;
        $modinfo = get_fast_modinfo($this->courseid, $USER->id);
        $totalcomplete = 0;
        $progress = block_completion_levels_get_progress($config, $USER->id, $this->courseid);
        if ($progress !== null) {
            $activities = block_completion_levels_get_tracked_activities($this->courseid, $config);
            foreach ($activities as $activity) {
                $cmid = $activity->id;
                $cminfo = $modinfo->get_cm($cmid);

                if ($cminfo->uservisible) {
                    $icon = html_writer::empty_tag('img', [ 'src' => $cminfo->get_icon_url()->out(), 'class' => 'activityicon',
                            'title' => $cminfo->get_module_type_name(), 'value' => $cminfo->modname ]);
                    $name = html_writer::span($cminfo->get_formatted_name(), 'activity');

                    if ($cminfo->url !== null) {
                        $linkattributes = [];
                        if (!$cminfo->visible) {
                            $linkattributes['class'] = 'dimmed';
                            $linkattributes['title'] = get_string('hiddenfromstudents');
                        }
                        $name = html_writer::link($cminfo->url, $name, $linkattributes);
                    }
                } else {
                    $icon = html_writer::span('', '', [ 'value' => 'zzzzzz' ]);
                    $name = html_writer::span(get_string('hiddenmodule', 'block_completion_levels'));
                }

                $relativescore = $progress->completion_info($cmid);
                $totalcomplete += $relativescore;
                $scoredisplay = '<span value="' . $relativescore . '">' .
                                    block_completion_levels_format_user_activity_completion($relativescore, $config) .
                                '</span>';

                $completed = '<span value="' . $relativescore . '">' .
                                $output->render(new completionicon($relativescore)) .
                            '</span>';

                $table->data[] = [ $icon, $name, $scoredisplay, $activity->weight, $completed ];
            }

            $badge = $output->render(new badge($config, $this->block->context, $progress, 'block_completion_levels-badge-small'));
            $badge = html_writer::div($badge, 'block_completion_levels-badge-small-container');
            $table->data[] = [
                    '',
                    '<b>' . get_string('total') . '</b>',
                    $badge . '<b>' . $progress->display() . '</b>',
                    '',
                    format_float($totalcomplete / count($activities) * 100.0, 2) . '%',
            ];

            $table->id = uniqid('block_completion_levels');

            return [ 'table' => html_writer::table($table), 'tableid' => $table->id ];
        } else {
            return [];
        }
    }
}
