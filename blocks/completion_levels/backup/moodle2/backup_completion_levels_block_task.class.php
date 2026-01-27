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
 * Define how completion_levels blocks should behave on backup.
 * @package    block_completion_levels
 * @copyright  2022 Astor Bizard, 2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Specialised backup task for the completion_levels block.
 *
 * @copyright  2022 Astor Bizard, 2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_completion_levels_block_task extends backup_block_task {

    /**
     * Define (add) particular settings that this block can have
     */
    protected function define_my_settings() {
    }

    /**
     * Define (add) particular steps that this block can have
     */
    protected function define_my_steps() {
    }

    /**
     * Define one array of fileareas that this block controls
     */
    public function get_fileareas() {
        return [ 'levels_pix' ];
    }

    /**
     * Define one array of configdata attributes that need to be processed by the contenttransformer
     */
    public function get_configdata_encoded_attributes() {
        return [];
    }

    /**
     * Code the transformations to perform in the block in order to get transportable (encoded) links
     * @param mixed $content
     */
    public static function encode_content_links($content) {
        return $content; // No special encoding of links.
    }
}
