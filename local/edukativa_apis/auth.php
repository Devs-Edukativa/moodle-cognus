<?php

function check_bearer_token() {
    $valid_token = '4izxmIfKebEmkOzASGgODS1imHbgziDvcZB6hpfYApvsTXXJ5JoASMA1vMmvPjf3';

    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['status' => 'error', 'message' => 'Autenticação necessária.']);
        exit;
    } else {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        list($type, $token) = explode(' ', $auth_header, 2);

        if ($type !== 'Bearer' || $token !== $valid_token) {
            header('HTTP/1.0 401 Unauthorized');
            echo json_encode(['status' => 'error', 'message' => 'Credenciais inválidas.']);
            exit;
        }
    }
}