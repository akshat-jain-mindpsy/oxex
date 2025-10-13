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
	$tableset = $pdo->prepare("SELECT usrkey FROM who_there WHERE isdev = ? AND usrkey != ?");
	$tableset->execute([$value0, $msgUsr]);
	while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
		$msgusrkey = $row['usrkey'];
			// add the message
			$insert_stmt = $pdo->prepare("INSERT INTO admin_msg (usrkey_from, usrkey_to, date_sent, date_read, msg, isread) VALUES (?, ?, ?, ?, ?, ?)");
			$insert_stmt->execute([$msgUsr, $msgusrkey, $today, $value0, $msgTxt, $value0]);
			//printf("[%d] %s\n", $pdo->errorCode(), $pdo->errorInfo()[2]);
			$insert_stmt->closeCursor();
	}
	$tableset->closeCursor();
} else {
	$tableset = $pdo->prepare("SELECT whid, realname, usrkey FROM who_there WHERE isdev = ?");
	$tableset->execute([$value0]);
	while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
		$msgrtnwhid = $row['whid'];
		$msgrtnrealname = $row['realname'];
		$msgusrkey = $row['usrkey'];
		// get POST data
		$posmarker = 'msgEmail'.$msgrtnwhid;
		$clicked = $_POST[$posmarker];
		if ($clicked == $msgrtnwhid) {
			$msglist .= $msgrtnrealname. ' ';
			// add the message
			$insert_stmt = $pdo->prepare("INSERT INTO admin_msg (usrkey_from, usrkey_to, date_sent, date_read, msg, isread) VALUES (?, ?, ?, ?, ?, ?)");
			$insert_stmt->execute([$msgUsr, $msgusrkey, $today, $value0, $msgTxt, $value0]);
			//printf("[%d] %s\n", $pdo->errorCode(), $pdo->errorInfo()[2]);
			$insert_stmt->closeCursor();
		}
	}
	$tableset->closeCursor();
}
// return a message to the sender
echo "Your message $msgTxt  to $msglist was posted";
?>