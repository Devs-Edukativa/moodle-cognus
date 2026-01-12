<?php

/**
 * APIs Internas Customizadas.
 *
 * @created    24/03/25 11:00
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

/* 
return data as JSON
{
    id: number,
    shortname: string,
    fullname: string,
    displayname: string,
    courseimage: string,
    progress: number,
    completed: boolean,
    startdate: number,
    lastaccess: number,
}
*/

function get_all_enrolls($limit, $skip)
{
  global $DB, $CFG;

  require_once($CFG->dirroot . '/course/lib.php');
  require_once($CFG->dirroot . '/user/lib.php');
  require_once($CFG->libdir . '/completionlib.php');

  // Abordagem direta para obter matrículas com paginação - agora incluindo enddate
  $sql = "SELECT ue.id as ueid, ue.userid, c.id as courseid, c.shortname, c.fullname, c.startdate, c.enddate, ue.timestart as enrolmentstart, ue.timecreated 
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            JOIN {course} c ON e.courseid = c.id
            ORDER BY ue.userid, c.id";

  if ($limit > 0) {
    $enrollments = $DB->get_records_sql($sql, [], (int) $skip, (int) $limit);
  } else {
    $enrollments = $DB->get_records_sql($sql, []);
  }

  if (empty($enrollments)) {
    echo json_encode([]);
    return;
  }

  $results = [];
  foreach ($enrollments as $enrollment) {
    $userid = (int) $enrollment->userid;
    $courseid = (int) $enrollment->courseid;

    // Obtenção do último acesso
    $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
      'userid' => $userid,
      'courseid' => $courseid
    ], IGNORE_MISSING);

    // Obtendo o progresso e status de conclusão
    $progress = 0;
    $completed = false;
    $timecompleted = 0;

    // Obtendo objeto do curso para trabalhar com completion e imagem
    $course = $DB->get_record('course', ['id' => $courseid]);

    // Verificar se o curso tem acompanhamento de progresso habilitado
    if ($DB->record_exists('course', ['id' => $courseid, 'enablecompletion' => 1])) {
      $completion = new completion_info($course);
      if ($completion->is_tracked_user($userid)) {
        // Verificar se o curso foi concluído
        $completed = $completion->is_course_complete($userid);

        // Obter timestamp da conclusão do curso
        $completionRecord = $DB->get_record(
          'course_completions',
          ['userid' => $userid, 'course' => $courseid],
          'timecompleted',
          IGNORE_MISSING
        );

        if ($completionRecord && !empty($completionRecord->timecompleted)) {
          $timecompleted = (int) $completionRecord->timecompleted;
        }

        // Obter o percentual de progresso
        $progressPercent = \core_completion\progress::get_course_progress_percentage($course, $userid);
        $progress = is_null($progressPercent) ? 0 : floor($progressPercent);
      }
    }

    // Obter imagem do curso
    $courseimage = '';
    if (class_exists('\core_course\external\course_summary_exporter')) {
      $courseobj = new stdClass();
      $courseobj->id = $courseid;
      $courseimage = \core_course\external\course_summary_exporter::get_course_image($courseobj);
      if (empty($courseimage)) {
        $courseimage = '';
      }
    }

    $results[] = [
      'id' => (int) $courseid,
      'courseid' => (int) $courseid,
      'userid' => (int) $userid,
      'shortname' => $enrollment->shortname,
      'fullname' => $enrollment->fullname,
      'displayname' => $enrollment->fullname,
      'courseimage' => $courseimage,
      'progress' => (int) $progress,
      'completed' => $completed,
      'timecompleted' => $timecompleted,
      'startdate' => (int) $enrollment->enrolmentstart?: $enrollment->timecreated ?: time(),
      'lastaccess' => (int) ($lastaccess ?: 0)
    ];
  }

  echo json_encode($results);
}

