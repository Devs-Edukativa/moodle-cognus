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
 * Completion Levels block helper functions
 *
 * @package    block_completion_levels
 * @copyright  2021 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_completion_levels\progress;

/**
 * Check consistency of given block instance with given parent context. Throws a moodle_exception on failed check.
 *
 * @param object|int $instanceorid Block instance or id.
 * @param context $context Parent context of the block.
 * @param string $errorcontext Information about the context in which a failed check occured.
 * @param string $errorurl URL to redirect to on a failed check.
 * @throws moodle_exception
 */
function block_completion_levels_check_instance($instanceorid, $context, $errorcontext = '', $errorurl = '') {
    global $DB;
    if (is_object($instanceorid)) {
        $blockrecord = $instanceorid;
    } else {
        $blockrecord = $DB->get_record('block_instances', [ 'id' => $instanceorid ]);
    }
    if ($blockrecord === false || $blockrecord->parentcontextid != $context->id || $blockrecord->blockname != 'completion_levels') {
        throw new moodle_exception('invalidblockinstance', 'error', $errorurl,
                format_string($errorcontext) . ' / ' . get_string('pluginname', 'block_completion_levels'));
    }
}

/**
 * Determine what is the maximum badge level, from stored images.
 * This proceeds by efficient dichotomic search of the lowest non-existing image.
 * @param mixed $fileareacontextid File area context ID where images are stored.
 * @param string $filearea File area name where images are stored.
 * @return null|number Returns highest badge level found, null if none found.
 */
function block_completion_levels_find_highest_badge($fileareacontextid, $filearea) {
    $fs = get_file_storage();
    if (!$fs->file_exists($fileareacontextid, 'block_completion_levels', $filearea, 0, '/', '0.png')) {
        return null;
    }
    $lowbound = 0;
    $highbound = null;
    $i = 1;
    while ($highbound === null || $lowbound < $highbound - 1) {
        if ($fs->file_exists($fileareacontextid, 'block_completion_levels', $filearea, 0, '/', $i . '.png')) {
            $lowbound = $i;
            $i = $highbound === null ? $i * 2 : (int)round(($i + $highbound) / 2);
        } else {
            $highbound = $i;
            $i = (int)round(($i + $lowbound) / 2);
        }
    }
    return $lowbound;
}

/**
 * Retrieve and return progress for given users, related to a block instance.
 * @param object $blockconfig The block instance configuration object.
 * @param int|array $userids An array of user IDs, or a single user ID.
 * @param int $courseid Course ID.
 * @return progress|progress[]|null
 *      If $userids is a single value, returns a progress object, or null if no activities are tracked.
 *      If $userids is an array, returns an array of [userid => progress object], or an empty array if no activities are tracked.
 */
function block_completion_levels_get_progress($blockconfig, $userids, $courseid) {
    global $DB;
    $activities = block_completion_levels_get_tracked_activities($courseid, $blockconfig);

    if (!is_array($userids)) {
        $singlevalue = true;
        $userids = [ $userids ];
    } else {
        $singlevalue = false;
    }

    if (empty($activities)) {
        return $singlevalue ? null : [];
    }

    // TODO find a way to cache completion data (/!\ cache it per block instance (or trackingmethod), as trackingmethod may differ).
    $completioninfo = [];
    foreach ($userids as $userid) {
        $completioninfo[$userid] = [];
    }

    $nusers = count($userids);
    // Query by slices of at most 1000 users, to avoid too large IN statement.
    for ($i = 0; $i < $nusers; $i += 1000) {
        list($inuserssql, $params) = $DB->get_in_or_equal(
                array_slice($userids, $i, min([$nusers - $i, 1000])),
                SQL_PARAMS_NAMED);
        if ($blockconfig->trackingmethod == 0) {
            // Completion mode.
            $sql = "SELECT cmc.id, cmc.userid, cmc.coursemoduleid as cmid, cmc.completionstate
                    FROM {course_modules} cm
                    JOIN {course_modules_completion} cmc ON cm.id = cmc.coursemoduleid
                    WHERE cm.course = :courseid AND cmc.userid $inuserssql";
            $params['courseid'] = $courseid;

            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $completiondata) {
                $completioninfo[$completiondata->userid][$completiondata->cmid] =
                        in_array($completiondata->completionstate, [ COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS ]) ? 1 : 0;
            }
            $rs->close();
        } else {
            // Gradebook mode.
            $sql = "SELECT gg.id, gg.userid, cm.id as cmid, gg.finalgrade, gg.rawgrade, gg.rawgrademax, gg.rawgrademin
                    FROM {grade_grades} gg
                    JOIN {grade_items} gi ON gi.id = gg.itemid
                    JOIN {modules} m ON m.name = gi.itemmodule
                    JOIN {course_modules} cm ON cm.instance = gi.iteminstance AND cm.module = m.id
                    WHERE gi.courseid = :courseid AND gg.userid $inuserssql";
            $params['courseid'] = $courseid;

            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $gradedata) {
                $grade = $gradedata->finalgrade !== null ? $gradedata->finalgrade : $gradedata->rawgrade;
                if ($grade !== null) {
                    $relativegrade = ($grade - $gradedata->rawgrademin) / ($gradedata->rawgrademax - $gradedata->rawgrademin);
                    $completioninfo[$gradedata->userid][$gradedata->cmid] = $relativegrade;
                }
            }
            $rs->close();
        }
    }

    $progresses = [];

    foreach ($userids as $userid) {

        $progress = new progress($blockconfig->progressover);

        $progressvalue = 0;
        $totalweight = 0;
        foreach ($activities as $activity) {
            // TODO manage activities with weight 0 (?).
            $weight = $activity->weight ?: 1;
            $totalweight += $weight;
            if (isset($completioninfo[$userid][$activity->id])) {
                $progressvalue += $completioninfo[$userid][$activity->id] * $weight;
                $progress->set_completion_info($activity->id, $completioninfo[$userid][$activity->id]);
            }
        }

        $progress->set($progressvalue / $totalweight);

        $progresses[$userid] = $progress;
    }

    if ($singlevalue) {
        return reset($progresses);
    } else {
        return $progresses;
    }
}

