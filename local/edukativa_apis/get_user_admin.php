<?php

/**
 * APIs Internas Customizadas.
 *
 * @created    11/11/24 11:00
 * @package    local_edukativa_apis
 * @copyright  Rafael Dantas
 */

require(__DIR__ . '/../../config.php');
require(__DIR__ . '/auth.php'); // Inclui o arquivo de autenticação
global $DB, $CFG;
require_once($CFG->libdir . '/moodlelib.php');

header('Content-Type: application/json');

check_bearer_token(); // Verifica se o token de autenticação é válido

$input = file_get_contents('php://input');
$data = json_decode($input, true); // Decodifica o JSON para um array associativo

// Verifica se o 'userid' foi passado via POST
if (isset($data['userid'])) {
    $userid = intval($data['userid']); // Certifique-se de que o userid seja um número inteiro para segurança

    // Verifica se o usuário é administrador
    if (is_siteadmin($userid)) {
        echo json_encode(['status' => 'success', 'isAdmin' => true, 'message' => 'O usuário é administrador.']);
    } else {
        echo json_encode(['status' => 'success', 'isAdmin' => false, 'message' => 'O usuário não é administrador.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'O userid não foi fornecido.']);
}

