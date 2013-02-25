<?php
require('../../config.php');
require_once($CFG->dirroot . '/lib/weblib.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/blocks/mydawson/lib.php');

global $DB, $PAGE, $SITE, $OUTPUT, $USER;

require_login();
$courseid = required_param('id', PARAM_INT); //if no id is given
$session = required_param('session', PARAM_INT);
$cancel = optional_param('cancel', '', PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$context = get_context_instance(CONTEXT_COURSE, $courseid);
require_capability('moodle/block:edit', $context);

$PAGE->set_course($course);
$PAGE->set_url('/blocks/mydawson/merge.course.php');
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('mergecourses', 'block_mydawson'));
$PAGE->navbar->add(get_string('mergecourses', 'block_mydawson'));

// OUTPUT
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mergecourses', 'block_mydawson'), 3, 'main');

require_once('merge_form.php');

$children = mydawson_get_potential_children($courseid, $session);
$parents = mydawson_get_parents($children);

//Need to pass an array of courses to the constructor so the form knows
//what checkboxes to display. First sort them so that only sections from
//that course are shown.

//$sorted_children = mydawson_sort_courses($children);
$course_code = mydawson_extract_coursenumber($course->idnumber);

//$mform = new mydawson_merge_form($sorted_children[$session][$course_code], $parents);
$mform = new mydawson_merge_form($children, $parents);
$mform->add_action_buttons();

if (($data = $mform->get_data()) && !$mform->is_cancelled()) {

    //no parent_id? we'll need to create a new course...
    if (!isset($data->parent_id)) {

        $sections_to_add = array();
        $keys = get_object_vars($data);
        
        $children_to_add = array();

        //go through submitted data. POST vars starting with "merge-" 
        //have the course IDs of child courses to add to the merged course.
        foreach ($keys as $key => $value) {
            if (strpos($key, "merge-") !== false) {
                if ($data->{$key} == 1) {
                    $arr = explode("-", $key);
                    $children_to_add[] = $arr[1];
                }
            }
        }

        foreach ($children as $c) {
            if (in_array($c->id, $children_to_add)) {
                $sections_to_add[] = mydawson_extract_section($c->idnumber);
            }
        }

        $sections_to_add = array_unique($sections_to_add);

        sort($sections_to_add, SORT_NUMERIC);
        $section_string = implode("+", $sections_to_add);
        $section_string_full = get_string('section', 'block_mydawson') . (count($sections_to_add) > 1 ? 's' : '') . ' ' . implode(", ", $sections_to_add);

        /*
        TODO:

        - Create new course

        - Insert appropriate rows into mydawson_merged_course table.

        - Enroll teacher

        - Enroll all students from the child courses

        - Hide all child courses

        */


        //check if the parent course exists. It's possible
        //that a teacher merged, unmerged, and wants to merge
        //again, in which case the parent course will exist but the
        //teacher will not have been enrolled.

        $newidnumber  = $data->coursenumber . '-' . $section_string . '-' . $data->session;

        if (($newcourse = $DB->get_record('course', array('idnumber'=>$newidnumber), '*'))) {
            //do the enrolments.
            mydawson_merge_courses($newcourse->id, $children_to_add);
        }
        else {
            //create new course
            require_once("$CFG->dirroot/course/lib.php");

            //use the first child as a template.
            //clone is needed, otherwise it's passed by ref and $children[0]->id, etc
            //get unset, which we don't want!
            $template = clone($children[0]);

            unset($template->id);
            unset($template->fullname);
            unset($template->shortname);
            unset($template->idnumber);

            $newcourse = clone($template);
//            $newcourse->fullname  = $data->coursetitle . " " . $section_string_full . " - " . mydawson_session_to_string($data->session);
            $newcourse->fullname  = $data->coursetitle;
            $newcourse->shortname  = $data->coursetitle . " " . $section_string_full . " - " . mydawson_session_to_string($data->session);
            $newcourse->idnumber  = $data->coursenumber . '-' . $section_string . '-' . $data->session;

            $newcourse->category  = $template->category;

            //the enrol/cegep plugin has a create_course event that
            //checks if $newcourse->child_ids is set and enrols all student
            //from the child courses into the new course, so let's set child_ids
            //before the event is fired, then create the course.

            $newcourse->child_ids = $children_to_add;
            $newcourse = create_course($newcourse); //mydawson_merge_courses gets called by the created_course event.
        }

        if ($newcourse->id) {
            //insert row into merged_courses for the parent course.
            $newrow = new stdClass();
            $newrow->parent_courseid = 0;
            $newrow->courseid = $newcourse->id;
            $newrow->section = 0;
            $DB->insert_record('mydawson_merged_course', $newrow);
            unset($newrow);

            foreach ($children as $c) {
                if (in_array($c->id, $children_to_add)) {
                    $newrow = new stdClass();
                    $newrow->parent_courseid = $newcourse->id;
                    $newrow->courseid = $c->id;
                    $newrow->section = mydawson_extract_section($c->idnumber);
                    $DB->insert_record('mydawson_merged_course', $newrow); 
                    unset($newrow);
                }
            }

            if (!enrol_is_enabled('cegep')) {
                die('enrol_cegep plugin is disabled, sync is disabled');
            }


            //we want the teacher to be enrolled in the course right away, so let's
            //call sync_user_enrolments now, even though they would be enrolled if they
            //logged out and back in again, since the enrol/cegep create_course event
            //was fired and takes care to insert new rows into the external database.

            $enrol = enrol_get_plugin('cegep');
            $enrol->sync_user_enrolments($USER);
        }
        unset($template);
    }
    //Go back to my moodle page
    $mymoodle = new moodle_url('/my/');
    redirect($mymoodle);
}
else if ($mform->is_cancelled()) {
    $mymoodle = new moodle_url('/my/');
    redirect($mymoodle);
}
else {
    $toform = new stdClass();
    $toform->id = $course->id;
    $toform->session = $session;
    $toform->coursenumber = mydawson_extract_coursenumber($course->idnumber);
    $toform->coursetitle = $course->fullname; //mydawson_extract_coursetitle($course->shortname);

    foreach ($children as $c) {
        if (isset($c->checked) && $c->checked == 1) {
            $toform->{'merge-' . $c->id} = 1;
        }
    }

    $mform->set_data($toform);
}

$mform->display();

echo $OUTPUT->footer();
?>
