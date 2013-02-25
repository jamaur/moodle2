<?php
require_once("$CFG->libdir/formslib.php");
class mydawson_session_form extends moodleform {
    function definition() {
        global $CFG;
        $mform =& $this->_form;

        require_once $CFG->dirroot."/blocks/mydawson/lib.php";

        //Get current session according to DC101.
        $session = mydawson_get_session();
        
        //allow to start one session if the future for those who plan ahead
        $session = mydawson_increment_session($session);

        $sessions = array();

        //allow going back 10 sessions (3 years)
        for ($i = 0; $i <= 10; $i++) {
            $sessions[$session] = mydawson_session_to_string($session);
            $session = mydawson_decrement_session($session);
        }

		$sessions['other'] = "Other";

        // A sample string variable with a default value.
        $mform->addElement('submit', 'changesession', 'Change session');
        $mform->addElement('select', 'session', 'Session', $sessions, array('style'=>'float:right;'));

    }
}
?>
