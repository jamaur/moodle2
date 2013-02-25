<?php
require('../../config.php');
require_once($CFG->dirroot . '/lib/weblib.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/blocks/mydawson/lib.php');

global $DB, $PAGE, $SITE, $OUTPUT, $USER;

require_login();
$courseid = required_param('id', PARAM_INT); //if no id is given
$cancel = optional_param('cancel', '', PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$context = get_context_instance(CONTEXT_COURSE, $courseid);
require_capability('moodle/block:edit', $context);

$PAGE->set_course($course);
$PAGE->set_url('/blocks/mydawson/unmerge.course.php');
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('unmergecourses', 'block_mydawson'));
$PAGE->navbar->add(get_string('unmergecourses', 'block_mydawson'));

// OUTPUT
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unmergecourses', 'block_mydawson'), 3, 'main');

require_once('unmerge_form.php');

//Need to pass an array of courses to the constructor so the form knows
//what checkboxes to display.

$mform = new mydawson_unmerge_form();
$mform->add_action_buttons(true, get_string('unmergecourses', 'block_mydawson'));

if (($data = $mform->get_data()) && !$mform->is_cancelled()) {
    if (($parent = $DB->get_record('course', array('id' => $data->id), '*', MUST_EXIST))) {
        mydawson_unmerge_course($parent);

        //Go back to my moodle page
        $mymoodle = new moodle_url('/my/');
        redirect($mymoodle);
    }
}
else if ($mform->is_cancelled()) {
    $mymoodle = new moodle_url('/my/');
    redirect($mymoodle);
}
else {
    $toform = new stdClass();
    $toform->id = $course->id;
    $mform->set_data($toform);
}
echo "Do you want to unmerge these courses?";
$mform->display();

echo $OUTPUT->footer();
?>
