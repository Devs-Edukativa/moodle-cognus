<?php
/**
 * Enrollment event observers
 *
 * @package    local_edukativa_apis
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edukativa_apis\observer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_observer.php');

/**
 * Observer for enrollment events
 */
class enrol_observer extends base_observer {
    
    /**
     * Handle user enrolment created event
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function enrolment_created(\core\event\user_enrolment_created $event) {
        try {
            $data = [
                'userid' => $event->relateduserid,
                'courseid' => $event->courseid,
                'crud' => 'c',
            ];
            
            self::send_request('/enrolls', 'POST', $data);
            
        } catch (\Exception $e) {
            self::handle_error($event, 'enrolment_created', $e);
        }
    }
    
    /**
     * Handle user enrolment deleted event
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        try {
            $data = [
                'userid' => $event->relateduserid,
                'courseid' => $event->courseid,
                'crud' => 'd',
            ];
            
            self::send_request('/enrolls', 'DELETE', $data);
            
        } catch (\Exception $e) {
            self::handle_error($event, 'enrolment_deleted', $e);
        }
    }
}
