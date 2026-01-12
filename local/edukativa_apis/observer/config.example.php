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
    
    // Bearer token para autenticação das APIs REST (auth.php, get_*.php, etc.)
    // Este token é usado para validar requisições externas aos endpoints do plugin
    // Exemplo: Authorization: Bearer seu_token_aqui
    'bearer_token' => 'your_jwt_bearer_token_here',
    
    // Optional: Enable/disable sync
    'sync_enabled' => true,
    
    // Optional: Enable detailed logging
    'debug_mode' => false,
    
    // Git Auto-Push Configuration (for plugin installations)
    'git_auto_push' => [
        'enabled' => false,  // Set to true to enable auto-commit on plugin install/update
        'auto_push' => false, // Set to true to auto-push to GitHub
        'branch' => 'main',  // Branch to push to
        'user_name' => 'Moodle Auto-Commit',
        'user_email' => 'moodle@your-domain.com',
        
        // GitHub Configuration (for private repositories)
        'github_token' => 'your_github_personal_access_token_here', // GitHub Personal Access Token (classic) with repo scope
        'github_repo_url' => 'github.com/usuario/moodle-cognus.git', // Repository URL without https://
        
        // Optional: Jenkins webhook to trigger build after push
        'jenkins_webhook' => '', // e.g., 'http://jenkins.your-domain.com/generic-webhook-trigger/invoke?token=YOUR_TOKEN'
    ],
];

return $config;
