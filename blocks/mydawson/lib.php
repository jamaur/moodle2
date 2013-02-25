<?php

//Global DB handles for DC101 and Clara, respectively.
$dc101db = null;
$claradb = null;

function mydawson_set_user_session($session) {
    global $USER, $DB;

    if (!($id = $DB->get_field("mydawson_user_session", "id", array("userid" => $USER->id)))) {
        $user_session = new stdClass();
        $user_session->userid = $USER->id;
        $user_session->session = $session;

        $DB->insert_record("mydawson_user_session", $user_session);
    }
    else {
        $DB->set_field("mydawson_user_session", "session", $session, array("id" => $id));
    }
}

/**
 * Gets the session that the user set their session
 * to last. If there's nothing, get the current session
 * as a sane default.
 */
function mydawson_get_user_session() {
    global $USER, $DB;

    if (!($session = $DB->get_field("mydawson_user_session", "session", array("userid" => $USER->id)))) {
        return mydawson_get_session();
    }
    else {
        return $session;
    }
}

function mydawson_print_overview($block_instance, $courses, $session, array $remote_courses=array()) {
    global $CFG, $USER, $DB, $OUTPUT, $PAGE;

    $PAGE->requires->js_init_call('M.block_mydawson.init_mydawson');

    $config = get_config('block_mydawson');

    require_once('session_form.php');
    $sessionform = new mydawson_session_form();

    if ($sessiondata = $sessionform->get_data()) { //form was submitted
        $sessionform->display(); //display it again
        $block_instance->config->session = $sessiondata->session; //set the session var to what was submitted.
        $block_instance->instance_config_commit(); //save the submitted data to the block_instance table.

		if ($sessiondata->session != 'other') {
			mydawson_set_user_session($sessiondata->session);
		}

        redirect($PAGE->url); //reload the page--but without reposting to cause an infinite loop.
    }
    else {
        $sessiontoform = new stdClass();
        $sessiontoform->session = $session;
        $sessionform->set_data($sessiontoform);
        $sessionform->display();
    }

    $htmlarray = array();
    if ($modules = $DB->get_records('modules')) {
        foreach ($modules as $mod) {
            if (file_exists(dirname(dirname(__FILE__)).'/mod/'.$mod->name.'/lib.php')) {
                include_once(dirname(dirname(__FILE__)).'/mod/'.$mod->name.'/lib.php');
                $fname = $mod->name.'_print_overview';
                if (function_exists($fname)) {
                    $fname($courses,$htmlarray);
                }
            }
        }
    }

    $sorted_courses = mydawson_sort_courses($courses);

    $count = 0;
    $fieldset_open = false;
    $first = true;

    $courseids = array();

    foreach ($courses as $cid => $c) {
        if (mydawson_course_is_child($cid)) {
            continue;
        }

        $course_session = mydawson_extract_session($c->idnumber);

        if ($session != $course_session) {
            $course_session = mydawson_extract_legacy_session($c->fullname);
            if ($session != $course_session) {
                continue;
            }
        }

        $courseids[] = $cid;
    }

	$teacher_flag = false;

    foreach ($courseids as $cid) {
        $course = $courses[$cid];

        $count++;

        //get the context
        $context = get_context_instance(CONTEXT_COURSE, $course->id);

        //Skip courses that are children
        if (mydawson_course_is_child($course->id)) {
            continue;
        }

        $course_session = mydawson_extract_session($course->idnumber);

        $legacy = false;

        if ($session != $course_session) {
            $course_session = mydawson_extract_legacy_session($course->fullname);
            if ($session != $course_session) {
                continue;
            }
            else {
                $legacy = true;
            }

        }

        //process the visible form now so that the link appears in
        //the proper colour.
        if ($teacher_flag || has_capability('moodle/block:edit', $context)) {
			$teacher_flag = true;

            if ($first) {
                $first = false;
                if ($course->visible == 1) {
                    echo '<div class="fakefieldset"><h1><span>Courses visible to students</span></h1>';
                }
                else {
                    echo '<div class="fakefieldset"><h1><span>Courses not visible to students</span></h1>';
                }
                $fieldset_open = true;
            }
            else if (!$fieldset_open && $course->visible == 0) {
                echo '<div class="fakefieldset"><h1><span>Courses not visible to students</span></h1>';
                $fieldset_open = true;
            }
        }

        
        echo $OUTPUT->box_start('coursebox');

        $attributes = array('title' => s($course->fullname));
        if (empty($course->visible)) {
            $attributes['class'] = 'dimmed';
        }
        echo $OUTPUT->heading(html_writer::link(
            new moodle_url('/course/view.php', array('id' => $course->id)), format_string($course->fullname), $attributes), 3);
        if (array_key_exists($course->id,$htmlarray)) {
            foreach ($htmlarray[$course->id] as $modname => $html) {
                echo $html;
            }
        }

        //teacher's and course creator's are the only ones who can edit a
        //block's settings. if they can, display the merge and visible settings.
        if (!$legacy && has_capability('moodle/block:edit', $context)) {
            mydawson_print_coursetimes($course->id, $session);

            if (mydawson_course_is_merged($course->id)) {
                echo '<p class="center"><a href="' . $CFG->wwwroot . '/blocks/mydawson/unmerge.course.php?id=' . $course->id . '">Unmerge sections</a></p>';
            }
            else {
                $coursecode = mydawson_extract_coursenumber($course->idnumber);
                if (!$legacy && count($sorted_courses[$session][$coursecode]) > 1) {
                    echo '<p class="center"><a href="' . $CFG->wwwroot . '/blocks/mydawson/merge.course.php?id=' . $course->id . '&session=' . $session . '">Merge sections together</a></p>';
                }
            }
        }

        echo $OUTPUT->box_end();
        if ($teacher_flag || has_capability('moodle/block:edit', $context)) {
            if ($fieldset_open && isset($courses[$courseids[$count]]) && ($courses[$courseids[$count]]->visible != $courses[$courseids[($count - 1)]]->visible)) {
                echo '</div>';
                $fieldset_open = false;
            }
            else if ($count == count($courseids)) {
                echo '</div>';
            }
            else {
                echo '<hr class="course" />';
            }
        }
        else if ($count < count($courseids)) {
            echo '<hr class="course" />';
        }
    }

    if (!empty($remote_courses)) {
        echo $OUTPUT->heading(get_string('remotecourses', 'mnet'));
    }
    foreach ($remote_courses as $course) {
        echo $OUTPUT->box_start('coursebox');
        $attributes = array('title' => s($course->fullname));
        echo $OUTPUT->heading(html_writer::link(
            new moodle_url('/auth/mnet/jump.php', array('hostid' => $course->hostid, 'wantsurl' => '/course/view.php?id='.$course->remoteid)),
            format_string($course->shortname),
            $attributes) . ' (' . format_string($course->hostname) . ')', 3);
        echo $OUTPUT->box_end();
    }
}

