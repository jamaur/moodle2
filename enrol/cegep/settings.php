<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Cegep enrolment plugin settings and presets.
 *
 * @package    enrol
 * @subpackage cegep
 * @copyright  2011 Jason Maur
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_cegep_settings', '', get_string('pluginname_desc', 'enrol_cegep')));

    $settings->add(new admin_setting_heading('enrol_cegep_exdbheader', get_string('settingsheaderdb', 'enrol_cegep'), ''));

    $options = array('', "access","ado_access", "ado", "ado_mssql", "borland_ibase", "csv", "db2", "fbsql", "firebird", "ibase", "informix72", "informix", "mssql", "mssql_n", "mysql", "mysqli", "mysqlt", "oci805", "oci8", "oci8po", "odbc", "odbc_mssql", "odbc_oracle", "oracle", "postgres64", "postgres7", "postgres", "proxy", "sqlanywhere", "sybase", "vfp");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect('enrol_cegep/dbtype', get_string('dbtype', 'enrol_cegep'), get_string('dbtype_desc', 'enrol_cegep'), '', $options));

    $settings->add(new admin_setting_configtext('enrol_cegep/dbhost', get_string('dbhost', 'enrol_cegep'), get_string('dbhost_desc', 'enrol_cegep'), 'localhost'));

    $settings->add(new admin_setting_configtext('enrol_cegep/dbuser', get_string('dbuser', 'enrol_cegep'), '', ''));

    $settings->add(new admin_setting_configpasswordunmask('enrol_cegep/dbpass', get_string('dbpass', 'enrol_cegep'), '', ''));

    $settings->add(new admin_setting_configtext('enrol_cegep/dbname', get_string('dbname', 'enrol_cegep'), '', ''));

    $settings->add(new admin_setting_configtext('enrol_cegep/dbencoding', get_string('dbencoding', 'enrol_cegep'), '', 'utf-8'));

    $settings->add(new admin_setting_configtext('enrol_cegep/dbsetupsql', get_string('dbsetupsql', 'enrol_cegep'), get_string('dbsetupsql_desc', 'enrol_cegep'), ''));

    $settings->add(new admin_setting_configcheckbox('enrol_cegep/dbsybasequoting', get_string('dbsybasequoting', 'enrol_cegep'), get_string('dbsybasequoting_desc', 'enrol_cegep'), 0));

    $settings->add(new admin_setting_configcheckbox('enrol_cegep/debugdb', get_string('debugdb', 'enrol_cegep'), get_string('debugdb_desc', 'enrol_cegep'), 0));



    $settings->add(new admin_setting_heading('enrol_cegep_localheader', get_string('settingsheaderlocal', 'enrol_cegep'), ''));

    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'shortname'=>'shortname');
    $settings->add(new admin_setting_configselect('enrol_cegep/localcoursefield', get_string('localcoursefield', 'enrol_cegep'), '', 'idnumber', $options));

    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'email'=>'email', 'username'=>'username'); // only local users if username selected, no mnet users!
    $settings->add(new admin_setting_configselect('enrol_cegep/localuserfield', get_string('localuserfield', 'enrol_cegep'), '', 'idnumber', $options));

    $options = array('id'=>'id', 'shortname'=>'shortname', 'fullname'=>'fullname');
    $settings->add(new admin_setting_configselect('enrol_cegep/localrolefield', get_string('localrolefield', 'enrol_cegep'), '', 'shortname', $options));



    $settings->add(new admin_setting_heading('enrol_cegep_remoteheader', get_string('settingsheaderremote', 'enrol_cegep'), ''));

    $settings->add(new admin_setting_configtext('enrol_cegep/remoteenroltable', get_string('remoteenroltable', 'enrol_cegep'), get_string('remoteenroltable_desc', 'enrol_cegep'), ''));

    $settings->add(new admin_setting_configtext('enrol_cegep/remotecoursefield', get_string('remotecoursefield', 'enrol_cegep'), get_string('remotecoursefield_desc', 'enrol_cegep'), ''));

    $settings->add(new admin_setting_configtext('enrol_cegep/remoteuserfield', get_string('remoteuserfield', 'enrol_cegep'), get_string('remoteuserfield_desc', 'enrol_cegep'), ''));

    $settings->add(new admin_setting_configtext('enrol_cegep/remoterolefield', get_string('remoterolefield', 'enrol_cegep'), get_string('remoterolefield_desc', 'enrol_cegep'), ''));

    $settings->add(new admin_setting_configtext('enrol_cegep/remotesectionfield', get_string('remotesectionfield', 'enrol_cegep'), get_string('remotesectionfield_desc', 'enrol_cegep'), ''));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(get_context_instance(CONTEXT_SYSTEM));
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_cegep/defaultrole', get_string('defaultrole', 'enrol_cegep'), get_string('defaultrole_desc', 'enrol_cegep'), $student->id, $options));
    }

    $settings->add(new admin_setting_configcheckbox('enrol_cegep/ignorehiddencourses', get_string('ignorehiddencourses', 'enrol_cegep'), get_string('ignorehiddencourses_desc', 'enrol_cegep'), 0));

    $options = array(ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                     ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
    $settings->add(new admin_setting_configselect('enrol_cegep/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));



    $settings->add(new admin_setting_heading('enrol_cegep_newcoursesheader', get_string('settingsheadernewcourses', 'enrol_cegep'), ''));

    $settings->add(new admin_setting_configtext('enrol_cegep/newcoursetable', get_string('newcoursetable', 'enrol_cegep'), get_string('newcoursetable_desc', 'enrol_cegep'), ''));

    $settings->add(new admin_setting_configtext('enrol_cegep/newcoursefullname', get_string('newcoursefullname', 'enrol_cegep'), '', 'fullname'));

    $settings->add(new admin_setting_configtext('enrol_cegep/newcourseshortname', get_string('newcourseshortname', 'enrol_cegep'), '', 'shortname'));

    $settings->add(new admin_setting_configtext('enrol_cegep/newcourseidnumber', get_string('newcourseidnumber', 'enrol_cegep'), '', 'idnumber'));

    $settings->add(new admin_setting_configtext('enrol_cegep/newcoursecategory', get_string('newcoursecategory', 'enrol_cegep'), '', ''));

    if (!during_initial_install()) {
        require_once($CFG->dirroot.'/course/lib.php');
        $options = array();
        $parentlist = array();
        make_categories_list($options, $parentlist);
        $settings->add(new admin_setting_configselect('enrol_cegep/defaultcategory', get_string('defaultcategory', 'enrol_cegep'), get_string('defaultcategory_desc', 'enrol_cegep'), 1, $options));
        unset($parentlist);
    }

    $settings->add(new admin_setting_configtext('enrol_cegep/templatecourse', get_string('templatecourse', 'enrol_cegep'), get_string('templatecourse_desc', 'enrol_cegep'), ''));
}
