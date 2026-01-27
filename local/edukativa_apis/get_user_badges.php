<?php
/**
 * Endpoint to get all badges for a specific user from Moodle
 * 
 * This endpoint retrieves all badges (awarded and available) for a given user
 * Used by the Sistema de Gestão Acadêmica to sync user badges
 * 
 * @package    local_edukativa_apis
 * @copyright  2026 Edukativa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

// Require login
require_login();

// Get user ID from request
$userid = required_param('userid', PARAM_INT);

// Check if user has permission to view badges (must be admin or self)
$context = context_system::instance();
if (!has_capability('moodle/badges:viewbadges', $context) && $USER->id != $userid) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode([
        'error' => 'Permission denied',
        'message' => 'You do not have permission to view badges for this user'
    ]);
    exit;
}

try {
    global $DB, $CFG;
    
    require_once($CFG->dirroot . '/badges/lib.php');
    
    $result = [
        'userid' => $userid,
        'badges' => []
    ];
    
    // Get all active badges (site and course badges)
    $sql = "SELECT b.* 
            FROM {badge} b 
            WHERE b.status = :active";
    
    $badges = $DB->get_records_sql($sql, ['active' => BADGE_STATUS_ACTIVE]);
    
    foreach ($badges as $badge) {
        $badge_data = [
            'id' => $badge->id,
            'name' => $badge->name,
            'description' => $badge->description,
            'timecreated' => $badge->timecreated,
            'timemodified' => $badge->timemodified,
            'usercreated' => $badge->usercreated,
            'usermodified' => $badge->usermodified,
            'issuername' => $badge->issuername,
            'issuerurl' => $badge->issuerurl,
            'issuercontact' => $badge->issuercontact,
            'expiredate' => $badge->expiredate,
            'expireperiod' => $badge->expireperiod,
            'type' => $badge->type,
            'status' => $badge->status,
            'version' => $badge->version ?? null,
            'language' => $badge->language ?? null,
            'imageauthorname' => $badge->imageauthorname ?? null,
            'imageauthoremail' => $badge->imageauthoremail ?? null,
            'imageauthorurl' => $badge->imageauthorurl ?? null,
            'imagecaption' => $badge->imagecaption ?? null,
        ];
        
        // Get badge image URL
        $context = context_system::instance();
        if ($badge->type == BADGE_TYPE_COURSE) {
            $context = context_course::instance($badge->courseid);
        }
        
        $badge_obj = new badge($badge->id);
        $badge_data['imageUrl'] = moodle_url_make_pluginfile_url(
            $context->id,
            'badges',
            'badgeimage',
            $badge->id,
            '/',
            'f1',
            false
        )->out(false);
        
        // Check if user has been awarded this badge
        $issued = $DB->get_record('badge_issued', [
            'badgeid' => $badge->id,
            'userid' => $userid
        ]);
        
        if ($issued) {
            $badge_data['awarded'] = true;
            $badge_data['dateissued'] = $issued->dateissued;
            $badge_data['dateexpire'] = $issued->dateexpire;
            $badge_data['uniquehash'] = $issued->uniquehash;
            $badge_data['visible'] = $issued->visible;
        } else {
            $badge_data['awarded'] = false;
        }
        
        $result['badges'][] = $badge_data;
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