/**
 * Gets a string, like "Sections 1, 2, and 3" for the given
 * course ID. Section information is in the mydawson_merged_course
 * table.
 *
 * @param int Unique course ID.
 * @return string String describing what sections have been merged.
 */
function mydawson_get_merged_section_string($courseid) {
    global $DB;

    $sections = $DB->get_records_select('mydawson_merged_course', "parent_courseid=$courseid", null, "section ASC");

    if (!$sections) {
        return false;
    }
    
    $str = get_string("sections", "block_mydawson");

    $first = true;
    $count = 0;

    $added_sections = array();

    foreach ($sections as $s) {
        
        if (in_array($s->section, $added_sections)) {
            $count++;
            continue;
        }
        else {
            $added_sections[] = $s->section;
        }

        if (count($sections) == 1) {
            return get_string("section", "block_mydawson") . " " . $s->section;
        }

        if ($first) {
            if (count($sections) == 2) {
                $str .= " $s->section ";
            }
            else {
                $str .= " $s->section, ";
            }
            $first = false;
        }
        else {
            if ($count == (count($sections) - 1)) {
                $str .= get_string("and", "block_mydawson") . " $s->section";
            }
            else {
                $str .= "$s->section, ";
            }
        }
        $count++;
    }
    return $str;
}

/**
 * Take a course's shortname, which is in the form
 * "Some course title <coursecode>-<section>-<term>"
 * and extracts the "Some course title" part.
 *
 * @param string $shortname The shortname of the course.
 * @return string The extracted course title.
 */
function mydawson_extract_coursetitle($shortname) {
    $pattern = '/\w{8}-\d{1,5}-\d{5}/i';
    return trim(preg_replace($pattern, '', $shortname));
}

