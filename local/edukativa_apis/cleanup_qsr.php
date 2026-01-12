<?php

/**
 * API para limpeza de referências corrompidas em question_set_references.
 *
 * @created    22/12/25
 * @package    local_edukativa_apis
 * @copyright  Rafael Dantas
 */

require(__DIR__ . '/../../config.php');
require(__DIR__ . '/auth.php'); // Inclui o arquivo de autenticação

global $DB;

header('Content-Type: application/json');

check_bearer_token(); // Verifica se o token de autenticação é válido

$input = file_get_contents('php://input');
$data = json_decode($input, true); // Decodifica o JSON para um array associativo

$action = isset($data['action']) ? $data['action'] : null;
$backup_table = 'z_backup_qsr_corrupt';

/**
 * Busca a melhor categoria disponível no curso (Contexto 50 ou 70) que contenha questões.
 */
function find_populated_category($DB, $course_context_id) {
    try {
        // Busca categorias do curso que contenham questões
        $sql = "SELECT qc.id, COUNT(q.id) as qcount
                FROM {question_categories} qc
                JOIN {context} ctx ON ctx.id = qc.contextid
                LEFT JOIN {question} q ON q.category = qc.id 
                     AND q.parent = 0 
                     AND q.hidden = 0
                WHERE qc.contextid = :ctx
                  AND ctx.contextlevel IN (50, 70)
                GROUP BY qc.id
                HAVING COUNT(q.id) > 0
                ORDER BY COUNT(q.id) DESC
                LIMIT 1";
                
        $record = $DB->get_record_sql($sql, ['ctx' => $course_context_id], IGNORE_MULTIPLE);
        
        if ($record && isset($record->id)) {
            return $record->id;
        }
        
        return null;
    } catch (Exception $e) {
        // Se houver erro, retorna null para usar fallback
        return null;
    }
}

/**
 * Função para criar uma categoria padrão de questões em um curso
 */
