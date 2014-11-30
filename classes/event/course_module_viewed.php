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
 * This file contains an event for when a reader activity is viewed.
 *
 * @package    mod_reader
 * @copyright  2013 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reader\event;

/** prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/**
 * Event for when a reader activity is viewed.
 *
 * @package    mod_reader
 * @copyright  2014 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'reader';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Does this event replace a legacy event?
     *
     * @return string legacy event name
     */
    public static function get_legacy_eventname() {
        return 'reader_viewed';
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return 'User with id ' . $this->userid . ' viewed content ' . $this->get_url() . ' In phase ' . $this->other['content'];
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_reader_viewed', 'mod_reader');
    }

    /**
     * Returns relevant URL.
     * @return \moodle_url
     */
    public function get_url() {
        $url = '/mod/reader/view.php';
        return new \moodle_url($url, array('id'=>$this->context->instanceid));
    }

    /**
     * Legacy event data if get_legacy_eventname() is not empty.
     *
     * @return mixed
     */
    protected function get_legacy_eventdata() {
        global $USER;
        $reader = $this->get_record_snapshot('reader', $this->objectid);
        $course    = $this->get_record_snapshot('course', $this->courseid);
        $cm        = $this->get_record_snapshot('course_modules', $this->context->instanceid);
        return (object)array('reader' => $reader, 'course' => $course, 'cm' => $cm, 'user' => $USER);
    }

    /**
     * replace add_to_log() statement.
     *
     * @return array of parameters to be passed to legacy add_to_log() function.
     */
    protected function get_legacy_logdata() {
        $url = new \moodle_url('view.php', array('id' => $this->context->instanceid));
        return array($this->courseid, 'reader', 'view', $url->out(), $this->objectid, $this->context->instanceid);
    }
}
