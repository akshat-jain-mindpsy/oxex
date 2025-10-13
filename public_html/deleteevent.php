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

$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
if ($usingSupabase) {
	$stmt = $supabase_pdo->prepare('delete from timesheet where trainkey = ? and tsid = ?');
	$stmt->execute([$trainee, $id]);
} else {
	http_response_code(500);
	echo json_encode(['status' => 'error', 'message' => 'Database connection unavailable']);
}

?>