<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Only allow authorized admins
if (login_check($pdo) !== true || ($admintype !== 'AT' && $admintype !== 'DV')) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Not authorized.']);
    exit;
}

header('Content-Type: application/json');

$stid = isset($_GET['stid']) ? (int)$_GET['stid'] : 0;

if ($stid === 0) {
    echo json_encode([]);
    exit;
}

$options = [];
// This query assumes that the predefined values for a field are stored in the `select_gen` table.
// Adjust the table and column names if your schema is different.
$sql = "SELECT select_val FROM select_gen WHERE stid = ? ORDER BY select_val ASC";

$stmt = $supabase_pdo->prepare($sql);
$stmt->execute([$stid]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $options[] = $row['select_val'];
}

echo json_encode($options); 