function create_default_question_category($DB, $courseid) {
    try {
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return null;
        }

        // Verificar se já existe alguma categoria
        $existing = $DB->get_record_select('question_categories', 
            "contextid = :ctx", ['ctx' => $context->id], 'id', IGNORE_MULTIPLE);
        
        if ($existing) {
            return $existing;
        }

        // Criar nova categoria padrão
        $category = new stdClass();
        $category->name = 'Default for ' . $DB->get_field('course', 'shortname', ['id' => $courseid]);
        $category->contextid = $context->id;
        $category->info = 'Categoria criada automaticamente para reparo de questões aleatórias';
        $category->infoformat = FORMAT_HTML;
        $category->stamp = make_unique_id_code();
        $category->parent = 0;
        $category->sortorder = 999;
        $category->idnumber = null;
        
        $category->id = $DB->insert_record('question_categories', $category);
        
        return $category;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Função de reparo atualizada com filtro de contexto e população.
 * Atualiza o formato JSON para Moodle 5.0 e vincula a categorias válidas que contenham questões.
 * Isso evita o erro de "invalid types" nos questionários.
 */
function repair_corrupt_references($DB, $backup_table) {
    // 1. Identifica os registros que precisam de reparo
    $sql = "SELECT qsr.id, qsr.itemid as slot_id, qz.course as courseid
            FROM {question_set_references} qsr
            JOIN {quiz_slots} qs ON qsr.itemid = qs.id
            JOIN {quiz} qz ON qs.quizid = qz.id
            WHERE qsr.component = 'mod_quiz'
              AND (qsr.filtercondition LIKE '%questioncategoryid%' OR qsr.filtercondition IS NULL)";
    
    try {
        $records = $DB->get_records_sql($sql);
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Erro ao buscar registros corrompidos: ' . $e->getMessage()
        ];
    }

    if (empty($records)) {
        return [
            'status' => 'success',
            'message' => 'Nenhum registro corrompido encontrado para reparar.',
            'records_found' => 0
        ];
    }

    $count = count($records);
    $repaired_count = 0;
    $skipped_count = 0;
    $errors = [];
    $ids_to_backup = array_keys($records);

    // Iniciar Transação
    $transaction = $DB->start_delegated_transaction();

    try {
        // 2. Criar tabela de backup se não existir
        $dbman = $DB->get_manager();
        $table = new xmldb_table($backup_table);
        
        if (!$dbman->table_exists($table)) {
            $sql_create = "CREATE TABLE {{$backup_table}} AS 
                           SELECT * FROM {question_set_references} WHERE id IN (" . implode(',', $ids_to_backup) . ")";
            $DB->execute($sql_create);
        } else {
            // Limpar registros anteriores se existirem
            $DB->delete_records($backup_table);
            // Adicionar os novos registros
            $sql_insert = "INSERT INTO {{$backup_table}} 
                          SELECT * FROM {question_set_references} 
                          WHERE id IN (" . implode(',', $ids_to_backup) . ")";
            $DB->execute($sql_insert);
        }

        // 3. Reparar cada registro corrompido
        foreach ($records as $rec) {
            try {
                // Verificar se o curso existe
                $course = $DB->get_record('course', ['id' => $rec->courseid], 'id', IGNORE_MISSING);
                if (!$course) {
                    $skipped_count++;
                    $errors[] = "ID {$rec->id}: Curso {$rec->courseid} não encontrado";
                    continue;
                }

                // Encontrar contexto do curso
                $context = context_course::instance($rec->courseid, IGNORE_MISSING);
                
                if (!$context) {
                    $skipped_count++;
                    $errors[] = "ID {$rec->id}: Contexto do curso {$rec->courseid} não encontrado";
                    continue;
                }

                // Tenta achar categoria com questões, se não achar, pega a default do curso
                $target_category_id = find_populated_category($DB, $context->id);
                
                if (!$target_category_id) {
                    $default_cat = $DB->get_record('question_categories', 
                        ['contextid' => $context->id, 'parent' => 0], 'id', IGNORE_MULTIPLE);
                    $target_category_id = $default_cat ? $default_cat->id : null;
                }

                if (!$target_category_id) {
                    // Fallback crítico: tentar criar uma categoria padrão
                    $created_cat = create_default_question_category($DB, $rec->courseid);
                    $target_category_id = $created_cat ? $created_cat->id : null;
                }

                if (!$target_category_id) {
                    $skipped_count++;
                    $errors[] = "ID {$rec->id}: Impossível encontrar/criar categoria para curso {$rec->courseid}";
                    continue;
                }

                // 4. Novo JSON Moodle 5.0 (Rigoroso)
                $new_filter = [
                    'filter' => [
                        'category' => [
                            'name' => 'category',
                            'jointype' => 1,
                            'values' => [(int)$target_category_id],
                            'filteroptions' => ['includesubcategories' => true]
                        ]
                    ]
                ];

                // 5. Atualizar o registro
                $DB->set_field('question_set_references', 'filtercondition', json_encode($new_filter), ['id' => $rec->id]);
                $repaired_count++;
                
            } catch (Exception $e) {
                $skipped_count++;
                $error_msg = $e->getMessage();
                // Capturar mais detalhes do erro se disponível
                if (method_exists($e, 'debuginfo') && !empty($e->debuginfo)) {
                    $error_msg .= ' | Debug: ' . $e->debuginfo;
                }
                $errors[] = "ID {$rec->id}: " . $error_msg;
            }
        }

        $transaction->allow_commit();
        
        $response = [
            'status' => 'success',
            'message' => "Reparo concluído com contextos válidos. {$repaired_count} de {$count} registros foram reparados.",
            'records_found' => $count,
            'records_repaired' => $repaired_count,
            'records_skipped' => $skipped_count,
            'backup_table' => $backup_table,
            'note' => 'As questões aleatórias foram vinculadas a categorias válidas com questões. Os professores podem precisar reconfigurar os filtros.'
        ];

        if (!empty($errors)) {
            $response['errors_sample'] = array_slice($errors, 0, 10); // Primeiros 10 erros
            $response['total_errors'] = count($errors);
        }

        return $response;
        
    } catch (Exception $e) {
        $transaction->rollback($e);
        return [
            'status' => 'error',
            'message' => 'Erro no reparo: ' . $e->getMessage(),
            'records_found' => $count,
            'records_repaired' => $repaired_count
        ];
    }
}

/**
 * Função para identificar e deletar registros órfãos (mantida para casos extremos)
 */
