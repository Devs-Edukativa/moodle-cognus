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

use block_completion_levels\progress;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable widget representing the progress bar.
 *
 * @package    block_completion_levels
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progressbar implements renderable, templatable {

    /**
     * @var progress The progress object.
     */
    public $progress;

    /**
     * Constructor.
     * @param progress $progress The progress object.
     */
    public function __construct($progress) {
        $this->progress = $progress;
    }

    /**
     * {@inheritDoc}
     * @param renderer_base $output
     * @see templatable::export_for_template()
     */
    public function export_for_template(renderer_base $output) {
        return [
                'percentage' => $this->progress->percentage,
                'text' => $this->progress->display(),
        ];
    }
}
