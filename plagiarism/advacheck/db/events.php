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
 *
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_submitted', // Submitting an essay in a quiz for verification.
        'callback' => '\plagiarism_advacheck\eventobservers::quiz_essay_submit',
    ],
    [
        'eventname' => '\mod_workshop\event\assessable_uploaded', // Sending a response from the workshop for verification.
        'callback' => '\plagiarism_advacheck\eventobservers::workshop_save_answer',
    ],
    [
        'eventname' => '\mod_forum\event\assessable_uploaded', // Sending a response from the forum for review.
        'callback' => '\plagiarism_advacheck\eventobservers::forum_post_upload',
    ],

    [
        'eventname' => '\assignsubmission_file\event\assessable_uploaded', // Uploading a file in the assign submittion.
        'callback' => '\plagiarism_advacheck\eventobservers::assign_file_upload',
    ],
    [
        'eventname' => '\assignsubmission_onlinetext\event\assessable_uploaded', // Loading a text answer in the assign submittion.
        'callback' => '\plagiarism_advacheck\eventobservers::assign_text_save',
    ],
    [
        'eventname' => '\mod_assign\event\assessable_submitted', // After confirmation of sending for verification.
        'callback' => '\plagiarism_advacheck\eventobservers::assign_submission_submit',
    ],
    [
        'eventname' => '\mod_assign\event\submission_locked', // The answer is blocked by the teacher.
        'callback' => '\plagiarism_advacheck\eventobservers::assign_submission_locked',
    ],
    [
        'eventname' => '\mod_assign\event\submission_unlocked', // The answer is unlocked by the teacher.
        'callback' => '\plagiarism_advacheck\eventobservers::assign_submission_unlocked',
    ],
];