function clean_corrupt_references($DB, $backup_table) {
    // 1. Identificar os IDs corrompidos (Legado + Categoria Inexistente)
    $sql_find = "SELECT qsr.id 
                 FROM {question_set_references} qsr 
                 WHERE qsr.component = 'mod_quiz' 
                   AND qsr.filtercondition LIKE '%questioncategoryid%'
                   AND NOT EXISTS (
                       SELECT 1 FROM {question_categories} qc 
                       WHERE qc.id = CAST(JSON_EXTRACT(qsr.filtercondition, '$.questioncategoryid') AS UNSIGNED)
                   )";

    try {
        $ids_to_delete = $DB->get_fieldset_sql($sql_find);
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Erro ao buscar registros corrompidos: ' . $e->getMessage()
        ];
    }

    if (empty($ids_to_delete)) {
        return [
            'status' => 'success',
            'message' => 'Nenhum registro corrompido encontrado.',
            'records_found' => 0
        ];
    }

    $count = count($ids_to_delete);

    // Iniciar Transação
    $transaction = $DB->start_delegated_transaction();

    try {
        // 2. Criar tabela de backup se não existir
        $dbman = $DB->get_manager();
        $table = new xmldb_table($backup_table);
        
        if (!$dbman->table_exists($table)) {
            $sql_create = "CREATE TABLE {{$backup_table}} AS 
                           SELECT * FROM {question_set_references} WHERE id IN (" . implode(',', $ids_to_delete) . ")";
            $DB->execute($sql_create);
        } else {
            // Se a tabela já existe, adicionar os novos registros
            $sql_insert = "INSERT INTO {{$backup_table}} 
                          SELECT * FROM {question_set_references} 
                          WHERE id IN (" . implode(',', $ids_to_delete) . ")";
            $DB->execute($sql_insert);
        }

        // 3. Deletar os registros da tabela principal
        list($insql, $params) = $DB->get_in_or_equal($ids_to_delete);
        $DB->delete_records_select('question_set_references', "id $insql", $params);

        $transaction->allow_commit();
        
        return [
            'status' => 'success',
            'message' => 'Limpeza concluída com sucesso (AVISO: Esta ação remove os registros).',
            'records_deleted' => $count,
            'backup_table' => $backup_table,
            'warning' => 'Use a ação "repair" em vez de "clean" para manter os questionários funcionando.'
        ];
    } catch (Exception $e) {
        $transaction->rollback($e);
        return [
            'status' => 'error',
            'message' => 'Erro na limpeza: ' . $e->getMessage()
        ];
    }
}

/**
 * Função para restaurar os dados do backup
 */
function rollback_references($DB, $backup_table, $keep_backup = false) {
    $dbman = $DB->get_manager();
    $table = new xmldb_table($backup_table);
    
    if (!$dbman->table_exists($table)) {
        return [
            'status' => 'error',
            'message' => "Tabela de backup {{$backup_table}} não encontrada."
        ];
    }

    // Contar registros antes do rollback
    try {
        $count = $DB->count_records($backup_table);
        if ($count == 0) {
            return [
                'status' => 'error',
                'message' => 'Tabela de backup está vazia. Nada para restaurar.'
            ];
        }
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Erro ao contar registros do backup: ' . $e->getMessage()
        ];
    }

    $transaction = $DB->start_delegated_transaction();

    try {
        // 1. Obter IDs dos registros do backup
        $backup_ids = $DB->get_fieldset_sql("SELECT id FROM {{$backup_table}}");
        
        if (empty($backup_ids)) {
            throw new Exception('Nenhum ID encontrado no backup');
        }

        // 2. Deletar registros atuais que serão substituídos
        list($insql, $params) = $DB->get_in_or_equal($backup_ids);
        $deleted_count = $DB->count_records_select('question_set_references', "id $insql", $params);
        $DB->delete_records_select('question_set_references', "id $insql", $params);
        
        // 3. Restaurar registros do backup
        $sql_restore = "INSERT INTO {question_set_references} SELECT * FROM {{$backup_table}}";
        $DB->execute($sql_restore);
        
        // 4. Dropar tabela de backup (opcional)
        if (!$keep_backup) {
            $dbman->drop_table($table);
        }

        $transaction->allow_commit();
        
        $message = 'Rollback concluído. Dados restaurados.';
        if (!$keep_backup) {
            $message .= ' Tabela de backup removida.';
        } else {
            $message .= ' Tabela de backup mantida para segurança.';
        }
        
        return [
            'status' => 'success',
            'message' => $message,
            'records_restored' => $count,
            'records_deleted_before_restore' => $deleted_count,
            'backup_kept' => $keep_backup
        ];
    } catch (Exception $e) {
        $transaction->rollback($e);
        return [
            'status' => 'error',
            'message' => 'Erro no rollback: ' . $e->getMessage(),
            'hint' => 'Verifique se há conflitos de chave primária ou se a estrutura da tabela mudou.'
        ];
    }
}

/**
 * Função para verificar o status do backup
 */
function check_backup_status($DB, $backup_table) {
    $dbman = $DB->get_manager();
    $table = new xmldb_table($backup_table);
    
    if (!$dbman->table_exists($table)) {
        return [
            'status' => 'success',
            'backup_exists' => false,
            'message' => 'Nenhum backup encontrado.'
        ];
    }

    try {
        $count = $DB->count_records($backup_table);
        return [
            'status' => 'success',
            'backup_exists' => true,
            'backup_table' => $backup_table,
            'records_in_backup' => $count,
            'message' => "Backup encontrado com {$count} registro(s)."
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Erro ao verificar backup: ' . $e->getMessage()
        ];
    }
}

/**
 * Função para diagnosticar categorias disponíveis por curso
 */
