<?php
class block_mydawson_edit_form extends block_edit_form {
 
    protected function specific_definition($mform) {
        global $CFG;

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

        // Section header title according to language file.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));
 
        // A sample string variable with a default value.
        $mform->addElement('select', 'config_session', get_string('session', 'block_mydawson'), $sessions);
    }
}
