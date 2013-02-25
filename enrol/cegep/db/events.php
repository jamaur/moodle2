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
 * Cegep course enrolment plugin event handler definition.
 *
 * @package    enrol
 * @subpackage cegep
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/* List of handlers */
$handlers = array (
    'role_assigned' => array (
        'handlerfile'      => '/enrol/cegep/locallib.php',
        'handlerfunction'  => array('enrol_cegep_handler', 'role_assigned'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'role_unassigned' => array (
        'handlerfile'      => '/enrol/cegep/locallib.php',
        'handlerfunction'  => array('enrol_cegep_handler', 'role_unassigned'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'user_enrolled' => array (
        'handlerfile'      => '/enrol/cegep/locallib.php',
        'handlerfunction'  => array('enrol_cegep_handler', 'user_enrolled'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'user_unenrolled' => array (
        'handlerfile'      => '/enrol/cegep/locallib.php',
        'handlerfunction'  => array('enrol_cegep_handler', 'user_unenrolled'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'course_deleted' => array (
        'handlerfile'      => '/enrol/cegep/locallib.php',
        'handlerfunction'  => array('enrol_cegep_handler', 'course_deleted'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'course_created' => array (
        'handlerfile'      => '/enrol/cegep/locallib.php',
        'handlerfunction'  => array('enrol_cegep_handler', 'course_created'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'groups_group_created' => array (
        'handlerfile'      => '/enrol/cegep/locallib.php',
        'handlerfunction'  => array('enrol_cegep_handler', 'groups_group_created'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),
);
