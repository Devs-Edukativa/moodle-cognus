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
 * Edition form reponsivity with total weight calculation and badges deletion.
 * @copyright  2022 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, ajax, notification) {
    return {
        setupActivitites: function() {
            var $trackingmodeselect = $('[name="config_trackingmethod"]');
            var updateMessages = function() {
                $('[data-block-completion-levels-role="nocompletion-message"]')
                .toggle($trackingmodeselect.val() == '0');
                $('[data-block-completion-levels-role="nograde-message"]')
                .toggle($trackingmodeselect.val() == '1');
            };
            $trackingmodeselect.change(updateMessages);
            updateMessages();

            $('[data-block-completion-levels-role="trackall"]').click(function() {
                $('[data-block-completion-levels-role="trackactivity-checkbox"]' +
                  '[data-block-completion-levels-section="' + $(this).data('block-completion-levels-section') + '"]' +
                  ':not([disabled])')
                .prop('checked', $(this).data('block-completion-levels-dotrack')).change();
                M.form.updateFormState($(this).closest('form').attr('id'));
            });

            var $totalweightmessage = $('<div>', {
                'class': 'd-inline-block text-info ml-1'
            })
            .html(M.util.get_string('totalweight', 'block_completion_levels', '<span></span>'));

            var updateTotalWeight = function() {
                var count = 0;
                $('[data-block-completion-levels-role="activityweight-text"]:not([disabled])').each(function() {
                    count += Number($(this).val());
                });
                $totalweightmessage.find('span').html(count);
            };

            $trackingmodeselect.after($totalweightmessage);
            setInterval(updateTotalWeight, 1000); // Update weight every second, in case we missed some change.

            $('[data-block-completion-levels-role="activityweight-text"]').focus(function() {
                $totalweightmessage.insertAfter($(this));
                updateTotalWeight();
            })
            .blur(function() {
                $totalweightmessage.insertAfter($trackingmodeselect);
            })
            .on('input', updateTotalWeight);
            $('[data-block-completion-levels-role="trackactivity-checkbox"]').change(updateTotalWeight);

        },
        setupCustomBadges: function(contextid) {
            var $deletebutton = $('[data-block-completion-levels-role="delete-custom-pix"]');
            $deletebutton.click(function(e) {
                M.util.show_confirm_dialog(e, {
                    message: M.util.get_string('deletebadgeconfirmation', 'block_completion_levels'),
                    callback: function() {
                        ajax.call([
                            {
                                methodname: 'block_completion_levels_delete_custom_pix',
                                args: {
                                    contextid: contextid,
                                    draftitemid: $('input[name="config_levels_pix"]').val()
                                },
                                done: function() {
                                    // Remove badge preview and button.
                                    $('.pix-preview[data-source="custom"]').remove();
                                    $deletebutton.remove();
                                    // Refresh draft area files.
                                    // # For an unknown reason, the following instruction with jQuery does not work
                                    // # (or at least does not trigger the expected listener).
                                    document.querySelector('fieldset[id^=id_config_levels_pix] .fp-path-folder-name').click();
                                },
                                fail: notification.exception
                            }
                        ]);
                    }
                });
            })
            .show();
        }
    };
});