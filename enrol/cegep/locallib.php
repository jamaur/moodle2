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
 * Local stuff for meta course enrolment plugin.
 *
 * @package    enrol
 * @subpackage cegep
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event handler for meta enrolment plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */

$cegep_extdb = null;

class enrol_cegep_handler {

    public static function db_init() {
        global $CFG, $cegep_extdb;

        if (!enrol_is_enabled('cegep')) {
            return true;
        }

        if (!is_null($cegep_extdb)) {
            return $cegep_extdb;
        }

        require_once($CFG->libdir.'/adodb/adodb.inc.php');
        require_once($CFG->libdir.'/moodlelib.php');

        // Connect to the external database (forcing new connection)
        $cegep_extdb = ADONewConnection(get_config('enrol_cegep', 'dbtype'));
        if (get_config('enrol_cegep', 'debugdb')) {
            $cegep_extdb->debug = true;
            ob_start(); //start output buffer to allow later use of the page headers
        }   

        $cegep_extdb->Connect(get_config('enrol_cegep', 'dbhost'), get_config('enrol_cegep', 'dbuser'), get_config('enrol_cegep', 'dbpass'), get_config('enrol_cegep', 'dbname'), true);
        $cegep_extdb->SetFetchMode(ADODB_FETCH_ASSOC);
        if (get_config('enrol_cegep', 'dbsetupsql')) {
            $cegep_extdb->Execute(get_config('enrol_cegep', 'dbsetupsql'));
        }   
        return $cegep_extdb;
    } 

    public function groups_group_created($ggc) {
        return true;
    }

    /**
     * This event creates a grouping called "Sections" for all
     * courses that are created.
     */
    public function course_created($cc) {
        global $DB, $CFG, $cegep_extdb;

		if (!is_null($cegep_extdb) && !$cegep_extdb->IsConnected()) {
			$cegep_extdb = null;
		}

        if (is_null($cegep_extdb)) {
            $cegep_extdb = enrol_cegep_handler::db_init();
        }

        if (!enrol_is_enabled('cegep')) {
            return true;
        }

        if (!$course = $DB->get_record('course', array('id'=>$cc->id))) {
            return true;
        }

        require_once($CFG->dirroot . '/group/lib.php');

        $data = new stdClass();
        $data->name = get_string('groupingsections', 'enrol_cegep');
        $data->courseid = $course->id;

        if ($id = groups_create_grouping($data)) {
			//    Do nothing: Disabled per Rafael's request on 14-Aug-2012
			//    $DB->set_field('course', 'defaultgroupingid', $id, array('id'=>$course->id));
        }

        //clean up
        unset($data);

        //add a sane default course summary.
        $summary = '<h2 style="text-align: center;">' . $cc->fullname . '</h2>';

        //test the custom "child_ids" variable, which should be an
        //array of course IDs that are children to this newly created course.
        //that means the course created was merged.
        //consequently, in the extdb, we need to get all the students in the 
        //child courses, put them in the parent course, and flag the rows as "custom",
        //so that when we sync with Clara, the custom rows are not deleted.

        require_once($CFG->dirroot . '/blocks/mydawson/lib.php');

        if (isset($cc->child_ids) && is_array($cc->child_ids) && count($cc->child_ids) > 0) {
            mydawson_merge_courses($cc->id, $cc->child_ids);
        }

        $sections = explode("+", mydawson_extract_section($cc->idnumber));

        $summary .= '<h3 style="text-align: center;">';

        if (count($sections) > 1) {
            $count = 0;
            $first = true;

            $summary .= get_string("sections", "block_mydawson") . " ";

            foreach ($sections as $s) {
                if ($first) {
                    if (count($sections) == 2) {
                        $summary .= " $s ";
                    }
                    else {
                        $summary .= " $s, ";
                    }
                    $first = false;
                }
                else {
                    if ($count == (count($sections) - 1)) {
                        $summary .= get_string("and", "block_mydawson") . " $s";
                    }
                    else {
                        $summary .= "$s, ";
                    }
                }
                $count++;
            }
        }
        else {
            $summary .= get_string("section", "block_mydawson") . " " . $sections[0];
        }

        $summary .= " - " . mydawson_session_to_string(mydawson_extract_session($cc->idnumber));
        $summary .= "</h3>";

        if (($teacher_names = mydawson_get_teacher_names($cc->idnumber))) {
            $first = true;
            foreach ($teacher_names as $teacher_name) {
                if ($first && count($teacher_names) > 1) {
                    $summary .= '<h3 style="text-align: center;">Teachers:</h3>';
                }
                else if ($first) {
                    $summary .= '<h3 style="text-align: center;">Teacher: ' . $teacher_name . '</h3>';
                    $first = false;
                    continue;
                }

                $summary .= '<h3 style="text-align: center;">' . $teacher_name . '</h3>';
                $first = false;
            }
        }


        $DB->set_field('course', 'summary', $summary, array('id'=>$cc->id));

		//default to 15 topics
        $DB->set_field('course', 'numsections', 15, array('id'=>$cc->id));

        //let's make a "section 0" as well with the same text so it appears
        //at the top of the course page.
        if ($section = $DB->get_record("course_sections", array("course"=>$cc->id, "section"=>0))) {
            $section->summary = $summary;
            $DB->set_field("course_sections", "summary", $summary, array("id"=>$section->id));
        }

        return true;
    }