function mydawson_get_teacher_names($courseno) {
    global $CFG, $DB, $cegep_extdb;

    if (is_null($cegep_extdb)) {
        require_once($CFG->dirroot . '/enrol/cegep/locallib.php');
        $cegep_extdb = enrol_cegep_handler::db_init();
    }

    //get the config variables.
    $enrol = enrol_get_plugin('cegep');
    $table = $enrol->get_config('remoteenroltable');

    $query = "SELECT
                firstname,
                lastname
            FROM
                `$table`
            WHERE
                course='$courseno'
                AND role='editingteacher'";

    $teachers = false;

    if (($rs = $cegep_extdb->Execute($query))) {
        $teachers = array();
        if (!$rs->EOF) {

            while ($fields = $rs->FetchRow()) {
                $teachers[] = $fields['firstname'] . " " . $fields['lastname'];
            }

            $teachers = array_unique($teachers);
        }
    }
    return $teachers;
}

function mydawson_get_students($courseidnumber) {
    global $CFG, $DB, $cegep_extdb;

    if (is_null($cegep_extdb)) {
        require_once($CFG->dirroot . '/enrol/cegep/locallib.php');
        $cegep_extdb = enrol_cegep_handler::db_init();
    }

    //get the config variables.
    $enrol = enrol_get_plugin('cegep');
    $table = $enrol->get_config('remoteenroltable');

    $query = "SELECT DISTINCT * FROM
                `$table`
              WHERE
                course='$courseidnumber' AND
                role='student'
			  GROUP BY `userno`
              ORDER BY
                `section` ASC,
                `lastname` ASC";

    $students = false;

    if (($rs = $cegep_extdb->Execute($query))) {
        $students = array();
        if (!$rs->EOF) {

            while ($fields = $rs->FetchRow()) {
                if (!isset($students[$fields['section']]) || !is_array($students[$fields['section']])) {
                    $students[$fields['section']] = array();
                }
                $students[$fields['section']][] = $fields;
            }
        }
    }
    return $students;
}

/**
 * Takes an array of child course IDs and updates the external enrol
 * DB by enrolling all the students / teachers who are in the child
 * courses into the parent course, identified by the parent_id param.
 *
 * @param int $parent_id Unique parent course ID
 * @param array $child_ids Array of child course IDs who belong to the parent.
 * @return void
 */
function mydawson_merge_courses($parent_id, $child_ids) {
    global $CFG, $DB, $cegep_extdb;

    if (is_null($cegep_extdb)) {
        require_once($CFG->dirroot . '/enrol/cegep/locallib.php');
        $cegep_extdb = enrol_cegep_handler::db_init();
    }

    //get the config variables.
    $enrol = enrol_get_plugin('cegep');
    $table = $enrol->get_config('remoteenroltable');

    if (($parent = $DB->get_record('course', array('id'=>$parent_id)))) {
        foreach ($child_ids as $child_id) {

            //get the child course
            if (($child_course = $DB->get_record('course', array('id'=>$child_id), '*'))) {

                //we need to check if rows already exist in the
                //enrolment table for the parent course so we
                //don't add duplicate rows to the enrol table.
                $query = "SELECT
                            userno,
                            firstname,
                            lastname,
                            course,
                            role,
                            session,
                            section,
                            custom
                        FROM
                            `$table`
                        WHERE
                            course='$parent->idnumber'";

                $existing_enrolments = false;

                if (($rs = $cegep_extdb->Execute($query))) {
                    $existing_enrolments = array();
                    if (!$rs->EOF) {
                        while ($fields = $rs->FetchRow()) {
                            $existing_enrolments[] = $fields['userno'];
                        }
                        $existing_enrolments = array_unique($existing_enrolments);
                    }
                }

                //the course and external db are linked via the idnumber.
                //do a "SELECT INTO" type query to put all child course enrolments into the parent course enrolment.

                $query = "INSERT INTO `$table` 
                            (
                                userno,
                                firstname,
                                lastname,
                                course,
                                role,
                                session,
                                section,
                                custom
                            )
                            SELECT
                                userno,
                                firstname,
                                lastname,
                                '$parent->idnumber',
                                role,
                                session,
                                section,
                                1
                            FROM `$table` WHERE course='$child_course->idnumber'
                            ";

                if ($existing_enrolments) {
                    $query .= " AND userno NOT IN ('" . implode("','", $existing_enrolments) . "')";
                }

                $cegep_extdb->Execute($query);

                if ($child_id != $parent_id) {
                    //set the child course as hidden.
                    $DB->set_field('course', 'visible', 0, array('id'=>$child_id));
                }
            }
        }
    }
}

