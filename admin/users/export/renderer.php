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
 * mod/reader/admin/users/renderer.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/** Include required files */
require_once($CFG->dirroot.'/mod/reader/admin/users/renderer.php');

/**
 * mod_reader_admin_users_export_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_export_renderer extends mod_reader_admin_users_renderer {

    /**
     * mode_export
     *
     * @return string formatted html output
     */
    public function mode_export() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/reader/admin/users/export/form.php');

        $url = $this->page->url;
        $params = $url->params();
        $params['id'] = $this->reader->cm->id;
        $params['tab'] = $this->get_tab();
        $params['mode'] = mod_reader::get_mode('admin/users');
        $url->params($params);

        $mform = new mod_reader_admin_users_export_form($url->out(false));

        if ($data = $mform->get_submitted_data()) {
            $filename = $data->filename;

            $select = 'ra.*, u.username, rb.image, rl.currentlevel';
            $from   = '{reader_attempts} ra '.
                      'JOIN {user} u ON ra.userid = u.id '.
                      'JOIN {reader_books} rb ON ra.bookid = rb.id '.
                      'JOIN {reader_levels} rl ON ra.userid = rl.userid AND ra.reader = rl.readerid';
            $where  = 'ra.reader = ?';
            $order  = 'ra.userid, ra.quizid, ra.timefinish, ra.uniqueid DESC';
            $params = array($this->reader->id);

            if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {

                if (! headers_sent()) {
                    header('Content-Type: text/plain; filename="'.$filename.'"');
                }

                $userid = 0;
                $quizid = 0;
                $timefinish = 0;

                foreach ($attempts as $attempt) {

                    // ignore lower uniqueids with same userid/quizid/timefinish
                    if ($attempt->userid==$userid && $attempt->quizid==$quizid && $attempt->timefinish==$timefinish) {
                        continue;
                    }

                    $userid = $attempt->userid;
                    $quizid = $attempt->quizid;
                    $timefinish = $attempt->timefinish;

                    echo $attempt->username.','.
                         $attempt->uniqueid.','.
                         $attempt->attempt.','.
                         $attempt->sumgrades.','.
                         $attempt->percentgrade.','.
                         $attempt->bookrating.','.
                         $attempt->ip.','.
                         $attempt->image.','.
                         $attempt->timefinish.','.
                         $attempt->passed.','.
                         $attempt->percentgrade.','.
                         $attempt->currentlevel."\n";
                }
            }
        } else {
            $mform->display();
        }
    }
}
