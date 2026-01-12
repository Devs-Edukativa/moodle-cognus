<?php

/**
 * Fixers para corrigir problemas de dados no Moodle
 *
 * @created    24/11/25
 * @package    local_edukativa_apis
 * @copyright  Rafael Dantas
 */

require(__DIR__ . '/../../config.php');
require(__DIR__ . '/auth.php');
require_once($CFG->libdir . '/ddllib.php');

global $DB, $CFG;

header('Content-Type: application/json');

check_bearer_token();

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$fixer = $data['fixer'] ?? null;
$response = [
    'status' => 'error',
    'message' => 'Fixer não especificado',
    'data' => null
];

if (!$fixer) {
    echo json_encode($response);
    exit;
}

/**
 * Fixer: trim_user_emails
 * Descrição: Remove espaços em branco antes e depois dos emails dos usuários
 * 
 * SQL: UPDATE mdl_user SET email = TRIM(email) WHERE email LIKE '% ';
 */
function fix_trim_user_emails() {
    global $DB;
    
    try {
        // Contar quantos usuários têm emails com espaços
        $count_sql = "SELECT COUNT(*) as count FROM {user} WHERE email LIKE '% ' OR email LIKE ' %'";
        $count_result = $DB->get_record_sql($count_sql);
        $affected_before = $count_result->count;

        // Executar o UPDATE
        $sql = "UPDATE {user} SET email = TRIM(email) WHERE email LIKE '% ' OR email LIKE ' %'";
        $result = $DB->execute($sql);

        // Verificar quantos ficaram após
        $count_after = $DB->get_record_sql($count_sql);
        $affected_after = $count_after->count;
        $fixed = $affected_before - $affected_after;

        return [
            'success' => true,
            'message' => "Emails corrigidos com sucesso",
            'data' => [
                'affected_users_before' => $affected_before,
                'affected_users_after' => $affected_after,
                'users_fixed' => $fixed,
                'updated_rows' => $result
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Erro ao corrigir emails: " . $e->getMessage(),
            'data' => null
        ];
    }
}

/**
 * Fixer: remove_spaces_from_usernames
 * Descrição: Remove TODOS os espaços do username dos usuários
 * Importante: Seguro usar pois usernames são CPF ou email (sem espaços)
 * 
 * SQL: UPDATE mdl_user SET username = REPLACE(username, ' ', '') WHERE username LIKE '% %';
 */
function fix_remove_spaces_from_usernames() {
    global $DB;
    
    try {
        // Contar quantos usuários têm espaços no username
        $count_sql = "SELECT COUNT(*) as count FROM {user} WHERE username LIKE '% %'";
        $count_result = $DB->get_record_sql($count_sql);
        $affected_before = $count_result->count;

        // Executar o UPDATE - Remove TODOS os espaços
        $sql = "UPDATE {user} SET username = REPLACE(username, ' ', '') WHERE username LIKE '% %'";
        $result = $DB->execute($sql);

        // Verificar quantos ficaram após
        $count_after = $DB->get_record_sql($count_sql);
        $affected_after = $count_after->count;
        $fixed = $affected_before - $affected_after;

        return [
            'success' => true,
            'message' => "Espaços removidos dos usernames com sucesso",
            'data' => [
                'affected_users_before' => $affected_before,
                'affected_users_after' => $affected_after,
                'users_fixed' => $fixed,
                'updated_rows' => $result
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Erro ao remover espaços dos usernames: " . $e->getMessage(),
            'data' => null
        ];
    }
}

/**
 * Fixer: trim_user_usernames
 * Descrição: Remove espaços em branco antes e depois dos nomes de usuário
 * 
 * SQL: UPDATE mdl_user SET username = TRIM(username) WHERE username LIKE '% ' OR username LIKE ' %';
 */
function fix_trim_user_usernames() {
    global $DB;
    
    try {
        // Contar quantos usuários têm usernames com espaços
        $count_sql = "SELECT COUNT(*) as count FROM {user} WHERE username LIKE '% ' OR username LIKE ' %'";
        $count_result = $DB->get_record_sql($count_sql);
        $affected_before = $count_result->count;

        // Executar o UPDATE
        $sql = "UPDATE {user} SET username = TRIM(username) WHERE username LIKE '% ' OR username LIKE ' %'";
        $result = $DB->execute($sql);

        // Verificar quantos ficaram após
        $count_after = $DB->get_record_sql($count_sql);
        $affected_after = $count_after->count;
        $fixed = $affected_before - $affected_after;

        return [
            'success' => true,
            'message' => "Nomes de usuário corrigidos com sucesso",
            'data' => [
                'affected_users_before' => $affected_before,
                'affected_users_after' => $affected_after,
                'users_fixed' => $fixed,
                'updated_rows' => $result
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Erro ao corrigir usernames: " . $e->getMessage(),
            'data' => null
        ];
    }
}

/**
 * Fixer: trim_user_names
 * Descrição: Remove espaços em branco antes e depois dos nomes e sobrenomes dos usuários
 */
function fix_trim_user_names() {
    global $DB;
    
    try {
        // Trim em firstname
        $sql_firstname = "UPDATE {user} SET firstname = TRIM(firstname) WHERE firstname LIKE '% ' OR firstname LIKE ' %'";
        $result_firstname = $DB->execute($sql_firstname);

        // Trim em lastname
        $sql_lastname = "UPDATE {user} SET lastname = TRIM(lastname) WHERE lastname LIKE '% ' OR lastname LIKE ' %'";
        $result_lastname = $DB->execute($sql_lastname);

        return [
            'success' => true,
            'message' => "Nomes corrigidos com sucesso",
            'data' => [
                'firstname_updated' => $result_firstname,
                'lastname_updated' => $result_lastname,
                'total_updated' => $result_firstname + $result_lastname
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Erro ao corrigir nomes: " . $e->getMessage(),
            'data' => null
        ];
    }
}

/**
 * Fixer: remove_duplicate_users
 * Descrição: Identifica usuários duplicados por username e email
 * Nota: Este é apenas um diagnóstico, não remove automaticamente
 */
function fix_remove_duplicate_users() {
    global $DB;
    
    try {
        // Encontrar usernames duplicados
        $duplicate_usernames_sql = "
            SELECT username, COUNT(*) as count, GROUP_CONCAT(id) as ids
            FROM {user}
            WHERE deleted = 0 AND username NOT IN ('guest', 'admin')
            GROUP BY username
            HAVING count > 1
            ORDER BY count DESC
        ";
        $duplicate_usernames = $DB->get_records_sql($duplicate_usernames_sql);

        // Encontrar emails duplicados
        $duplicate_emails_sql = "
            SELECT email, COUNT(*) as count, GROUP_CONCAT(id) as ids
            FROM {user}
            WHERE deleted = 0 AND email != ''
            GROUP BY email
            HAVING count > 1
            ORDER BY count DESC
        ";
        $duplicate_emails = $DB->get_records_sql($duplicate_emails_sql);

        return [
            'success' => true,
            'message' => "Análise de duplicatas concluída",
            'data' => [
                'duplicate_usernames_found' => count($duplicate_usernames),
                'duplicate_emails_found' => count($duplicate_emails),
                'duplicate_usernames' => $duplicate_usernames,
                'duplicate_emails' => $duplicate_emails
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Erro ao analisar duplicatas: " . $e->getMessage(),
            'data' => null
        ];
    }
}

/**
 * Fixer: sanitize_usernames_special_chars
 * Descrição: Remove acentos, caracteres especiais e inválidos dos usernames
 * Converte números em superscript/subscript para números normais
 * Converte para lowercase (Moodle exige lowercase)
 * Remove pontos de CPF/CNPJ (usernames sem @), mas mantém pontos em emails (usernames com @)
 * Mantém apenas: a-z, 0-9, underscore, hífen, ponto (apenas em emails), @
 * Exemplos: 
 * - "ElenLobão" → "elenlobao"
 * - "ElenLobao" → "elenlobao"
 * - "018.738.123-00" → "01873812300" (CPF)
 * - "12.345.678/0001-90" → "12345678000190" (CNPJ)
 * - "User.Name@email.com" → "user.name@email.com" (email mantém pontos)
 * - "5798113884¹" → "57981138841"
 * 
 * Usa a mesma lógica da sanitização no NestJS
 */
function fix_sanitize_usernames_special_chars() {
    global $DB;
    
    try {
        // Buscar usuários que têm caracteres especiais/acentos, letras maiúsculas ou pontos (em não-emails)
        // O Moodle exige usernames em lowercase sem caracteres especiais
        $sql = "SELECT id, username FROM {user} 
                WHERE username LIKE '%ã%' 
                   OR username LIKE '%á%' 
                   OR username LIKE '%à%' 
                   OR username LIKE '%â%'
                   OR username LIKE '%õ%'
                   OR username LIKE '%ó%'
                   OR username LIKE '%ô%'
                   OR username LIKE '%é%'
                   OR username LIKE '%è%'
                   OR username LIKE '%ê%'
                   OR username LIKE '%í%'
                   OR username LIKE '%ì%'
                   OR username LIKE '%ú%'
                   OR username LIKE '%ù%'
                   OR username LIKE '%û%'
                   OR username LIKE '%ç%'
                   OR username LIKE '%¹%'
                   OR username LIKE '%²%'
                   OR username LIKE '%³%'
                   OR username LIKE '%⁴%'
                   OR username LIKE '%⁵%'
                   OR username LIKE '%⁶%'
                   OR username LIKE '%⁷%'
                   OR username LIKE '%⁸%'
                   OR username LIKE '%⁹%'
                   OR username LIKE '%⁰%'
                   OR username LIKE '%₁%'
                   OR username LIKE '%₂%'
                   OR username LIKE '%₃%'
                   OR username LIKE '%₄%'
                   OR username LIKE '%₅%'
                   OR username LIKE '%₆%'
                   OR username LIKE '%₇%'
                   OR username LIKE '%₈%'
                   OR username LIKE '%₉%'
                   OR username LIKE '%₀%'
                   OR username LIKE '%/%'
                   OR username LIKE '%-%'
                   OR username REGEXP BINARY '[A-Z]'
                   OR (username LIKE '%.%' AND username NOT LIKE '%@%')";
        $users_to_fix = $DB->get_records_sql($sql);
        
        if (empty($users_to_fix)) {
            return [
                'success' => true,
                'message' => "Nenhum username com caracteres inválidos encontrado",
                'data' => [
                    'users_fixed' => 0,
                    'affected_users' => []
                ]
            ];
        }

        // Pré-carregar todos os usernames existentes em MEMÓRIA (única query!)
        $all_existing_usernames = $DB->get_records_sql(
            "SELECT DISTINCT username FROM {user}",
            [],
            null,
            'username',
            null
        );
        $existing_usernames = array_keys($all_existing_usernames);

        $affected_users = [];
        $fixed_count = 0;
        
        // Mapa de caracteres especiais para suas versões normais
        $special_char_map = [
            // Superscripts
            '¹' => '1', '²' => '2', '³' => '3', '⁴' => '4', '⁵' => '5',
            '⁶' => '6', '⁷' => '7', '⁸' => '8', '⁹' => '9', '⁰' => '0',
            // Subscripts
            '₁' => '1', '₂' => '2', '₃' => '3', '₄' => '4', '₅' => '5',
            '₆' => '6', '₇' => '7', '₈' => '8', '₉' => '9', '₀' => '0',
        ];

        foreach ($users_to_fix as $user) {
            $original_username = $user->username;
            
            // Substituir caracteres especiais mapeados
            $sanitized = $original_username;
            foreach ($special_char_map as $special => $normal) {
                $sanitized = str_replace($special, $normal, $sanitized);
            }
            
            // Normalizar UTF-8 e remover diacríticos (acentos)
            // Usar iconv para remover acentos de forma mais robusta
            $sanitized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $sanitized);
            
            // Verificar se é email (contém @) ou CPF/CNPJ (não contém @)
            $is_email = strpos($sanitized, '@') !== false;
            
            if ($is_email) {
                // Para emails: manter pontos e @, remover apenas caracteres realmente inválidos
                $sanitized = preg_replace('/[^a-zA-Z0-9_.@-]/', '', $sanitized);
            } else {
                // Para CPF/CNPJ: remover pontos, traços, barras e outros caracteres especiais
                $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $sanitized);
            }
            
            // Remover espaços
            $sanitized = str_replace(' ', '', $sanitized);
            
            // IMPORTANTE: Converter para lowercase (Moodle exige lowercase)
            $sanitized = strtolower($sanitized);
            
            // Se ficou vazio, usar um username padrão
            if (empty($sanitized)) {
                $sanitized = 'user_' . $user->id;
            }
            
            // Se mudou, atualizar no banco
            if ($sanitized !== $original_username) {
                // Verificar se o novo username já existe (busca em memória, não em DB!)
                $username_already_used = in_array($sanitized, $existing_usernames);
                
                if (!$username_already_used) {
                    // Atualizar
                    $user_update = new stdClass();
                    $user_update->id = $user->id;
                    $user_update->username = $sanitized;
                    $DB->update_record('user', $user_update);
                    
                    // Adicionar ao array de usernames existentes para evitar duplicatas no mesmo lote
                    $existing_usernames[] = $sanitized;
                    
                    $fixed_count++;
                    $affected_users[] = [
                        'id' => $user->id,
                        'username_before' => $original_username,
                        'username_after' => $sanitized
                    ];
                }
            }
        }

        return [
            'success' => true,
            'message' => "Usernames sanitizados com sucesso",
            'data' => [
                'total_checked' => count($users_to_fix),
                'users_fixed' => $fixed_count,
                'affected_users' => $affected_users
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Erro ao sanitizar usernames: " . $e->getMessage(),
            'data' => null
        ];
    }
}

/**
 * Fixer: list_available_fixers
 * Descrição: Lista todos os fixers disponíveis
 */
function fix_list_available_fixers() {
    return [
        'success' => true,
        'message' => "Fixers disponíveis",
        'data' => [
            [
                'name' => 'trim_user_emails',
                'description' => 'Remove espaços em branco antes e depois dos emails dos usuários',
                'sql' => "UPDATE {user} SET email = TRIM(email) WHERE email LIKE '% ' OR email LIKE ' %'"
            ],
            [
                'name' => 'remove_spaces_from_usernames',
                'description' => 'Remove TODOS os espaços do username dos usuários (seguro para CPF/email)',
                'sql' => "UPDATE {user} SET username = REPLACE(username, ' ', '') WHERE username LIKE '% %'"
            ],
            [
                'name' => 'trim_user_usernames',
                'description' => 'Remove espaços em branco antes e depois dos nomes de usuário',
                'sql' => "UPDATE {user} SET username = TRIM(username) WHERE username LIKE '% ' OR username LIKE ' %'"
            ],
            [
                'name' => 'trim_user_names',
                'description' => 'Remove espaços em branco antes e depois dos nomes e sobrenomes',
                'sql' => "UPDATE {user} SET firstname = TRIM(firstname), lastname = TRIM(lastname)"
            ],
            [
                'name' => 'sanitize_usernames_special_chars',
                'description' => 'Remove acentos, caracteres especiais e converte para lowercase. CPF/CNPJ: remove pontos/traços. Email: mantém pontos. Exemplos: "018.738.123-00" → "01873812300", "User@email.com" → "user@email.com"',
                'sql' => null
            ],
            [
                'name' => 'remove_duplicate_users',
                'description' => 'Identifica usuários duplicados por username e email (diagnóstico apenas)',
                'sql' => null
            ],
            [
                'name' => 'list_available_fixers',
                'description' => 'Lista todos os fixers disponíveis',
                'sql' => null
            ],
            [
                'name' => 'fix_auth_type',
                'description' => 'Migra usuários de "email" e "enrolkey" para "manual". Cria backup para rollback.',
                'sql' => null
            ],
            [
                'name' => 'restore_auth_types',
                'description' => 'Reverte migração de autenticação usando backup',
                'sql' => null
            ]
        ]
    ];
}

// Executar o fixer solicitado
switch ($fixer) {
    case 'trim_user_emails':
        $response = fix_trim_user_emails();
        break;
    
    case 'remove_spaces_from_usernames':
        $response = fix_remove_spaces_from_usernames();
        break;
    
    case 'trim_user_usernames':
        $response = fix_trim_user_usernames();
        break;
    
    case 'trim_user_names':
        $response = fix_trim_user_names();
        break;
    
    case 'sanitize_usernames_special_chars':
        $response = fix_sanitize_usernames_special_chars();
        break;
    
    case 'remove_duplicate_users':
        $response = fix_remove_duplicate_users();
        break;
    
    case 'list_available_fixers':
        $response = fix_list_available_fixers();
        break;
    
    case 'fix_auth_type':
        $response = fix_auth_type();
        break;
    
    case 'restore_auth_types':
        $response = fix_restore_auth_types();
        break;
    
    default:
        $response = [
            'status' => 'error',
            'message' => "Fixer '{$fixer}' não encontrado",
            'data' => null
        ];
}

/**
 * Fixer: fix_auth_type
 * Descrição: Migra usuários de 'email' e 'enrolkey' para 'manual'
 * Cria backup antes de migrar para permitir rollback
 * 
 * Retorna: {email_count, enrolkey_count, total_migrated, backup_created}
 */
function fix_auth_type() {
    global $DB;
    
    try {
        // Criar tabela de backup se não existir
        $dbman = $DB->get_manager();
        $table = new xmldb_table('user_auth_backup');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('old_auth', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('new_auth', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('migration_date', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
            
            $dbman->create_table($table);
        }

        // Contar usuários antes da migração
        $email_count_sql = "SELECT COUNT(*) as count FROM {user} WHERE auth = 'email' AND deleted = 0";
        $enrolkey_count_sql = "SELECT COUNT(*) as count FROM {user} WHERE auth = 'enrolkey' AND deleted = 0";
        
        $email_result = $DB->get_record_sql($email_count_sql);
        $enrolkey_result = $DB->get_record_sql($enrolkey_count_sql);
        
        $email_count = $email_result->count;
        $enrolkey_count = $enrolkey_result->count;
        $total = $email_count + $enrolkey_count;

        if ($total == 0) {
            return [
                'success' => true,
                'message' => 'Nenhum usuário encontrado para migração',
                'data' => [
                    'email_count' => 0,
                    'enrolkey_count' => 0,
                    'total_migrated' => 0,
                    'backup_created' => false
                ]
            ];
        }

        // Iniciar transação
        $transaction = $DB->start_delegated_transaction();

        // Fazer backup dos usuários que serão alterados
        $backup_sql = "INSERT INTO {user_auth_backup} (userid, old_auth, new_auth, migration_date)
                       SELECT id, auth, 'manual', :migration_date
                       FROM {user}
                       WHERE (auth = 'email' OR auth = 'enrolkey') AND deleted = 0";
        
        $DB->execute($backup_sql, ['migration_date' => time()]);

        // Migrar usuários de 'email' para 'manual'
        $sql_email = "UPDATE {user} SET auth = 'manual' WHERE auth = 'email' AND deleted = 0";
        $DB->execute($sql_email);

        // Migrar usuários de 'enrolkey' para 'manual'
        $sql_enrolkey = "UPDATE {user} SET auth = 'manual' WHERE auth = 'enrolkey' AND deleted = 0";
        $DB->execute($sql_enrolkey);

        // Confirmar transação
        $transaction->allow_commit();

        return [
            'success' => true,
            'message' => "Migração concluída com sucesso",
            'data' => [
                'email_count' => $email_count,
                'enrolkey_count' => $enrolkey_count,
                'total_migrated' => $total,
                'backup_created' => true,
                'backup_table' => 'mdl_user_auth_backup'
            ]
        ];
    } catch (Exception $e) {
        if (isset($transaction)) {
            $transaction->rollback($e);
        }
        return [
            'success' => false,
            'message' => "Erro ao migrar autenticação: " . $e->getMessage(),
            'data' => null
        ];
    }
}

/**
 * Fixer: restore_auth_types
 * Descrição: Reverte migração de autenticação usando backup
 * Remove registros do backup após restauração bem-sucedida
 * 
 * Retorna: {restored_count, backup_entries_removed}
 */
function fix_restore_auth_types() {
    global $DB;
    
    try {
        // Verificar se a tabela de backup existe
        $dbman = $DB->get_manager();
        $table = new xmldb_table('user_auth_backup');
        
        if (!$dbman->table_exists($table)) {
            return [
                'success' => false,
                'message' => 'Tabela de backup não encontrada. Nenhuma migração foi feita anteriormente.',
                'data' => null
            ];
        }

        // Contar registros de backup
        $count_sql = "SELECT COUNT(*) as count FROM {user_auth_backup}";
        $count_result = $DB->get_record_sql($count_sql);
        $backup_count = $count_result->count;

        if ($backup_count == 0) {
            return [
                'success' => true,
                'message' => 'Nenhum registro de backup encontrado',
                'data' => [
                    'restored_count' => 0,
                    'backup_entries_removed' => 0
                ]
            ];
        }

        // Iniciar transação
        $transaction = $DB->start_delegated_transaction();

        // Restaurar autenticação dos usuários usando JOIN
        $restore_sql = "UPDATE {user} u
                        INNER JOIN {user_auth_backup} b ON u.id = b.userid
                        SET u.auth = b.old_auth
                        WHERE u.deleted = 0";
        
        $DB->execute($restore_sql);

        // Contar quantos foram restaurados
        $restored_sql = "SELECT COUNT(*) as count 
                         FROM {user} u
                         INNER JOIN {user_auth_backup} b ON u.id = b.userid
                         WHERE u.deleted = 0";
        $restored_result = $DB->get_record_sql($restored_sql);
        $restored_count = $restored_result->count;

        // Limpar tabela de backup
        $DB->execute("TRUNCATE TABLE {user_auth_backup}");

        // Confirmar transação
        $transaction->allow_commit();

        return [
            'success' => true,
            'message' => "Rollback concluído com sucesso",
            'data' => [
                'restored_count' => $restored_count,
                'backup_entries_removed' => $backup_count
            ]
        ];
    } catch (Exception $e) {
        if (isset($transaction)) {
            $transaction->rollback($e);
        }
        return [
            'success' => false,
            'message' => "Erro ao restaurar autenticação: " . $e->getMessage(),
            'data' => null
        ];
    }
}

echo json_encode($response);
?>
