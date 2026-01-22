<?php
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

namespace block_completion_levels\output\mobile;

use plugin_renderer_base;
use renderable;
use templatable;

/**
 * Renderer for mobile app.
 *
 * @package    block_completion_levels
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * {@inheritDoc}
     * @param renderable $widget instance with renderable interface
     * @see plugin_renderer_base::render()
     */
    public function render(renderable $widget) {
        global $CFG;
        $classparts = explode('\\', get_class($widget));
        $classname = array_pop($classparts);
        $component = array_shift($classparts);
        if ($component === 'block_completion_levels'
                && file_exists($CFG->dirroot . '/blocks/completion_levels/templates/mobile-' . $classname . '.mustache')
                && $widget instanceof templatable) {
            // There is a template for mobile app for this widget - render with that template.
            return $this->render_from_template($component . '/mobile-' . $classname, $widget->export_for_template($this));
        } else {
            // There is no template for mobile app for this widget - fallback to default rendering behaviour.
            return parent::render($widget);
        }
    }
}