/**
 * Takes a parent course object and unmerges it by deleting
 * the custom rows from the enroldb table, unhiding the child
 * courses (which get hidden when a course is merged), hide
 * the parent course, and unenrol the currently logged in teacher 
 * from the parent course, so that that user sees things as happening
 * "instantly."
 *
 * @param $parent Course object of the parent course to be unmerged.
 * @return bool true
 */
function mydawson_unmerge_course($parent) {
    global $CFG, $DB, $cegep_extdb, $USER;

    //not enabled? get out of here.
    if (!enrol_is_enabled('cegep')) {
        return true;
    }

    if (is_null($cegep_extdb)) {
        require_once($CFG->dirroot . '/enrol/cegep/locallib.php');
        $cegep_extdb = enrol_cegep_handler::db_init();
    }

    //get the config variable for the enrolment table.
    $table = get_config('enrol_cegep', 'remoteenroltable');

    $query = "DELETE FROM `$table` WHERE course='$parent->idnumber' AND custom='1'";
    $cegep_extdb->Execute($query);
    $DB->delete_records('mydawson_merged_course', array('parent_courseid' => $parent->id));
    $DB->delete_records('mydawson_merged_course', array('courseid' => $parent->id));
    $DB->set_field('course', 'visible', 0, array('id'=>$parent->id));

    $enrol = enrol_get_plugin('cegep');
    $enrol->sync_user_enrolments($USER);
    return true;
}

/**
 * Gets an array of Course objects that could potentially
 * be children for the given course ID.
 *
 * @param int $courseid Unique course ID.
 * @param int $session The session to consider, in YYYYT format.
 * @return array Array of course objects that could be children.
 */
function mydawson_get_potential_children($courseid, $session) {
    global $DB;

    //Get the course for the given course id.
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

    //Extract the coursenumber from the course->idnumber. A normal idnumber is in the
    //form "<coursenumber>-<section>-<session>".
    $coursenumber = mydawson_extract_coursenumber($course->idnumber);

    //Get courses that belong to this teacher, for this session.
    $courses = enrol_get_my_courses('id, shortname, modinfo', 'visible DESC,sortorder ASC');

    //Loop through the courses.
    if (is_array($courses) && count($courses) > 0) {
        $arr = array();

        //For each course, extract the coursenumber ($cnum).
        //If the coursenumber ($cnum) matches the first coursenumber we found ($coursenumber),
        //then add the course to the array.
        foreach ($courses as $c) {
            $sess = mydawson_extract_session($c->idnumber);

            //not the same session? ignore.
            if ($sess != $session) {
                continue;
            }

            $cnum = mydawson_extract_coursenumber($c->idnumber);


            $parent_id = mydawson_get_parent_id($c->id);

            if ($parent_id && $parent_id != $courseid) {
                //already a child of someone else? ignore.
                continue;
            }
            else if ($parent_id && $parent_id == $courseid) {
                $c->checked = 1;
            }
            else if ($c->id == $courseid) {
                $c->checked = 1;
            }

            $arr[] = $c;
        }
    }
    
    //Return the array, or false if none found.
    if (is_array($arr) && count($arr) > 0) {
        return $arr;
    }
    else {
        return false;
    }
}

function mydawson_get_parents($courses) {
    global $DB;
    $arr = false;

    foreach ($courses as $c) {
        if ($parentid = mydawson_get_parent_id($c->id)) {

            if (!is_array($arr)) {
                $arr = array();
            }

            if ($parent = $DB->get_record('course', array('id' => $parentid), '*')) {
                $arr[] = $parent;
            }
        }
    }
    return $arr;
}

/**
 * This function takes an array of courses and
 * sorts it into a new associative array with
 * the session and coursecodes as the keys.
 *
 * So the structure would look like $arr[session][coursecode]
 * and it would be an array containing all the sections for
 * that course.
 */
function mydawson_sort_courses($courses) {
    $arr = array();
    foreach ($courses as $course) {
        $session = mydawson_extract_session($course->idnumber);
        $coursecode = mydawson_extract_coursenumber($course->idnumber);

        if (!is_array($arr[$session])) {
            $arr[$session] = array();
        }

        if (!is_array($arr[$session][$coursecode])) {
            $arr[$session][$coursecode] = array();
        }
        $arr[$session][$coursecode][] = $course;
    }
    return $arr;
}

