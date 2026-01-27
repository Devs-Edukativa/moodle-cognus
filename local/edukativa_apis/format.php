<?php

/**
 * Formatação de campos customizados de cursos
 * 
 * Processa o campo fullname dos cursos para extrair e popular os campos:
 * - nome_curso: nome do curso sem ano e turma
 * - cadastro_turma: número da turma
 * 
 * Exemplo: "Curso de Recursos Genéticos para Alimentação e Agricultura - 2025 - Turma 12"
 * - nome_curso: "Curso de Recursos Genéticos para Alimentação e Agricultura"
 * - cadastro_turma: "12"
 *
 * @created    13/12/25
 * @package    local_edukativa_apis
 * @copyright  Rafael Dantas
 */

require(__DIR__ . '/../../config.php');
require(__DIR__ . '/auth.php');
require_once($CFG->libdir . '/accesslib.php');

global $DB, $CFG;

header('Content-Type: application/json');

check_bearer_token();

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$action = $data['action'] ?? 'process_all_courses';

/**
 * Parse nome do curso e turma do campo fullname
 * 
 * Padrões aceitos:
 * - "{Nome do Curso} - {Ano} - Turma {Número}"
 * - "{Nome do Curso} - Turma {Número}"
 * - "{Nome do Curso} - {Ano} - turma {Número}" (case insensitive)
 * 
 * @param string $fullname Nome completo do curso
 * @return array|null ['nome_curso' => string, 'turma' => int] ou null se não corresponder
 */
function parse_course_name_and_turma($fullname) {
    if (empty($fullname)) {
        return null;
    }
    
    // Padrão: "{Nome} - {Ano (opcional)} - Turma {Número}"
    // Regex: captura nome, ano opcional, e número da turma
    $pattern = '/^(.+?)\s*-\s*(?:\d{4}\s*-\s*)?turma\s+(\d+)$/i';
    
    if (preg_match($pattern, $fullname, $matches)) {
        $nome_curso = trim($matches[1]);
        $turma = (int)$matches[2];
        
        // Remove o ano do final do nome se presente (ex: "Nome - 2025")
        $nome_curso = preg_replace('/\s*-\s*\d{4}$/', '', $nome_curso);
        
        return [
            'nome_curso' => $nome_curso,
            'turma' => $turma
        ];
    }
    
    return null;
}

/**
 * Obtém IDs dos campos customizados nome_curso e cadastro_turma
 * 
 * @return array|null ['nome_curso_id' => int, 'turma_id' => int] ou null se não encontrado
 */
