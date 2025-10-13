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
try {
    global $supabase_pdo;
    $stmtCheck = $supabase_pdo->prepare('select tsid from timesheet where trainkey = ? and taskdate = ?');
    $stmtCheck->execute([$trainee, $date]);
    $numlinks = count($stmtCheck->fetchAll(PDO::FETCH_ASSOC));
    if ($numlinks == 0) {
        // create a timesheet entry for this person, this task on this date
        $insertStmt = $supabase_pdo->prepare('insert into timesheet (trainkey, dtid, taskdate) values (?, ?, ?)');
        $insertStmt->execute([$trainee, $id, $date]);
        // count how many of this task for this trainee for this year
        // for updating tak qtys in attendance.php
        $stmtCount = $supabase_pdo->prepare('select tsid from timesheet where trainkey = ? and dtid = ? and taskdate >= ? and taskdate <= ?');
        $stmtCount->execute([$trainee, $id, $valueyearstart, $valueyearend]);
        $numtasks = count($stmtCount->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (Throwable $e) {
    error_log('addevent PDO error: ' . $e->getMessage());
}

echo "{";
echo "\"total\" : \"$numtasks\",";;
echo "}";

 
?>