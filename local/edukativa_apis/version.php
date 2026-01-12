<?php
/**
 * APIs Internas Customizadas da Edukativa.
 *

 * @created    11/11/24 11:00
 * @package    local_edukativa_apis
 * @copyright  Rafael Dantas Boeira
 * @var stdClass $plugin
 
 */

 defined('MOODLE_INTERNAL') || die();

 $plugin->component = 'local_edukativa_apis';
 $plugin->version = 2025121601; // Atualizado: Observers para sincronização automática com backend
 $plugin->requires = 2022041900;