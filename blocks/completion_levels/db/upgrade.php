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
 * Database upgrade steps definition.
 *
 * @package    block_completion_levels
 * @copyright  2022 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Performs database actions to upgrade from older versions, if required.
 * @param int $oldversion Plugin version we are upgrading from.
 * @param object $block Block version information.
 * @return boolean
 */
function xmldb_block_completion_levels_upgrade($oldversion, $block) {
    global $DB;

    $v2x0 = 2022032900; // Block v2.0.
    if ($oldversion < $v2x0) {

        $blockrecords = $DB->get_records('block_instances', [ 'blockname' => 'completion_levels' ]);
        foreach ($blockrecords as $blockrecord) {
            if (!empty($blockrecord->configdata)) {
                $config = unserialize(base64_decode($blockrecord->configdata));

                // Badge selection has changed, update corresponding settings.
                if (!isset($config->pixselect)) {
                    $custompix = isset($config->enablecustom) && $config->enablecustom
                            && isset($config->enablecustomlevelpix) && $config->enablecustomlevelpix;
                    if ($custompix) {
                        $config->pixselect = 'custom';
                        $config->levels = 10;
                    } else if (get_config('block_completion_levels', 'enablecustomlevelpix')) {
                        $config->pixselect = 'admin';
                        $config->levels = 10;
                    } else {
                        $config->pixselect = 'default';
                    }
                }
                unset($config->enablecustom, $config->enablecustomlevelpix);

                // These settings values changed in some cases, update them.
                if (isset($config->levelsTitle)) {
                    if (trim($config->levelsTitle) == '') {
                        $config->levelsTitle = 'Completion Levels';
                    }
                }
                if (isset($config->totalcompteactivity)) {
                    if ($config->totalcompteactivity == 0 || $config->totalcompteactivity == 100) {
                        $config->totalcompteactivity = '';
                    }
                }

                // Rename some settings.
                $mapping = [
                        'levelsTitle' => 'blocktitle',
                        'totalcompteactivity' => 'progressover',
                        'nbWallOfFame' => 'walloffamesize',
                        'WallOfFameNom' => 'anonymous',
                        'WallOfFameGroup' => 'showonlycogroups',
                        'grade_mode' => 'trackingmethod',
                        'levels' => 'maxlevel',
                ];

                foreach ($mapping as $oldname => $newname) {
                    if (isset($config->$oldname)) {
                        $config->$newname = $config->$oldname;
                        unset($config->$oldname);
                    }
                }

                $DB->update_record('block_instances', [
                        'id' => $blockrecord->id,
                        'configdata' => base64_encode(serialize($config)),
                        'timemodified' => time(),
                ]);
            }
        }
        upgrade_block_savepoint( true , $v2x0, 'completion_levels');
    }

    return true;
}
