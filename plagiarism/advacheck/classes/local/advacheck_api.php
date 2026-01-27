<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class for working with the Anti-Plagiarism API
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\local;

/**
 * API to connect check services
 */
class advacheck_api
{

    /**
     * @var \SoapClient Object with connection.
     */
    private $client;
    /**
     * @var string Developer's certificate from the Advacheck (AP). Without it, the server will not accept the document.
     */
    private $developerid = 'b5a00098-9691-494a-b56b-b1a3e846c3c3';

    /**
     * Gets the settings and creates an object to connect to the verification server.
     */
    public function __construct()
    {
        $settings = get_config('plagiarism_advacheck');
        $this->client = new \SoapClient(
            $settings->soap_wsdl,
            [
                "trace" => 1,
                "login" => $settings->login,
                "password" => $settings->password,
                "soap_version" => SOAP_1_1,
                "features" => SOAP_SINGLE_ELEMENT_ARRAYS,
            ]
        );
    }

    /**
     * Checks the connection and returns tariff information.
     *
     * @param string $login
     * @param string $password
     * @param string $soap_wsdl
     * @return \stdClass
     */
    public static function check_tarif($login, $password, $soap_wsdl)
    {

        $tarif_data = new \stdClass();
        // We are trying to create a connection.
        $connection = new \SoapClient(
            $soap_wsdl,
            [
                "trace" => 1,
                "login" => $login,
                "password" => $password,
                "soap_version" => SOAP_1_1,
                "features" => SOAP_SINGLE_ELEMENT_ARRAYS
            ]
        );

        try {
            $tarif_data->tarif = $connection->GetTariffInfo()->GetTariffInfoResult;
        } catch (\SoapFault $e) {
            // Displays errors when trying to connect.
            $m = $e->getMessage();
            $m .= get_string('requestheaders', 'plagiarism_advacheck') . $connection->__getLastRequestHeaders();
            $m .= get_string('request', 'plagiarism_advacheck') . $connection->__getLastRequest();
            $m .= get_string('response', 'plagiarism_advacheck') . $connection->__getLastResponse();
            $m .= get_string('ecode', 'plagiarism_advacheck') . $e->getCode();
            $m .= get_string('trace', 'plagiarism_advacheck') . $e->getTraceAsString();
            $m .= get_string('soap_wsdl', 'plagiarism_advacheck') . $soap_wsdl;

            $tarif_data->error = $m;
        }
        return $tarif_data;
    }

    /**
     * Uploads a document and its attributes.
     *
     * @param string $filepath
     * @param string $filecontent
     * @param int $courseid Course ID to obtain the course instructor.
     * @param mixed $auto string|bool false - then the teacher checks, if the text, then start from the task.
     * @param string $conn_error  String to record the error.
     * @param array $da Document attributes.
     * @return mixed
     */
    public function upload_doc($filepath, $filecontent, $courseid, $auto, &$conn_error, $da)
    {
        global $DB, $USER;
        // Retrieving the course teacher ID to look up the User ID record.
        if (!$auto) {
            $t = new \stdClass();
            if ($auto) {
                $sql = "SELECT u.id
                          FROM {user} u
                     LEFT JOIN {context} ctx ON ctx.instanceid = ?
                          JOIN {role_assignments} tra ON tra.contextid = ctx.id 
                               AND u.id = tra.userid
                         WHERE  tra.roleid = 3
                      ORDER BY u.lastname";

                $t = $DB->get_record_sql($sql, [$courseid], IGNORE_MULTIPLE);
                // If there are no teachers in the course, then write ExternalUserID=0.
                if (!$t) {
                    $t->id = 0;
                }
            } else {
                $t->id = $USER->id;
            }
        } else {
            $t = new \stdClass();
            $t->id = $auto;
        }

        // Structure of the downloaded file.
        $data = [
            "Data" => $filecontent,
            "FileName" => basename($filepath),
            "FileType" => "." . substr(strrchr($filepath, "."), 1),
            "ExternalUserID" => $t->id,
            "DeveloperID" => $this->developerid,
        ];
        // Uploading a file.
        try {
            $conn_error = false;

            $uploadResult = $this->client->UploadDocument(["data" => $data, "attributes" => ["DocumentDescription" => $da]]);

            // Document ID. If it is not an archive that is being downloaded, then the list of downloaded documents will consist of one element.
            $id = $uploadResult->UploadDocumentResult->Uploaded[0]->Id;
            // Deleting a temporary file.
            if (!isset($id)) {
                // If there is a problem with the connection.
                $conn_error = true;
                return get_string('error_upload_service', 'plagiarism_advacheck');
            }
            return $uploadResult->UploadDocumentResult->Uploaded[0];
        } catch (\SoapFault $e) {
            mtrace(date('Y-m-d H:i:s ') . var_export($this->client->__getLastRequestHeaders(), true));
            mtrace(date('Y-m-d H:i:s ') . var_export($this->client->__getLastRequest(), true));
            mtrace(date('Y-m-d H:i:s ') . var_export($this->client->__getLastResponse(), true));
            mtrace(date('Y-m-d H:i:s ') . $e->getCode());
            mtrace(date('Y-m-d H:i:s ') . $e->getTraceAsString());
            if ($e->faultcode == 'HTTP') {
                // If there is a problem with the connection.
                $conn_error = true;
                return get_string('error_upload_connect', 'plagiarism_advacheck');
            }
            return $e->getMessage();
        }
    }

