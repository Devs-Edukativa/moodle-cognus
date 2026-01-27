<?php
/**
 * Certificate event observers
 *
 * @package    local_edukativa_apis
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edukativa_apis\observer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_observer.php');

/**
 * Observer for certificate events from simplecertificate plugin
 */
class certificate_observer extends base_observer {
    
    /**
     * Handle certificate issued event from simplecertificate
     *
     * @param \mod_simplecertificate\event\certificate_issued $event
     */
    public static function certificate_issued(\mod_simplecertificate\event\certificate_issued $event) {
        try {
            // Get event data
            $eventdata = $event->get_data();
            $other = $eventdata['other'];
            $userid = $event->relateduserid;
            $courseid = $other['courseid'];
            
            // Prepare minimal data for backend
            // Backend will fetch remaining certificate data from Moodle
            $data = [
                'userid' => (string) $userid,
                'courseid' => (string) $courseid,
            ];
            
            // Send to backend
            $result = self::send_request('/certificates', 'POST', $data);
            
            if ($result['success']) {
                debugging(
                    '[certificate_observer] Certificate sync requested for user ' . $userid . 
                    ' in course ' . $courseid,
                    DEBUG_DEVELOPER
                );
            } else {
                debugging(
                    '[certificate_observer] Failed to sync certificate for user ' . $userid . 
                    ' | HTTP ' . $result['http_code'] . 
                    ' | Error: ' . $result['error'],
                    DEBUG_NORMAL
                );
            }
            
        } catch (\Exception $e) {
            self::handle_error($event, 'certificate_issued', $e);
        }
    }
}