function mydawson_get_all_parent_ids() {
    global $DB;
    $arr = false;

    if (($parents = $DB->get_records_select('mydawson_merged_course', "parent_courseid='0'", null, 'courseid'))) {
        foreach ($parents as $p) {
            if (!is_array($arr)) {
                $arr = array();
            }
            $arr[] = $p->courseid;
        }
    }
    return $arr;
}

function mydawson_get_children($parentid) {
    global $DB;
    
    $arr = false;

    if (($children= $DB->get_records_select('mydawson_merged_course', "parent_courseid='$parentid'", null, 'courseid'))) {

        foreach ($children as $c) {

            if (!is_array($arr)) {
                $arr = array();
            }

            $arr[] = $c->courseid;
        }
    }
    return $arr;
}

function mydawson_get_parent_id($courseid) {
    global $DB;
    $result = $DB->get_record_select('mydawson_merged_course', "courseid='$courseid' AND parent_courseid != 0");

    if ($result) {
        return $result->parent_courseid;
    }
    else {
        return false;
    }
}

function mydawson_extract_coursenumber($idnumber) {
    $arr = explode("-", $idnumber);
    return $arr[0];
}

function mydawson_extract_section($idnumber) {
    $arr = explode("-", $idnumber);
    return $arr[1];
}


function mydawson_get_session() {
    global $CFG, $dc101db;

    if (is_null($dc101db)) {
        $dc101db = mydawson_init_dc101db();
    }

    //TODO: write your own code here to get the "current"
    //session in YYYYT format. E.g., 20131.
    this_function_call_means_there_is_work_to_be_done();
    
}

/**
 * Determines if a course has been merged or not.
 *
 * @param int Unique course ID.
 * @return bool True if the course is a merged course, false otherwise.
 */
function mydawson_course_is_merged($courseid) {
    global $DB;
    $merged = $DB->get_record('mydawson_merged_course', array('courseid'=>$courseid, 'parent_courseid'=>0));

    if ($merged) {
        return true;
    }
    else {
        return false;
    }
}

/**
 * Determines if a course is the child of a merged course or not.
 *
 * @param int Unique course ID.
 * @return bool True if the course is a child of a merged course, false otherwise.
 */
function mydawson_course_is_child($courseid) {
    global $DB;
    $child = $DB->get_record_select('mydawson_merged_course', "courseid='$courseid' AND parent_courseid != 0 AND courseid != parent_courseid");

    if ($child) {
        return true;
    }
    else {
        return false;
    }
}

/**
 * Takes a course object and figures out what session that course belongs
 * to, then returns the session, in YYYYT format, or false if session can't
 * be determined.
 */

function mydawson_extract_session($idnumber) {
    $pieces = explode("-", $idnumber);
    if (count($pieces) == 3) {
        return $pieces[2];
    }
    else {
        return false;
    }
}

function mydawson_extract_legacy_session($fullname) {
    if (preg_match("/20\d{2}\)/", $fullname, $matches)) {
        $year = substr($matches[0], 0, 4);
        $term = false;

        if (preg_match("/winter/i", $fullname)) {
            $term = 1;
        }
        else if (preg_match("/summer/i", $fullname)) {
            $term = 2;
        }
        else if (preg_match("/fall/i", $fullname)) {
            $term = 3;
        }
    }
    if ($term) {
        return $year . $term;
    }
    else {
        return 0;
    }
}

function mydawson_init_dc101db() {
    global $CFG, $dc101db;

    if (!is_null($dc101db)) {
        return $dc101db;
    }

    require_once($CFG->libdir.'/adodb/adodb.inc.php');
    require_once($CFG->libdir.'/moodlelib.php');

    // Connect to the external database (forcing new connection)
    $dc101db = ADONewConnection(get_config('block_mydawson', 'dc101_dbtype'));

    $dc101db->Connect(get_config('block_mydawson', 'dc101_host'), get_config('block_mydawson', 'dc101_user'), get_config('block_mydawson', 'dc101_pass'), get_config('block_mydawson', 'dc101_db'), true);
    $dc101db->SetFetchMode(ADODB_FETCH_ASSOC);
    if (get_config('block_mydawson', 'dc101_sql')) {
        $dc101db->Execute(get_config('block_mydawson', 'dc101_sql'));
    }   
    return $dc101db;

}

