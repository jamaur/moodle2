<?php
require_once("$CFG->libdir/formslib.php");
class mydawson_unmerge_form extends moodleform {

    function definition() {
        $mform =& $this->_form;
        $mform->addElement('hidden', 'id', 0);
    }

}
?>