    public function role_assigned($ra) {
        return true;
    }

    public function role_unassigned($ra) {
        return true;
    }

    public function user_enrolled($ue) {
        global $DB, $CFG, $cegep_extdb;

		if (!is_null($cegep_extdb) && !$cegep_extdb->IsConnected()) {
			$cegep_extdb = null;
		}

        if (is_null($cegep_extdb)) {
            $cegep_extdb = enrol_cegep_handler::db_init();
        }

        if (!enrol_is_enabled('cegep')) {
            return true;
        }

        if (!$course = $DB->get_record('course', array('id'=>$ue->courseid))) {
            return true;
        }

        if (!$user = $DB->get_record('user', array('id'=>$ue->userid))) {
            return true;
        }

        require_once($CFG->dirroot . '/group/lib.php');

        $query = "SELECT " . get_config('enrol_cegep', 'remotesectionfield') . " FROM `" . get_config('enrol_cegep', 'remoteenroltable') . "` WHERE " . get_config('enrol_cegep', 'remotecoursefield') . "='$course->idnumber' AND " . get_config('enrol_cegep', 'remoteuserfield') . "='$user->idnumber'";

        if ($rs = $cegep_extdb->Execute($query)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {

                    $groupname = get_string('groupsection', 'enrol_cegep') . " " . $fields['section'];

                    if (!$group = $DB->get_record('groups', array('courseid'=>$course->id, 'name'=>$groupname))) {
                        $groupdata = new stdClass();
                        $groupdata->name = $groupname;
                        $groupdata->courseid = $course->id;
                        $groupid = groups_create_group($groupdata);

                        if ($grouping = $DB->get_record('groupings', array('courseid'=>$course->id, 'name'=>get_string('groupingsections', 'enrol_cegep')))) {
                            groups_assign_grouping($grouping->id, $groupid);
                        }

                    }
                    else {
                        $groupid = $group->id;
                    }
                    groups_add_member($groupid, $user->id);
                }
            }
        }

		/* if the enrolled user is a teacher, add them to the dawsonteachers cohort. */

        $query = "SELECT * FROM `" . get_config('enrol_cegep', 'remoteenroltable') . "` WHERE " . get_config('enrol_cegep', 'remotecoursefield') . "='$course->idnumber' AND " . get_config('enrol_cegep', 'remoteuserfield') . "='$user->idnumber' AND " . get_config('enrol_cegep', 'remoterolefield') . "='editingteacher'";

