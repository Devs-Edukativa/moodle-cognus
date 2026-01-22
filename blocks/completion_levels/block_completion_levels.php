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
 * Completion Levels block definition.
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
 * Completion Levels block class.
 *
 * Inspired from Michael de Raadt's block_completion_progress.
 *
 * @package   block_completion_levels
 * @copyright 2022 Astor Bizard, 2016 Michael de Raadt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_completion_levels extends block_base {
    /**
     * Sets the block title.
     */
    public function init() {
        $this->title = get_string('defaultblocktitle', 'block_completion_levels');
    }

    /**
     * We have admin settings for this block plugin.
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Controls the block title based on instance configuration.
     *
     * @return bool
     */
    public function specialization() {
        if (isset($this->config->blocktitle) && trim($this->config->blocktitle) > '') {
            $this->title = format_string($this->config->blocktitle);
        }
    }

    /**
     * Controls whether multiple instances of the block are allowed on a page.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal -- $this->page is not set when restoring, so we need global $PAGE.
        global $COURSE, $PAGE;
        // Allow multiple instances on a course. On dashboard, allow only one instance.
        // Special case: while restoring, we're at system level but we must allow multiple instances to be added to restored course.
        return $COURSE->id != SITEID || $PAGE->requestorigin === 'restore';
        // phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal
    }

    /**
     * Defines where the block can be added.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'course-view' => true,
            'site'        => true,
            'mod'         => false,
            'my'          => true,
        ];
    }

    /**
     * Creates the blocks main content.
     *
     * @return object
     */
    public function get_content() {
        global $COURSE, $OUTPUT;

        // If content has already been generated, don't waste time generating it again.
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content         = new stdClass();
        $this->content->text   = '';
        $this->content->footer = '';

        // Guests do not have any progress. Don't show them the block.
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        // Draw the multi-bar content for the Dashboard and Front page.
        if ($COURSE->id == 1) {
            $renderableblock = new block_completion_levels\widget\dashboard();
        } else {
            $renderableblock = new block_completion_levels\widget\course($this);
        }

        $this->content->text = $OUTPUT->render($renderableblock);

        return $this->content;
    }

    /**
     * {@inheritDoc}
     * @see block_base::get_required_javascript()
     */
    public function get_required_javascript() {
        parent::get_required_javascript();

        global $COURSE;
        if (!$this->page->user_is_editing() && isset($this->config->markactivities) && $this->config->markactivities) {

            $this->page->requires->string_for_js('completionrequiredforblockinstance', 'block_completion_levels');
            $this->page->requires->js_call_amd('block_completion_levels/activitiesmarker', 'markActivities',
                    [
                        array_keys(block_completion_levels_get_tracked_activities($COURSE->id, $this->config)),
                        $this->instance->id,
                        $this->title,
                    ]);
        }
    }

    /**
     * {@inheritDoc}
     * @see block_base::instance_config_save()
     * @param mixed $data
     * @param mixed $nolongerused
     */
    public function instance_config_save($data, $nolongerused = false) {
        $config = clone($data);
        if ($config->pixselect == 'custom') {
            $config->levels_pix = file_save_draft_area_files(
                    $data->levels_pix, $this->context->id, 'block_completion_levels', 'levels_pix', 0);
        }
        parent::instance_config_save($config, $nolongerused);
    }

    /**
     * {@inheritDoc}
     * @see block_base::instance_delete()
     */
    public function instance_delete() {
        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'block_completion_levels');
        return true;
    }

    /**
     * {@inheritDoc}
     * @see block_base::instance_copy()
     * @param int $fromid the id number of the block instance to copy from
     */
    public function instance_copy($fromid) {
        $fromcontext = context_block::instance($fromid);
        $fs = get_file_storage();
        // This extra check if file area is empty adds one query if it is not empty but saves several if it is.
        if (!$fs->is_area_empty($fromcontext->id, 'block_completion_levels', 'levels_pix', 0, false)) {
            $draftitemid = 0;
            file_prepare_draft_area($draftitemid, $fromcontext->id, 'block_completion_levels', 'levels_pix', 0);
            file_save_draft_area_files($draftitemid, $this->context->id, 'block_completion_levels', 'levels_pix', 0);
        }

        return true;
    }
}
