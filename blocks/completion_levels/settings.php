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
 * Completion Levels block settings.
 *
 * @package   block_completion_levels
 * @copyright 2016 Michael de Raadt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'block_completion_levels/enablecustomlevelpix',
        get_string('enablecustomlevelpix', 'block_completion_levels'),
        '',
        0
    ));
    $settings->add(new admin_setting_configstoredfile(
        'block_completion_levels/levels_pix',
        new lang_string('config:levels_pix', 'block_completion_levels'),
        new lang_string('config:levels_pix_help', 'block_completion_levels'),
        'preset',
        0,
        ['subdirs' => 0, 'maxfiles' => 20, 'accepted_types' => '.png']
    ));
}