        if ($rs = $cegep_extdb->Execute($query)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    if (($teacher_cohort = $DB->get_record('cohort', array('idnumber'=>'dawsonteachers'), '*', MUST_EXIST))) {
                        require_once($CFG->dirroot . '/cohort/lib.php');

                        if (!$DB->record_exists('cohort_members', array('cohortid'=>$teacher_cohort->id, 'userid'=>$user->id))) {
                            cohort_add_member($teacher_cohort->id, $user->id);
                        }
                    }
                }
            }
        }

        /* if the enrolled user is a student, add them to the students cohort. */
        $query = "SELECT * FROM `" . get_config('enrol_cegep', 'remoteenroltable') . "` WHERE " . get_config('enrol_cegep', 'remotecoursefield') . "='$course->idnumber' AND " . get_config('enrol_cegep', 'remoteuserfield') . "='$user->idnumber' AND " . get_config('enrol_cegep', 'remoterolefield') . "='student'";

        if ($rs = $cegep_extdb->Execute($query)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    if (($student_cohort = $DB->get_record('cohort', array('idnumber'=>'dawsonstudents'), '*', MUST_EXIST))) {
                        require_once($CFG->dirroot . '/cohort/lib.php');

                        if (!$DB->record_exists('cohort_members', array('cohortid'=>$student_cohort->id, 'userid'=>$user->id))) {
                            cohort_add_member($student_cohort->id, $user->id);
                        }
                    }
                }
            }
        }
        return true;
    }

    public function user_unenrolled($ue) {
        return true;
    }

    public function course_deleted($course) {
        return true;
    }
}

/**
 * Get an array of users to be passed to the sync_user_enrolments
 * function.
 */
function enrol_cegep_get_student_numbers_for_teacher($username) {
    global $DB, $CFG, $cegep_extdb;

	if (!is_null($cegep_extdb) && !$cegep_extdb->IsConnected()) {
		$cegep_extdb = null;
	}

    if (is_null($cegep_extdb)) {
        $cegep_extdb = enrol_cegep_handler::db_init();
    }

    if (!enrol_is_enabled('cegep')) {
        return false;
    }

    $studentnos = false;

    require_once($CFG->dirroot . '/blocks/mydawson/lib.php');
    $session = mydawson_get_session();

    $query = "SELECT DISTINCT course FROM enrolment WHERE session >= '$session' AND userno='$username' AND role='editingteacher'";

    if ($rs = $cegep_extdb->Execute($query)) {
        if (!$rs->EOF) {
            $courses = array();
            while ($course = $rs->FetchRow()) {
                $courses[] = $course['course'];
            }
        }

        if (count($courses) > 0) {
            $query = "SELECT DISTINCT userno FROM enrolment WHERE role='student' AND course IN ('" . implode("','", $courses) . "')";
            if ($rs = $cegep_extdb->Execute($query)) {
                if (!$rs->EOF) {
                    $studentnos = array();
                    while ($stud = $rs->FetchRow()) {
                        $studentnos[] = $stud['userno'];
                    }
                }
            }
        }
    }
    return $studentnos;
}

/**
 * Get an array of courses to be passed to the sync_courses
 * function.
 */
