<?php

/**
 * API para reparo e limpeza de referências corrompidas no Banco de Questões (Moodle 5.0).
 * Resolve: TypeError (null/array), Unsupported Context e Questionários Quebrados.
 */

require(__DIR__ . '/../../config.php');
require(__DIR__ . '/auth.php'); 

global $DB;

header('Content-Type: application/json');
check_bearer_token(); 

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$action = $data['action'] ?? null;
$backup_table = 'z_backup_qsr_corrupt';

/**
 * Busca a melhor categoria populada disponível (Contexto 50 ou 70).
 */
function find_best_category_for_repair($DB, $courseid) {
    try {
        $coursecontext = context_course::instance($courseid);
        $sql = "SELECT qc.id, COUNT(q.id) as qcount
                FROM {question_categories} qc
                JOIN {context} ctx ON ctx.id = qc.contextid
                LEFT JOIN {question} q ON q.category = qc.id AND q.parent = 0 AND q.hidden = 0
                WHERE (qc.contextid = :ctx OR ctx.path LIKE :ctxpath)
                  AND ctx.contextlevel IN (50, 70) 
                GROUP BY qc.id
                ORDER BY qcount DESC, qc.id ASC";
                
        $params = ['ctx' => $coursecontext->id, 'ctxpath' => $coursecontext->path . '/%'];
        $records = $DB->get_records_sql($sql, $params);
        
        foreach ($records as $rec) {
            if ($rec->qcount > 0) return $rec->id;
        }

        $default = $DB->get_record('question_categories', 
            ['contextid' => $coursecontext->id, 'parent' => 0], 'id', IGNORE_MULTIPLE);
            
        return $default ? $default->id : null;
    } catch (Exception $e) { return null; }
}

/**
 * Cria categoria padrão segura.
 */
function create_safe_default_category($DB, $courseid) {
    try {
        $context = context_course::instance($courseid);
        $category = new stdClass();
        $category->name = 'Default for ' . $DB->get_field('course', 'shortname', ['id' => $courseid]);
        $category->contextid = $context->id;
        $category->info = 'Categoria de reparo automático Moodle 5.0';
        $category->infoformat = FORMAT_HTML;
        $category->stamp = make_unique_id_code();
        $category->parent = 0;
        $category->sortorder = 999;
        return $DB->insert_record('question_categories', $category);
    } catch (Exception $e) { return null; }
}

/**
 * AÇÃO: REPAIR
 */
function repair_references_v2($DB, $backup_table) {
    $sql = "SELECT qsr.id, qz.course as courseid
            FROM {question_set_references} qsr
            JOIN {quiz_slots} qs ON qsr.itemid = qs.id
            JOIN {quiz} qz ON qs.quizid = qz.id
            WHERE qsr.component = 'mod_quiz'
              AND (qsr.filtercondition LIKE '%questioncategoryid%' OR qsr.filtercondition IS NULL OR qsr.filtercondition = '')";

    $records = $DB->get_records_sql($sql);
    if (empty($records)) return ['status' => 'success', 'message' => 'Nada para reparar.'];

    $dbman = $DB->get_manager();
    if ($dbman->table_exists($backup_table)) {
        $dbman->drop_table(new xmldb_table($backup_table));
    }
    
    // Criar backup físico
    $DB->execute("CREATE TABLE {{$backup_table}} AS SELECT * FROM {question_set_references} WHERE id IN (".implode(',', array_keys($records)).")");

    $repaired = 0;
    foreach ($records as $rec) {
        $target_cat_id = find_best_category_for_repair($DB, $rec->courseid) ?? create_safe_default_category($DB, $rec->courseid);
        if (!$target_cat_id) continue;

        $filter = ['filter' => ['category' => ['name' => 'category', 'jointype' => 1, 'values' => [(int)$target_cat_id], 'filteroptions' => ['includesubcategories' => true]]]];
        $DB->set_field('question_set_references', 'filtercondition', json_encode($filter), ['id' => $rec->id]);
        $repaired++;
    }
    return ['status' => 'success', 'repaired' => $repaired, 'message' => 'Cursos reparados. Limpe os caches!'];
}

/**
 * AÇÃO: ROLLBACK
 */
function rollback_references($DB, $backup_table) {
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists($backup_table)) {
        return ['status' => 'error', 'message' => 'Tabela de backup não encontrada.'];
    }

    $transaction = $DB->start_delegated_transaction();
    try {
        $backup_ids = $DB->get_fieldset_sql("SELECT id FROM {{$backup_table}}");
        if (!empty($backup_ids)) {
            list($insql, $params) = $DB->get_in_or_equal($backup_ids);
            $DB->delete_records_select('question_set_references', "id $insql", $params);
            $DB->execute("INSERT INTO {question_set_references} SELECT * FROM {{$backup_table}}");
        }
        $dbman->drop_table(new xmldb_table($backup_table));
        $transaction->allow_commit();
        return ['status' => 'success', 'message' => 'Rollback concluído.'];
    } catch (Exception $e) {
        $transaction->rollback($e);
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * AÇÃO: CHECK
 */
function check_corrupt_references($DB) {
    $sql = "SELECT COUNT(id) FROM {question_set_references} 
            WHERE component = 'mod_quiz' AND (filtercondition LIKE '%questioncategoryid%' OR filtercondition IS NULL)";
    $count = $DB->count_records_sql($sql);
    return ['status' => 'success', 'corrupt_count' => $count];
}

// EXECUÇÃO
$response = [];
switch ($action) {
    case 'repair': $response = repair_references_v2($DB, $backup_table); break;
    case 'rollback': $response = rollback_references($DB, $backup_table); break;
    case 'check': $response = check_corrupt_references($DB); break;
    default: $response = ['status' => 'error', 'message' => 'Ação inválida.']; break;
}

echo json_encode($response, JSON_PRETTY_PRINT);