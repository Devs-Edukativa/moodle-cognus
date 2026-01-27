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

namespace block_completion_levels;

use block_completion_levels\widget\progressbar;

/**
 * Class representing overall progress of a user in a course.
 *
 * @package    block_completion_levels
 * @copyright  2022 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {
    /**
     * @var int Progress value. It is relative to $over.
     */
    public $value;
    /**
     * @var int Progress value as percentage.
     */
    public $percentage;
    /**
     * @var int Maximum value for the progress. $value is relative to this value.
     */
    public $over;
    /**
     * @var bool Whether this progress should be expressed as a percentage.
     */
    public $forcepercent;

    /**
     * @var number Progress relative value, between 0 and 1. Use this value to compare progresses.
     */
    public $relativevalue;

    /**
     * @var number[] Completion info for cms. A number between 0 (not completed) and 1 (completed).
     */
    private $completions;

    /**
     * Constructor.
     * @param number $over Maximum value for the progress.
     */
    public function __construct($over = null) {
        if (!$over) {
            $this->over = 100;
            $this->forcepercent = true;
        } else {
            $this->over = intval($over);
            $this->forcepercent = false;
        }
        $this->relativevalue = 0;
        $this->percentage = 0;
        $this->value = 0;
        $this->completions = [];
    }

    /**
     * Set a value for this overall progress.
     * @param number $relativevalue A relative value between 0 and 1.
     */
    public function set($relativevalue) {
        $this->relativevalue = $relativevalue;
        $this->percentage = (int)floor($relativevalue * 100);
        $this->value = (int)floor($relativevalue * $this->over);
    }

    /**
     * Set a completion info for one cm.
     * @param int $cmid Course module ID.
     * @param number $completioninfo A number between 0 (not completed) and 1 (completed).
     */
    public function set_completion_info($cmid, $completioninfo) {
        $this->completions[$cmid] = $completioninfo;
    }

    /**
     * Get completion info for a cm.
     * @param int $cmid Course module ID.
     * @return number|null A number between 0 (not completed) and 1 (completed), or null if not set.
     */
    public function completion_info($cmid) {
        return isset($this->completions[$cmid]) ? $this->completions[$cmid] : null;
    }

    /**
     * Display this progress as a readable string.
     * @return string
     */
    public function display() {
        if ($this->forcepercent) {
            return $this->percentage . '%';
        } else {
            return $this->value . '<span class="progress-over-separator">/</span>' . $this->over;
        }
    }

    /**
     * Create and return the progress bar rendarable widget.
     * @return progressbar
     */
    public function get_progress_bar() {
        return new progressbar($this);
    }

    /**
     * Comparison function of two progresses.
     * @param progress $p1
     * @param progress $p2
     * @return int -1, 0 or 1
     */
    public static function compare(progress $p1, progress $p2) {
        // Get the sign of the difference (returning floating values in [0,1] in a comparison function is a bad idea).
        return ($p2->relativevalue - $p1->relativevalue > 0) - ($p2->relativevalue - $p1->relativevalue < 0);
    }
}
