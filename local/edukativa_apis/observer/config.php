<?php
/**
 * Configuration file for backend sync - PRODUCTION
 * 
 * DO NOT COMMIT THIS FILE TO GIT!
 * 
 * @package    local_edukativa_apis
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Backend API Configuration
$config = [
    // Backend base URL
    'backend_url' => 'https://backend.edukativa.com.br',
    
    // Authentication headers
    'x_url' => 'cognus.edukativa.com.br',
    'x_wstoken' => '0b6a37e3be5848c4b74c14a0d7fb75f3',
    'mkey' => 'cognus',
    'bearer_token' => '4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3',
    
    // Optional: Enable/disable sync
    'sync_enabled' => true,
    
    // Optional: Enable detailed logging
    'debug_mode' => true,
];

return $config;
