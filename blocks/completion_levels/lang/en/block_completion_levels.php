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
 * Strings for component 'block_completion_levels', language 'en'.
 *
 * Inspired from Michael de Raadt's block_completion_progress.
 *
 * @package    block_completion_levels
 * @copyright  2022 Astor Bizard, 2016 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activitiescompletion'] = 'Activities completion';
$string['activitiestracking'] = 'Activities tracking';
$string['adminpix'] = 'Site default:';
$string['allstudents'] = 'All students';
$string['badge'] = 'Badge';
$string['badgeconfiguration'] = 'Badge configuration';
$string['badgestouse'] = 'Badges to use';
$string['completion'] = 'Completion';
$string['completion_levels:addinstance'] = 'Add a new Completion Levels block';
$string['completion_levels:myaddinstance'] = 'Add a Completion Levels block to My home page';
$string['completion_levels:overview'] = 'View course overview of Completion Levels for all students';
$string['completionrequiredforblockinstance'] = 'This activity needs to be completed, in order to complete the {$a} block.';
$string['config:anonymouswalloffame'] = 'Keep wall of fame anonymous';
$string['config:anonymouswalloffame_help'] = 'Whether students names should be hidden in the wall of fame';
$string['config:blocktitle'] = 'Custom block title';
$string['config:blocktitle_help'] = 'There can be multiple instances of Completion Levels block.<br>
You may use different Completion Levels blocks to monitor different sets of activities or resources.<br>
For instance you could track progress in assignments in one block and quizzes in another.<br>
For this reason, you can override the default title and set a more appropriate block title for each instance.';
$string['config:completionnotifications'] = 'Block completion notifications';
$string['config:displayprogressover'] = 'Display progress as value over';
$string['config:displayprogressover_help'] = 'Display progress as a value over this number. If empty, progress will be displayed as a percentage.';
$string['config:filterinactiveusers'] = 'Filter out users with inactive enrolment';
$string['config:filterinactiveusers_help'] = 'Filter out from wall of fame and overviews users whose enrolment status is suspended, or enrolment has expired or has not started.';
$string['config:group'] = 'Visible only to group';
$string['config:group_help'] = 'Selecting a group limits display of this block to that group only.';
$string['config:levels_pix'] = 'Custom badges';
$string['config:levels_pix_help'] = 'Name the files [level].png, from 0 up to the desired maximum level. For instance: 0.png, 1.png, etc. The recommended image size is 150x150.';
$string['config:markactivities'] = 'Mark required activities on course page';
$string['config:markactivities_help'] = 'If set to Yes, activities set to require completion by this block instance will be marked by a star when viewing the course page.';
$string['config:maxlevel'] = 'Maximum level';
$string['config:maxlevel_help'] = 'Maximum level to use. Levels will extend from 0 to &lt;maxlevel&gt; (ie. there will be &lt;maxlevel&gt;+1 levels).';
$string['config:sendcompletionnotifications'] = 'Send block completion notifications';
$string['config:sendcompletionnotifications_help'] = 'Send notifications when a student achieves 100% on this block.';
$string['config:sendnotificationsto'] = 'Send notification to';
$string['config:showonlycogroupmembers'] = 'Restrict to user groups';
$string['config:showonlycogroupmembers_help'] = 'In the wall of fame, show only students who belong to the same group as the user.';
$string['config:trackingmethod'] = 'Tracking method';
$string['config:trackingmethod_help'] = 'Determines the metric to use to track activities.<br>
If "Completion" is selected, students will make progress for the activity if they complete it (in the standard sense of Completion, ie. when the checkbox is ticked on course page).<br>
If "Relative grade" is selected, students will make progress for the activity relative to their grade.<br>
In both cases, progress for an activity is weighted (see below).';
$string['config:usealternatenames'] = 'Use alternate names';
$string['config:usealternatenames_help'] = 'Use alternate names of users (if set) to display on the wall of fame.';
$string['config:walloffamesize'] = 'Number of students';
$string['config:walloffamesize_help'] = 'The number of students displayed in the wall of fame. Select "No students" to not display any wall of fame.';
$string['contextualizedstring'] = '{$a->context}: {$a->content}';
$string['custompix'] = 'Custom:';
$string['defaultblocktitle'] = 'Completion Levels';
$string['defaultpix'] = 'Default:';
$string['deletebadgeconfirmation'] = 'Are you sure you want to delete custom badges for this block?
This will delete currently saved badges and files in the draft area below. This action can not be undone.';
$string['deletecustompix'] = 'Delete custom';
$string['details'] = 'Details';
$string['dotrack'] = 'Do track';
$string['enablecustomlevelpix'] = 'Use custom level badges';
$string['hiddenfromstudents'] = 'This activity is hidden from students.';
$string['hiddenfromstudents_help'] = 'This activity is hidden from students. You can still track it, but be aware that students may not be able to complete it.';
$string['hiddenmodule'] = 'Hidden module';
$string['levela'] = 'Level {$a}';
$string['message:blockcompleted:fullmessage:completion'] = '{$a->username} just achieved 100% on the *{$a->blockname}* block in {$a->coursename}, by completing the {$a->cmname} {$a->modname}.';
$string['message:blockcompleted:fullmessage:grade'] = '{$a->username} just achieved 100% on the *{$a->blockname}* block in {$a->coursename}, by achieving max grade on the {$a->cmname} {$a->modname}.';
$string['message:blockcompleted:shortmessage'] = '{$a->username} just achieved 100% on the {$a->blockname} block in {$a->coursename}.';
$string['message:blockcompleted:title'] = '[{$a->coursename}] {$a->blockname} completed by {$a->username}';
$string['messageprovider:blockcompleted'] = 'Block completion notifications';
$string['no_blocks'] = 'No Completion Levels blocks are set up for your courses.';
$string['noactivitiestracked'] = 'No activity is currently tracked by this block. It will not be shown to students.<br>
To change this and start tracking activities, please configure this block.';
$string['nocompletion'] = 'No completion set for this activity.';
$string['nograde'] = 'No grade or grade item set for this activity.';
$string['nostudents'] = 'No students';
$string['notcompletedyet'] = 'Not completed yet';
$string['nothingtoshow'] = 'Nothing to show.';
$string['notrackableactivities'] = 'No activity is currently trackable by this block. Setup completion or grade items for activities you want to track, then configure this block.';
$string['overview'] = 'Overview';
$string['overviewofstudents'] = 'Overview of students';
$string['partiallycompleted'] = 'Partially completed ({$a})';
$string['pluginname'] = 'Completion Levels';
$string['privacy:metadata'] = 'The Completion Levels block plugin does not store any personal data.';
$string['progress'] = 'Progress';
$string['score'] = 'Score';
$string['totalweight'] = 'Total weight: {$a}';
$string['trackall'] = 'Track all';
$string['trackingmethodcompletion'] = 'Completion';
$string['trackingmethodgrades'] = 'Relative grade';
$string['type'] = 'Type';
$string['untrackall'] = 'Untrack all';
$string['validation:enterpositiveorempty'] = 'Please enter a positive value, or leave empty.';
$string['validation:providebadges0toN'] = 'Please provide badge pictures, named 0.png, 1.png... up to the maximum desired level.';
$string['viewprogress'] = 'View my progress';
$string['walloffame'] = 'Wall of Fame';
$string['walloffamea'] = 'Wall of Fame {$a}';
$string['weight'] = 'Weight';
$string['weighta'] = 'Weight: {$a}';
