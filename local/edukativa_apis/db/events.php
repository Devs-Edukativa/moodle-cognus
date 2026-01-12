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
    
    // Plugin installation/update events
    [
        'eventname' => '\core\event\plugin_installed',
        'callback' => '\local_edukativa_apis\observer\plugin_observer::plugin_installed',
    ],
    [
        'eventname' => '\core\event\plugin_updated',
        'callback' => '\local_edukativa_apis\observer\plugin_observer::plugin_updated',
    ],
];