/**
 * Format completion info of a course module, as to be displayed.
 * @param number $relativecompletion Completion info for a course module, as returned by progress->completion_info()*;
 * @param object $blockconfig The block instance configuration object.
 * @return string
 */
function block_completion_levels_format_user_activity_completion($relativecompletion, $blockconfig) {
    if ($relativecompletion !== null) {
        if (!isset($blockconfig->trackingmethod) || $blockconfig->trackingmethod == 0) {
            return $relativecompletion;
        } else {
            return ((int)round($relativecompletion * 100)) . '%';
        }
    } else {
        return '-';
    }
}

/**
 * Used in details.php and overview.php to print two tabs for navigation.
 * @param string $current Current tab name ('details' or 'overview').
 * @param int $blockinstanceid Block instance ID.
 * @param int $courseid Course ID.
 * @param int $group Selected group (0 for all groups).
 * @param int $role Selected role (0 for all roles).
 */
function block_completion_levels_print_view_tabs($current, $blockinstanceid, $courseid, $group, $role) {
    global $OUTPUT;
    $parameters = [
            'instanceid' => $blockinstanceid,
            'courseid'   => $courseid,
            'group'      => $group,
            'role'       => $role,
    ];

    $tabs = [];
    foreach ([ 'overview', 'details' ] as $tab) {
        $tabs[] = new tabobject(
                $tab,
                new moodle_url('/blocks/completion_levels/' . $tab . '.php', $parameters),
                get_string($tab, 'block_completion_levels')
        );
    }

    echo $OUTPUT->tabtree($tabs, $current);
}

/**
 * Retrieve and return all users a block instance might consider, filtered by some criteria.
 * @param number $courseid Course ID.
 * @param object $blockconfig The block instance configuration object.
 * @param number $groupid Limit users who belong to this group (0 for all groups).
 * @param number|string $rolearchetypeorid Limit users who have the given role ID or archetype (0 for all roles).
 * @param number $contextid Course context ID, required if $rolearchetypeorid is provided.
 * @param bool $withlastaccess If true, include information about last access to course as lastonlinetime.
 * @return array
 */
function block_completion_levels_get_users($courseid, $blockconfig, $groupid = 0,
        $rolearchetypeorid = 0, $contextid = null, $withlastaccess = false) {
    global $DB;
    $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
    $select = "SELECT DISTINCT $fields"; // Distinct is needed because there can be duplicate enrolments.
    $from = " FROM {user} u
              JOIN {user_enrolments} ue ON ue.userid = u.id
              JOIN {enrol} e ON (e.id = ue.enrolid)";
    $where = " WHERE e.courseid = :courseid";
    $params = [ 'courseid' => $courseid ];
    if ($withlastaccess) {
        $select .= ", COALESCE(ul.timeaccess, 0) AS lastonlinetime";
        $from .= " LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid2)";
        $params['courseid2'] = $courseid;
    }
    if ($rolearchetypeorid !== 0) {
        if (is_number($rolearchetypeorid)) {
            // Filter by role ID.
            $from .= " JOIN {role_assignments} ra ON ra.userid = u.id";
            $where .= " AND ra.contextid = :contextid AND ra.roleid = :roleid";
            $params['contextid'] = $contextid;
            $params['roleid'] = $rolearchetypeorid;
        } else {
            // Filter by role archetype.
            $from .= " JOIN {role_assignments} ra ON ra.userid = u.id
                       JOIN {role} r ON r.id = ra.roleid";
            $where .= " AND ra.contextid = :contextid AND r.archetype = :rolearchetype";
            $params['contextid'] = $contextid;
            $params['rolearchetype'] = $rolearchetypeorid;
        }
    }
    if ($groupid != 0) {
        $from .= " JOIN {groups_members} g ON (g.userid = u.id AND g.groupid = :groupid)";
        $params['groupid'] = $groupid;
    }
    if (!isset($blockconfig->filterinactiveusers) || $blockconfig->filterinactiveusers) {
        // Limit to users not inactive.
        $where .= " AND ue.status = :enrolactive AND e.status = :enrolenabled
                    AND ue.timestart < :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)";
        $params['enrolactive'] = ENROL_USER_ACTIVE;
        $params['enrolenabled'] = ENROL_INSTANCE_ENABLED;
        $params['now1'] = time();
        $params['now2'] = $params['now1'];
    }
    return $DB->get_records_sql($select . $from . $where, $params);
}

