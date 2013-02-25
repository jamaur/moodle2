<?php
global $CFG;

require_once("$CFG->libdir/formslib.php");
class mydawson_merge_form extends moodleform {

    /**
     * @var array $children Array of child courses to include in the merge form.
     */
    protected $children;

    /**
     * @var array $parents Array of parent courses to include in the merge form.
     */
    protected $parents;

    function mydawson_merge_form($children, $parents) {
        $this->children = $children;
        $this->parents = $parents;
        $this->moodleform();
    }

    function definition() {
        global $CFG;
        $mform =& $this->_form;

        if (!$this->parents || count($this->parents) == 0) {
            $options = array(0 => 'New Course');
            $attributes = array('disabled' => 'true');
            $mform->addElement('select', 'parent_id', 'Parent course', $options, $attributes);
        }
        else {
            $options = array();
            $options[0] = 'New Course';

            foreach ($this->parents as $p) {
                $options[$p->id] = $p->fullname;
            }
            $mform->addElement('select', 'parent_id', 'Parent course', $options, $attributes);
        }

        foreach ($this->children as $c) {
            //$mform->addElement('advcheckbox', 'merge-' . $c->id, $c->fullname, 'Label displayed after checkbox', array('group' => 1), array(0, 1));
            $section = mydawson_extract_section($c->idnumber);
            $mform->addElement('advcheckbox', 'merge-' . $c->id, '&nbsp', $c->fullname . " (Section " . $section . ")", array('group' => 1), array(0, 1));
        }

        $mform->addElement('hidden', 'id', 0);
        $mform->addElement('hidden', 'coursenumber', 0);
        $mform->addElement('hidden', 'session', 0);
        $mform->addElement('hidden', 'coursetitle', 0);
    }
}
?>
