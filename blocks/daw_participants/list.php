<?php

//  Lists all the users within a given course

    require_once('../../config.php');
    require_once($CFG->libdir.'/tablelib.php');
    require_once($CFG->libdir.'/filelib.php');
    require_once($CFG->dirroot.'/blocks/mydawson/lib.php');

    global $cegep_extdb;
    if (is_null($cegep_extdb)) {
        require_once($CFG->dirroot . '/enrol/cegep/locallib.php');
        $cegep_extdb = enrol_cegep_handler::db_init();
    }

    $courseid = optional_param('id', 0, PARAM_INT); // required

    $course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
    $context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

    // not needed anymore
    unset($courseid);

    require_login($course);


    if (!($has = has_capability('moodle/course:update', $context))) {
        die("Permission denied.");
    }
    else {
        //display table
    }

    $PAGE->navbar->add("Student List");
    $PAGE->set_title("$course->shortname: Student List");
    $PAGE->set_heading($course->fullname);
    $PAGE->set_pagetype('course-view-' . $course->format);
    $PAGE->add_body_class('path-user');                     // So we can style it independently
    $PAGE->set_other_editing_capability('moodle/course:manageactivities');

    echo $OUTPUT->header();

    $students_arr = mydawson_get_students($course->idnumber);

    ?>
    <a name="top"></a>

    <h2 class="main">Student List</h2>
	<p>This is a list of all students enrolled (<strong>according to Clara</strong>) in this course and section(s). The student photos will become available as the session progresses. If a student has not confirmed his or her e-mail, it means that he or she has not accessed this Moodle course (or any other Moodle course).</p>

	<p><a href="<?php echo $CFG->wwwroot . '/enrol/users.php?id=' . $course->id;?>">Click here to enrol students or teachers manually</a>.</p>

    <a href="<?php echo $CFG->wwwroot . '/course/view.php?id=' . $course->id;?>">&lt;&lt; Back to course</a>

    <?php
    if ($students_arr) {
        ?>

        <ul>

            <?php
            foreach ($students_arr as $sect => $students) {
                ?><li><a href="#section-<?php echo $sect;?>">Section <?php echo $sect;?></a></li><?php
            }
            ?>

        </ul>

        <?php

        foreach ($students_arr as $sect => $students) {
        ?>

        <table class="studentlist generaltable generalbox">
            <caption>
                <a name="section-<?php echo $sect;?>"></a>Section <?php echo $sect . ": " . count($students) . " student" . (count($students) > 1 ? "s" : "");?>
            </caption>
            <thead>
                <tr>
                    <th class="header c0">Picture</th>
                    <th class="header">Name</th>
                    <th class="header">Student #</th>
                    <th class="header">Confirmed E-mail?</th>
                </tr>
            </thead>

            <tbody>
                <?php
                foreach ($students as $s) {

                    if (($u = $DB->get_record('user', array('idnumber'=>$s['userno'])))) {
                        if ($u->confirmed == '1' && strlen(trim($u->email)) > 0) {
                            $conf = '<span style="color: darkgreen;">Yes</span>';
                        }
                        else {
                            $conf = '<span style="color: red;">No</span>';
                        }
                    }
                    else {
                        $conf = '<span style="color: red;">No</span>';
                    }
                    ?>
                    <tr>
                        <td><img width="100" alt="" src="<?php echo $CFG->wwwroot . "/blocks/daw_participants/image.php?courseid=" . $course->id . "&id=" . $s['userno'];?>" /></td>
                        <td><?php echo $s['firstname'] . " " . $s['lastname'];?></td>
                        <td><?php echo $s['userno'];?></td>
                        <td><?php echo $conf;?></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody> 
            <tfoot>
                <tr>
                    <td colspan="4" class="top"><a href="#top">^ Back to top</a></td>
                </tr>
            </tfoot>
        </table>
        <?php
        }
    }

    echo $OUTPUT->footer();

