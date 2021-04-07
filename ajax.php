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
 * Class containing data for createusers block.
 *
 * Post data example
 * {"firstname":["aa","gg",""],"lastname":["bb","hh",""],"email":["cc@dd.ee","ii@jj.kk.ll",""],"organisation":["ff","mm",""],"notify":"1","course":"2"}
 *
 *
 * @package    block_createusers
 * @copyright  tim st.clair <https://github.com/frumbert>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require('../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

$PAGE->set_context(context_system::instance());

try {
    require_login();
    require_sesskey();


    header('Content-Type: application/json');

	$manualenrol = enrol_get_plugin('manual'); // get the enrolment plugin
	$roleid = 5; // student
    $notify = ($_POST['notify'] === '1');
    $course = (int)$_POST['course'];
    $doenrol = false;

    if ($course > SITEID) {
    	$doenrol = true;
		$enrolinstance = $DB->get_record('enrol',
			array('courseid' => $course,
				'status' => ENROL_INSTANCE_ENABLED,
				'enrol' => 'manual'
			),
			'*',
			MUST_EXIST
		);
	}

    $out = [];

    foreach($_POST['email'] as $index => $email) {
    	$firstname = trim($_POST['firstname'][$index]);
    	$lastname = trim($_POST['lastname'][$index]);
    	$organisation = trim($_POST['organisation'][$index]);
    	$email = trim($email);

    	$create = true;
    	$error = [];

    	if (empty($email)) {
    		$error[] = 1;
    		$create = false;
    	}

    	if (!validate_email($email)) {
    		$error[] = 2;
    		$create = false;
    	}

		if ($DB->record_exists('user', array('email' => $email, 'mnethostid' => $CFG->mnet_localhost_id))) {
			$create = false;
			$error[] = 3;
		}

    	if (empty($firstname)) {
    		$error[] = 4;
    		$create = false;
    	}

    	if (empty($lastname)) {
    		$error[] = 5;
    		$create = false;
    	}

    	if ($create) {
    		$user = create_user_record(core_text::strtolower($email), 'secret');

    		$user->firstname = $firstname;
    		$user->lastname = $lastname;
    		$user->email = $email;
    		$user->institution = $organisation;
    		user_update_user($user, false, false); 

    		$userid = $user->id;

    		if ($notify) {
				setnew_password_and_mail($user);
    		}

    		$error[] = 0;

			if ($doenrol) {
				$manualenrol->enrol_user($enrolinstance, $userid, $roleid); // enrol the user
			}


			if (!empty($organisation) && $course > SITEID) {
				$context = context_course::instance($course);
				if (!$DB->record_exists('groups', array('name' => $organisation, 'courseid' => $course))) {
					$group = new stdClass();
					$group->courseid = $course;
					$group->name = $organisation;
					$group->picture = 0;
					$group->hidepicture = 0;
					$group->timecreated = time();
					$group->timemodified = time();
					$group->descriptionformat = 1;
					$group->description = '';
					$group->idnumber = '';
					$DB->insert_record('groups', $group);

				    // Trigger group event.
				    $params = array(
				        'context' => $context,
				        'objectid' => $group->id
				    );
				    $event = \core\event\group_created::create($params);
				    $event->add_record_snapshot('groups', $group);
				    $event->trigger();

				}
				$group = $DB->get_record('groups', array('name' => $organisation, 'courseid' => $course), '*', MUST_EXIST);
				if (!$DB->record_exists('groups_members', array('groupid' => $group->id, 'userid' => $userid))) {
					$member = new stdClass();
					$member->groupid = $group->id;
					$member->userid = $userid;
					$member->timeadded = time();
					$member->itemid = 0;
					$member->component = '';
					$DB->insert_record('groups_members', $member);

				    // Trigger groups_members event.
				    $params = array(
				        'context' => $context,
				        'objectid' => $group->id,
				        'relateduserid' => $userid,
				        'other' => array(
				            'component' => $member->component,
				            'itemid' => $member->itemid
				        )
				    );
				    $event = \core\event\group_member_added::create($params);
				    $event->add_record_snapshot('groups', $group);
				    $event->trigger();

				}
			}

	    }
		$out[] = [
			"email" => $email,
			"code" => $error,	
		];
    }
    echo json_encode($out);

} catch (Exception $e) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
    if (isloggedin()) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $e->getMessage();
    }
}