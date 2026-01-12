<?php

/**
 * APIs Internas Customizadas.
 *

 * @created    11/11/24 11:00
 * @package    local_edukativa_apis
 * @copyright  Rafael Dantas
 
 */


//defined('MOODLE_INTERNAL') || die();

require('../../config.php');
require(__DIR__ . '/auth.php'); // Inclui o arquivo de autenticação

global $DB;

check_bearer_token(); // Verifica se o token de autenticação é válido

$fields = $DB->get_records_sql('SELECT uif.id as fieldId, uif.categoryid as categoryId, uic.name as categoryname, uif.shortname as shortname,
                                         uif.name as name, uif.datatype as fieldtype, uif.sortorder as ordem, uif.required as required,
                                         uif.locked as bloqueado, uif.signup as signup, uif.defaultdata as defaultdata, uif.param1 as parametros
                            FROM {user_info_field} as uif
                            inner join {user_info_category} as uic
                                on uif.categoryid = uic.id
                            order by categoryid');

$fields_array = array();
foreach ($fields as $field) {
  $fields_array[] = (array)$field;
}

header('Content-Type: application/json');
echo json_encode($fields_array);
?>
