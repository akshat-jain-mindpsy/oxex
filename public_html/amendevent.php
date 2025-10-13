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
try {
    global $supabase_pdo;
    $stmtCheck = $supabase_pdo->prepare('select tsid from timesheet where trainkey = ? and taskdate = ?');
    $stmtCheck->execute([$trainee, $date]);
    $numlinks = count($stmtCheck->fetchAll(PDO::FETCH_ASSOC));
    if ($numlinks == 0) {
        // update timesheet entry for this tsid
        $stmtUpd = $supabase_pdo->prepare('update timesheet set taskdate = ? where tsid = ?');
        $stmtUpd->execute([$date, $id]);
    }
} catch (Throwable $e) {
    error_log('amendevent PDO error: ' . $e->getMessage());
}
/* return data for testing
echo "{";
echo "\"ID\" : \"$id\",";
echo "\"DATE\" : \"$date\",";
echo "\"TRAINEE\" : \"$trainee\"";
echo "}";
*/
 
?>