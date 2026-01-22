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
 * Completion Levels block configuration form definition.
 *
 * Inspired from Michael de Raadt's block_completion_progress.
 *
 * @package    block_completion_levels
 * @copyright  2022 Astor Bizard, 2016 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/locallib.php');

/**
 * Completion Levels block config form class.
 *
 * Inspired from Michael de Raadt's block_completion_progress.
 *
 * @package    block_completion_levels
 * @copyright  2022 Astor Bizard, 2016 Michael de Raadt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_completion_levels_edit_form extends block_edit_form {

    /**
     * @var array Javascript functions that need to be added on display (needed since Moodle 4.2 and dynamic forms).
     */
    protected $requiredjs = [];

    /**
     * {@inheritDoc}
     * @param MoodleQuickForm $mform
     * @see block_edit_form::specific_definition()
     */
    protected function specific_definition($mform) {
        global $COURSE, $OUTPUT;

        // The My home version is not configurable.
        if ($COURSE->id == 1) {
            return;
        }

        // Add block_completion_levels class to form element for styling,
        // as it is not done for the body element on block edition page.
        $mform->updateAttributes([ 'class' => $mform->getAttribute('class') . ' block_completion_levels' ]);

        // Start block specific section in config form.
        $mform->addElement('header', 'blocksettings', get_string('blocksettings', 'block'));
        // Set block instance title.
        $mform->addElement('text', 'config_blocktitle', get_string('config:blocktitle', 'block_completion_levels'));
        $mform->setDefault('config_blocktitle', get_string('defaultblocktitle', 'block_completion_levels'));
        $mform->setType('config_blocktitle', PARAM_TEXT);
        $mform->addHelpButton('config_blocktitle', 'config:blocktitle', 'block_completion_levels');

        $mform->addElement('text', 'config_progressover', get_string('config:displayprogressover', 'block_completion_levels'));
        $mform->setType('config_progressover', PARAM_ALPHANUM);
        $mform->addHelpButton('config_progressover', 'config:displayprogressover', 'block_completion_levels');
        $mform->setDefault('config_progressover', '');

        // Allow the block to be visible to a single group.
        $groups = groups_get_all_groups($COURSE->id);
        if (!empty($groups)) {
            $groupsmenu = [];
            $groupsmenu[0] = get_string('allparticipants');
            foreach ($groups as $group) {
                $groupsmenu[$group->id] = format_string($group->name);
            }
            $grouplabel = get_string('config:group', 'block_completion_levels');
            $mform->addElement('select', 'config_group', $grouplabel, $groupsmenu);
            $mform->setDefault('config_group', '0');
            $mform->addHelpButton('config_group', 'config:group', 'block_completion_levels');
        }

        $mform->addelement('selectyesno', 'config_markactivities', get_string('config:markactivities', 'block_completion_levels'));
        $mform->addHelpButton('config_markactivities', 'config:markactivities', 'block_completion_levels');

        $mform->addElement('selectyesno', 'config_filterinactiveusers',
                get_string('config:filterinactiveusers', 'block_completion_levels'));
        $mform->addHelpButton('config_filterinactiveusers', 'config:filterinactiveusers', 'block_completion_levels');
        $mform->setDefault('config_filterinactiveusers', 1);
        $mform->setAdvanced('config_filterinactiveusers');

        $mform->addElement('header', 'walloffame', get_string('walloffame', 'block_completion_levels'));
        $mform->setExpanded('walloffame');

        $walloffamenb = range(0, 50);
        $walloffamenb[0] = get_string('nostudents', 'block_completion_levels');
        $walloffamenb[-1] = get_string('allstudents', 'block_completion_levels');
        $mform->addElement('select', 'config_walloffamesize',
                get_string('config:walloffamesize', 'block_completion_levels'), $walloffamenb);
        $mform->addHelpButton('config_walloffamesize', 'config:walloffamesize', 'block_completion_levels');
        $mform->setDefault('config_walloffamesize', 0);

        $mform->addElement('selectyesno', 'config_anonymous', get_string('config:anonymouswalloffame', 'block_completion_levels'));
        $mform->addHelpButton('config_anonymous', 'config:anonymouswalloffame', 'block_completion_levels');
        $mform->disabledIf('config_anonymous', 'config_walloffamesize', 'eq', 0);

        $mform->addElement('selectyesno', 'config_usealternatenames',
                get_string('config:usealternatenames', 'block_completion_levels'));
        $mform->addHelpButton('config_usealternatenames', 'config:usealternatenames', 'block_completion_levels');
        $mform->disabledIf('config_usealternatenames', 'config_anonymous', 'eq', 1);
        $mform->disabledIf('config_usealternatenames', 'config_walloffamesize', 'eq', 0);

        $mform->addElement('selectyesno', 'config_showonlycogroups',
                get_string('config:showonlycogroupmembers', 'block_completion_levels'));
        $mform->addHelpButton('config_showonlycogroups', 'config:showonlycogroupmembers', 'block_completion_levels');
        $mform->disabledIf('config_showonlycogroups', 'config_walloffamesize', 'eq', 0);

        $mform->addElement('header', 'activities', get_string('activitiestracking', 'block_completion_levels'));
        $mform->setExpanded('activities');

        $mform->addElement('select', 'config_trackingmethod', get_string('config:trackingmethod', 'block_completion_levels'),
                [
                        get_string('trackingmethodcompletion', 'block_completion_levels'),
                        get_string('trackingmethodgrades', 'block_completion_levels'),
                ]);
        $mform->addHelpButton('config_trackingmethod', 'config:trackingmethod', 'block_completion_levels');

        $activities  = block_completion_levels_get_trackable_activities($COURSE->id);

        // Check that there are activities to monitor.
        if (empty($activities)) {
            $warningstring = get_string('notrackableactivities', 'block_completion_levels');
            $warning = new \core\output\notification($warningstring, \core\output\notification::NOTIFY_WARNING);
            $mform->addElement('static', '', get_string('activities'), $OUTPUT->render($warning->set_show_closebutton(false)));
        } else {
            $modinfo = get_fast_modinfo($COURSE, -1);
            $sectionid = -1;
            $infoicon = $OUTPUT->pix_icon('info', get_string('info'), 'block_completion_levels', [ 'class' => 'mx-1' ]);
            $warningicon = $OUTPUT->pix_icon('warning', get_string('warning'), 'block_completion_levels', [ 'class' => 'mx-1' ]);

            foreach ($activities as $activity) {
                $cmid = $activity->id;
                $cminfo = $modinfo->get_cm($cmid);

                if ($sectionid != $cminfo->sectionnum) {
                    // New section. Print header with Track all / Untrack all buttons.
                    $sectionid = $cminfo->sectionnum;
                    $sectiontext = html_writer::tag('h4', get_section_name($COURSE, $sectionid),
                            [ 'class' => 'd-inline-block mr-4 pr-2' ]);
                    $trackdata = [
                            'data-block-completion-levels-section' => $sectionid,
                            'data-block-completion-levels-role' => 'trackall',
                    ];
                    $trackdata['data-block-completion-levels-dotrack'] = '1';
                    $trackall = html_writer::span(get_string('trackall', 'block_completion_levels'),
                            'btn-link clickable mr-1', $trackdata);
                    $trackdata['data-block-completion-levels-dotrack'] = '0';
                    $untrackall = html_writer::span(get_string('untrackall', 'block_completion_levels'),
                            'btn-link clickable ml-1', $trackdata);
                    $buttons = $trackall . '/' . $untrackall;

                    $mform->addElement('html', $sectiontext . '<div class="d-inline-block">' . $buttons . '</div>');
                }

                $activityarray = [];
                $checkboxfield = 'config_activity['.$cmid.'][checkbox]';
                $weightfield = 'config_activity['.$cmid.'][weight]';
                $activityarray[] =& $mform->createElement('advcheckbox',
                        $checkboxfield,
                        get_string('dotrack', 'block_completion_levels'),
                        null,
                        [
                                'data-block-completion-levels-section' => $sectionid,
                                'data-block-completion-levels-role' => 'trackactivity-checkbox',
                        ]);

                $weightinput =& $mform->createElement('text',
                        $weightfield,
                        null, [ 'size' => 8, 'data-block-completion-levels-role' => 'activityweight-text' ]);
                $weightinput->_generateId();

                // Create custom label for text element, as it won't display within a group.
                $activityarray[] =& $mform->createElement('html',
                        '<label for="' . $weightinput->getAttribute('id') . '" class="mr-1 ml-2">' .
                            get_string('weight', 'block_completion_levels') .
                        '</label>');

                $activityarray[] = $weightinput;
                $mform->setType($weightfield, PARAM_INT);
                // TODO : default weight value (allow weight 0?).
                // $mform->setDefault($weightfield, 1); // This doesn't work (it sets weight to 1 even if a value is saved). Why?
                if (empty($this->block->config) || !is_object($this->block->config)) {
                    $mform->setDefault($weightfield, 1);
                }
                $mform->disabledIf($weightfield, $checkboxfield, 'notchecked');

                if (!$cminfo->visible) {
                    $helpicon = $OUTPUT->help_icon('hiddenfromstudents', 'block_completion_levels', true);
                    $activityarray[] =& $mform->createElement('html',
                            $warningicon . get_string('hiddenfromstudents', 'block_completion_levels') . $helpicon);
                }

                if (!$activity->completionenabled) {
                    $mform->disabledIf($checkboxfield, 'config_trackingmethod', 'eq', 0);
                    $mform->hideIf($weightfield, 'config_trackingmethod', 'eq', 0);
                    $activityarray[] =& $mform->createElement('html',
                            '<span data-block-completion-levels-role="nocompletion-message">' .
                                $infoicon . get_string('nocompletion', 'block_completion_levels') .
                            '</span>');
                }

                if (!$activity->hasgrades) {
                    $mform->disabledIf($checkboxfield, 'config_trackingmethod', 'eq', 1);
                    $mform->hideIf($weightfield, 'config_trackingmethod', 'eq', 1);
                    $activityarray[] =& $mform->createElement('html',
                            '<span data-block-completion-levels-role="nograde-message">' .
                                $infoicon . get_string('nograde', 'block_completion_levels') .
                            '</span>');
                }

                $icon = html_writer::empty_tag('img',
                        [
                                'src' => $cminfo->get_icon_url()->out(),
                                'title' => $cminfo->get_module_type_name(),
                                'class' => 'iconlarge activityicon',
                        ]);
                $mform->addGroup($activityarray, null, '<div class="activity">' . $icon. $cminfo->get_formatted_name() . '</div>');

            }

            $this->requiredjs[] = [
                    'func' => 'setupActivitites',
                    'params' => [],
                    'strings' => [ 'totalweight' ],
            ];
        }

        // Set custom badges.
        $mform->addElement('header', 'custom', get_string('badgeconfiguration', 'block_completion_levels'));

        $adminpixenabled = get_config('block_completion_levels', 'enablecustomlevelpix');
        // List existing pix. Three options:
        // - default pix (in blocks/point_view/pix),
        // - admin pix (in block administration settings),
        // - custom pix (in block configuration).
        $pix = [ 'default' => [], 'admin' => [], 'custom' => [] ];
        for ($i = 0; $i <= 10; $i++) {
            $pix['default'][] = $OUTPUT->image_url('default/' . $i, 'block_completion_levels');
        }
        if ($adminpixenabled) {
            $highest = block_completion_levels_find_highest_badge(1, 'preset');
            if ($highest !== null) {
                for ($i = 0; $i <= $highest; $i++) {
                    $pix['admin'][] = moodle_url::make_pluginfile_url(1, 'block_completion_levels', 'preset', 0, '/', $i);
                }
            }
        }
        $contextid = $this->block->context->id;
        $highest = block_completion_levels_find_highest_badge($contextid, 'levels_pix');
        if ($highest !== null) {
            for ($i = 0; $i <= $highest; $i++) {
                $pix['custom'][] = moodle_url::make_pluginfile_url($contextid, 'block_completion_levels', 'levels_pix', 0, '/', $i);
            }
            $deletecustombutton = html_writer::tag('button', get_string('deletecustompix', 'block_completion_levels'),
                    [
                            'type' => 'button',
                            'class' => 'btn btn-outline-warning ml-3',
                            'data-block-completion-levels-role' => 'delete-custom-pix',
                            'style' => 'display:none',
                    ]);
            $this->requiredjs[] = [
                    'func' => 'setupCustomBadges',
                    'params' => [ $contextid ],
                    'strings' => [ 'deletebadgeconfirmation' ],
            ];
        } else {
            $deletecustombutton = null;
        }

        $pixselect = [];
        $pixselect[] = &$mform->createElement('html', '<div class="pixselectgroup">');
        $this->create_badges_radioselect($mform, $pixselect, 'default', $pix);

        $maxlevelinput =& $mform->createElement('text', 'config_maxlevel', null, [ 'size' => 4 ]);
        $maxlevelinput->_generateId();

        // Create custom label for text element, as it won't display within a group.
        $pixselect[] = &$mform->createElement('html',
                '<span class="form-inline ml-4 pl-2">
                    <label for="' . $maxlevelinput->getAttribute('id') . '" class="mr-1">' .
                        get_string('config:maxlevel', 'block_completion_levels') .
                    '</label>' .
                    $OUTPUT->help_icon('config:maxlevel', 'block_completion_levels') .
                '</span>');
        $pixselect[] = $maxlevelinput;
        $mform->setType('config_maxlevel', PARAM_INT);
        $mform->setDefault('config_maxlevel', 10);
        $mform->disabledIf('config_maxlevel', 'config_pixselect', 'neq', 'default');
        if ($adminpixenabled && !empty($pix['admin'])) {
            $this->create_badges_radioselect($mform, $pixselect, 'admin', $pix);
        }
        $this->create_badges_radioselect($mform, $pixselect, 'custom', $pix, $deletecustombutton);
        $pixselect[] = &$mform->createElement('html', '</div>');
        $mform->addGroup($pixselect, 'pixselectgroup', get_string('badgestouse', 'block_completion_levels'), '', false);
        $mform->setDefault('config_pixselect', $adminpixenabled ? 'admin' : 'default');

        $fmoptions = ['subdirs' => 0, 'maxfiles' => 101, 'accepted_types' => '.png'];
        $mform->addElement('filemanager', 'config_levels_pix',
                get_string('config:levels_pix', 'block_completion_levels'), null, $fmoptions);
        $mform->disabledIf('config_levels_pix', 'config_pixselect', 'neq', 'custom');
        $mform->addHelpButton('config_levels_pix', 'config:levels_pix', 'block_completion_levels');

        $mform->addElement('header', 'completionnotifications',
                get_string('config:completionnotifications', 'block_completion_levels'));

        $mform->addElement('selectyesno', 'config_sendcompletionnotifications',
                get_string('config:sendcompletionnotifications', 'block_completion_levels'));
        $mform->setDefault('config_sendcompletionnotifications', 0);
        $mform->addHelpButton('config_sendcompletionnotifications',
                'config:sendcompletionnotifications', 'block_completion_levels');
        $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
        $notifiableusers = block_completion_levels_get_notifiable_users($COURSE->id, $fields);
        $group = [];
        foreach ($notifiableusers as $notifiableuser) {
            $username = fullname($notifiableuser);
            $group[] =& $mform->createElement('advcheckbox', 'config_sendnotificationsto[' . $notifiableuser->id . ']', $username);
        }
        $mform->addGroup($group, null, get_string('config:sendnotificationsto', 'block_completion_levels'));
        $mform->disabledIf('sendnotificationsto', 'config_sendcompletionnotifications', 'neq', 1);
    }

    /**
     * Helper function to create a radio select element for badges and add it to a form.
     *
     * @param MoodleQuickForm $mform
     * @param Html_Common[] $group Array to which the element should be added.
     * @param string $value
     * @param string[][] $pix Array of pix sources.
     * @param string|null $additionallegend Optional html to add after the badges.
     */
    protected function create_badges_radioselect(&$mform, &$group, $value, $pix, $additionallegend = null) {
        $group[] = $mform->createElement('radio', 'config_pixselect', '', get_string($value . 'pix', 'block_completion_levels'),
                $value, [ 'class' => 'pr-2 mr-0 w-100 justify-content-start' ]);

        $legend = '<label for="id_config_pixselect_' . $value . '" class="d-inline-block">';
        $npix = count($pix[$value]);
        $doellipsis = ($value == 'custom' && $npix > 11);
        foreach ($pix[$value] as $level => $src) {
            if ($doellipsis) {
                if ($level == 5) {
                    // Put the ellipsis at this position.
                    $legend .= html_writer::span('&bull;&bull;&bull;', 'pix-preview my-1 mr-1 text-center d-inline-block',
                            [ 'data-source' => $value ]);
                    continue;
                } else if ($level > 5 && $level < $npix - 5) {
                    // We are still in the ellipsed part.
                    continue;
                }
            }
            if ($value == 'default') {
                $title = ($level * 10) . '%' . ($level == 10 ? '' : '-' . ($level * 10 + 9) . '%');
            } else {
                $title = get_string('levela', 'block_completion_levels', $level);
            }
            $legend .= html_writer::empty_tag('img', [
                    'src' => $src,
                    'class' => 'pix-preview my-1 mr-1 d-inline-block',
                    'title' => $title,
                    'data-source' => $value,
            ]);
        }
        $legend .= '</label>';
        if ($additionallegend !== null) {
            $legend = '<span>' . $legend . $additionallegend . '</span>';
        }
        $group[] = $mform->createElement('html', $legend);
    }

    /**
     * {@inheritDoc}
     * @see moodleform::display()
     */
    public function display() {
        parent::display();
        self::call_javascript(); // Javascript needs to be added from display() function, since Moodle 4.2 and dynamic forms.
    }

    /**
     * Include javascript for edit form.
     */
    protected function call_javascript() {
        // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal -- $PAGE is the form page we want, $this->page is course page.
        global $PAGE;
        foreach ($this->requiredjs as $jsfunc) {
            $PAGE->requires->strings_for_js($jsfunc['strings'], 'block_completion_levels');
            $PAGE->requires->js_call_amd('block_completion_levels/editform', $jsfunc['func'], $jsfunc['params']);
        }
        // phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal
    }

    /**
     * {@inheritDoc}
     * @see moodleform::validation()
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     */
    public function validation($data, $files) {
        global $USER;
        $errors = parent::validation($data, $files);
        $progressover = $data['config_progressover'];
        if ($progressover !== '' && (!is_number($progressover) || intval($progressover) <= 0)) {
            $errors['config_progressover'] = get_string('validation:enterpositiveorempty', 'block_completion_levels');
        }
        if ($data['config_pixselect'] == 'default' && $data['config_maxlevel'] < 1) {
            $errors['config_maxlevel'] = get_string('errorlevelsincorrect', 'block_completion_levels');
        }
        if ($data['config_pixselect'] == 'custom') {
            // Make sure the user has uploaded all the badges.
            $fs = get_file_storage();
            $usercontext = context_user::instance($USER->id);
            $itemid = $data['config_levels_pix'];
            $i = 0;
            while ($fs->file_exists($usercontext->id, 'user', 'draft', $itemid, '/', $i . '.png')) {
                $i++;
            }
            if ($i == 0 || $i < count($fs->get_area_files($usercontext->id, 'user', 'draft', $itemid, '', false))) {
                $errors['config_levels_pix'] = get_string('validation:providebadges0toN', 'block_completion_levels');
            }
        }
        return $errors;
    }

    /**
     * {@inheritDoc}
     * @see block_edit_form::set_data()
     * @param stdClass|array $defaults object or array of default values
     */
    public function set_data($defaults) {
        if (is_array($defaults)) {
            $defaults = (object)$defaults;
        }
        if (!empty($this->block->config) && is_object($this->block->config)) {
            $draftid = file_get_submitted_draft_itemid('config_levels_pix');
            file_prepare_draft_area(
                    $draftid,
                    $this->block->context->id,
                    'block_completion_levels',
                    'levels_pix',
                    0,
                    [
                            'subdirs' => 0,
                            'maxfiles' => 20,
                            'accepted_types' => '.png',
                    ]);
            $defaults->config_levels_pix = $draftid;
            $this->block->config->levels_pix = $draftid;

            // TODO default weight value (allow weight 0?).
            foreach ($this->block->config->activity as $key => $value) {
                if (empty($value["weight"])) {
                    $this->block->config->activity[$key]["weight"] = 1;
                }
            }
        }

        parent::set_data($defaults);
    }

}
