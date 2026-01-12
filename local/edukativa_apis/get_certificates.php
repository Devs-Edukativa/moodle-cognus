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

error_reporting(E_ERROR | E_PARSE);

function validate_certificate($code)
{
  global $DB;
  $params = array('code' => $code);
  $fields_array = array();

  try {
    if ($DB->get_manager()->table_exists('tool_certificate_issues')) {
      // Consulta na tabela tool_certificate_issues pega o code, o fullname do curso e o nome do usuario
      $sql1 = 'SELECT 
        si.id,
        si.code,
        c.fullname AS course_name,
        concat(u.firstname, " ", u.lastname) AS user_name,
        "coursecertificate" as certificate_type
        FROM {tool_certificate_issues} AS si
        JOIN {user} AS u ON u.id = si.userid
        JOIN {course} AS c ON c.id = si.courseid
        WHERE si.code = :code';

      $fields1 = $DB->get_recordset_sql($sql1, $params);

      foreach ($fields1 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }

  try {
    if ($DB->get_manager()->table_exists('customcert_issues')) {
      // Consulta na tabela customcert_issues pega o code, o fullname do curso e o nome do usuario
      $sql2 = 'SELECT
        ci.id,
        ci.code,
        c.fullname AS course_name,
        concat(u.firstname, " ", u.lastname) AS user_name,
        "customcertificate" as certificate_type
        FROM {customcert_issues} AS ci
        JOIN {customcert} AS cc ON cc.id = ci.customcertid
        JOIN {course} AS c ON c.id = cc.course
        JOIN {user} AS u ON u.id = ci.userid
        WHERE ci.code = :code';
      $fields2 = $DB->get_recordset_sql($sql2, $params);

      foreach ($fields2 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }

  try {
    if ($DB->get_manager()->table_exists('simplecertificate_issues')) {
      // Consulta na tabela simplecertificate_issues pega o nome do curso e o fullname do usuario
      $sql3 = 'SELECT
        si.id,
        si.code,
        si.coursename AS course_name,
        concat(u.firstname, " ", u.lastname) AS user_name,
        "simplecertificate" as certificate_type
        FROM {simplecertificate_issues} AS si
        JOIN {user} AS u ON u.id = si.userid
        WHERE si.code = :code';
      $fields3 = $DB->get_recordset_sql($sql3, $params);

      foreach ($fields3 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }

  echo json_encode($fields_array);
}

function get_single_certificate($courseid, $userid)
{
  global $DB;
  $params = array('courseid' => $courseid, 'userid' => $userid);
  $fields_array = array();

  try {
    if ($DB->get_manager()->table_exists('tool_certificate_issues')) {
      // Tool Certificate
      $sql1 = 'SELECT 
      si.id,
      si.courseid,
      si.userid,
      si.code,
      "coursecertificate" as certificate_type
      FROM {tool_certificate_issues} AS si
      WHERE si.courseid = :courseid AND si.userid = :userid';

      $fields1 = $DB->get_recordset_sql($sql1, $params);

      foreach ($fields1 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }
  try {
    // Custom Certificate
    if ($DB->get_manager()->table_exists('customcert_issues')) {
      $sql2 = 'SELECT
      ci.id,
      cc.course AS courseid,
      ci.userid,
      ci.code,
      "customcertificate" as certificate_type
      FROM {customcert_issues} AS ci
      JOIN {customcert} AS cc ON cc.id = ci.customcertid
      WHERE cc.course = :courseid AND ci.userid = :userid';

      $fields2 = $DB->get_recordset_sql($sql2, $params);
      foreach ($fields2 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }

  try {
    if ($DB->get_manager()->table_exists('simplecertificate_issues')) {
      // Simple Certificate
      $sql3 = 'SELECT
      si.id,
      sc.course as courseid,
      si.userid,
      si.code,
      "simplecertificate" as certificate_type
      FROM {simplecertificate_issues} AS si
      JOIN {simplecertificate} AS sc ON sc.id = si.certificateid
      WHERE sc.course = :courseid AND si.userid = :userid';

      $fields3 = $DB->get_recordset_sql($sql3, $params);
      foreach ($fields3 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }

  echo json_encode($fields_array);
}

function get_user_certificates($userid)
{
  global $DB;
  $params = array('userid' => $userid);
  $fields_array = array();

  try {
    if ($DB->get_manager()->table_exists('tool_certificate_issues')) {
      // Tool Certificate
      $sql1 = 'SELECT 
      si.id,
      si.courseid,
      si.userid,
      si.code,
      "coursecertificate" as certificate_type
      FROM {tool_certificate_issues} AS si
      WHERE si.userid = :userid';

      $fields1 = $DB->get_recordset_sql($sql1, $params);

      foreach ($fields1 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }

  try {
    if ($DB->get_manager()->table_exists('customcert_issues')) {
      // Custom Certificate
      $sql2 = 'SELECT
      ci.id,
      cc.course AS courseid,
      ci.userid,
      ci.code,
      "customcertificate" as certificate_type
      FROM {customcert_issues} AS ci
      JOIN {customcert} AS cc ON cc.id = ci.customcertid
      WHERE ci.userid = :userid';

      $fields2 = $DB->get_recordset_sql($sql2, $params);

      foreach ($fields2 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }
  try {
    if ($DB->get_manager()->table_exists('simplecertificate_issues')) {
      // Simple Certificate
      $sql3 = 'SELECT
      si.id,
      sc.course as courseid,
      si.userid,
      si.code,
      "simplecertificate" as certificate_type
      FROM {simplecertificate_issues} AS si
      JOIN {simplecertificate} AS sc ON sc.id = si.certificateid
      WHERE si.userid = :userid';

      $fields3 = $DB->get_recordset_sql($sql3, $params);

      foreach ($fields3 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }

  echo json_encode($fields_array);
}

function get_course_certificates($courseid)
{
  global $DB;
  $fields_array = array();
  $params = array('courseid' => $courseid);

  // Tool Certificate
  try {
    if ($DB->get_manager()->table_exists('tool_certificate_issues')) {
      $sql1 = 'SELECT 
      si.id,
      si.courseid,
      si.userid,
      si.code,
      "coursecertificate" as certificate_type
      FROM {tool_certificate_issues} AS si
      WHERE si.courseid = :courseid';

      $fields1 = $DB->get_recordset_sql($sql1, $params);

      foreach ($fields1 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }
  try {
    if ($DB->get_manager()->table_exists('customcert_issues')) {
      // Custom Certificate
      $sql2 = 'SELECT
      ci.id,
      cc.course AS courseid,
      ci.userid,
      ci.code,
      "customcertificate" as certificate_type
      FROM {customcert_issues} AS ci
      JOIN {customcert} AS cc ON cc.id = ci.customcertid
      WHERE cc.course = :courseid';

      $fields2 = $DB->get_recordset_sql($sql2, $params);

      foreach ($fields2 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }
  try {
    // Simple Certificate
    if ($DB->get_manager()->table_exists('simplecertificate_issues')) {
      $sql3 = 'SELECT
      si.id,
      sc.course AS courseid,
      si.userid,
      si.code,
      "simplecertificate" as certificate_type
      FROM {simplecertificate_issues} AS si
      JOIN {simplecertificate} AS sc ON sc.id = si.certificateid
      WHERE sc.course = :courseid';

      $fields3 = $DB->get_recordset_sql($sql3, $params);

      foreach ($fields3 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }

  echo json_encode($fields_array);
}

function get_all_certificates()
{
  global $DB;

  try {
    if ($DB->get_manager()->table_exists('tool_certificate_issues')) {
      // Tool Certificate
      $sql1 = 'SELECT
      si.id,
      si.courseid,
      si.userid,
      si.code,
      "coursecertificate" as certificate_type
      FROM {tool_certificate_issues} AS si';

      $fields1 = $DB->get_recordset_sql($sql1);

      foreach ($fields1 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }
  try {
    if ($DB->get_manager()->table_exists('customcert_issues')) {
      // Custom Certificate
      $sql2 = 'SELECT
      ci.id,
      cc.course AS courseid,
      ci.userid,
      ci.code,
      "customcertificate" as certificate_type
      FROM {customcert_issues} AS ci
      JOIN {customcert} AS cc ON cc.id = ci.customcertid';

      $fields2 = $DB->get_recordset_sql($sql2);
      foreach ($fields2 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }
  try {
    if ($DB->get_manager()->table_exists('simplecertificate_issues')) {
      //Simple Certificate
      $sql3 = 'SELECT
      si.id,
      sc.course AS courseid,
      si.userid,
      si.code,
      "simplecertificate" as certificate_type
      FROM {simplecertificate_issues} AS si
      JOIN {simplecertificate} AS sc ON sc.id = si.certificateid';

      $fields3 = $DB->get_recordset_sql($sql3);

      foreach ($fields3 as $field) {
        $fields_array[] = (array) $field;
      }
    }
  } catch (Exception $e) {
    //
  }
  echo json_encode(value: $fields_array);
}

if (isset($data['courseid']) || isset($data['userid']) || isset($data['wsfunction'])) {
  $courseid = isset($data['courseid']) ? $data['courseid'] : '';
  $userid = isset($data['userid']) ? $data['userid'] : '';
  $wsfunction = $data['wsfunction'];

  switch ($wsfunction) {
    case 'get_single_certificate':
      get_single_certificate($courseid, $userid);
      break;
    case 'get_user_certificates':
      get_user_certificates($userid);
      break;
    case 'get_course_certificates':
      get_course_certificates($courseid);
      break;
    case 'get_all_certificates':
      get_all_certificates();
      break;
    case 'validate_certificate':
      $code = $data['code'];
      validate_certificate($code);
      break;
    default:
      echo json_encode(['status' => 'error', 'message' => 'Função inválida.']);
      break;
  }
} else {
  echo json_encode(['status' => 'error', 'message' => 'Parâmetros inválidos.']);
}