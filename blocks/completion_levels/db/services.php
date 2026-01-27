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
 * Completion Levels services declaration.
 *
 * @package    block_completion_levels
 * @copyright  2022 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_completion_levels_delete_custom_pix' => [
        'classname'   => 'block_completion_levels_external',
        'methodname'  => 'delete_custom_pix',
        'classpath'   => 'blocks/completion_levels/externallib.php',
        'description' => 'delete custom badges for this block instance',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'block_completion_levels_get_progress' => [
        'classname'   => 'block_completion_levels_external',
        'methodname'  => 'get_progress',
        'classpath'   => 'blocks/completion_levels/externallib.php',
        'description' => 'get progress of one or all users',
        'type'        => 'read',
        'services'    => [ MOODLE_OFFICIAL_MOBILE_SERVICE ],
    ],
];