function mydawson_init_claradb() {
    return false;
}

/* Increments a session, in the form of YYYYT. */
function mydawson_increment_session($session) {
    $year = substr($session, 0, 4); 
    $semester = substr($session, 4, 1); 
    if ($semester == 3) {
        $semester = 1;
        $year++;
    }   
    else {
        $semester++;
    }   
    return $year . $semester;
}

/* Decrements a session, in the form of YYYYT. */
function mydawson_decrement_session($session) {
    $year = substr($session, 0, 4); 
    $semester = substr($session, 4, 1); 
    if ($semester == 1) { 
        $semester = 3;  
        $year--;
    }    
    else {
        $semester--;
    }    
    return $year . $semester;
}

/**
 * Convert a semester code (YYYYS) into a string,
 * like 'Fall 2009' or 'Winter 2010'.
 */
function mydawson_session_to_string($code) {
    $year = substr($code, 0, 4);
    $semester = substr($code, 4, 1);

    $str = '';

    switch ($semester) {
        case '1':
            $str = 'Winter ';
            break;
        case '2':
            $str = 'Summer ';
            break;
        case '3':
            $str = 'Fall ';
            break;
        default:
            $str = 'Fall ';
            break;
    }

    $str .= $year;
    return $str;

}

function mydawson_print_coursetimes($courseid, $session) {
    global $DB;

    $is_merged = mydawson_course_is_merged($courseid);

    if ($is_merged) {
        if (($child_ids = mydawson_get_children($courseid))) {
			$in_clause = "courseid IN (" . join(',', $child_ids) . ")";
			$rawtimes = $DB->get_records_select('mydawson_coursetime', $in_clause, null, "id ASC");
		}
		else {
			$rawtimes = $DB->get_records_select('mydawson_coursetime', "courseid=$courseid", null, "id ASC");
        }
    }
    else {
        $rawtimes = $DB->get_records_select('mydawson_coursetime', "courseid=$courseid", null, "id ASC");
    }

    if ($rawtimes) {
        //massage the data
        $times = array();

        foreach ($rawtimes as $t) {

            if (empty($times[$t->coursenumber])) {
                $times[$t->coursenumber] = array();
            }

            if (empty($times[$t->coursenumber][$t->section])) {
                $times[$t->coursenumber][$t->section] = array();
            }

            $times[$t->coursenumber][$t->section][$t->day] = "$t->start_time - $t->end_time";
        }

        echo '<table class="coursetimes" cellpadding="0" cellspacing="0">';
        echo '<thead>';
            if (true) { //$is_merged) {
                echo '<tr><th>Course</th><th>Section</th><th># of Students</th><th>Time</th></tr>';
            }
            else {
                echo '<tr><th>Section</th><th># of Students</th><th>Time</th></tr>';
            }
        echo '</thead>';

        echo '<tbody>';

        $prevsect = false;
        $prevcourse = false;

        foreach ($times as $coursenumber => $sections) {
            foreach ($sections as $section => $days) {
                $i = 0;
                foreach ($days as $day => $time) {
                    $i++;

                    if ($i == count($days)) {
                        echo '<tr class="bottomtime">';
                    }
                    else {
                        echo '<tr>';
                    }

                        if (true) { //$is_merged) {
                            if ($prevcourse == $coursenumber && $prevsect == $section) {
                                echo '<td>'; //style="padding-top: 0px; padding-bottom: 0px;">';
                            }
                            else {
                                echo '<td>'; //style="padding-bottom:0px;">';
                            }

                            if ($prevcourse != $coursenumber) {
                                echo $coursenumber;
                            }
                            else {
                                echo '&nbsp;';
                            }

                            echo '</td>';
                        }

                        if ($prevcourse == $coursenumber && $prevsect == $section) {
                            echo '<td>';// style="padding-top: 0px;padding-bottom: 0px;">';
                        }
                        else {
                            echo '<td>';// style="padding-bottom:0px;">';
                        }

                        if ($prevsect != $section || $prevcourse != $coursenumber) {
                            echo $section;
                        }
                        else {
                            echo '&nbsp;';
                        }

                        echo '</td>';

                        if ($prevcourse == $coursenumber && $prevsect == $section) {
                            echo '<td>';
                        }
                        else {
                            echo '<td>';
                        }

                        if ($prevsect != $section) {
                            //echo mydawson_get_nstudents($courseid, $section);
							echo mydawson_get_nclarastudents($coursenumber . '-' . $section . '-' . $session);
                        }
                        else {
                            echo '&nbsp;';
                        }

                        echo '</td>';

                        if ($prevcourse == $coursenumber && $prevsect == $section) {
                            echo '<td>'; //style="padding-top: 0px;padding-bottom:0px;">';
                        }
                        else {
                            echo '<td>'; //style="padding-bottom:0px;">';
                        }
                        echo "$day: $time";
                        echo '</td>';
                    echo '</tr>';

                    $prevsect = $section;
                    $prevcourse = $coursenumber;
                }
            }
        }

        /*
        if ($is_merged) {
            echo '<tr><td class="mergecourses" colspan="3"><a href="' . $CFG->wwwroot . '/blocks/mydawson/unmerge.course.php?id=' . $courseid . '">Unmerge these sections</a></td></tr>';
        }
        else {
            echo '<tr><td class="mergecourses" colspan="3"><a href="' . $CFG->wwwroot . '/blocks/mydawson/merge.course.php?id=' . $courseid . '&session=' . $session . '">Merge this course with other sections</a></td></tr>';
        }
        */

        echo '</tbody>';
        echo '</table>';
    }
}

