<?php
/**
 * Completion event observers
 *
 * @package    local_edukativa_apis
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edukativa_apis\observer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_observer.php');

/**
 * Observer for completion events
 */
class completion_observer extends base_observer {
    
    /**
     * Handle course completed event
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event) {
        try {
            $data = [
                'userid' => $event->relateduserid,
                'courseid' => $event->courseid,
                'crud' => 'c',
            ];
            
            self::send_request('/enrolls/completed', 'POST', $data);
            
        } catch (\Exception $e) {
            self::handle_error($event, 'course_completed', $e);
        }
    }
    
    /**
     * Handle course module completion updated event
     * Triggered when a user completes an activity/module
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function module_completed(\core\event\course_module_completion_updated $event) {
        try {
            // Verifica se a conclusão é completa (não apenas em progresso)
            $completionstate = $event->other['completionstate'];
            
            // COMPLETIONSTATE_COMPLETE = 1, COMPLETIONSTATE_COMPLETE_PASS = 2
            if ($completionstate == COMPLETION_COMPLETE || $completionstate == COMPLETION_COMPLETE_PASS) {
                $data = [
                    'userid' => $event->relateduserid,
                    'courseid' => $event->courseid,
                    'cmid' => $event->contextinstanceid, // Course module ID
                    'crud' => 'c',
                ];
                
                self::send_request('/enrolls', 'POST', $data);
            }
            
        } catch (\Exception $e) {
            self::handle_error($event, 'module_completed', $e);
        }
    }
}
