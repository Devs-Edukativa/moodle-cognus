<?php
/**
 * Course event observers
 *
 * @package    local_edukativa_apis
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edukativa_apis\observer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_observer.php');

/**
 * Observer for course events
 */
class course_observer extends base_observer {
    
    /**
     * Handle course updated event (created or updated)
     *
     * @param \core\event\course_updated $event
     */
    public static function course_updated(\core\event\course_updated $event) {
        try {
            $data = [
                'cursoid' => $event->courseid,
                'crud' => 'c', // 'c' for create/update
            ];
            
            self::send_request('/courses', 'POST', $data);
            
        } catch (\Exception $e) {
            self::handle_error($event, 'course_updated', $e);
        }
    }
    
    /**
     * Handle course deleted event
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        try {
            $data = [
                'cursoid' => $event->objectid,
                'crud' => 'd',
            ];
            
            self::send_request('/courses', 'DELETE', $data);
            
        } catch (\Exception $e) {
            self::handle_error($event, 'course_deleted', $e);
        }
    }
}