function get_user_enrolls($userid, $limit = 0, $skip = 0)
{
  global $DB, $CFG;

  require_once($CFG->dirroot . '/course/lib.php');
  require_once($CFG->dirroot . '/user/lib.php');
  require_once($CFG->libdir . '/completionlib.php');

  // Filtrar matrículas por usuário específico
  $sql = "SELECT ue.id as ueid, ue.userid, c.id as courseid, c.shortname, c.fullname, c.startdate, c.enddate,
            ue.timestart as enrolmentstart, ue.timecreated
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            JOIN {course} c ON e.courseid = c.id
            WHERE ue.userid = :userid
            ORDER BY c.id";
  $params = ['userid' => $userid];

  if ($limit > 0) {
    $enrollments = $DB->get_records_sql($sql, $params, (int) $skip, (int) $limit);
  } else {
    $enrollments = $DB->get_records_sql($sql, $params);
  }

  if (empty($enrollments)) {
    echo json_encode([]);
    return;
  }

  $results = [];
  foreach ($enrollments as $enrollment) {
    $userid = (int) $enrollment->userid;
    $courseid = (int) $enrollment->courseid;

    // Obtenção do último acesso
    $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
      'userid' => $userid,
      'courseid' => $courseid
    ], IGNORE_MISSING);

    // Obtendo o progresso e status de conclusão
    $progress = 0;
    $completed = false;
    $timecompleted = 0;

    // Obtendo objeto do curso para trabalhar com completion e imagem
    $course = $DB->get_record('course', ['id' => $courseid]);

    // Verificar se o curso tem acompanhamento de progresso habilitado
    if ($DB->record_exists('course', ['id' => $courseid, 'enablecompletion' => 1])) {
      $completion = new completion_info($course);
      if ($completion->is_tracked_user($userid)) {
        // Verificar se o curso foi concluído
        $completed = $completion->is_course_complete($userid);

        // Obter timestamp da conclusão do curso
        $completionRecord = $DB->get_record(
          'course_completions',
          ['userid' => $userid, 'course' => $courseid],
          'timecompleted',
          IGNORE_MISSING
        );

        if ($completionRecord && !empty($completionRecord->timecompleted)) {
          $timecompleted = (int) $completionRecord->timecompleted;
        }

        // Obter o percentual de progresso
        $progressPercent = \core_completion\progress::get_course_progress_percentage($course, $userid);
        $progress = is_null($progressPercent) ? 0 : floor($progressPercent);
      }
    }

    // Obter imagem do curso
    $courseimage = '';
    if (class_exists('\core_course\external\course_summary_exporter')) {
      $courseobj = new stdClass();
      $courseobj->id = $courseid;
      $courseimage = \core_course\external\course_summary_exporter::get_course_image($courseobj);
      if (empty($courseimage)) {
        $courseimage = '';
      }
    }

    $results[] = [
      'id' => (int) $courseid,
      'courseid' => (int) $courseid,
      'userid' => (int) $userid,
      'shortname' => $enrollment->shortname,
      'fullname' => $enrollment->fullname,
      'displayname' => $enrollment->fullname,
      'courseimage' => $courseimage,
      'progress' => (int) $progress,
      'completed' => $completed,
      'timecompleted' => $timecompleted,
      'startdate' => (int) $enrollment->enrolmentstart?: $enrollment->timecreated ?: time(),
      'enddate' => (int) ($enrollment->enddate ?? 0),
      'lastaccess' => (int) ($lastaccess ?: 0)
    ];
  }

  echo json_encode($results);
}

