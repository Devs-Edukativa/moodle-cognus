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

use block_completion_levels;
use context_course;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Renderable widget representing the wall of fame for a block instance.
 *
 * @package    block_completion_levels
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class walloffame implements renderable, templatable {

    /**
     * @var block_completion_levels The block instance.
     */
    public $block;

    /**
     * @var number Course ID.
     */
    public $courseid;

    /**
     * Constructor.
     * @param block_completion_levels $block The block instance.
     * @param number $courseid The course id (defaults to global $COURSE id).
     */
    public function __construct(block_completion_levels $block, $courseid = null) {
        global $COURSE;
        $this->block = $block;
        $this->courseid = $courseid ?? $COURSE->id;
    }

    /**
     * {@inheritDoc}
     * @param renderer_base $output
     * @see templatable::export_for_template()
     */
    public function export_for_template(renderer_base $output) {
        global $USER;
        $limit = $this->block->config->walloffamesize ?? 0;
        $group = $this->block->config->group ?? 0;

        if ($limit != 0) {

            $coursecontext = context_course::instance($this->courseid);
            $users = block_completion_levels_get_users($this->courseid, $this->block->config,
                    $group, 'student', $coursecontext->id);

            // Filter by user groups if needed.
            if ($this->block->config->showonlycogroups
                    && (!has_capability('block/completion_levels:overview', $this->block->context)
                    || !has_capability('moodle/site:accessallgroups', $coursecontext))) {
                // TODO consider only groups in grouping defined at course level.
                // TODO if user has no groups, show a general ranking of all users.
                $usergroups = groups_get_all_groups($this->courseid, $USER->id);
                if (empty($usergroups)) {
                    // User has no groups, do not bother filtering.
                    $users = [ $USER->id => $users[$USER->id] ];
                } else {
                    $cogroupusers = groups_get_groups_members(array_keys($usergroups));
                    $users = array_uintersect($users, $cogroupusers, function($u1, $u2) {
                        return $u1->id - $u2->id;
                    });
                }
            }

            $usersprogress = block_completion_levels_get_progress($this->block->config, array_keys($users), $this->courseid);

            // TODO Sort by completion date (if possible and not too costly).
            // See for example completion_info::get_user_completion()->timecompleted.
            uasort($usersprogress, 'block_completion_levels\progress::compare');

        } else {
            $usersprogress = [];
        }

        if (empty($usersprogress)) {
            return [];
        }
        $i = 1;
        $idisplay = 1;
        $lastidisplayed = 1;
        $previouscompletion = -1;
        $currentuserdone = false;
        $templatedata = [
                'groupname' => ($group > 0) ? '(' . groups_get_group_name($group) . ')' : '',
                'users' => [],
        ];

        foreach ($usersprogress as $userid => $progress) {

            // Manage ranking equality: increase rank only if there is a strict difference in progress.
            if ($progress->value != $previouscompletion) {
                $idisplay = $i;
            }
            $previouscompletion = $progress->value;

            $user = new stdClass();
            $user->i = $idisplay . ')';
            $user->currentuser = ($userid == $USER->id);
            if (!isset($this->block->config->anonymous) || !$this->block->config->anonymous || $user->currentuser) {
                if (isset($this->block->config->usealternatenames) && $this->block->config->usealternatenames
                        && isset($users[$userid]->alternatename) && trim($users[$userid]->alternatename) > '') {
                    $user->name = trim($users[$userid]->alternatename);
                } else {
                    $user->name = fullname($users[$userid]);
                }
            } else {
                $user->name = '';
            }

            if ($i > $limit && $limit != -1) {
                if ($currentuserdone) {
                    break;
                } else if ($user->currentuser) {
                    if ($lastidisplayed != $idisplay) {
                        $ellipsisrow = new stdClass();
                        $ellipsisrow->i = '<span class="ranking-ellipsis">&bull;&bull;&bull;</span>';
                        $ellipsisrow->progress = '';
                        $ellipsisrow->currentuser = false;
                        $ellipsisrow->name = '';
                        $templatedata['users'][] = $ellipsisrow;
                    }
                    $user->progress = $progress->display();
                    $templatedata['users'][] = $user;
                    break;
                } else {
                    $i++;
                    continue;
                }
            }

            $currentuserdone = $currentuserdone || $user->currentuser;

            $user->progress = $progress->display();

            $lastidisplayed = $idisplay;
            $templatedata['users'][] = $user;

            $i++;
        }

        return $templatedata;
    }
}
