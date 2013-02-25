<?php
require_once("$CFG->libdir/formslib.php");
class mydawson_hidden_form extends moodleform {

    function definition() {
        $mform =& $this->_form;

        $radioarr = array();

        $radioarr[] = &MoodleQuickForm::createElement('radio', 'visible-' . $_SESSION['mydawson_courseid'], '', get_string('yes'), 1, array('class'=>'visible_select'));

        $radioarr[] = &MoodleQuickForm::createElement('radio', 'visible-' . $_SESSION['mydawson_courseid'], '', get_string('no'), 0, array('class'=>'visible_select'));

        $mform->addElement('hidden', 'courseid', $_SESSION['mydawson_courseid']);

        $mform->addGroup($radioarr, 'radioar', 'Visible to students', array(' '), false);
    }

}
?>
