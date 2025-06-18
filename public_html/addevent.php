<?php
// called from attendance.php to add new dates to trainee's timesheet
header('Content-Type: application/json; charset=utf-8');
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';
$id = isset($_POST['id']) ? $_POST['id'] : 0; # the task
	$id = (int)$id;
$date = isset($_POST['date']) ? $_POST['date'] : 0; # the date
	$date = (int)$date;
$trainee = isset($_POST['trainee']) ? $_POST['trainee'] : ''; # who
	$trainee = preg_replace('/[^\p{Latin}\d\s\p{P}]/u', '', $trainee);
// only allowed one task per day:
$vids = $mysqli->prepare("SELECT tsid FROM timesheet WHERE trainkey = ? AND taskdate = ? ");
$vids->bind_param("si", $trainee, $date);
$vids->execute();
$vids->store_result();
$numlinks = $vids->num_rows;
$vids->close();
if ($numlinks == 0) {
	// create a timesheet entry for this person, this task on this date
	$insert_stmt = $mysqli->prepare("INSERT INTO timesheet (trainkey, dtid, taskdate) VALUES (?, ?, ?)");
	$insert_stmt->bind_param("sii", $trainee, $id, $date);
	$insert_stmt->execute();
	$newid = $insert_stmt->insert_id;
	$insert_stmt->close();
	// count how many of this task for this trainee for this year
	// for updating tak qtys in attendance.php
	$vids = $mysqli->prepare("SELECT tsid FROM timesheet WHERE trainkey = ? AND dtid = ? AND taskdate >= ? AND taskdate <= ?");
	$vids->bind_param("siii", $trainee, $id, $valueyearstart, $valueyearend);
	$vids->execute();
	$vids->store_result();
	$numtasks = $vids->num_rows;
	$vids->close();
}

echo "{";
echo "\"total\" : \"$numtasks\",";;
echo "}";

 
?>