function get_course_enrolls($courseid, $limit = 0, $skip = 0)
{
  global $DB, $CFG;

  require_once($CFG->dirroot . '/course/lib.php');
  require_once($CFG->dirroot . '/user/lib.php');
  require_once($CFG->libdir . '/completionlib.php');

  // Filtrar matrículas por curso específico
  $sql = "SELECT ue.id as ueid, ue.userid, c.id as courseid, c.shortname, c.fullname, c.startdate, c.enddate,
            ue.timestart as enrolmentstart, ue.timecreated
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            JOIN {course} c ON e.courseid = c.id
            WHERE c.id = :courseid
            ORDER BY ue.userid";
  $params = ['courseid' => $courseid];

  if ($limit > 0) {
    $enrollments = $DB->get_records_sql($sql, $params, (int) $skip, (int) $limit);
  } else {
    $enrollments = $DB->get_records_sql($sql, $params);
  }

  if (empty($enrollments)) {
    echo json_encode([]);
    return;
  }

  $results = [];
  foreach ($enrollments as $enrollment) {
    $userid = (int) $enrollment->userid;
    $courseid = (int) $enrollment->courseid;

    // Obtenção do último acesso
    $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
      'userid' => $userid,
      'courseid' => $courseid
    ], IGNORE_MISSING);

    // Obtendo o progresso e status de conclusão
    $progress = 0;
    $completed = false;
    $timecompleted = 0;

    // Obtendo objeto do curso para trabalhar com completion e imagem
    $course = $DB->get_record('course', ['id' => $courseid]);

    // Verificar se o curso tem acompanhamento de progresso habilitado
    if ($DB->record_exists('course', ['id' => $courseid, 'enablecompletion' => 1])) {
      $completion = new completion_info($course);
      if ($completion->is_tracked_user($userid)) {
        // Verificar se o curso foi concluído
        $completed = $completion->is_course_complete($userid);

        // Obter timestamp da conclusão do curso
        $completionRecord = $DB->get_record(
          'course_completions',
          ['userid' => $userid, 'course' => $courseid],
          'timecompleted',
          IGNORE_MISSING
        );

        if ($completionRecord && !empty($completionRecord->timecompleted)) {
          $timecompleted = (int) $completionRecord->timecompleted;
        }

        // Obter o percentual de progresso
        $progressPercent = \core_completion\progress::get_course_progress_percentage($course, $userid);
        $progress = is_null($progressPercent) ? 0 : floor($progressPercent);
      }
    }

    // Obter imagem do curso
    $courseimage = '';
    if (class_exists('\core_course\external\course_summary_exporter')) {
      $courseobj = new stdClass();
      $courseobj->id = $courseid;
      $courseimage = \core_course\external\course_summary_exporter::get_course_image($courseobj);
      if (empty($courseimage)) {
        $courseimage = '';
      }
    }

    $results[] = [
      'id' => (int) $courseid,
      'courseid' => (int) $courseid,
      'userid' => (int) $userid,
      'shortname' => $enrollment->shortname,
      'fullname' => $enrollment->fullname,
      'displayname' => $enrollment->fullname,
      'courseimage' => $courseimage,
      'progress' => (int) $progress,
      'completed' => $completed,
      'timecompleted' => $timecompleted,
      'startdate' => (int) $enrollment->enrolmentstart?: $enrollment->timecreated ?: time(),
      'enddate' => (int) ($enrollment->enddate ?? 0),
      'lastaccess' => (int) ($lastaccess ?: 0)
    ];
  }

  echo json_encode($results);
}

if (isset($data['wsfunction'])) {
  $courseid = isset($data['courseid']) ? $data['courseid'] : '';
  $userid = isset($data['userid']) ? $data['userid'] : '';
  $limit = isset($data['limit']) ? $data['limit'] : '';
  $skip = isset($data['skip']) ? $data['skip'] : '';
  $wsfunction = $data['wsfunction'];

  switch ($wsfunction) {
    case 'get_all_enrolls':
      get_all_enrolls($limit, $skip);
      break;
    case 'get_user_enrolls':
      get_user_enrolls($userid, $limit, $skip);
      break;
    case 'get_course_enrolls':
      get_course_enrolls($courseid, $limit, $skip);
      break;
    default:
      echo json_encode(['status' => 'error', 'message' => 'Função inválida.']);
      break;
  }
} else {
  echo json_encode(['status' => 'error', 'message' => 'Parâmetros inválidos.']);
}