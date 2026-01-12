<?php
/**
 * Script para testar requisições REST
 * 
 * Recebe JSON com:
 * - url: URL de destino
 * - method: Método HTTP (GET, POST, PUT, DELETE, PATCH)
 * - headers: Array de headers (opcional)
 * - body: Corpo da requisição (opcional)
 *
 * @package    local_edukativa_apis
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define content type como JSON
header('Content-Type: application/json; charset=utf-8');

// Permite CORS para testes
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responde OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Função para enviar resposta JSON
 */
function send_response($success, $data = null, $error = null, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Função para realizar requisição cURL
 */
function make_curl_request($url, $method, $headers = [], $body = null) {
    $ch = curl_init();
    
    // Configurações básicas
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    // Captura headers da resposta
    $response_headers = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$response_headers) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) {
            return $len;
        }
        $response_headers[strtolower(trim($header[0]))] = trim($header[1]);
        return $len;
    });
    
    // Configura método HTTP
    $method = strtoupper($method);
    switch ($method) {
        case 'GET':
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            break;
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            break;
        case 'PATCH':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            break;
        default:
            return [
                'error' => 'Método HTTP inválido: ' . $method,
                'error_code' => 'INVALID_METHOD'
            ];
    }
    
    // Configura headers
    if (!empty($headers) && is_array($headers)) {
        $header_array = [];
        foreach ($headers as $key => $value) {
            $header_array[] = $key . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header_array);
    }
    
    // SSL - permite certificados auto-assinados (apenas para desenvolvimento)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Executa requisição
    $response_body = curl_exec($ch);
    
    // Captura informações da requisição
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    curl_close($ch);
    
    // Verifica erros de cURL
    if ($curl_errno !== 0) {
        return [
            'error' => 'Erro cURL: ' . $curl_error,
            'error_code' => 'CURL_ERROR_' . $curl_errno,
            'curl_errno' => $curl_errno
        ];
    }
    
    // Tenta decodificar JSON se o content-type for application/json
    $decoded_response = null;
    if (strpos($content_type, 'application/json') !== false) {
        $decoded_response = json_decode($response_body, true);
    }
    
    // Retorna resposta completa
    return [
        'http_code' => $http_code,
        'response_body' => $response_body,
        'response_body_decoded' => $decoded_response,
        'response_headers' => $response_headers,
        'content_type' => $content_type,
        'total_time' => round($total_time, 3),
        'request_info' => [
            'url' => $url,
            'method' => $method,
            'headers_sent' => $headers,
            'body_sent' => $body
        ]
    ];
}

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(false, null, 'Método não permitido. Use POST.', 405);
}

// Lê input JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Valida JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    send_response(false, null, 'JSON inválido: ' . json_last_error_msg(), 400);
}

// Valida campos obrigatórios
if (empty($data['url'])) {
    send_response(false, null, 'Campo "url" é obrigatório', 400);
}

if (empty($data['method'])) {
    send_response(false, null, 'Campo "method" é obrigatório', 400);
}

// Prepara dados
$url = trim($data['url']);
$method = strtoupper(trim($data['method']));
$headers = isset($data['headers']) && is_array($data['headers']) ? $data['headers'] : [];
$body = isset($data['body']) ? $data['body'] : null;

// Se body for array ou objeto, converte para JSON
if (is_array($body) || is_object($body)) {
    $body = json_encode($body);
    // Adiciona Content-Type se não existir
    if (!isset($headers['Content-Type'])) {
        $headers['Content-Type'] = 'application/json';
    }
}

// Valida URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    send_response(false, null, 'URL inválida', 400);
}

// Realiza requisição
$result = make_curl_request($url, $method, $headers, $body);

// Verifica se houve erro
if (isset($result['error'])) {
    send_response(false, null, $result, 500);
}

// Retorna sucesso
send_response(true, $result, null, 200);
