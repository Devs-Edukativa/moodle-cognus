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
use context_block;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable widget representing the completion level progress badge for an user.
 *
 * @package    block_completion_levels
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badge implements renderable, templatable {

    /**
     * @var object The block instance configuration object.
     */
    public $blockconfig;

    /**
     * @var context_block The context of the block.
     */
    public $blockcontext;

    /**
     * @var progress The progress object linked with this badge.
     */
    public $progress;

    /**
     * @var string Extra CSS classes for the badge.
     */
    public $additionalclasses;

    /**
     * Constructor.
     * @param object $blockconfig The block instance configuration object.
     * @param context_block $blockcontext The context of the block.
     * @param progress $progress The progress we want the badge of.
     * @param string $additionalclasses Extra CSS classes for the badge.
     */
    public function __construct($blockconfig, $blockcontext, $progress, $additionalclasses = '') {
        $this->blockconfig = $blockconfig;
        $this->blockcontext = $blockcontext;
        $this->progress = $progress;
        $this->additionalclasses = $additionalclasses;
    }

    /**
     * {@inheritDoc}
     * @param renderer_base $output
     * @see templatable::export_for_template()
     */
    public function export_for_template(renderer_base $output) {
        // Proceed by try-fallback in order custom -> admin -> default.

        $pixselect = $this->blockconfig->pixselect ?? 'admin';

        if ($pixselect == 'custom') {
            // Try with custom pix.
            $highestlevel = block_completion_levels_find_highest_badge($this->blockcontext->id, 'levels_pix');
            if ($highestlevel === null) {
                // No custom pix found, fallback to admin pix.
                $pixselect = 'admin';
            } else {
                // Custom pix are set, success!
                $userlevel = (int)floor($this->progress->percentage * $highestlevel / 100);
                return [
                        'isdefault' => false,
                        'imgurl' => moodle_url::make_pluginfile_url($this->blockcontext->id, 'block_completion_levels',
                                'levels_pix', 0, '/', $userlevel, false, WS_SERVER),
                        'level' => $userlevel,
                        'additionalclasses' => $this->additionalclasses,
                ];
            }
        }

        if ($pixselect == 'admin') {
            // Try with admin pix.
            $highestlevel = block_completion_levels_find_highest_badge(1, 'preset');
            if ($highestlevel === null || !get_config('block_completion_levels', 'enablecustomlevelpix')) {
                // No admin pix found or admin pix disabled, fallback to default pix.
                $pixselect = 'default';
            } else {
                // Admin pix are set, success!
                $userlevel = (int)floor(($this->progress->percentage * $highestlevel) / 100.0);
                return [
                        'isdefault' => false,
                        'imgurl' => moodle_url::make_pluginfile_url(1, 'block_completion_levels',
                                'preset', 0, '/', $userlevel, false, WS_SERVER),
                        'level' => $userlevel,
                        'additionalclasses' => $this->additionalclasses,
                ];
            }
        }

        // Default pix.
        $highestlevel = $this->blockconfig->maxlevel ?? 10;

        $userlevel = (int)floor($this->progress->percentage * $highestlevel / 100);
        $pix = (int)floor($userlevel / $highestlevel * 10);
        $classes = $this->additionalclasses;
        if ($userlevel >= 100) {
            $classes .= ' block_completion_levels-badge-3digits';
        } else if ($userlevel >= 10) {
            $classes .= ' block_completion_levels-badge-2digits';
        }

        return [
                'isdefault' => true,
                'imgurl' => $output->image_url('default/' . $pix, 'block_completion_levels'),
                'level' => $userlevel,
                'additionalclasses' => $classes,
        ];
    }
}
