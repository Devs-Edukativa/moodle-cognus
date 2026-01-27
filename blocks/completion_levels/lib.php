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
 * Completion Levels block common configuration and helper functions
 *
 * @package    block_completion_levels
 * @copyright  2016 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * File serving.
 *
 * @param stdClass $course The course object.
 * @param stdClass $bi Block instance record.
 * @param context $context The context object.
 * @param string $filearea The file area.
 * @param array $args List of arguments.
 * @param bool $forcedownload Whether or not to force the download of the file.
 * @param array $options Array of options.
 * @return void|false
 */
function block_completion_levels_pluginfile($course, $bi, $context, $filearea, $args, $forcedownload, array $options = []) {
    $fs = get_file_storage();
    $file = null;

    if (($filearea == 'levels_pix') || ($filearea == 'preset')) {
        // For performance reason, and very low risk, we do not restrict the access to the level badges
        // to the participant of the course, nor do we check if they have the required level, etc...
        $itemid = array_shift($args);
        $filename = array_shift($args);
        $file = $fs->get_file($context->id, 'block_completion_levels', $filearea, $itemid, '/', $filename . '.png');
    }

    if (!$file) {
        return false;
    }

    send_stored_file($file);
}

/**
 * Returns the list of Moodle features this block supports.
 * @param string $feature FEATURE_xx constant.
 * @return boolean|null Whether this block supports feature, null if unspecified.
 */
function block_completion_levels_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2 :
            return true;
        default:
            return null;
    }
}

/**
 * Definition of Fontawesome icons mapping.
 * @return string[] Fontawesome icons mapping.
 */
function block_completion_levels_get_fontawesome_icon_map() {
    return [
            'block_completion_levels:completed' => 'fa-fw fa-check text-success',
            'block_completion_levels:partiallycompleted' => 'fa-fw fa-dot-circle-o text-warning',
            'block_completion_levels:notcompleted' => 'fa-fw fa-times text-danger',
            'block_completion_levels:nocompletiondata' => 'fa-fw fa-minus text-danger block_completion_levels-text-small',
            'block_completion_levels:info' => 'fa-info-circle text-info',
            'block_completion_levels:warning' => 'fa-warning text-warning',
    ];
}