function get_course_custom_field_ids() {
    global $DB;
    
    try {
        // Buscar campo nome_curso
        $nome_curso_field = $DB->get_record('customfield_field', 
            ['shortname' => 'nome_curso'], 
            'id, shortname, type'
        );
        
        // Buscar campo cadastro_turma (conforme documentação do sistema)
        $turma_field = $DB->get_record('customfield_field', 
            ['shortname' => 'cadastro_turma'], 
            'id, shortname, type'
        );
        
        if (!$nome_curso_field || !$turma_field) {
            return null;
        }
        
        return [
            'nome_curso_id' => $nome_curso_field->id,
            'turma_id' => $turma_field->id,
            'nome_curso_type' => $nome_curso_field->type,
            'turma_type' => $turma_field->type
        ];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Verifica se um curso já tem nome_curso e turma definidos
 * 
 * @param int $courseid ID do curso
 * @param array $fieldids Array com nome_curso_id e turma_id
 * @return array ['has_nome_curso' => bool, 'has_turma' => bool, 'nome_curso_value' => string, 'turma_value' => string]
 */
function check_course_custom_fields($courseid, $fieldids) {
    global $DB;
    
    try {
        // Buscar dados do campo nome_curso
        $nome_curso_data = $DB->get_record('customfield_data', [
            'fieldid' => $fieldids['nome_curso_id'],
            'instanceid' => $courseid
        ], 'id, value');
        
        // Buscar dados do campo turma
        $turma_data = $DB->get_record('customfield_data', [
            'fieldid' => $fieldids['turma_id'],
            'instanceid' => $courseid
        ], 'id, value');
        
        $has_nome_curso = $nome_curso_data && !empty(trim($nome_curso_data->value));
        $has_turma = $turma_data && !empty(trim($turma_data->value));
        
        return [
            'has_nome_curso' => $has_nome_curso,
            'has_turma' => $has_turma,
            'nome_curso_value' => $nome_curso_data ? $nome_curso_data->value : null,
            'turma_value' => $turma_data ? $turma_data->value : null,
            'nome_curso_id' => $nome_curso_data ? $nome_curso_data->id : null,
            'turma_id' => $turma_data ? $turma_data->id : null
        ];
    } catch (Exception $e) {
        return [
            'has_nome_curso' => false,
            'has_turma' => false,
            'nome_curso_value' => null,
            'turma_value' => null,
            'nome_curso_id' => null,
            'turma_id' => null
        ];
    }
}

/**
 * Atualiza ou insere campos customizados nome_curso e turma
 * 
 * @param int $courseid ID do curso
 * @param string $nome_curso Nome do curso
 * @param int $turma Número da turma
 * @param array $fieldids Array com nome_curso_id e turma_id
 * @param array $existing_data Dados existentes do check_course_custom_fields
 * @return bool Sucesso da operação
 */
function update_course_custom_fields($courseid, $nome_curso, $turma, $fieldids, $existing_data) {
    global $DB;
    
    try {
        $updated = false;
        
        // Atualizar/inserir nome_curso se não existir
        if (!$existing_data['has_nome_curso']) {
            if ($existing_data['nome_curso_id']) {
                // Atualizar registro existente
                $record = new stdClass();
                $record->id = $existing_data['nome_curso_id'];
                $record->value = $nome_curso;
                $record->valueformat = 0;
                $DB->update_record('customfield_data', $record);
            } else {
                // Inserir novo registro
                $record = new stdClass();
                $record->fieldid = $fieldids['nome_curso_id'];
                $record->instanceid = $courseid;
                $record->value = $nome_curso;
                $record->valueformat = 0;
                $record->timecreated = time();
                $record->timemodified = time();
                $record->contextid = context_course::instance($courseid)->id;
                $DB->insert_record('customfield_data', $record);
            }
            $updated = true;
        }
        
        // Atualizar/inserir turma se não existir
        if (!$existing_data['has_turma']) {
            if ($existing_data['turma_id']) {
                // Atualizar registro existente
                $record = new stdClass();
                $record->id = $existing_data['turma_id'];
                $record->value = (string)$turma;
                $record->valueformat = 0;
                $DB->update_record('customfield_data', $record);
            } else {
                // Inserir novo registro
                $record = new stdClass();
                $record->fieldid = $fieldids['turma_id'];
                $record->instanceid = $courseid;
                $record->value = (string)$turma;
                $record->valueformat = 0;
                $record->timecreated = time();
                $record->timemodified = time();
                $record->contextid = context_course::instance($courseid)->id;
                $DB->insert_record('customfield_data', $record);
            }
            $updated = true;
        }
        
        return $updated;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Processa todos os cursos do Moodle
 * Extrai nome_curso e turma do fullname e popula campos customizados
 * 
 * @return array Estatísticas do processamento
 */
function process_all_courses() {
    global $DB;
    
    try {
        // Obter IDs dos campos customizados
        $fieldids = get_course_custom_field_ids();
        
        if (!$fieldids) {
            return [
                'success' => false,
                'message' => 'Campos customizados "nome_curso" ou "cadastro_turma" não encontrados no Moodle',
                'data' => null
            ];
        }
        
        // Buscar todos os cursos (exceto site course, id=1)
        $sql = "SELECT id, fullname FROM {course} WHERE id > 1 ORDER BY id";
        $courses = $DB->get_records_sql($sql);
        
        $stats = [
            'total_courses' => count($courses),
            'processed' => 0,
            'updated' => 0,
            'skipped_already_set' => 0,
            'skipped_no_pattern' => 0,
            'errors' => 0,
            'courses_updated' => []
        ];
        
        foreach ($courses as $course) {
            $stats['processed']++;
            
            // Parse fullname
            $parsed = parse_course_name_and_turma($course->fullname);
            
            if (!$parsed) {
                // Não corresponde ao padrão esperado - ignorar
                $stats['skipped_no_pattern']++;
                continue;
            }
            
            // Verificar se campos já estão definidos
            $existing = check_course_custom_fields($course->id, $fieldids);
            
            if ($existing['has_nome_curso'] && $existing['has_turma']) {
                // Ambos já definidos - pular
                $stats['skipped_already_set']++;
                continue;
            }
            
            // Atualizar campos
            $updated = update_course_custom_fields(
                $course->id,
                $parsed['nome_curso'],
                $parsed['turma'],
                $fieldids,
                $existing
            );
            
            if ($updated) {
                $stats['updated']++;
                $stats['courses_updated'][] = [
                    'id' => $course->id,
                    'fullname' => $course->fullname,
                    'nome_curso' => $parsed['nome_curso'],
                    'turma' => $parsed['turma']
                ];
            } else {
                $stats['errors']++;
            }
        }
        
        return [
            'success' => true,
            'message' => 'Processamento concluído',
            'data' => $stats
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erro ao processar cursos: ' . $e->getMessage(),
            'data' => null
        ];
    }
}

// Executar ação solicitada
$response = [
    'status' => 'error',
    'message' => 'Ação não especificada',
    'data' => null
];

switch ($action) {
    case 'process_all_courses':
        $response = process_all_courses();
        break;
    
    default:
        $response = [
            'status' => 'error',
            'message' => "Ação '{$action}' não encontrada",
            'data' => null
        ];
}

echo json_encode($response);
?>
