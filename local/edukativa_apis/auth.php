<?php
function check_bearer_token() {
    $valid_token = '4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3';

    // Tenta diferentes formas de obter o token
    $token = null;
    $type = null;

    // Método 1: HTTP_AUTHORIZATION (padrão)
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        list($type, $token) = explode(' ', $_SERVER['HTTP_AUTHORIZATION'], 2);
    }
    // Método 2: REDIRECT_HTTP_AUTHORIZATION (alguns servidores usam isso)
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        list($type, $token) = explode(' ', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 2);
    }
    // Método 3: Authorization via getallheaders() (mais confiável)
    elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            list($type, $token) = explode(' ', $headers['Authorization'], 2);
        }
    }

    // Se não conseguiu obter o token de nenhuma forma
    if (!$token) {
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['status' => 'error', 'message' => 'Autenticação necessária.']);
        exit;
    }

    // Valida o token
    if ($type !== 'Bearer' || $token !== $valid_token) {
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['status' => 'error', 'message' => 'Credenciais inválidas.']);
        exit;
    }
}