/**
 * Get all activites a given block instance is currently tracking.
 * This filters out activites that are set to be tracked, but not trackable with current settings.
 * @param int $courseid Course ID.
 * @param object $blockconfig The block instance configuration object.
 * @return array of stdClass containing fields id (cmid) and weight.
 */
function block_completion_levels_get_tracked_activities($courseid, $blockconfig) {
    if (!isset($blockconfig->trackingmethod) || $blockconfig->trackingmethod == 0) {
        $trackingmode = 0;
    } else {
        $trackingmode = 1;
    }
    $trackable = block_completion_levels_get_trackable_activities($courseid, $trackingmode);
    $activities = [];
    foreach ($trackable as $activity) {
        $tracked = isset($blockconfig->activity[$activity->id]['checkbox']) ? $blockconfig->activity[$activity->id]['checkbox'] : 0;
        if (!$tracked) {
            continue;
        }
        $activitydata = new stdClass();
        $activitydata->id = $activity->id;
        $activitydata->weight = $blockconfig->activity[$activity->id]['weight'] ?: 1;
        $activities[$activity->id] = $activitydata;
    }
    return $activities;
}

/**
 * Returns all activities this block can track completion of.
 * @param int $courseid Course ID.
 * @param mixed|null $trackingmethod If set, return only activities trackable for this trackingmethod.
 * @return array[]
 */
function block_completion_levels_get_trackable_activities($courseid, $trackingmethod = null) {
    global $DB;
    $modinfo = get_fast_modinfo($courseid, -1);
    $activities = [];
    if ($trackingmethod === null || $trackingmethod == 0) {
        $completioninfo = new completion_info(get_course($courseid));
    }
    if ($trackingmethod === null || $trackingmethod != 0) {
        $cmswithgradeitems = $DB->get_records_sql(
                "SELECT cm.id as cmid
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                INNER JOIN {grade_items} gi ON gi.itemmodule = m.name AND gi.iteminstance = cm.instance
                WHERE cm.course = :courseid",
                [ 'courseid' => $courseid ]);
    }
    foreach ($modinfo->get_cms() as $cm) {
        $activitydata = new stdClass();
        $activitydata->id = $cm->id;
        if ($trackingmethod === null || $trackingmethod == 0) {
            $activitydata->completionenabled = $completioninfo->is_enabled($cm);
        }
        if ($trackingmethod === null || $trackingmethod != 0) {
            $function = "{$cm->modname}_supports";
            $activitydata->hasgrades = function_exists($function) && $function(FEATURE_GRADE_HAS_GRADE)
                    && isset($cmswithgradeitems[$cm->id]);
        }
        if ((($trackingmethod === null || $trackingmethod == 0) && $activitydata->completionenabled)
                || (($trackingmethod === null || $trackingmethod != 0) && $activitydata->hasgrades)) {
            $activities[$cm->id] = $activitydata;
        }
    }
    return $activities;
}

/**
 * Retrieve a role ID corresponding to the 'student' archetype in the given context.
 * @param int $contextid Context ID.
 * @return int Role ID, 0 if not found.
 */
function block_completion_levels_get_student_role_id($contextid) {
    global $DB;

    $sql = "SELECT r.id
                FROM {role} r
                JOIN {role_assignments} a ON a.roleid = r.id
                WHERE a.contextid = :contextid AND r.archetype = :archetype";

    $params = [ 'contextid' => $contextid, 'archetype' => 'student' ];
    $studentrole = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
    return $studentrole ? $studentrole->id : 0;
}

/**
 * Retrieve all users that are eligible for block completion notifications.
 * @param int $courseid
 * @param string $fields
 * @return array of user database records.
 */
function block_completion_levels_get_notifiable_users($courseid, $fields = "u.*") {
    global $DB;
    $context = context_course::instance($courseid);
    $roles = get_roles_with_caps_in_context($context, [ 'moodle/course:manageactivities' ]);
    list($insql, $params) = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED);
    $sql = "SELECT DISTINCT $fields
                  FROM {user} u
                  JOIN {role_assignments} r ON r.userid = u.id
                 WHERE r.contextid = :contextid AND r.roleid $insql";
    $params['contextid'] = $context->id;
    return $DB->get_records_sql($sql, $params);
}
