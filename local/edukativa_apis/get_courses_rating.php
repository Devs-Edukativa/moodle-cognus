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

global $DB;

header('Content-Type: application/json');

check_bearer_token(); // Verifica se o token de autenticação é válido

$input = file_get_contents('php://input');
$data = json_decode($input, true); // Decodifica o JSON para um array associativo

function get_all_ratings() {
    global $DB;
    try {
        $sql = 'SELECT
        q.course AS courseid,
        qq.content AS question_content,
        AVG(qr.rankvalue) AS average_rank_value,
        COUNT(qr.rankvalue) AS response_count
        FROM {questionnaire} AS q
        JOIN {questionnaire_question} AS qq ON q.id = qq.surveyid
        JOIN {questionnaire_response_rank} AS qr ON qq.id = qr.question_id
        WHERE qq.content REGEXP "(<p>|<strong>)*[aA][vV][aA][lL][iI][aA][çÇ][ãÃ][oO] [gG][eE][rR][aA][lL](</p>|</strong>)*"
        GROUP BY q.course, qq.content';
        $fields = $DB->get_records_sql($sql);

        $fields_array = array();
        foreach ($fields as $field) {
            $fields_array[] = (array)$field;
        }
        echo json_encode($fields_array);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar avaliação do curso.', 'error' => $e->getMessage()]);
    }
}

function get_course_rating($courseid) {
    global $DB;
    try {
        $sql = 'SELECT
        qq.content AS question_content,
        AVG(qr.rankvalue) AS average_rank_value,
        COUNT(qr.rankvalue) AS response_count,
        q.course AS courseid
        FROM {questionnaire} AS q
        JOIN {questionnaire_question} AS qq ON q.id = qq.surveyid
        JOIN {questionnaire_response_rank} AS qr ON qq.id = qr.question_id
        WHERE q.course = :courseid AND qq.content REGEXP "(<p>|<strong>)*[aA][vV][aA][lL][iI][aA][çÇ][ãÃ][oO] [gG][eE][rR][aA][lL](</p>|</strong>)*"
        GROUP BY qq.content, q.course';
        $params = array('courseid' => $courseid);
        $fields = $DB->get_records_sql($sql, $params);

        $fields_array = array();
        foreach ($fields as $field) {
            $fields_array[] = (array)$field;
        }
        echo json_encode($fields_array);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar avaliação do curso.', 'error' => $e->getMessage()]);
    }
}

if (isset($data['courseid']) || isset($data['wsfunction'])) {
    $courseid = isset($data['courseid']) ? $data['courseid'] : '';
    $wsfunction = $data['wsfunction'];

    switch ($wsfunction) {
        case 'get_course_rating':
            get_course_rating($courseid);
            break;
        case 'get_all_ratings':
            get_all_ratings();
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Função inválida.']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Parâmetros inválidos.']);
}