function enrol_cegep_get_courses($username) {
    global $DB, $CFG, $cegep_extdb;

	if (!is_null($cegep_extdb) && !$cegep_extdb->IsConnected()) {
        $cegep_extdb = null;
	}

    if (is_null($cegep_extdb)) {
        $cegep_extdb = enrol_cegep_handler::db_init();
    }

    if (!enrol_is_enabled('cegep')) {
        return false;
    }

    require_once($CFG->dirroot . '/blocks/mydawson/lib.php');
    $session = mydawson_get_session();

    $query = "
        SELECT 
            c.fullname,
            c.shortname,
            c.category,
            c.idnumber
        FROM `enrolment` e
        LEFT JOIN `course` c ON c.idnumber = e.course
        WHERE e.session >= '$session' AND e.userno='$username' AND e.role='editingteacher'
    ";

    $table     = get_config('enrol_cegep', 'newcoursetable');
    $fullname  = strtolower(get_config('enrol_cegep', 'newcoursefullname'));
    $shortname = strtolower(get_config('enrol_cegep', 'newcourseshortname'));
    $idnumber  = strtolower(get_config('enrol_cegep', 'newcourseidnumber'));
    $category  = strtolower(get_config('enrol_cegep', 'newcoursecategory'));

    $createcourses = false;

    if ($rs = $cegep_extdb->Execute($query)) {
        $createcourses = array();
        if (!$rs->EOF) {
            while ($fields = $rs->FetchRow()) {
                $fields = array_change_key_case($fields, CASE_LOWER);
                if (empty($fields[$shortname]) or empty($fields[$fullname])) {
                    //invalid record - these two are mandatory
                    continue;
                }

                //WARNING: this function will probably not work if you're not
                //using utf-8 in the external DB. 
                //$fields = $this->db_decode($fields);

                if ($DB->record_exists('course', array('shortname'=>$fields[$shortname]))) {
                    // already exists
                    continue;
                }
                if ($idnumber and $DB->record_exists('course', array('idnumber'=>$fields[$idnumber]))) {
                    // idnumber duplicates are not allowed
                    continue;
                }
                if ($category and !$DB->record_exists('course_categories', array('id'=>$fields[$category]))) {
                    // invalid category id, better to skip
                    continue;
                }
                $course = new stdClass();
                $course->fullname  = $fields[$fullname];
                $course->shortname = $fields[$shortname];
                $course->idnumber  = $idnumber ? $fields[$idnumber] : NULL;
                $course->category  = $category ? $fields[$category] : NULL;
                $createcourses[] = $course;
            }
        }
    } 
    return $createcourses;
}

/**
 * Sync all cegep course links.
 * @param int $courseid one course, empty mean all
 * @return void
 */
