<?php
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
* ("License"); You may not use this file except in compliance with the License
* The Original Code is:  vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
********************************************************************************/
/**
 * this function returns the asterisk server information
 * @param $adb - the peardatabase type object
 * @return array $data - contains the asterisk server and port information in the format array(server, port)
 */
function getAsteriskInfo($adb) {
	global $log;
	$result = $adb->pquery('select * from vtiger_asterisk', array());
	if ($adb->num_rows($result)>0) {
		$data = array();
		$data['server'] = $adb->query_result($result, 0, 'server');
		$data['port'] = $adb->query_result($result, 0, 'port');
		$data['username'] = $adb->query_result($result, 0, 'username');
		$data['password'] = $adb->query_result($result, 0, 'password');
		$data['version'] = $adb->query_result($result, 0, 'version');
		return $data;
	} else {
		$log->debug('< getAsteriskInfo: error: Asterisk server settings not specified');
		return false;
	}
}

/**
 * this function will authorize the first user from the database that it finds
 * this is required as some user must be authenticated into the asterisk server to
 * receive the events that are being generated by asterisk
 * @param string $username - the asterisk username
 * @param string $password - the asterisk password
 * @param object $asterisk - asterisk type object
 */
function authorizeUser($username, $password, $asterisk) {
	echo "Trying to login to asterisk\n";

	if (!empty($username) && !empty($password)) {
		$asterisk->setUserInfo($username, $password);
		if (!$asterisk->authenticateUser()) {
			echo "Cannot login to asterisk using\n
					User: $username\n
					Password: $password\n
					Please check your configuration details.\n";
			exit(0);
		} else {
			echo "Logged in successfully to asterisk server\n\n";
			return true;
		}
	} else {
		return false;
	}
}

/**
 * this function logs in a user so that he can make calls
 * @param string $username - the asterisk username
 * @param string $password - the asterisk password
 * @param object $asterisk - asterisk type object
 */
function loginUser($username, $password, $asterisk) {
	if (!empty($username) && !empty($password)) {
		$asterisk->setUserInfo($username, $password);
		if (!$asterisk->authenticateUser()) {
			echo "Cannot login to asterisk using\n
					User: $username\n
					Password: $password\n
					Please check your configuration details.\n";
			exit(0);
		} else {
			return true;
		}
	} else {
		echo 'Missing username and/or password';
		return false;
	}
}

/**
 * this function returns the channel for the current call
 * @param object $asterisk - the asterisk object
 * @return :: on success - string $value - the channel for the current call
 * 			on failure - false
 */
function getChannel($asterisk) {
	$res = array();
	while (true) {
		$res = $asterisk->getAsteriskResponse(false);
		if (empty($res)) {
			continue;
		}
		foreach ($res as $action => $value) {
			if ($action == 'Channel') {
				return $value;
			}
		}
	}
	return false;
}

/**
 * this function accepts a asterisk extension and returnsthe userid for which it is associated to
 * in case of multiple users having the extension, it returns the first find
 * @param string $extension - the asterisk extension for the user
 * @param object $adb - the peardatabase object
 * @return integer $userid - the user id with the extension
 */
function getUserFromExtension($extension, $adb) {
	$userid = false;
	$result = $adb->pquery('select userid from vtiger_asteriskextensions where asterisk_extension=?', array($extension));
	if ($adb->num_rows($result) > 0) {
		$userid = $adb->query_result($result, 0, 'userid');
	}
	return $userid;
}

/**
 * this function adds the call information to the actvity history
 * @param string $callerName - the caller name
 * @param string $callerNumber - the callers' number
 * @param string $callerType - the caller type (SIP/PSTN...)
 * @param object $adb - the peardatabase object
 * @param object $current_user - the current user
 * @return string $status - on success - string success
 * 							on failure - string failure
 */
