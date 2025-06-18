<?php
// called from attendance.php to amend existing dates from trainee's timesheet
header('Content-Type: application/json; charset=utf-8');
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';

$id = isset($_POST['id']) ? $_POST['id'] : 0; # timesheet.tsid
	$id = (int)$id;
$date = isset($_POST['date']) ? $_POST['date'] : 0; # the new date
	$date = (int)$date;
$trainee = isset($_POST['trainee']) ? $_POST['trainee'] : ''; # who
	$trainee = preg_replace('/[^\p{Latin}\d\s\p{P}]/u', '', $trainee);
// only allowed one task per day so check new date:
$vids = $mysqli->prepare("SELECT tsid FROM timesheet WHERE trainkey = ? AND taskdate = ? ");
$vids->bind_param("si", $trainee, $date);
$vids->execute();
$vids->store_result();
$numlinks = $vids->num_rows;
$vids->close();
if ($numlinks == 0) {
	// update timesheet entry for this tsid
	$stmt = $mysqli->prepare("UPDATE timesheet SET taskdate = ? WHERE tsid = ?"); 
	$stmt->bind_param("ii", $date, $id);
	$stmt->execute();
	$stmt->close();
}
/* return data for testing
echo "{";
echo "\"ID\" : \"$id\",";
echo "\"DATE\" : \"$date\",";
echo "\"TRAINEE\" : \"$trainee\"";
echo "}";
*/
 
?>