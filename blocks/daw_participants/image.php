<?php

//Get a student's ID image.

require_once('../../config.php');

$id = required_param('id', PARAM_ALPHANUM); // required
$courseid = optional_param('courseid', 0, PARAM_INT); // required

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

// not needed anymore
unset($courseid);

require_login($course);

if (!($has = has_capability('moodle/course:update', $context))) {
    die("Permission denied.");
}
else {
    //display image
    $file = '../../../student-images/' . $id . '.jpg';
    if (file_exists($file)) {
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
    else {
        //do nothing, or output some "empty student" image here.
    }
}
?>
