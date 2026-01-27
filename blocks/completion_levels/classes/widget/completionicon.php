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

use pix_icon;
use pix_icon_fontawesome;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable widget representing an icon from a completion info of a course module.
 *
 * @package    block_completion_levels
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completionicon implements renderable, templatable {

    /**
     * @var number Completion info, as returned by progress->completion_info().
     */
    public $relativecompletion;

    /**
     * @var string Extra context to add to the icon title.
     */
    public $titlecontext;

    /**
     * Constructor.
     * @param number $relativecompletion Completion info for a course module, as returned by progress->completion_info().
     * @param string $titlecontext Extra context to add to the icon title.
     */
    public function __construct($relativecompletion, $titlecontext = null) {
        $this->relativecompletion = $relativecompletion;
        $this->titlecontext = $titlecontext;
    }

    /**
     * {@inheritDoc}
     * @param renderer_base $output
     * @see templatable::export_for_template()
     */
    public function export_for_template(renderer_base $output) {
        if ($this->relativecompletion == 1) {
            $cicon = 'completed';
            $faclass = 'fa-check';
            $color = 'success';
            $title = get_string('completed', 'completion');
        } else if ($this->relativecompletion > 0) {
            $cicon = 'partiallycompleted';
            $faclass = 'fa-dot-circle-o';
            $color = 'warning';
            $percent = (int)round($this->relativecompletion * 100);
            $title = get_string('partiallycompleted', 'block_completion_levels', $percent . '%');
        } else if ($this->relativecompletion === null) {
            $cicon = 'nocompletiondata';
            $faclass = 'fa-minus';
            $color = 'danger';
            $title = get_string('notcompletedyet', 'block_completion_levels');
        } else {
            $cicon = 'notcompleted';
            $faclass = 'fa-times';
            $color = 'danger';
            $title = get_string('notcompleted', 'completion');
        }
        if ($this->titlecontext !== null) {
            $title = get_string('contextualizedstring', 'block_completion_levels',
                    [ 'context' => $this->titlecontext, 'content' => $title ]);
        }
        $pixicon = new pix_icon($cicon, $title, 'block_completion_levels');
        return [
                'pixicon' => (new pix_icon_fontawesome($pixicon))->export_for_template($output),
                'faclass' => $faclass,
                'color' => $color,
                'title' => $title,
        ];
    }
}
