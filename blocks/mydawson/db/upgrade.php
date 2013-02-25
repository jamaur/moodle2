<?php
function xmldb_block_mydawson_upgrade($oldversion = 0) {
    global $DB;
    $dbman = $DB->get_manager();

    $result = true;
    
    if ($oldversion < 2011042100) {

        // Define table mydawson_merged_course to be created
        $table = new xmldb_table('mydawson_merged_course');

        // Adding fields to table mydawson_merged_course
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('parent_courseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('section', XMLDB_TYPE_INTEGER, '5', XMLDB_UNSIGNED, null, null, null);

        // Adding keys to table mydawson_merged_course
        $table->add_key('id', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for mydawson_merged_course
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // mydawson savepoint reached
        upgrade_block_savepoint(true, 2011042100, 'mydawson');
    }

    if ($oldversion < 2011060801) {

        // Define table mydawson_coursetime to be created
        $table = new xmldb_table('mydawson_coursetime');

        // Adding fields to table mydawson_coursetime
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('coursenumber', XMLDB_TYPE_CHAR, '8', null, XMLDB_NOTNULL, null, null);
        $table->add_field('section', XMLDB_TYPE_INTEGER, '5', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('day', XMLDB_TYPE_CHAR, '2', null, XMLDB_NOTNULL, null, 'M');
        $table->add_field('start_time', XMLDB_TYPE_CHAR, '5', null, XMLDB_NOTNULL, null, null);
        $table->add_field('end_time', XMLDB_TYPE_CHAR, '5', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table mydawson_coursetime
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for mydawson_coursetime
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // mydawson savepoint reached
        upgrade_block_savepoint(true, 2011060801, 'mydawson');
    }

    if ($oldversion < 2012082201) {
        //I need to hack the navigation block to filter courses
        //so that only courses from the current selected session 
        //(or "global" courses) are used. I tried grabbing the
        //block instance for mydawson and figuring out what the
        //session is set to, but I couldn't figure out how to
        //get the proper instance for the user's specific mydawson
        //block on any given page, since the navigation 
        //bar is everywhere. This table will make my life easier.

        // Define table mydawson_user_session to be created
        $table = new xmldb_table('mydawson_user_session');

        // Adding fields to table mydawson_coursetime
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('session', XMLDB_TYPE_INTEGER, '5', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);

        // Adding keys to table mydawson_coursetime
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for mydawson_coursetime
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // mydawson savepoint reached

        upgrade_block_savepoint(true, 2012082201, 'mydawson');
    }

    return $result;
}

?>
