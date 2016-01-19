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
 * Create a table to display attempts at a Reader activity
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// get parent class
require_once($CFG->dirroot.'/mod/reader/admin/reports/tablelib.php');

/**
 * reader_admin_reports_userdetailed_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_userdetailed_table extends reader_admin_reports_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'studentview', 'username', 'fullname', 'groups', 'currentlevel',
        'difficulty', 'words', 'points', 'name',
        'selected', 'timefinish', 'duration', 'grade', 'passed',
        'attemptwords', 'termwords', 'attemptpoints', 'termpoints'
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array('studentview', 'groups', 'username', 'fullname', 'currentlevel', 'difficulty');

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array('studentview', 'groups', 'attemptwords', 'attemptpoints', 'termwords', 'termpoints');

    /** @var text columns in this table */
    protected $textcolumns = array('username', 'fullname', 'name');

    /** @var number columns in this table */
    protected $numbercolumns = array('currentlevel', 'difficulty', 'attemptwords', 'termwords', 'attemptpoints', 'termpoints');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('username', 'fullname', 'name');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('username' => SORT_ASC, 'timefinish' => SORT_DESC);

    /** @var filter fields ($fieldname => $advanced) */
    protected $filterfields = array(
        'group'      => 0, 'username'     => 1, 'realname'     => 0,
        'lastname'   => 1, 'firstname'    => 1, 'currentlevel' => 1,
        'difficulty' => 1, 'words'        => 1, 'points'       => 1, 'name'   => 1,
        'timefinish' => 1, 'duration'     => 1, 'grade'        => 1, 'passed' => 1
       );

    /** @var option fields */
    protected $optionfields = array('rowsperpage' => self::DEFAULT_ROWSPERPAGE,
                                    'showhidden'  => self::DEFAULT_SHOWHIDDEN,
                                    'showdeleted' => self::DEFAULT_SHOWDELETED,
                                    'sortfields'  => array());

    /** @var actions */
    protected $actions = array('deleteattempts', 'restoreattempts', 'passfailattempts');

    /**
     * Constructor
     *
     * @param int $uniqueid
     */
    public function __construct($uniqueid, $output) {
        $wordsfields = array('words',  'attemptwords',  'termwords');
        $pointsfields = array('points', 'attemptpoints', 'termpoints');
        $this->fix_words_or_points_fields($output, $wordsfields, $pointsfields);
        parent::__construct($uniqueid, $output);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to extract data from $DB                                         //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * select_sql
     *
     * @uses $DB
     * @param xxx $userid (optional, default=0)
     * @param xxx $attemptid (optional, default=0)
     * @return xxx
     */
    function select_sql($userid=0, $attemptid=0) {

        // get users who can access this Reader activity
        list($usersql, $userparams) = $this->select_sql_users();

        if ($this->output->reader->wordsorpoints==0) {
            $wordsorpointsalias = 'words';
            $wordsorpoints = 'words';
            $attemptalias = 'attemptwords';
            $termalias = 'termwords';
        } else {
            $wordsorpointsalias = 'points';
            $wordsorpoints = 'points';
            $attemptalias = 'attemptpoints';
            $termalias = 'termpoints';
        }

        $score    = "CASE WHEN (ra.passed = :passed1) THEN rb.$wordsorpoints ELSE 0 END";
        $grade    = 'CASE WHEN (ra.percentgrade IS NULL) THEN 0 ELSE ra.percentgrade END';
        $duration = 'CASE WHEN (ra.timefinish IS NULL OR ra.timefinish = 0) THEN 0 ELSE (ra.timefinish - ra.timestart) END';

        // total of all words/points this term up to and including this attempt
        $total    = 'rat.readerid = ra.readerid AND rat.userid = ra.userid AND '.
                    'rat.timefinish > :time1 AND rat.timefinish <= ra.timefinish AND '.
                    'rat.passed = :passed2 AND rat.deleted = :deleted';
        $total    = "(SELECT SUM(rbt.$wordsorpoints) FROM {reader_attempts} rat LEFT JOIN {reader_books} rbt ON rat.bookid = rbt.id WHERE $total)";

        $select = "ra.id, ra.timefinish, ra.layout, ".
                  "($duration) as duration, ($grade) as grade, ra.passed, ".
                  "($score) AS $attemptalias, $total AS $termalias, ".
                  $this->get_userfields('u', array('username'), 'userid').', '.
                  "rl.currentlevel, rb.difficulty, rb.$wordsorpointsalias, rb.name";
        $from   = '{reader_attempts} ra '.
                  'LEFT JOIN {user} u ON ra.userid = u.id '.
                  'LEFT JOIN {reader_levels} rl ON ra.readerid = rl.readerid AND u.id = rl.userid '.
                  'LEFT JOIN {reader_books} rb ON ra.bookid = rb.id';
        $where  = "ra.readerid = :readerid AND ra.timefinish > :time2";
        if ($usersql) {
            $where .= " AND u.id $usersql";
        }

        $params = array('readerid' => $this->output->reader->id,
                        'time1'    => $this->output->reader->ignoredate,
                        'time2'    => $this->output->reader->ignoredate,
                        'passed1'  => 'true',
                        'passed2'  => 'true',
                        'deleted'  => 0) + $userparams;

        //if ($this->output->reader->bookinstances) {
        //    $from  .= ' LEFT JOIN {reader_book_instances} rbi ON rb.id = rbi.bookid';
        //    $where .= ' AND rbi.id IS NOT NULL AND rbi.readerid = :rbireader';
        //    $params['rbireader'] = $this->output->reader->id;
        //}

        return $this->add_filter_params($select, $from, $where, '', '', '', $params);
    }

    /**
     * get_table_name_and_alias
     *
     * @param string $fieldname
     * @return array($tablename, $tablealias, $jointype, $jointable, $joinconditions)
     * @todo Finish documenting this function
     */
    public function get_table_name_and_alias($fieldname) {
        switch ($fieldname) {

            case 'currentlevel':
                return array('reader_levels', 'rl');

            case 'name':
            case 'words':
            case 'points':
            case 'difficulty':
                return array('reader_levels', 'rb');

            case 'termwords':
            case 'termpoints':
                return array('', '');

            case 'timefinish':
            case 'passed':
                return array('reader_attempts', 'ra');

            default:
                return parent::get_table_name_and_alias($fieldname);
        }
    }

    /**
     * records_exist
     */
    public function records_exist() {
        return $this->users_exist();
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format header cells                                           //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * header_attemptwords
     *
     * @return xxx
     */
    public function header_attemptwords() {
        $header = $this->header_words();
        return $this->header_add_period($header, 'thisattempt');
    }

    /**
     * header_attemptpoints
     *
     * @return xxx
     */
    public function header_attemptpoints() {
        $header = $this->header_points();
        return $this->header_add_period($header, 'thisattempt');
    }

    /**
     * header_termwords
     *
     * @return xxx
     */
    public function header_termwords() {
        $header = $this->header_words();
        return $this->header_add_period($header, 'thisterm');
    }

    /**
     * header_termpoints
     *
     * @return xxx
     */
    public function header_termpoints() {
        $header = $this->header_points();
        return $this->header_add_period($header, 'thisterm');
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format data cells                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * col_termwords
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_termwords($row)  {
        if ($this->is_downloading()) {
            return $row->termwords;
        } else {
            return number_format($row->termwords);
        }
    }

    /**
     * col_termpoints
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_termpoints($row)  {
        if ($this->is_downloading()) {
            return $row->termpoints;
        } else {
            return number_format($row->termpoints);
        }
    }
}
