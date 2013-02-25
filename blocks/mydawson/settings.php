<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    //DB type options
    $options = array('', "access","ado_access", "ado", "ado_mssql", "borland_ibase", "csv", "db2", "fbsql", "firebird", "ibase", "informix72", "informix", "mssql", "mssql_n", "mysql", "mysqli", "mysqlt", "oci805", "oci8", "oci8po", "odbc", "odbc_mssql", "odbc_oracle", "oracle", "postgres64", "postgres7", "postgres", "proxy", "sqlanywhere", "sybase", "vfp");
    $options = array_combine($options, $options);

    //DC101 DB type
    $settings->add(new admin_setting_configselect('block_mydawson/dc101_dbtype', get_string('configdc101dbtype', 'block_mydawson'), '', '', $options));

    //DC101 host
    $settings->add(new admin_setting_configtext('block_mydawson/dc101_host', get_string('configdc101host', 'block_mydawson'), '', '', PARAM_TEXT));

    //DC101 database
    $settings->add(new admin_setting_configtext('block_mydawson/dc101_db', get_string('configdc101db', 'block_mydawson'), '', '', PARAM_TEXT));

    //DC101 username 
    $settings->add(new admin_setting_configtext('block_mydawson/dc101_user', get_string('configdc101user', 'block_mydawson'), '', '', PARAM_TEXT));

    //DC101 password
    $settings->add(new admin_setting_configpasswordunmask('block_mydawson/dc101_pass', get_string('configdc101pass', 'block_mydawson'), '', ''));

    //DC101 setup sql
    $settings->add(new admin_setting_configtext('block_mydawson/dc101_sql', get_string('configdc101sql', 'block_mydawson'), get_string('configsqldesc', 'block_mydawson'), '', PARAM_TEXT));
}