function diagnose_categories($DB) {
    $sql = "SELECT DISTINCT qz.course as courseid, c.shortname
            FROM {question_set_references} qsr
            JOIN {quiz_slots} qs ON qsr.itemid = qs.id
            JOIN {quiz} qz ON qs.quizid = qz.id
            JOIN {course} c ON qz.course = c.id
            WHERE qsr.component = 'mod_quiz'
              AND qsr.filtercondition LIKE '%questioncategoryid%'
              AND NOT EXISTS (
                  SELECT 1 FROM {question_categories} qc 
                  WHERE qc.id = CAST(JSON_EXTRACT(qsr.filtercondition, '$.questioncategoryid') AS UNSIGNED)
              )
            ORDER BY qz.course";
    
    try {
        $courses = $DB->get_records_sql($sql);
        $diagnostics = [];
        
        foreach ($courses as $course) {
            $context = context_course::instance($course->courseid, IGNORE_MISSING);
            if (!$context) {
                $diagnostics[] = [
                    'course_id' => $course->courseid,
                    'course_name' => $course->shortname,
                    'categories_found' => 0,
                    'issue' => 'Contexto não encontrado'
                ];
                continue;
            }
            
            $categories = $DB->get_records('question_categories', ['contextid' => $context->id], '', 'id, name, parent');
            
            $diagnostics[] = [
                'course_id' => $course->courseid,
                'course_name' => $course->shortname,
                'context_id' => $context->id,
                'categories_found' => count($categories),
                'categories' => array_values($categories)
            ];
        }
        
        return [
            'status' => 'success',
            'total_courses_affected' => count($courses),
            'courses' => $diagnostics
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Erro no diagnóstico: ' . $e->getMessage()
        ];
    }
}

/**
 * Função para verificar registros corrompidos sem executar limpeza
 */
function check_corrupt_references($DB) {
    $sql_find = "SELECT qsr.id 
                 FROM {question_set_references} qsr 
                 WHERE qsr.component = 'mod_quiz' 
                   AND qsr.filtercondition LIKE '%questioncategoryid%'
                   AND NOT EXISTS (
                       SELECT 1 FROM {question_categories} qc 
                       WHERE qc.id = CAST(JSON_EXTRACT(qsr.filtercondition, '$.questioncategoryid') AS UNSIGNED)
                   )";

    try {
        $ids_to_delete = $DB->get_fieldset_sql($sql_find);
        $count = count($ids_to_delete);
        
        return [
            'status' => 'success',
            'corrupt_records_found' => $count,
            'message' => $count > 0 
                ? "Encontrados {$count} registro(s) corrompido(s)." 
                : 'Nenhum registro corrompido encontrado.',
            'record_ids' => array_values($ids_to_delete)
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Erro ao verificar registros: ' . $e->getMessage()
        ];
    }
}

// Lógica de execução
$response = [];

switch ($action) {
    case 'repair':
        $response = repair_corrupt_references($DB, $backup_table);
        break;
    
    case 'clean':
        $response = clean_corrupt_references($DB, $backup_table);
        break;
    
    case 'rollback':
        $keep_backup = isset($data['keep_backup']) ? (bool)$data['keep_backup'] : false;
        $response = rollback_references($DB, $backup_table, $keep_backup);
        break;
    
    case 'check':
        $response = check_corrupt_references($DB);
        break;
    
    case 'backup_status':
        $response = check_backup_status($DB, $backup_table);
        break;
    
    case 'diagnose':
        $response = diagnose_categories($DB);
        break;
    
    default:
        $response = [
            'status' => 'error',
            'message' => 'Ação inválida. Use: repair, clean, rollback, check ou backup_status',
            'usage' => [
                'repair' => '🔧 RECOMENDADO: Repara registros corrompidos atualizando para formato Moodle 5.0 e cria backup',
                'clean' => '⚠️ Remove registros corrompidos (pode causar problemas nos questionários)',
                'rollback' => '↩️ Restaura dados do backup (use keep_backup:true para manter o backup após restaurar)',
                'check' => '🔍 Verifica quantos registros corrompidos existem sem executar ações',
                'backup_status' => '📦 Verifica se existe backup e quantos registros contém',
                'diagnose' => '🩺 Diagnostica categorias disponíveis por curso afetado'
            ],
            'workflow' => [
                '1' => 'Execute "check" para ver quantos registros estão corrompidos',
                '2' => 'Execute "repair" para consertar as referências (RECOMENDADO)',
                '3' => 'Se algo der errado, execute "rollback" para reverter',
                '4' => 'Use "backup_status" para verificar o backup a qualquer momento'
            ]
        ];
        break;
}

echo json_encode($response, JSON_PRETTY_PRINT);
