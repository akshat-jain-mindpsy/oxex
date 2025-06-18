<?php
include '../OXEXfolder/config.php';
// This page receives admin messages sent Ajax from any page
// messages can be to 'all' or a list of individuals (their id in who_there)
$value0 = 0;
$today = date("Ymd");
$msglist = '';
$msgUsr = isset($_POST['msgUsr']) ? $_POST['msgUsr'] : '';
$msgTxt = isset($_POST['msgTxt']) ? $_POST['msgTxt'] : '';
	$msgTxt = preg_replace('/[^\p{Latin}\d\s\p{P}]/u', '', $msgTxt);
$msgEmailAll = isset($_POST['msgEmailAll']) ? $_POST['msgEmailAll'] : '';

// do we add a message to all or individuals
if ($msgEmailAll == 'all') {
	$msglist = 'everyone';
	// send to everyone excvept devs and the sender
	$tableset = $mysqli->prepare("SELECT usrkey FROM who_there WHERE isdev = ? AND usrkey != ?");
	$tableset->bind_param("is", $value0, $msgUsr);
	$tableset->execute();
	$tableset->store_result();
	$tableset->bind_result($msgusrkey);
	while ($tableset->fetch()){
			// add the message
			$insert_stmt = $mysqli->prepare("INSERT INTO admin_msg (usrkey_from, usrkey_to, date_sent, date_read, msg, isread) VALUES (?, ?, ?, ?, ?, ?)");
			$insert_stmt->bind_param("ssiisi", $msgUsr, $msgusrkey, $today, $value0, $msgTxt, $value0);
			$insert_stmt->execute();
			//printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
			$insert_stmt->close();
	}
	$tableset->close();
} else {
	$tableset = $mysqli->prepare("SELECT whid, realname, usrkey FROM who_there WHERE isdev = ?");
	$tableset->bind_param("i", $value0);
	$tableset->execute();
	$tableset->store_result();
	$tableset->bind_result($msgrtnwhid, $msgrtnrealname, $msgusrkey);
	while ($tableset->fetch()){
		// get POST data
		$posmarker = 'msgEmail'.$msgrtnwhid;
		$clicked = $_POST[$posmarker];
		if ($clicked == $msgrtnwhid) {
			$msglist .= $msgrtnrealname. ' ';
			// add the message
			$insert_stmt = $mysqli->prepare("INSERT INTO admin_msg (usrkey_from, usrkey_to, date_sent, date_read, msg, isread) VALUES (?, ?, ?, ?, ?, ?)");
			$insert_stmt->bind_param("ssiisi", $msgUsr, $msgusrkey, $today, $value0, $msgTxt, $value0);
			$insert_stmt->execute();
			//printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
			$insert_stmt->close();
		}
	}
	$tableset->close();
}
// return a message to the sender
echo "Your message $msgTxt  to $msglist was posted";
?>