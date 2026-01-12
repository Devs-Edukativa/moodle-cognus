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
 * Clearing old log entries.
 * @package  plagiarism_advacheck
 * @copyright © 2023 onwards Advacheck OU
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_advacheck\task;

class clear_action_log extends \core\task\scheduled_task
{
    private $sm;
    public function __construct()
    {
        $this->sm = get_string_manager();
    }

    public function get_name()
    {
        return get_string('clear_action_log', 'plagiarism_advacheck');
    }

    public function execute()
    {
        global $DB, $CFG;
        $store_action_time = get_config('plagiarism_advacheck', 'store_action_time');
        // Current time - (number of months) * (seconds in month).
        $time_store = time() - $store_action_time * 2592000;
        $sql = "SELECT COUNT(id) AS cnt FROM {plagiarism_advacheck_act_log} WHERE ? > time_action";
        $r = $DB->get_record_sql($sql, [$time_store]);
        mtrace(get_string('clear_action_log_cntrecfordel', 'plagiarism_advacheck', $r->cnt));
        // We will delete all entries before the specified date.
        $DB->execute("DELETE FROM {plagiarism_advacheck_act_log} WHERE ? > time_action", [$time_store]);
        return true;
    }

}
