<?php
function check_bearer_token() {
    // Carregar configuração
    $config = load_auth_config();
    $valid_token = $config['bearer_token'] ?? null;

    if (!$valid_token) {
        header('HTTP/1.0 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'Token de autenticação não configurado.']);
        exit;
    }

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

function load_auth_config() {
    // Tentar carregar do arquivo de configuração
    $configfile = __DIR__ . '/observer/config.php';
    
    if (!file_exists($configfile)) {
        // Fallback para config.php na raiz do plugin
        $configfile = __DIR__ . '/config.php';
    }
    
    if (file_exists($configfile)) {
        $config = include($configfile);
        return is_array($config) ? $config : [];
    }
    
    // Retornar array vazio se não encontrar config
    return [];
}