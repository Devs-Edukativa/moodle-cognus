<?php

/**
 * APIs Internas Customizadas.
 *
 * Verifica se o usuário possui permissões administrativas no Moodle.
 * Considera como "admin" usuários que são:
 * - Administradores do site (is_siteadmin)
 * - Gerentes do sistema (role manager no contexto do sistema)
 *
 * @created    11/11/24 11:00
 * @updated    05/12/24 - Adicionado suporte para role manager
 * @package    local_edukativa_apis
 * @copyright  Rafael Dantas
 */

require(__DIR__ . '/../../config.php');
require(__DIR__ . '/auth.php'); // Inclui o arquivo de autenticação
global $DB, $CFG;
require_once($CFG->libdir . '/moodlelib.php');

header('Content-Type: application/json');

check_bearer_token(); // Verifica se o token de autenticação é válido

/**
 * Verifica se o usuário possui a role de manager no contexto do sistema.
 *
 * A verificação é feita consultando a tabela role_assignments para verificar
 * se o usuário tem a role "manager" atribuída no contexto do sistema (context_system).
 *
 * @param int $userid ID do usuário a ser verificado
 * @return bool true se o usuário é manager, false caso contrário
 */
function is_manager($userid) {
    global $DB;

    // Buscar o role ID do manager
    $managerrole = $DB->get_record('role', array('shortname' => 'manager'));

    if (!$managerrole) {
        return false;
    }

    // Obtém o contexto do sistema
    $systemcontext = context_system::instance();

    // Verifica se o usuário tem a role de manager no contexto do sistema
    $hasrole = $DB->record_exists('role_assignments', array(
        'roleid' => $managerrole->id,
        'userid' => $userid,
        'contextid' => $systemcontext->id
    ));

    return $hasrole;
}

/**
 * Verifica se o usuário possui permissões administrativas.
 * Considera administradores do site e gerentes do sistema.
 *
 * @param int $userid ID do usuário a ser verificado
 * @return bool true se o usuário tem permissões administrativas
 */
function has_admin_permissions($userid) {
    return is_siteadmin($userid) || is_manager($userid);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true); // Decodifica o JSON para um array associativo

// Verifica se o 'userid' foi passado via POST
if (isset($data['userid'])) {
    $userid = intval($data['userid']); // Certifique-se de que o userid seja um número inteiro para segurança

    // Verifica se o usuário é administrador ou manager
    if (has_admin_permissions($userid)) {
        $role = is_siteadmin($userid) ? 'admin' : 'manager';
        echo json_encode([
            'status' => 'success',
            'isAdmin' => true,
            'role' => $role,
            'message' => 'O usuário possui permissões administrativas.'
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'isAdmin' => false,
            'role' => 'user',
            'message' => 'O usuário não possui permissões administrativas.'
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'O userid não foi fornecido.']);
}

