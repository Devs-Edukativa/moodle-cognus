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
 * The page with the plugin settings.
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once (__DIR__ . '/../../config.php');
require_once ($CFG->libdir . '/adminlib.php');
require_once ($CFG->libdir . '/plagiarismlib.php');
require_once ($CFG->dirroot . '/plagiarism/advacheck/lib.php');
require_once ($CFG->dirroot . '/plagiarism/advacheck/classes/forms/settings_form.php');

require_login($SITE, false);

admin_externalpage_setup('plagiarismadvacheck');

$context = context_system::instance();

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");
$tmptab = optional_param('tab', 'common', PARAM_TEXT);

$row = $tabs = [];
$url_common = new moodle_url('/plagiarism/advacheck/settings.php', ['tab' => 'common']);
$row[] = new tabobject('common', $url_common, get_string('common', 'plagiarism_advacheck'));
// Added the first row of tabs.
$tabs[] = $row;

$customdata = new stdClass();
$customdata->tab = 'common';
$url_form = new moodle_url('/plagiarism/advacheck/settings.php', ['tab' => 'common']);
$mform = new plagiarism_advacheck_settings_form($url_form, $customdata);

$notice = false;

$mform->process($notice);

echo $OUTPUT->header();
echo print_tabs($tabs, 'common', null, null, true);
echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
if ($notice) {
    echo $notice;
}
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