function enrol_cegep_sync($courseid = NULL) {
    global $CFG, $DB;

    // unfortunately this may take a loooong time
    @set_time_limit(0); //if this fails during upgrade we can continue from cron, no big deal

    $cegep = enrol_get_plugin('cegep');

    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";

    // iterate through all not enrolled yet users
    if (enrol_is_enabled('cegep')) {
        list($enabled, $params) = $DB->get_in_or_equal(explode(',', $CFG->enrol_plugins_enabled), SQL_PARAMS_NAMED, 'e00');
        $onecourse = "";
        if ($courseid) {
            $params['courseid'] = $courseid;
            $onecourse = "AND e.courseid = :courseid";
        }
        $sql = "SELECT pue.userid, e.id AS enrolid
                  FROM {user_enrolments} pue
                  JOIN {enrol} pe ON (pe.id = pue.enrolid AND pe.enrol <> 'cegep' AND pe.enrol $enabled )
                  JOIN {enrol} e ON (e.customint1 = pe.courseid AND e.enrol = 'cegep' AND e.status = :statusenabled $onecourse)
             LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = pue.userid)
                 WHERE ue.id IS NULL";
        $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
        $params['courseid'] = $courseid;

        $rs = $DB->get_recordset_sql($sql, $params);
        $instances = array(); //cache
        foreach($rs as $ue) {
            if (!isset($instances[$ue->enrolid])) {
                $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
            }
            $cegep->enrol_user($instances[$ue->enrolid], $ue->userid);
        }
        $rs->close();
        unset($instances);
    }

    // unenrol as necessary - ignore enabled flag, we want to get rid of all
    $sql = "SELECT ue.userid, e.id AS enrolid
              FROM {user_enrolments} ue
              JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'cegep' $onecourse)
         LEFT JOIN (SELECT xue.userid, xe.courseid
                      FROM {enrol} xe
                      JOIN {user_enrolments} xue ON (xue.enrolid = xe.id)
                   ) pue ON (pue.courseid = e.customint1 AND pue.userid = ue.userid)
             WHERE pue.courseid IS NULL";
    //TODO: this may use a bit of SQL optimisation
    $rs = $DB->get_recordset_sql($sql, array('courseid'=>$courseid));
    $instances = array(); //cache
    foreach($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
        }
        $cegep->unenrol_user($instances[$ue->enrolid], $ue->userid);
    }
    $rs->close();
    unset($instances);

    // now assign all necessary roles
    if (enrol_is_enabled('cegep')) {
        $enabled = explode(',', $CFG->enrol_plugins_enabled);
        foreach($enabled as $k=>$v) {
            if ($v === 'cegep') {
                continue; // no cegep sync of cegep roles
            }
            $enabled[$k] = 'enrol_'.$v;
        }
        $enabled[] = $DB->sql_empty(); // manual assignments are replicated too

        list($enabled, $params) = $DB->get_in_or_equal($enabled, SQL_PARAMS_NAMED, 'e00');
        $sql = "SELECT DISTINCT pra.roleid, pra.userid, c.id AS contextid, e.id AS enrolid
                  FROM {role_assignments} pra
                  JOIN {user} u ON (u.id = pra.userid AND u.deleted = 0)
                  JOIN {context} pc ON (pc.id = pra.contextid AND pc.contextlevel = :coursecontext AND pra.component $enabled)
                  JOIN {enrol} e ON (e.customint1 = pc.instanceid AND e.enrol = 'cegep' AND e.status = :statusenabled $onecourse)
                  JOIN {context} c ON (c.contextlevel = pc.contextlevel AND c.instanceid = e.courseid)
             LEFT JOIN {role_assignments} ra ON (ra.contextid = c.id AND ra.userid = pra.userid AND ra.roleid = pra.itemid AND ra.itemid = e.id AND ra.component = 'enrol_cegep')
                 WHERE ra.id IS NULL";
        $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
        $params['coursecontext'] = CONTEXT_COURSE;
        $params['courseid'] = $courseid;

        if ($ignored = $cegep->get_config('nosyncroleids')) {
            list($notignored, $xparams) = $DB->get_in_or_equal(explode(',', $ignored), SQL_PARAMS_NAMED, 'i00', false);
            $params = array_merge($params, $xparams);
            $sql = "$sql AND pra.roleid $notignored";
        }

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach($rs as $ra) {
            role_assign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_cegep', $ra->enrolid);
        }
        $rs->close();
    }

    // remove unwanted roles - include ignored roles and disabled plugins too
    $params = array('coursecontext' => CONTEXT_COURSE, 'courseid' => $courseid);
    if ($ignored = $cegep->get_config('nosyncroleids')) {
        list($notignored, $xparams) = $DB->get_in_or_equal(explode(',', $ignored), SQL_PARAMS_NAMED, 'i00', false);
        $params = array_merge($params, $xparams);
        $notignored = "AND pra.roleid $notignored";
    } else {
        $notignored = "";
    }
    $sql = "SELECT ra.roleid, ra.userid, ra.contextid, ra.itemid
              FROM {role_assignments} ra
              JOIN {enrol} e ON (e.id = ra.itemid AND ra.component = 'enrol_cegep' AND e.enrol = 'cegep' $onecourse)
              JOIN {context} pc ON (pc.instanceid = e.customint1 AND pc.contextlevel = :coursecontext)
         LEFT JOIN {role_assignments} pra ON (pra.contextid = pc.id AND pra.userid = ra.userid AND pra.roleid = ra.roleid AND pra.component <> 'enrol_cegep' $notignored)
             WHERE pra.id IS NULL";

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs as $ra) {
        role_unassign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_cegep', $ra->itemid);
    }
    $rs->close();

}
