<?php
/**
 * Configuration file for backend sync - EXAMPLE
 * 
 * Copy this file to config.php and fill with your real values
 * 
 * @package    local_edukativa_apis
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Backend API Configuration
$config = [
    // Backend base URL
    'backend_url' => 'https://your-backend-url.com',
    
    // Authentication headers
    'x_url' => 'your-moodle-url.com/moodle',
    'x_wstoken' => 'your_webservice_token_here',
    'mkey' => 'your_mkey_here',
    'bearer_token' => 'your_jwt_bearer_token_here',
    
    // Optional: Enable/disable sync
    'sync_enabled' => true,
    
    // Optional: Enable detailed logging
    'debug_mode' => false,
];

return $config;
