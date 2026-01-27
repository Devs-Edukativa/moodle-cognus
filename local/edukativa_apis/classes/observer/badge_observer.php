<?php
/**
 * Badge event observers
 *
 * @package    local_edukativa_apis
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edukativa_apis\observer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_observer.php');

/**
 * Observer for badge events from Moodle core badges
 */
class badge_observer extends base_observer {
    
    /**
     * Handle badge awarded event
     *
     * @param \core\event\badge_awarded $event
     */
    public static function badge_awarded(\core\event\badge_awarded $event) {
        try {
            // Get event data
            $eventdata = $event->get_data();
            $other = isset($eventdata['other']) ? $eventdata['other'] : [];
            
            // Extract badge data
            $userid = $event->relateduserid;
            $badgeid = $eventdata['objectid'];
            $badgehash = isset($other['badgehash']) ? $other['badgehash'] : null;
            
            // Get additional badge data if available
            $dateissued = isset($other['dateissued']) ? $other['dateissued'] : time();
            $dateexpire = isset($other['dateexpire']) ? $other['dateexpire'] : null;
            
            // Prepare data for backend
            $data = [
                'userid' => (string) $userid,
                'badgeid' => (string) $badgeid,
                'action' => 'awarded',
            ];
            
            if ($badgehash) {
                $data['badgehash'] = $badgehash;
            }
            
            if ($dateissued) {
                $data['dateissued'] = (int) $dateissued;
            }
            
            if ($dateexpire) {
                $data['dateexpire'] = (int) $dateexpire;
            }
            
            // Send to backend
            $result = self::send_request('/badges/sync', 'POST', $data);
            
            if ($result['success']) {
                debugging(
                    '[badge_observer] Badge awarded sync: user ' . $userid . 
                    ', badge ' . $badgeid,
                    DEBUG_DEVELOPER
                );
            } else {
                debugging(
                    '[badge_observer] Failed to sync badge awarded for user ' . $userid . 
                    ', badge ' . $badgeid .
                    ' | HTTP ' . $result['http_code'] . 
                    ' | Error: ' . $result['error'],
                    DEBUG_NORMAL
                );
            }
            
        } catch (\Exception $e) {
            self::handle_error($event, 'badge_awarded', $e);
        }
    }
    
    /**
     * Handle badge revoked event
     *
     * @param \core\event\badge_revoked $event
     */
    public static function badge_revoked(\core\event\badge_revoked $event) {
        try {
            // Get event data
            $eventdata = $event->get_data();
            $other = isset($eventdata['other']) ? $eventdata['other'] : [];
            
            // Extract badge data
            $userid = $event->relateduserid;
            $badgeid = $eventdata['objectid'];
            $badgehash = isset($other['badgehash']) ? $other['badgehash'] : null;
            
            // Prepare data for backend
            $data = [
                'userid' => (string) $userid,
                'badgeid' => (string) $badgeid,
                'action' => 'revoked',
            ];
            
            if ($badgehash) {
                $data['badgehash'] = $badgehash;
            }
            
            // Send to backend
            $result = self::send_request('/badges/sync', 'POST', $data);
            
            if ($result['success']) {
                debugging(
                    '[badge_observer] Badge revoked sync: user ' . $userid . 
                    ', badge ' . $badgeid,
                    DEBUG_DEVELOPER
                );
            } else {
                debugging(
                    '[badge_observer] Failed to sync badge revoked for user ' . $userid . 
                    ', badge ' . $badgeid .
                    ' | HTTP ' . $result['http_code'] . 
                    ' | Error: ' . $result['error'],
                    DEBUG_NORMAL
                );
            }
            
        } catch (\Exception $e) {
            self::handle_error($event, 'badge_revoked', $e);
        }
    }
    
    /**
     * Handle badge disabled event
     *
     * @param \core\event\badge_disabled $event
     */
    public static function badge_disabled(\core\event\badge_disabled $event) {
        try {
            // Get event data
            $eventdata = $event->get_data();
            
            // Extract badge data
            $badgeid = $eventdata['objectid'];
            
            // For disabled events, we notify about the badge but without specific user
            // Backend will handle disabling the badge for all users
            $data = [
                'userid' => '0', // System action
                'badgeid' => (string) $badgeid,
                'action' => 'disabled',
            ];
            
            // Send to backend
            $result = self::send_request('/badges/sync', 'POST', $data);
            
            if ($result['success']) {
                debugging(
                    '[badge_observer] Badge disabled sync: badge ' . $badgeid,
                    DEBUG_DEVELOPER
                );
            } else {
                debugging(
                    '[badge_observer] Failed to sync badge disabled for badge ' . $badgeid .
                    ' | HTTP ' . $result['http_code'] . 
                    ' | Error: ' . $result['error'],
                    DEBUG_NORMAL
                );
            }
            
        } catch (\Exception $e) {
            self::handle_error($event, 'badge_disabled', $e);
        }
    }
}
