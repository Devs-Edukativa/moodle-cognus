<?php
/**
 * User event observers
 *
 * @package    local_edukativa_apis
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edukativa_apis\observer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_observer.php');

/**
 * Observer for user events
 */
class user_observer extends base_observer {
    
    /**
     * Handle user created event
     *
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event) {
        try {
            $data = [
                'userid' => $event->objectid,
                'crud' => 'c',
            ];
            
            self::send_request('/users', 'POST', $data);
            
        } catch (\Exception $e) {
            self::handle_error($event, 'user_created', $e);
        }
    }
    
    /**
     * Handle user updated event
     *
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event) {
        try {
            $data = [
                'userid' => $event->objectid,
                'crud' => 'u', // 'u' for update
            ];
            
            self::send_request('/users', 'POST', $data);
            
        } catch (\Exception $e) {
            self::handle_error($event, 'user_updated', $e);
        }
    }
    
    /**
     * Handle user deleted event
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        try {
            $data = [
                'userid' => $event->objectid,
                'crud' => 'd',
            ];
            
            self::send_request('/users', 'DELETE', $data);
            
        } catch (\Exception $e) {
            self::handle_error($event, 'user_deleted', $e);
        }
    }
}
