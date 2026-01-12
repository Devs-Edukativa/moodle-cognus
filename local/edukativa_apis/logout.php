<?php

/**
 * Logout endpoint - Encerra a sessão do usuário no Moodle.
 *
 * Este endpoint permite que sistemas externos (como o SGA) encerrem
 * a sessão do usuário no Moodle e redirecionem para uma URL externa.
 *
 * Uso: GET /local/edukativa_apis/logout.php?redirect=https://painel.agricultura.gov.br
 *
 * @created    05/12/24
 * @package    local_edukativa_apis
 * @copyright  Rafael Dantas
 */

require(__DIR__ . '/../../config.php');

// Obtém a URL de redirecionamento (opcional)
$redirect = optional_param('redirect', '', PARAM_URL);

// Se não foi fornecida URL de redirecionamento, usa a URL padrão
if (empty($redirect)) {
    // Tenta obter do header Referer ou usa a página inicial do Moodle
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $CFG->wwwroot;
}

// Verifica se o usuário está logado antes de tentar fazer logout
if (isloggedin() && !isguestuser()) {
    // Executa o logout do Moodle
    require_logout();
}

// Redireciona para a URL especificada
redirect($redirect);