function mydawson_get_nclarastudents($course) {
//	return false;
    global $CFG, $DB, $cegep_extdb;

    if (is_null($cegep_extdb)) {
        require_once($CFG->dirroot . '/enrol/cegep/locallib.php');
        $cegep_extdb = enrol_cegep_handler::db_init();
    }

    //get the config variables.
    $enrol = enrol_get_plugin('cegep');
    $table = $enrol->get_config('remoteenroltable');

    $query = "SELECT DISTINCT userno FROM
                `$table`
              WHERE
                course='$course' AND
                role='student'
			  GROUP BY userno
			  ";

	$count = 0;

    if (($rs = $cegep_extdb->Execute($query))) {
        if (!$rs->EOF) {
            while ($fields = $rs->FetchRow()) {
				$count++;
            }
        }
    }
	return $count;
}

/**
 * Function to get the number of students in a given course.
 *
 * @param $courseid int Unique course ID.
 * @return int The number of students in the course.
 */
function mydawson_get_nstudents($courseid, $section=0) {
    global $DB, $CFG;

    $course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
    $context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);
    require_capability('moodle/course:viewparticipants', $context);
    $role = $DB->get_record('role', array('shortname'=>'student'), '*', MUST_EXIST);

    $contextlist = get_related_contexts_string($context);

    $groupid = NULL;

    if ($section > 0) {
        require_once($CFG->dirroot . '/group/lib.php');
        $groupname = get_string('groupsection', 'enrol_cegep') . " " . $section;
        if (($group = $DB->get_record('groups', array('courseid'=>$courseid, 'name'=>$groupname)))) {
            $groupid = $group->id;
        }
    }

    list($esql, $params) = get_enrolled_sql($context, NULL, $groupid, true);

    $joins = array("FROM {user} u");
    $wheres = array();

    $select = "SELECT u.id, u.username, u.firstname, u.lastname,
                      u.email, u.city, u.country, u.picture,
                      u.lang, u.timezone, u.maildisplay, u.imagealt,
                      COALESCE(ul.timeaccess, 0) AS lastaccess";
    $joins[] = "JOIN ($esql) e ON e.id = u.id"; // course enrolled users only
    $joins[] = "LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid)"; // not everybody accessed course yet
    $params['courseid'] = $course->id;

    // performance hacks - we preload user contexts together with accounts
    list($ccselect, $ccjoin) = context_instance_preload_sql('u.id', CONTEXT_USER, 'ctx');
    $select .= $ccselect;
    $joins[] = $ccjoin;


    // limit list to users with some role only
    if ($role) {
        $wheres[] = "u.id IN (SELECT userid FROM {role_assignments} WHERE roleid = :roleid AND contextid $contextlist)";
        $params['roleid'] = $role->id;
    }

    $from = implode("\n", $joins);
    if ($wheres) {
        $where = "WHERE " . implode(" AND ", $wheres);
    } else {
        $where = "";
    }

    $totalcount = $DB->count_records_sql("SELECT COUNT(u.id) $from $where", $params);
    return $totalcount;
}
?>
