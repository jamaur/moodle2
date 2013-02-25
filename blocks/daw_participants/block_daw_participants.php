<?php

class block_daw_participants extends block_list {
    function init() {
        $this->title = get_string('pluginname', 'block_daw_participants');
    }

    function get_content() {

        global $CFG, $OUTPUT, $COURSE;

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        /// MDL-13252 Always get the course context or else the context may be incorrect in the user/index.php
        $currentcontext = $this->page->context;

        if ($this->page->course->id == SITEID) {
			$this->content = '';
			return $this->content;
        } else {
			if (!has_capability('moodle/course:update', $currentcontext)) {
                $this->content = '';
                return $this->content;
            }
        }
        $icon = '<img src="'.$OUTPUT->pix_url('i/users') . '" class="icon" alt="" />&nbsp;';
        $this->content->items[] = '<a title="'.get_string('listofallpeople').'" href="'.
                                  $CFG->wwwroot.'/blocks/daw_participants/list.php?id='.$COURSE->id.'">'.$icon.'Student List</a>';//get_string('linktext', 'daw_participants').'</a>';

        return $this->content;
    }

    // my moodle can only have SITEID and it's redundant here, so take it away
    function applicable_formats() {
        return array('all' => true, 'my' => false, 'tag' => false);
    }

}


