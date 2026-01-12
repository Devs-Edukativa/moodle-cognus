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
 * A file with constants
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace plagiarism_advacheck\local;

/**
 * Class with constants
 */
class advacheck_constants
{
    // 2 - Check in automatic mode AUTO.
    const PLAGIARISM_ADVACHECK_AUTOMODE = 2;
    // 1 - Check in MANUAL mode.
    const PLAGIARISM_ADVACHECK_MANUALMODE = 1;
    // 0 - Disable the DISABLED check.
    const PLAGIARISM_ADVACHECK_DISABLEDMODE = 0;
    // The upload help option. Not all brands have this option.
    const PLAGIARISM_ADVACHECK_VIEW_CERTIFICATE = 0;
    // Checking/unloading status.
    // Response text or file not found.
    const PLAGIARISM_ADVACHECK_NOTFOUND = 0;
    // The response text or file was not found.
    const PLAGIARISM_ADVACHECK_WAITBLOCK = 1;
    // It is waiting to be uploaded to the AP.
    const PLAGIARISM_ADVACHECK_WAITUPLOAD = 2;
    // Uploaded to the AP.
    const PLAGIARISM_ADVACHECK_UPLOADED = 3;
    // Uploaded and verified in AP.
    const PLAGIARISM_ADVACHECK_CHECKED = 4;
    // Loaded in the AP index.
    const PLAGIARISM_ADVACHECK_ININDEX = 5;
    // In the process of loading and waiting for verification results.
    const PLAGIARISM_ADVACHECK_UPLOADING = 6;
    // In the process of verification.
    const PLAGIARISM_ADVACHECK_CHECKING = 7;
    // Text less than N words.
    const PLAGIARISM_ADVACHECK_LESSNWORDS = -1;
    // Invalid file type.
    const PLAGIARISM_ADVACHECK_INVALIDFILETYPE = -2;
    // Invalid file type.
    const PLAGIARISM_ADVACHECK_NORIGHTCHECKEDBY = -3;
    // Verification error in AP, as a verification status.
    const PLAGIARISM_ADVACHECK_ERROR_CHECK = -4;

    // Error constants.
    // Error while trying to download.
    const PLAGIARISM_ADVACHECK_ERROR_UPLOADING = -101;
    // An error occurred while attempting to initiate a scan.
    const PLAGIARISM_ADVACHECK_ERROR_CHECKING = -102;
    // There was an error trying to get the verification status.
    const PLAGIARISM_ADVACHECK_ERROR_GET_STATUS = -103;
    // There was an error trying to get the scan report.
    const PLAGIARISM_ADVACHECK_ERROR_GET_REPORT = -104;
    // Error when trying to add/remove to index.
    const PLAGIARISM_ADVACHECK_ERROR_INDEX = -105;

    // Types of documents being checked.
    // Assign submittion text in the assign.
    const PLAGIARISM_ADVACHECK_ASSIGN = 3;
    // Text in the forum.
    const PLAGIARISM_ADVACHECK_FORUM = 2;
    // File.
    const PLAGIARISM_ADVACHECK_FILE = 1;
    // The answer is in the workshop.
    const PLAGIARISM_ADVACHECK_WORKSHOP = 4;
    // The answer is ecce in the quiz.
    const PLAGIARISM_ADVACHECK_QUIZ = 5;

    /**
     * Returns array of works_types
     * @return array
     */
    public static function get_advacheck_works_types()
    {
        $advacheckworkstypes = [
            "none" => get_string('none', 'plagiarism_advacheck'),
            "Abstract" => get_string('abstract', 'plagiarism_advacheck'),
            "Article" => get_string('article', 'plagiarism_advacheck'),
            "Book" => get_string('book', 'plagiarism_advacheck'),
            "CandidateDissertation" => get_string('candidatedissertation', 'plagiarism_advacheck'),
            "CandidateDissertationAbstract" => get_string('candidatedissertationabstract', 'plagiarism_advacheck'),
            "CollectionOfExercises" => get_string('collectionofexercises', 'plagiarism_advacheck'),
            "CollectionOfWorks" => get_string('collectionofworks', 'plagiarism_advacheck'),
            "Coursework" => get_string('coursework', 'plagiarism_advacheck'),
            "DoctoralDissertation" => get_string('doctoraldissertation', 'plagiarism_advacheck'),
            "DoctoralDissertationAbstract" => get_string('doctoraldissertationabstract', 'plagiarism_advacheck'),
            "EducationalVisualEdition" => get_string('educationalvisualedition', 'plagiarism_advacheck'),
            "FinalQualifyingWork" => get_string('finalqualifyingwork', 'plagiarism_advacheck'),
            "GraduateWork" => get_string('graduatework', 'plagiarism_advacheck'),
            "Guidelines" => get_string('guidelines', 'plagiarism_advacheck'),
            "LaboratoryPractice" => get_string('laboratorypractice', 'plagiarism_advacheck'),
            "MasterDissertation" => get_string('masterdissertation', 'plagiarism_advacheck'),
            "MethodicalInstructions" => get_string('methodicalinstructions', 'plagiarism_advacheck'),
            "Monograph" => get_string('monograph', 'plagiarism_advacheck'),
            "Other" => get_string('other', 'plagiarism_advacheck'),
            "PracticalWork" => get_string('practicalwork', 'plagiarism_advacheck'),
            "PracticeReport" => get_string('practicereport', 'plagiarism_advacheck'),
            "ResearchReport" => get_string('researchreport', 'plagiarism_advacheck'),
            "ScientificQualificationWork" => get_string('scientificqualificationwork', 'plagiarism_advacheck'),
            "TeachingGuide" => get_string('teachingguide', 'plagiarism_advacheck'),
            "Textbook" => get_string('teachingguide', 'plagiarism_advacheck'),
            "Tutorial" => get_string('textbook', 'plagiarism_advacheck'),
            "DoctoralDissertationPhD" => get_string('tutorial', 'plagiarism_advacheck'),
            "DoctoralDissertationAbstractPhD" => get_string('doctoraldissertationabstractphd', 'plagiarism_advacheck'),
            "DiplomaProject" => get_string('diplomaproject', 'plagiarism_advacheck'),
        ];
        return $advacheckworkstypes;
    }


}