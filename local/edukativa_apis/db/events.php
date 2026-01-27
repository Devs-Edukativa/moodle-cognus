<?php
/**
 * Event observers registration
 *
 * @package    local_edukativa_apis
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // Enrollment events
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => '\local_edukativa_apis\observer\enrol_observer::enrolment_created',
    ],
    [
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => '\local_edukativa_apis\observer\enrol_observer::enrolment_deleted',
    ],
    
    // Course events
    [
        'eventname' => '\core\event\course_updated',
        'callback' => '\local_edukativa_apis\observer\course_observer::course_updated',
    ],
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\local_edukativa_apis\observer\course_observer::course_deleted',
    ],
    
    // User events
    [
        'eventname' => '\core\event\user_created',
        'callback' => '\local_edukativa_apis\observer\user_observer::user_created',
    ],
    [
        'eventname' => '\core\event\user_updated',
        'callback' => '\local_edukativa_apis\observer\user_observer::user_updated',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\local_edukativa_apis\observer\user_observer::user_deleted',
    ],
    
    // Completion events
    [
        'eventname' => '\core\event\course_completed',
        'callback' => '\local_edukativa_apis\observer\completion_observer::course_completed',
    ],
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => '\local_edukativa_apis\observer\completion_observer::module_completed',
    ],
    
    // Certificate events (simplecertificate plugin)
    [
        'eventname' => '\mod_simplecertificate\event\certificate_issued',
        'callback' => '\local_edukativa_apis\observer\certificate_observer::certificate_issued',
    ],
    
    // Badge events (core badges)
    [
        'eventname' => '\core\event\badge_awarded',
        'callback' => '\local_edukativa_apis\observer\badge_observer::badge_awarded',
    ],
    [
        'eventname' => '\core\event\badge_revoked',
        'callback' => '\local_edukativa_apis\observer\badge_observer::badge_revoked',
    ],
    [
        'eventname' => '\core\event\badge_disabled',
        'callback' => '\local_edukativa_apis\observer\badge_observer::badge_disabled',
    ],
];
