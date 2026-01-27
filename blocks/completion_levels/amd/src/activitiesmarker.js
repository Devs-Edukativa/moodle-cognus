// This file is part of Moodle - https://moodle.org/
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
 * Module to mark activities on course page with a star.
 * @copyright  2022 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    /**
     * Call a function each time a course section is loaded.
     * @param {Function} call Function to call.
     */
    function callOnModulesListLoad(call) {
        call();

        // The following listener is needed for the Tiles course format, where sections are loaded on demand.
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (typeof (settings.data) !== 'undefined') {
                var data = JSON.parse(settings.data);
                if (data.length > 0 && typeof (data[0].methodname) !== 'undefined') {
                    if (data[0].methodname == 'format_tiles_get_single_section_page_html' // Tile load.
                        || data[0].methodname == 'format_tiles_log_tile_click') { // Tile load, cached.
                        call();
                    }
                }
            }
        });
    }
    return {
        markActivities: function(activitiesIDs, blockID, blockInstanceName) {
            // Wait that the DOM is fully loaded.
            $(function() {
                var activitiesMarker = $('<i>', {
                    'class': 'fa fa-star block_completion_levels-required-activity-marker',
                    'data-blockid': blockID,
                    title: M.util.get_string('completionrequiredforblockinstance', 'block_completion_levels', blockInstanceName)
                })[0].outerHTML;
                callOnModulesListLoad(function() {
                    activitiesIDs.forEach(function(activityID) {
                        var $instancename = $('#module-' + activityID + ' .activityname > a').first();
                        if ($instancename.parent().find(
                                '.block_completion_levels-required-activity-marker' +
                                '[data-blockid="' + blockID + '"]').length == 0) {
                            $instancename.append(activitiesMarker);
                        }
                    });
                });
            });
        }
    };
});