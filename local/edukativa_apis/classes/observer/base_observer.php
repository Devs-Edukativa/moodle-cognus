<?php
/**
 * Base observer with common HTTP functionality
 *
 * @package    local_edukativa_apis
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edukativa_apis\observer;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for all observers
 */
abstract class base_observer {
    
    /** @var array Configuration loaded from config.php */
    private static $config = null;
    
    /**
     * Load configuration from config.php
     *
     * @return array Configuration array
     * @throws \Exception if config file doesn't exist
     */
    private static function load_config() {
        if (self::$config !== null) {
            return self::$config;
        }
        
        global $CFG;
        
        // Try to load config.php from observer directory
        $configfile = dirname(__DIR__, 2) . '/observer/config.php';
        
        if (!file_exists($configfile)) {
            // Fallback: try to find it in plugin root
            $configfile = dirname(__DIR__, 2) . '/config.php';
        }
        
        if (!file_exists($configfile)) {
            debugging(
                '[local_edukativa_apis] Configuration file not found! ' .
                'Please copy observer/config.example.php to observer/config.php and configure it.',
                DEBUG_DEVELOPER
            );
            
            // Return default disabled config
            return [
                'sync_enabled' => false,
                'backend_url' => '',
                'x_url' => '',
                'x_wstoken' => '',
                'mkey' => '',
                'bearer_token' => '',
                'debug_mode' => false,
            ];
        }
        
        self::$config = require($configfile);
        
        return self::$config;
    }
    
    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    protected static function get_config($key, $default = null) {
        $config = self::load_config();
        return isset($config[$key]) ? $config[$key] : $default;
    }
    
    /**
     * Check if sync is enabled
     *
     * @return bool
     */
    protected static function is_sync_enabled() {
        return self::get_config('sync_enabled', false);
    }
    
    /**
     * Send HTTP request to backend
     *
     * @param string $endpoint API endpoint (e.g., '/enrolls', '/users')
     * @param string $method HTTP method (POST, DELETE, PUT)
     * @param array $data Data to send
     * @return array Response data with success status
     */
    protected static function send_request($endpoint, $method, $data) {
        // Check if sync is enabled
        if (!self::is_sync_enabled()) {
            debugging('[local_edukativa_apis] Sync is disabled in config', DEBUG_DEVELOPER);
            return [
                'success' => false,
                'http_code' => 0,
                'response' => '',
                'error' => 'Sync disabled in configuration',
                'errno' => 0,
            ];
        }
        
        // Build full URL
        $url = self::get_config('backend_url') . $endpoint;
        
        // Prepare headers
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-url: ' . self::get_config('x_url'),
            'x-wstoken: ' . self::get_config('x_wstoken'),
            'mkey: ' . self::get_config('mkey'),
            'Authorization: Bearer ' . self::get_config('bearer_token'),
        ];
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set method and body
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            default:
                curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        
        // SSL - permite certificados auto-assinados (apenas para desenvolvimento)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Execute request
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        
        // Log the request for debugging
        self::log_request($endpoint, $method, $data, $httpcode, $response, $error);
        
        return [
            'success' => ($errno === 0 && ($httpcode >= 200 && $httpcode < 300)),
            'http_code' => $httpcode,
            'response' => $response,
            'error' => $error,
            'errno' => $errno,
        ];
    }
    
    /**
     * L// Check if debug mode is enabled
        if (!self::get_config('debug_mode', false)) {
            return; // Skip logging if debug is disabled
        }
        
        og request details for debugging
     *
     * @param string $endpoint
     * @param string $method
     * @param array $data
     * @param int $httpcode
     * @param string $response
     * @param string $error
     */
    protected static function log_request($endpoint, $method, $data, $httpcode, $response, $error) {
        global $CFG;
        
        $logmessage = sprintf(
            "[local_edukativa_apis] %s %s | HTTP %d | Data: %s | Response: %s | Error: %s\n",
            $method,
            $endpoint,
            $httpcode,
            json_encode($data),
            substr($response, 0, 200), // Limita tamanho do log
            $error ?: 'none'
        );
        
        // Log to Moodle debugging
        debugging($logmessage, DEBUG_DEVELOPER);
        
        // Optionally log to file
        if (isset($CFG->dataroot)) {
            $logfile = $CFG->dataroot . '/local_edukativa_apis.log';
            @file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $logmessage, FILE_APPEND);
        }
    }
    
    /**
     * Handle errors gracefully
     *
     * @param \core\event\base $event
     * @param string $context
     * @param \Exception $exception
     */
    protected static function handle_error($event, $context, $exception) {
        debugging(
            sprintf(
                "[local_edukativa_apis] Error in %s for event %s: %s",
                $context,
                $event->eventname,
                $exception->getMessage()
            ),
            DEBUG_DEVELOPER
        );
    }
}
