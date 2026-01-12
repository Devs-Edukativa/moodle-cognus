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

// Captura o conteúdo JSON enviado na requisição
$input = file_get_contents('php://input');
$data = json_decode($input, true); // Decodifica o JSON para um array associativo

// Verifica se os campos 'username' e 'password' foram fornecidos
if (isset($data['username']) && isset($data['password'])) {
  $username = $data['username'];
  $password = $data['password'];

  $user = authenticate_user_login($username, $password);

  if ($user) {
    // Completa o login do usuário, atualizando o registro de último acesso.
    complete_user_login($user);

    // O usuário foi autenticado com sucesso.
    $fullname = fullname($user);
    $email = $user->email;
    $response = array('userid' => $user->id, 'fullname' => $fullname, 'email' => $email);

    echo json_encode($response);
  } else {
    // Falha na autenticação.
    echo json_encode(array('error' => 'Usuário ou senha inválidos'));
  }
} else {
  // Parâmetros inválidos.
  echo json_encode(array('error' => 'Parâmetros inválidos'));
}