    /**
     * Loads additional information fields of the document into the AP.
     *
     * @param int $ap_docid ID of the document in the AP.
     * @param array $custom_attrs  Additional document information fields.
     * @return mixed boolean | error message
     */
    public function upload_doc_attr($ap_docid, $custom_attrs)
    {
        $externalid = new \stdClass();
        $externalid->Id = $ap_docid;
        $externalid->External = null;
        try {
            $this->client->UpdateDocumentAttributes(["docId" => $externalid, "attributes" => $custom_attrs]);
            return true;
        } catch (\SoapFault $e) {
            return $e->getMessage();
        }
    }

    /**
     * Starts document verification.
     *
     * @param int $ap_docid ID of the document in the AP.
     * @param array $ignoresections An array of document sections to exclude from verification.
     * @return mixed boolean | error message
     */
    public function start_check($ap_docid, $ignoresections)
    {
        $externalid = new \stdClass();
        $externalid->Id = $ap_docid;
        $externalid->External = null;

        $checkdocparameters = [
            "DisableFuzzySearch" => false,
            "IgnoreSections" => $ignoresections
        ];

        // Initiate verification.
        try {
            $this->client->CheckDocument(["docId" => $externalid, "checkServicesList" => [], "checkDocParameters" => $checkdocparameters]);
            return true;
        } catch (\SoapFault $e) {
            return $e->getMessage();
        }
    }

    /**
     * Gets the current status of the last check.
     *
     * @param int $ap_docid ID of the document in the AP.
     * @return mixed \stdClass | error message
     */
    public function get_current_check_status($ap_docid)
    {
        $externalid = new \stdClass();
        $externalid->Id = $ap_docid;
        $externalid->External = null;
        $st_data = new \stdClass();
        try {
            // Statuses InProgress Failed Ready None (no checks were performed).
            $status = $this->client->GetCheckStatus(["docId" => $externalid]);
            $st_data->status = $status->GetCheckStatusResult->Status;
            // Approximate time until the end of the check.
            $st_data->wait_time = $status->GetCheckStatusResult->EstimatedWaitTime;
            $st_data->msg = $status->GetCheckStatusResult->FailDetails;
            if ($st_data->status === "Ready") {
                $st_data->reportedit = $status->GetCheckStatusResult->Summary->ReportWebId;
                $st_data->reportread = $status->GetCheckStatusResult->Summary->ReadonlyReportWebId;
                $st_data->shortreport = $status->GetCheckStatusResult->Summary->ShortReportWebId;
                $st_data->plagiarism = $status->GetCheckStatusResult->Summary->DetailedScore->Plagiarism;
                $st_data->legal = $status->GetCheckStatusResult->Summary->DetailedScore->Legal;
                // Self-citation percentage.
                $st_data->selfcite = $status->GetCheckStatusResult->Summary->DetailedScore->SelfCite;
                // Suspicious document.
                $st_data->issuspicious = $status->GetCheckStatusResult->Summary->IsSuspicious;
                // We record the time of the LMS server, not the AP server.
                $st_data->timecheck_end = time();
            }
        } catch (\SoapFault $e) {
            $m = $e->getMessage();
            $st_data->error = $m;
        }
        return $st_data;
    }

