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
 * Provides utility methods to make a html table sortable by column.
 * @copyright  Astor Bizard, 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    /**
     * Makes an html table sortable by creating clickable column headers and arrows.
     * @param {String} tableid The id of the table to make sortable.
     * @param {Number[]} nosortcols (optional) An array of column indexes to exclude.
     *     Negative indexes can be specified to exclude columns counting from the last.
     * @param {Number} defaultsortcol (optional) The index of the column to sort by default.
     *     This index is computed after excluding columns. Negative indexes are allowed.
     * @param {Number} nexcludedlines (optional) The number of lines to ignore at the end of the table.
     */
    function makeSortable(tableid, nosortcols = [], defaultsortcol, nexcludedlines = 0) {
        var sortdirection;
        if (defaultsortcol === null) {
            defaultsortcol = undefined;
        }
        var table = $('#' + tableid + ' tbody');
        var $ths = $('#' + tableid + ' thead th');
        var nths = $ths.length;
        $ths.each(function() {
            // Create sorting arrows except for excluded columns.
            var i = $ths.index(this);
            if (nosortcols.indexOf(i) == -1 && nosortcols.indexOf(i - nths) == -1) {
                $(this).append('<i class="icon fa fa-fw sortarrow"></i>');
            }
        });
        // Setup sort arrows / headers.
        $('#' + tableid + ' thead .sortarrow').each(function() {
            var $arrow = $(this);
            var $th = $arrow.closest('th');
            var icol = $ths.index($th);
            $th.css('cursor', 'pointer').css('user-select', 'none').css('vertical-align', 'top').click(function() {
                if (!$arrow.hasClass('sortarrow-active')) {
                    // Change of sorting column: remove old sorting column arrow and setup the new one.
                    $('i.sortarrow-active').removeClass('sortarrow-active fa-caret-down fa-caret-up');
                    $arrow.addClass('sortarrow-active fa-caret-down');
                    sortdirection = 1;
                } else {
                    // Change sorting direction and arrow display.
                    sortdirection = -sortdirection;
                    $arrow.toggleClass('fa-caret-up', sortdirection != 1).toggleClass('fa-caret-down', sortdirection == 1);
                }
                // Sort rows.
                var num = 1;
                var rows = Array.from(table.children('tr'));
                var endrows = rows.splice(-nexcludedlines, nexcludedlines);
                rows.sort(function(a, b) {
                        // Sort according to 'value' attributes or inner text.
                        var getCellValue = function(tr) {
                            return $(tr).find('.cell.c' + icol + ' [value]').attr('value') || $(tr).find('.cell.c' + icol).text();
                        };
                        var v1 = getCellValue(a);
                        var v2 = getCellValue(b);
                        return sortdirection *
                            (v1 !== '' && v2 !== '' && !isNaN(v1) && !isNaN(v2) ? v1 - v2 : v1.toString().localeCompare(v2));
                })
                .forEach(function(tr) {
                    // Renumber first column.
                    var $firstcol = $(tr).children('td').first();
                    $firstcol.find(':not(:empty)').addBack(':not(:empty)').each(function() {
                        if (/^[0-9]+$/.test($(this).html())) {
                            $(this).html(num);
                        }
                    });
                    num++;
                    // Re-insert row inside table.
                    table.append(tr);
                });
                // Re-insert excluded rows at the end.
                endrows.forEach(function(tr) {
                    table.append(tr);
                });
            });
            // Do not sort upon click on header link.
            $th.find('a').click(function(e) {
                e.stopPropagation();
            });
        }).eq(defaultsortcol).click();
    }

    return {
        makeSortable: makeSortable
    };
});