function asterisk_addToActivityHistory($callerName, $callerNumber, $callerType, $adb, $userid, $relcrmid, $callerInfo = false) {
	// Reset date format for a while
	$date = new DateTimeField(null);
	$currentDate = $date->getDisplayDate();
	$currentTime = $date->getDisplayTime();
	$focus = CRMEntity::getInstance('cbCalendar');
	$focus->column_fields['subject'] = getTranslatedString('Call From', 'PBXManager')." $callerName ($callerNumber)";
	$focus->column_fields['activitytype'] = 'Call';
	$focus->column_fields['dtstart'] = $currentDate.' '.$currentTime;
	$focus->column_fields['dtend'] = $currentDate.' '.$currentTime;
	$focus->column_fields['eventstatus'] = 'Held';
	if (!empty($callerInfo['activityid'])) {
		$focus->column_fields['relatedwith'] = $callerInfo['activityid'];
	}
	if (empty($relcrmid)) {
		if (empty($callerInfo)) {
			$callerInfo = getCallerInfo($callerNumber);
		}
		$focus->column_fields['cto_id'] = empty($callerInfo['id']) ? 0 : $callerInfo['id'];
		$focus->column_fields['rel_id'] = 0;
	} else {
		$callerInfo = array();
		$callerInfo['module'] = getSalesEntityType($relcrmid);
		$callerInfo['id'] = $relcrmid;
		$ctoInfo = getCallerInfo($callerNumber);
		$focus->column_fields['cto_id'] = $ctoInfo['id'];
		$focus->column_fields['rel_id'] = $relcrmid;
	}
	$focus->column_fields['assigned_user_id'] = $userid;
	$focus->save('cbCalendar');
	$focus->setActivityReminder('off');

	if ($callerInfo) {
		$tablename = array(
			'Contacts'=>'vtiger_cntactivityrel',
			'Accounts'=>'vtiger_seactivityrel',
			'Leads'=>'vtiger_seactivityrel',
			'HelpDesk'=>'vtiger_seactivityrel',
			'Potentials'=>'vtiger_seactivityrel',
		);
		if (!empty($callerInfo['id']) && !empty($callerInfo['module'])) {
			$adb->pquery('insert ignore into '.$tablename[$callerInfo['module']].' values (?,?)', array($callerInfo['id'], $focus->id));
		}
	}
	$callerInfo['activityid'] = $focus->id;
	$callerInfo['pbxdirection'] = 'IN';
	cbEventHandler::do_action('corebos.pbxmanager.aftersaveactivity', $callerInfo);
	return $focus->id;
}

/* Function to add an outgoing call to the History
 * Params Object $current_user  - the current user
 * 		string $extension - the users extension number
 * 		int $record - the activity will be attached to this record
 * 		object $adb - the peardatabase object
 */
function addOutgoingcallHistory($current_user, $extension, $record, $adb) {
	$date = new DateTimeField(null);
	$currentDate = $date->getDisplayDate();
	$currentTime = $date->getDisplayTime();

	$setype = getSalesEntityType($record);
	$focus = CRMEntity::getInstance('cbCalendar');
	$focus->column_fields['subject'] = getTranslatedString('OutgoingCall', 'PBXManager').' '.$current_user->user_name." ($extension)";
	$focus->column_fields['activitytype'] = "Call";
	$focus->column_fields['dtstart'] = $currentDate.' '.$currentTime;
	$focus->column_fields['dtend'] = $currentDate.' '.$currentTime;
	$focus->column_fields['eventstatus'] = "Held";
	$focus->column_fields['assigned_user_id'] = $current_user->id;
	if ($setype=='Contacts') {
		$focus->column_fields['cto_id'] = $record;
	} elseif (!empty($setype)) {
		$focus->column_fields['rel_id'] = $record;
	}
	$focus->save('cbCalendar');
	$focus->setActivityReminder('off');
	$callerInfo = array();
	$ename = getEntityName($setype, $record);
	$callerInfo['module'] = $setype;
	$callerInfo['name'] = $ename[$record];
	$callerInfo['id'] = $record;
	$callerInfo['activityid'] = $focus->id;
	$callerInfo['pbxuuid'] = 0;
	$callerInfo['pbxdirection'] = 'OUT';
	cbEventHandler::do_action('corebos.pbxmanager.aftersaveactivity', $callerInfo);

	if (!empty($setype)) {
		$tablename = array('Contacts'=>'vtiger_cntactivityrel', 'Accounts'=>'vtiger_seactivityrel', 'Leads'=>'vtiger_seactivityrel');
		$adb->pquery('insert ignore into '.$tablename[$setype].' values (?,?)', array($record, $focus->id));
		$status = 'success';
	} else {
		$status = 'failure';
	}
	return $status;
}
?>
