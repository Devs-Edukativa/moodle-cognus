<?php
/**
 * Plugin installation observer
 *
 * @package    local_edukativa_apis
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edukativa_apis\observer;

defined('MOODLE_INTERNAL') || die();

/**
 * Observer for plugin installation and updates
 */
class plugin_observer extends base_observer {
    
    /**
     * Handle plugin installed event
     *
     * @param \core\event\base $event The event object
     */
    public static function plugin_installed($event) {
        global $CFG;
        
        try {
            $config = self::get_config();
            
            if (!$config['enabled'] || !isset($config['git_auto_push'])) {
                return;
            }
            
            $data = $event->get_data();
            $pluginname = isset($data['other']['name']) ? $data['other']['name'] : 'unknown';
            $plugintype = isset($data['other']['type']) ? $data['other']['type'] : 'unknown';
            
            mtrace("[local_edukativa_apis] Plugin installed: {$plugintype}/{$pluginname}");
            
            // Executar git auto-commit e push
            if ($config['git_auto_push']['enabled']) {
                self::git_commit_and_push($plugintype, $pluginname, 'installed', $config['git_auto_push']);
            }
            
        } catch (\Exception $e) {
            debugging('[local_edukativa_apis] Error in plugin_installed observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    
    /**
     * Handle plugin updated event
     *
     * @param \core\event\base $event The event object
     */
    public static function plugin_updated($event) {
        global $CFG;
        
        try {
            $config = self::get_config();
            
            if (!$config['enabled'] || !isset($config['git_auto_push'])) {
                return;
            }
            
            $data = $event->get_data();
            $pluginname = isset($data['other']['name']) ? $data['other']['name'] : 'unknown';
            $plugintype = isset($data['other']['type']) ? $data['other']['type'] : 'unknown';
            
            mtrace("[local_edukativa_apis] Plugin updated: {$plugintype}/{$pluginname}");
            
            // Executar git auto-commit e push
            if ($config['git_auto_push']['enabled']) {
                self::git_commit_and_push($plugintype, $pluginname, 'updated', $config['git_auto_push']);
            }
            
        } catch (\Exception $e) {
            debugging('[local_edukativa_apis] Error in plugin_updated observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    
    /**
     * Commit and push changes to git repository
     *
     * @param string $plugintype Type of plugin
     * @param string $pluginname Name of plugin
     * @param string $action Action performed (installed/updated)
     * @param array $gitconfig Git configuration
     */
    private static function git_commit_and_push($plugintype, $pluginname, $action, $gitconfig) {
        global $CFG;
        
        $moodledir = $CFG->dirroot;
        
        // Verificar se está em um repositório git
        if (!is_dir($moodledir . '/.git')) {
            mtrace("[local_edukativa_apis] Not a git repository, skipping auto-commit");
            return;
        }
        
        $commitMessage = sprintf(
            "[auto-commit] Plugin %s: %s/%s",
            $action,
            $plugintype,
            $pluginname
        );
        
        // Configurar remote com token se fornecido (para repos privados)
        $setupRemoteCmd = '';
        if (!empty($gitconfig['github_token']) && !empty($gitconfig['github_repo_url'])) {
            $token = $gitconfig['github_token'];
            $repoUrl = $gitconfig['github_repo_url'];
            $authenticatedUrl = "https://{$token}@{$repoUrl}";
            
            // Temporariamente configurar o remote com token
            $setupRemoteCmd = "git remote set-url origin {$authenticatedUrl}";
            mtrace("[local_edukativa_apis] Using GitHub token for authentication");
        }
        
        // Executar comandos git
        $commands = [
            "cd {$moodledir}",
            "git config user.name '{$gitconfig['user_name']}'",
            "git config user.email '{$gitconfig['user_email']}'",
        ];
        
        // Adicionar configuração de remote se necessário
        if ($setupRemoteCmd) {
            $commands[] = $setupRemoteCmd;
        }
        
        $commands[] = "git add .";
        $commands[] = "git commit -m \"{$commitMessage}\" || true";
        
        // Adicionar push se configurado
        if ($gitconfig['auto_push']) {
            $branch = $gitconfig['branch'] ?? 'main';
            $commands[] = "git push origin {$branch}";
        }
        
        $command = implode(' && ', $commands);
        
        mtrace("[local_edukativa_apis] Executing git commands...");
        exec($command . ' 2>&1', $output, $returnCode);
        
        foreach ($output as $line) {
            mtrace("[git] {$line}");
        }
        
        if ($returnCode === 0) {
            mtrace("[local_edukativa_apis] Git commit/push successful");
            
            // Disparar webhook do Jenkins se configurado
            if (isset($gitconfig['jenkins_webhook']) && !empty($gitconfig['jenkins_webhook'])) {
                self::trigger_jenkins_build($gitconfig['jenkins_webhook']);
            }
        } else {
            mtrace("[local_edukativa_apis] Git command failed with code: {$returnCode}");
        }
    }
    
    /**
     * Trigger Jenkins build via webhook
     *
     * @param string $webhookUrl Jenkins webhook URL
     */
    private static function trigger_jenkins_build($webhookUrl) {
        try {
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                mtrace("[local_edukativa_apis] Jenkins build triggered successfully");
            } else {
                mtrace("[local_edukativa_apis] Jenkins build trigger failed with HTTP code: {$httpCode}");
            }
            
        } catch (\Exception $e) {
            mtrace("[local_edukativa_apis] Failed to trigger Jenkins: " . $e->getMessage());
        }
    }
}