    /**
     * Requests the result of the check.
     *
     * @param int $ap_docid ID of the document in the AP.
     * @return \stdClass
     */
    public function get_check_report($ap_docid)
    {
        $externalid = new \stdClass();
        $externalid->Id = $ap_docid;
        $externalid->External = null;
        // Get the report.
        $result = new \stdClass();
        try {
            $report = $this->client->GetReportView(["docId" => $externalid]);
            $result->reportedit = $report->GetReportViewResult->Summary->ReportWebId;
            $result->reportread = $report->GetReportViewResult->Summary->ReadonlyReportWebId;
            $result->shortreport = $report->GetReportViewResult->Summary->ShortReportWebId;
            $result->plagiarism = $report->GetReportViewResult->Summary->DetailedScore->Plagiarism;
            $result->legal = $report->GetReportViewResult->Summary->DetailedScore->Legal;
            // Self-citation percentage.
            $result->selfcite = $report->GetReportViewResult->Summary->DetailedScore->SelfCite;
            // Suspicious document.
            $result->issuspicious = $report->GetReportViewResult->Summary->IsSuspicious;
            // We record the time of the LMS server, not the AP server.
            $result->timecheck_end = time();
        } catch (\SoapFault $e) {
            $m = $e->getMessage();
            $result->error = $m;
        }
        return $result;
    }

    /**
     * Adds a document to the index and removes it from the index
     *
     * @param int $docid ID of the document in the AP.
     * @param boolean $addToIndex true - to index, false - from index
     * @return mixed boolean | error message
     */
    public function set_index_status($docid, $addToIndex)
    {
        $externalid = new \stdClass();
        $externalid->Id = $docid;
        $externalid->External = null;
        try {
            $this->client->SetIndexedStatus(["docId" => $externalid, "addToIndex" => $addToIndex]);
            return true;
        } catch (\SoapFault $e) {
            return $e->getMessage();
        }
    }

    /**
     * Returns information about a document
     *
     * @param int $docid ID of the document in the AP.
     * @return mixed boolean | \stdClass
     */
    public function get_document_info($docid)
    {
        $externalid = new \stdClass();
        $externalid->Id = $docid;
        $externalid->External = null;
        try {
            $d = $this->client->GetDocumentInfo(["docId" => $externalid]);
            return $d->GetDocumentInfoResult;
        } catch (\SoapFault $e) {
            return $e->getMessage();
        }
    }

    /**
     * Download the certificate in PDF.
     *
     * @param int $docid ID of the document in the AP.
     * @param string $author Author's full initials.
     * @param string $verifier Full initials of the inspector.
     * @return mixed
     */
    public function get_verification_report($docid, $author, $verifier)
    {
        global $USER, $CFG;
        $externalid = new \stdClass();
        $externalid->Id = $docid;
        $externalid->External = null;
        try {
            $vopt = ['Author' => $author, 'Verifier' => $verifier];
            $l = empty($USER->lang) ? $CFG->lang : $USER->lang;
            $lang = ['Language' => $l];
            $pdf = $this->client->GetVerificationReport(["docId" => $externalid, "options" => $vopt, 'formattingOptions' => $lang]);
            return $pdf;
        } catch (\SoapFault $e) {
            return $e->getMessage();
        }
    }

}
