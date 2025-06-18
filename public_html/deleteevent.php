<?php
// called from attendance.php to delete events from trainee's timesheet
header('Content-Type: application/json; charset=utf-8');
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';
$id = isset($_POST['id']) ? $_POST['id'] : 0; # the task
	$id = (int)$id;
$trainee = isset($_POST['trainee']) ? $_POST['trainee'] : ''; # who
	$trainee = preg_replace('/[^\p{Latin}\d\s\p{P}]/u', '', $trainee);

$stmt = $mysqli->prepare("DELETE FROM timesheet WHERE trainkey = ? AND tsid = ? LIMIT 1");
$stmt->bind_param("si", $trainee, $id); 
$stmt->execute();
$stmt->close();

?>