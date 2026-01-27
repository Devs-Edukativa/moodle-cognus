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
 * Define all the backup steps that will be used by the backup_block_task
 * @package    block_completion_levels
 * @copyright  2022 Astor Bizard, 2015 Stephen Bourget, 2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Specialised restore task for the completion_levels block (using execute_after_tasks for recoding of target activity)
 *
 * @copyright  2022 Astor Bizard, 2015 Stephen Bourget, 2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_completion_levels_block_task extends restore_block_task {

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
     * Define one array of configdata attributes that need to be decoded
     */
    public function get_configdata_encoded_attributes() {
        return []; // No special handling of configdata.
    }

    /**
     * This function, executed after all the tasks in the plan have been executed,
     * will perform the recode of the target activities for the block.
     * This must be done here and not in normal execution steps because the activities can be restored after the block.
     */
    public function after_restore() {
        global $DB;

        // Get the blockid.
        $blockid = $this->get_blockid();

        if ($configdata = $DB->get_field('block_instances', 'configdata', [ 'id' => $blockid ])) {
            $config = unserialize(base64_decode($configdata));
            if (!empty($config->activity)) {
                // Get the mapping and replace cmids in config.
                $newconfig = clone($config);
                foreach ($config->activity as $cmid => $cmconfig) {
                    $cmidmapping = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'course_module', $cmid);
                    unset($newconfig->activity[$cmid]);
                    if ($cmidmapping) {
                        $newconfig->activity[$cmidmapping->newitemid] = $cmconfig;
                    }
                }
                // Encode and save the config.
                $configdata = base64_encode(serialize($newconfig));
                $DB->set_field('block_instances', 'configdata', $configdata, [ 'id' => $blockid ]);
            }
        }

    }

    /**
     * Define the contents in the block that must be processed by the link decoder
     */
    public static function define_decode_contents() {
        return [];
    }

    /**
     * Define the decoding rules for links belonging to the block to be executed by the link decoder
     */
    public static function define_decode_rules() {
        return [];
    }
}
